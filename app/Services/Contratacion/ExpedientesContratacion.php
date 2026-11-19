<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Closure;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Support\Collection;

/**
 * Expediente de contratación de un proceso: qué plantillas se le ofrecen y cómo nace.
 *
 * Reglas:
 * - Se ofrecen las plantillas que corresponden al proceso (PlantillasContratacion). Las de
 *   viabilización nunca (PlantillasExpediente es la de ellas).
 * - Un proceso tiene como máximo un expediente. Nace con una o varias plantillas compatibles,
 *   cada una con su versión vigente; la primera elegida es la principal, la que ordena el
 *   checklist primero. El checklist lo arma el motor del expediente (ExpedienteService).
 * - Al registrar un proceso, o al cambiarle la modalidad o la fuente mientras no tenga
 *   expediente, el expediente nace solo si hay exactamente una plantilla compatible; con cero
 *   o varias, se crea a mano eligiéndolas. Lo mismo para los procesos sin expediente de un
 *   proyecto al que le cambia el sector. Nunca interrumpe a quien guarda el proceso o el proyecto.
 * - Cambiar la modalidad o la fuente de un proceso con expediente no lo toca: el proceso avisa
 *   cuando ninguna de sus plantillas le sirve ya, y por qué (resumenDeProcesos).
 * - resumenDeProcesos también da los avisos de cada proceso (AvisosContratacion): etapas
 *   vencidas y próximas, cierre, días sin actualización e hitos.
 */
final class ExpedientesContratacion
{
    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly PlantillasContratacion $plantillas,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /**
     * Lo que se ofrece para crear el expediente del proceso, con lo que la pantalla del expediente
     * muestra del proceso cuando todavía no lo tiene (código, objeto, estado y proyecto).
     *
     * @return array{proceso: array, expediente_id: ?int, plantillas: list<array>}
     */
    public function disponibles(ProcesoLicitacion $proceso): array
    {
        $proceso->loadMissing(['modalidadRel:id,nombre', 'fuente:id,nombre', 'proyectoRel:id,nombre,sector', 'proyectoRel.sectorRel:id,nombre']);
        $sector = $proceso->proyectoRel?->sectorRel;
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;

        $plantillas = PlantillaChecklist::query()
            ->whereIn('id', $this->idsCompatibles($proceso))
            ->with(['versiones' => fn ($q) => $this->plantillas->publicada($q)->withCount(['requisitos' => fn ($r) => $r->where('activo', true)])])
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return [
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => $proceso->codigo_proceso,
                'objeto' => $proceso->objeto,
                'estado' => $proceso->estado,
                'proyecto' => $opcion($proceso->proyectoRel),
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
        return $this->plantillas->idsCompatibles($proceso);
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
     * nunca interrumpe a quien guarda el proyecto. Decidir no cuesta más con más procesos.
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
        $compatibles = $this->idsCompatibles($proceso);

        return count($compatibles) === 1 ? PlantillaChecklist::find($compatibles[0]) : null;
    }

    /**
     * Procesos sin expediente con exactamente una plantilla compatible, con ella; todos, o solo
     * los del proyecto dado. Las consultas no crecen con los procesos.
     *
     * @return Collection<int, array{proceso: ProcesoLicitacion, plantilla: PlantillaChecklist}>
     */
    public function faltantes(?int $proyectoId = null): Collection
    {
        $procesos = ProcesoLicitacion::query()
            ->when($proyectoId !== null, fn ($q) => $q->where('proyecto', $proyectoId))
            ->whereDoesntHave('expediente')
            ->with('proyectoRel:id,nombre,sector')
            ->orderBy('id')
            ->get();
        $unicas = collect($this->plantillas->idsCompatiblesPorProceso($procesos))
            ->filter(fn (array $ids) => count($ids) === 1)
            ->map(fn (array $ids) => $ids[0]);
        $plantillas = PlantillaChecklist::query()->whereIn('id', $unicas->unique()->values())->get()->keyBy('id');

        return $procesos
            ->filter(fn (ProcesoLicitacion $proceso) => $unicas->has($proceso->id))
            ->map(fn (ProcesoLicitacion $proceso) => ['proceso' => $proceso, 'plantilla' => $plantillas[$unicas[$proceso->id]]])
            ->values();
    }

    /**
     * Lo que la API del proceso muestra de su expediente y sus avisos. `expediente` es el
     * expediente resumido: su estado, su avance ponderado (ExpedienteService::avanceDeVarios) y
     * el aviso si ninguna de sus plantillas le sirve ya (no corresponde a la modalidad, la fuente
     * o el sector, está inactiva o no tiene versión publicada); null sin expediente. `avisos` es
     * el resumen de AvisosContratacion, también para un proceso sin expediente (solo con el
     * cierre y la actualización). Las consultas no crecen con los procesos.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @param ?array $avancesPorEtapa recibe el avance_por_etapa presentado del expediente de cada
     *        proceso que lo tiene (proceso_id => lista), para quien necesita las etapas (el tablero)
     * @return array<int, array{expediente: ?array{id: int, estado: string, progreso: array{total: int, cumplidos: int, porcentaje: int, peso_total: int, peso_cumplido: int}, aviso_plantilla: ?string}, avisos: array}> proceso_id => resumen
     */
    public function resumenDeProcesos(Collection $procesos, ?array &$avancesPorEtapa = null): array
    {
        $expedientes = $procesos->isEmpty() ? collect() : ProyectoExpediente::query()
            ->contratacion()
            ->whereIn('proceso_id', $procesos->pluck('id'))
            ->with('versionesPlantilla')
            ->get()
            ->keyBy('proceso_id');
        $avances = $this->expedientes->avanceDeVarios($expedientes->values());
        $eventos = $this->avisos->ultimosEventos($expedientes->pluck('id'));
        $estados = $this->plantillas->estadoPorProceso(
            $procesos->filter(fn (ProcesoLicitacion $p) => $expedientes->has($p->id))->values(),
            $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla->pluck('plantilla_id'))
                ->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $avancesPorEtapa = $expedientes->map(fn (ProyectoExpediente $e) => $avances[$e->id]['avance_por_etapa'])->all();

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($expedientes, $estados, $avances, $eventos) {
            $expediente = $expedientes->get($proceso->id);
            $avisos = $this->avisos->resumen(
                $proceso,
                $expediente,
                $expediente ? $avances[$expediente->id]['avance_por_etapa'] : [],
                $expediente ? ($eventos[$expediente->id] ?? null) : null
            );
            if (! $expediente) {
                return [$proceso->id => ['expediente' => null, 'avisos' => $avisos]];
            }
            $razones = $expediente->versionesPlantilla
                ->map(fn ($v) => PlantillasContratacion::razon($estados[$proceso->id][(int) $v->plantilla_id]))
                ->all();

            return [$proceso->id => [
                'expediente' => [
                    'id' => $expediente->id,
                    'estado' => $expediente->estado,
                    'progreso' => $avances[$expediente->id]['progreso'],
                    'aviso_plantilla' => $this->avisoPlantilla($razones),
                ],
                'avisos' => $avisos,
            ]];
        })->all();
    }

    /**
     * El aviso cuando ninguna plantilla del expediente le sirve ya al proceso, con la razón: no
     * corresponde a su modalidad, su fuente o su sector; está inactiva; o ya no tiene versión
     * publicada. Null si alguna le sirve.
     *
     * @param list<?string> $razones las de PlantillasContratacion::razon(), una por plantilla
     */
    private function avisoPlantilla(array $razones): ?string
    {
        if ($razones === [] || in_array(null, $razones, true)) {
            return null;
        }
        $una = count($razones) === 1;
        $textos = [
            'no_corresponde' => [
                'La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.',
                'Ninguna plantilla del expediente corresponde ya a la modalidad, la fuente o el sector del proceso.',
                'ya no corresponden a la modalidad, la fuente o el sector del proceso',
            ],
            'inactiva' => [
                'La plantilla del expediente está inactiva.',
                'Las plantillas del expediente están inactivas.',
                'están inactivas',
            ],
            'sin_publicada' => [
                'La plantilla del expediente ya no tiene una versión publicada.',
                'Las plantillas del expediente ya no tienen una versión publicada.',
                'ya no tienen una versión publicada',
            ],
        ];
        $distintas = array_values(array_intersect(array_keys($textos), $razones));
        if (count($distintas) === 1) {
            return $textos[$distintas[0]][$una ? 0 : 1];
        }
        $partes = array_map(fn (string $razon) => $textos[$razon][2], $distintas);
        $ultima = array_pop($partes);

        return 'Ninguna plantilla del expediente sigue vigente: unas ' . implode(', otras ', $partes) . " y otras {$ultima}.";
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
            ->with(['versiones' => fn ($q) => $this->plantillas->publicada($q)])
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
}
