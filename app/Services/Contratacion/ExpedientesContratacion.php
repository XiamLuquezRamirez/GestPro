<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Closure;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Expediente de contratación de un proceso: qué plantillas se le ofrecen y cómo nace.
 *
 * Reglas:
 * - Se ofrecen las plantillas de contratación activas, con motor simple y versión publicada
 *   vigente, que cubren la modalidad del proceso (o todas), su fuente (o todas; sin fuente,
 *   solo las de todas las fuentes) y el sector del proyecto (o todos; sin sector, solo las de
 *   todos los sectores). Las de viabilización nunca (PlantillasExpediente es la de ellas).
 * - Un proceso tiene como máximo un expediente. Nace con una o varias plantillas compatibles,
 *   cada una con su versión vigente; la primera elegida es la principal, la que ordena el
 *   checklist primero. El checklist lo arma el motor del expediente (ExpedienteService).
 * - Al registrar un proceso, o al cambiarle la modalidad o la fuente mientras no tenga
 *   expediente, el expediente nace solo si hay exactamente una plantilla compatible; con cero
 *   o varias, se crea a mano eligiéndolas. Lo mismo para los procesos sin expediente de un
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca.
 */
final class ExpedientesContratacion
{
    public function __construct(private readonly ExpedienteService $expedientes)
    {
    }

    /**
     * Lo que se ofrece para crear el expediente del proceso.
     *
     * @return array{proceso: array, expediente_id: ?int, plantillas: list<array>}
     */
    public function disponibles(ProcesoLicitacion $proceso): array
    {
        $proceso->loadMissing(['modalidadRel:id,nombre', 'fuente:id,nombre', 'proyectoRel:id,sector', 'proyectoRel.sectorRel:id,nombre']);
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;

        $plantillas = $this->compatibles($proceso)
            ->with(['versiones' => fn ($q) => $this->publicada($q)->withCount(['requisitos' => fn ($r) => $r->where('activo', true)])])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return [
            'proceso' => [
                'id' => $proceso->id,
                'modalidad' => $opcion($proceso->modalidadRel),
                'fuente' => $opcion($proceso->fuente),
                'sector' => $opcion($sector),
            ],
            'expediente_id' => $proceso->expediente()->value('id'),
            'plantillas' => $plantillas->map(function (PlantillaChecklist $p) {
                $version = $p->versiones->first();

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'todas_modalidades' => $p->todas_modalidades,
                    'todas_fuentes' => $p->todas_fuentes,
                    'todos_sectores' => $p->todos_sectores,
                    'requisitos' => (int) $version->requisitos_count,
                    'version' => ['id' => $version->id, 'numero' => $version->numero],
                ];
            })->values()->all(),
        ];
    }

    /** @return list<int> ids de las plantillas que hoy se ofrecen al proceso */
    public function idsCompatibles(ProcesoLicitacion $proceso): array
    {
        return $this->compatibles($proceso)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Crea el expediente del proceso con las plantillas elegidas, en el orden pedido.
     *
     * @param list<int> $plantillaIds
     */
    public function crear(ProcesoLicitacion $proceso, array $plantillaIds, ?User $usuario): ProyectoExpediente
    {
        // Se validan con el proceso ya bloqueado: un cambio simultáneo de su modalidad o su
        // fuente no deja un expediente con una plantilla que ya no le corresponde.
        return $this->expedientes->crearDeProceso(
            $proceso,
            fn (ProcesoLicitacion $bloqueado) => $this->validar($bloqueado, $plantillaIds),
            $usuario
        );
    }

    /**
     * Expediente que nace al registrar el proceso o cambiarle la modalidad o la fuente, si no
     * tiene y hay exactamente una plantilla compatible. Nunca interrumpe a quien guarda el
     * proceso: un fallo se registra y el expediente se puede crear a mano.
     */
    public function crearAutomatico(ProcesoLicitacion $proceso, ?User $usuario): ?ProyectoExpediente
    {
        // Dentro de intentar: un fallo al consultarlo tampoco interrumpe el guardado.
        return $this->intentar(function () use ($proceso, $usuario) {
            $unica = $this->plantillaAutomatica($proceso);

            return $unica ? $this->crear($proceso, [$unica->id], $usuario) : null;
        });
    }

    /**
     * Expedientes que nacen al cambiar el sector del proyecto: el de cada proceso suyo sin
     * expediente que ahora tiene exactamente una plantilla compatible. Como crearAutomatico,
     * nunca interrumpe a quien guarda el proyecto.
     *
     * @return int cuántos se crearon
     */
    public function crearAutomaticosDeProyecto(int $proyectoId, ?User $usuario): int
    {
        try {
            $faltantes = $this->faltantes($proyectoId);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }

        return $faltantes->filter(fn (array $fila) => $this->intentar(
            fn () => $this->crear($fila['proceso'], [$fila['plantilla']->id], $usuario)
        ) !== null)->count();
    }

    /**
     * Una creación automática: un fallo se registra y devuelve null. Una regla incumplida no es
     * un fallo (otra petición creó el expediente primero, o la plantilla dejó de corresponder
     * al proceso): devuelve null sin registrarse.
     */
    private function intentar(Closure $crear): ?ProyectoExpediente
    {
        try {
            return $crear();
        } catch (ReglaNegocioException) {
            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** La única plantilla compatible de un proceso sin expediente; null si no hay o hay varias. */
    public function plantillaAutomatica(ProcesoLicitacion $proceso): ?PlantillaChecklist
    {
        if ($proceso->expediente()->exists()) {
            return null;
        }
        $compatibles = $this->compatibles($proceso)->orderBy('id')->limit(2)->get();

        return $compatibles->count() === 1 ? $compatibles->first() : null;
    }

    /**
     * Procesos sin expediente con exactamente una plantilla compatible, con ella; todos, o solo
     * los del proyecto dado.
     *
     * @return Collection<int, array{proceso: ProcesoLicitacion, plantilla: PlantillaChecklist}>
     */
    public function faltantes(?int $proyectoId = null): Collection
    {
        return ProcesoLicitacion::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto', $proyectoId))
            ->whereDoesntHave('expediente')
            ->with('proyectoRel:id,nombre,sector')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcesoLicitacion $proceso) => ['proceso' => $proceso, 'plantilla' => $this->plantillaAutomatica($proceso)])
            ->filter(fn (array $fila) => $fila['plantilla'] !== null)
            ->values();
    }

    /**
     * Elimina los expedientes de contratación del proyecto, antes de eliminarlo: el proyecto
     * borra en cascada sus procesos y sus expedientes, pero un proceso con expediente no se
     * borra (restrict), y en MariaDB el orden de las cascadas no está garantizado. Como con los
     * de viabilización al eliminar el proyecto, sus soportes quedan en el disco y el historial
     * se conserva. Se llama dentro de la transacción que elimina el proyecto.
     */
    public function eliminarDeProyecto(int $proyectoId): int
    {
        // Antes de borrar se bloquean los procesos del proyecto: un expediente que nace a la vez
        // (crearDeProceso bloquea su proceso) espera a que termine la eliminación y después no
        // encuentra el proceso (a mano, 404; la creación automática lo absorbe). La carrera solo
        // se ve en MariaDB: SQLite, el de las pruebas, no tiene FOR UPDATE.
        ProcesoLicitacion::query()->where('proyecto', $proyectoId)->lockForUpdate()->get(['id']);

        return ProyectoExpediente::query()->contratacion()->where('proyecto_id', $proyectoId)->delete();
    }

    /**
     * Comprueba las plantillas pedidas y decide la versión de cada una, con la forma que guarda
     * PlantillasExpediente::guardar().
     *
     * @param list<int> $plantillaIds
     * @return array{versiones: array<int, int>, principal_id: int}
     */
    private function validar(ProcesoLicitacion $proceso, array $plantillaIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $plantillaIds)));
        if ($ids === []) {
            throw new ReglaNegocioException('El expediente no se puede crear.', ['Elige al menos una plantilla para el expediente.']);
        }
        if (count($ids) > PlantillasExpediente::MAX_PLANTILLAS) {
            throw new ReglaNegocioException('El expediente no se puede crear.', [
                'Un expediente admite como máximo ' . PlantillasExpediente::MAX_PLANTILLAS . ' plantillas.',
            ]);
        }
        $compatibles = array_flip($this->idsCompatibles($proceso));
        $encontradas = PlantillaChecklist::query()->whereIn('id', $ids)
            ->with(['versiones' => fn ($q) => $this->publicada($q)])
            ->get()->keyBy('id');

        $errores = [];
        $versiones = [];
        foreach ($ids as $id) {
            $plantilla = $encontradas->get($id);
            $version = $plantilla?->versiones->first();
            $error = match (true) {
                ! $plantilla => "La plantilla con id {$id} no existe.",
                ! $plantilla->esContratacion() => "La plantilla \"{$plantilla->nombre}\" es de viabilización y no se usa en un expediente de contratación.",
                ! $plantilla->activo => "La plantilla \"{$plantilla->nombre}\" está inactiva.",
                ! $version => "La plantilla \"{$plantilla->nombre}\" no tiene una versión publicada.",
                ! isset($compatibles[$id]) => "La plantilla \"{$plantilla->nombre}\" no es compatible con la modalidad, la fuente o el sector del proceso.",
                default => null,
            };
            if ($error !== null) {
                $errores[] = $error;
            } else {
                $versiones[$id] = $version->id;
            }
        }
        if ($errores) {
            throw new ReglaNegocioException('El expediente no se puede crear.', $errores);
        }

        return ['versiones' => $versiones, 'principal_id' => $ids[0]];
    }

    private function compatibles(ProcesoLicitacion $proceso): Builder
    {
        $sector = Proyecto::query()->whereKey($proceso->proyecto)->value('sector');

        return PlantillaChecklist::query()
            ->where('uso', PlantillaChecklist::USO_CONTRATACION)
            ->where('motor', PlantillaChecklist::MOTOR_SIMPLE)
            ->where('activo', true)
            ->whereHas('versiones', fn ($q) => $this->publicada($q))
            ->where(fn (Builder $q) => $q->where('todas_modalidades', true)
                ->when($proceso->modalidad !== null, fn (Builder $m) => $m->orWhereHas('modalidades', fn (Builder $w) => $w->where('modalidades.id', $proceso->modalidad))))
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
                ->when($proceso->fuente_id !== null, fn (Builder $f) => $f->orWhereHas('fuentes', fn (Builder $w) => $w->where('fuentes_financiacion.id', $proceso->fuente_id))))
            ->where(fn (Builder $q) => $q->where('todos_sectores', true)
                ->when($sector !== null, fn (Builder $s) => $s->orWhereHas('sectores', fn (Builder $w) => $w->where('sectores.id', $sector))));
    }

    /**
     * La versión vigente, si está publicada: un borrador nunca arma un expediente.
     *
     * @param Builder|HasMany $versiones consulta de versiones de plantilla
     */
    private function publicada(Builder|HasMany $versiones): Builder|HasMany
    {
        return $versiones->vigente()->where('estado', CatalogoVersion::PUBLICADA);
    }
}
