<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
use App\Models\RequisitoSector;
use App\Models\RequisitoTipo;
use App\Models\RequisitoTipologia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ciclo de vida del expediente de viabilización (la instancia por proyecto).
 *
 * Reglas:
 * - Se crea con la versión VIGENTE del catálogo y queda atada a ella.
 * - En borrador, cambiar la configuración recalcula el checklist: los requisitos
 *   que dejan de aplicar se marcan aplica=false (conservan cumplimiento,
 *   observación y soporte) y los que vuelven a aplicar se recuperan tal cual.
 * - Solo se radica con todos los requisitos que aplican cumplidos.
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 */
final class ExpedienteService
{
    public const DISCO = 'local'; // storage/app/private: no accesible por URL pública

    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    public function __construct(private readonly EvaluadorRequisitos $evaluador)
    {
    }

    // -------------------------------------------------------------- escritura

    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProyectoExpediente
    {
        $version = CatalogoVersion::vigente()->first()
            ?? throw new ReglaNegocioException('No hay una versión vigente del catálogo de requisitos.');

        $seleccion = $this->validarSeleccion($datos, null);

        return DB::transaction(function () use ($proyecto, $version, $datos, $seleccion, $usuario) {
            $expediente = ProyectoExpediente::create([
                'proyecto_id' => $proyecto->id,
                'catalogo_version_id' => $version->id,
                'fase_sgr' => (int) $datos['fase_sgr'],
                'estado' => ProyectoExpediente::BORRADOR,
                'usuario_creo_id' => $usuario?->id,
            ]);

            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);

            return $expediente;
        });
    }

    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos): ProyectoExpediente
    {
        $this->exigirBorrador($expediente);
        $seleccion = $this->validarSeleccion($datos, $expediente);

        return DB::transaction(function () use ($expediente, $datos, $seleccion) {
            $expediente->update(['fase_sgr' => (int) $datos['fase_sgr']]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);

            return $expediente;
        });
    }

    /** @param array{cumplido?: bool, observacion?: ?string} $datos */
    public function marcar(ProyectoExpediente $expediente, ProyectoRequisito $fila, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        if (array_key_exists('cumplido', $datos)) {
            $cumplido = (bool) $datos['cumplido'];
            if ($cumplido && ! $fila->cumplido) {
                $fila->fill(['cumplido' => true, 'fecha_cumplido' => now(), 'usuario_cumplido_id' => $usuario?->id]);
            } elseif (! $cumplido) {
                $fila->fill(['cumplido' => false, 'fecha_cumplido' => null, 'usuario_cumplido_id' => null]);
            }
        }
        if (array_key_exists('observacion', $datos)) {
            $fila->observacion = $datos['observacion'] !== null && trim($datos['observacion']) !== ''
                ? trim($datos['observacion'])
                : null;
        }

        $fila->save();
        $expediente->touch();

        return $fila;
    }

    public function guardarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila, UploadedFile $archivo): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        $anterior = $fila->soporte_archivo;
        // Extensión deducida del contenido, no la que declara quien sube el archivo.
        $ruta = $archivo->storeAs("expedientes/{$expediente->id}", Str::uuid() . '.' . $archivo->extension(), self::DISCO);

        $fila->update(['soporte_archivo' => $ruta, 'soporte_nombre' => $archivo->getClientOriginalName()]);

        if ($anterior) {
            Storage::disk(self::DISCO)->delete($anterior);
        }

        return $fila;
    }

    public function eliminarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        if ($fila->soporte_archivo) {
            Storage::disk(self::DISCO)->delete($fila->soporte_archivo);
        }
        $fila->update(['soporte_archivo' => null, 'soporte_nombre' => null]);

        return $fila;
    }

    public function radicar(ProyectoExpediente $expediente, ?User $usuario): ProyectoExpediente
    {
        $this->exigirBorrador($expediente);

        ['total' => $total, 'cumplidos' => $cumplidos] = $this->progresoDe($expediente);
        if ($total === 0) {
            throw new ReglaNegocioException('No se puede radicar: el expediente no tiene requisitos aplicables.');
        }
        if ($cumplidos < $total) {
            $pendientes = $total - $cumplidos;
            $verbo = $pendientes === 1 ? 'falta' : 'faltan';
            throw new ReglaNegocioException("No se puede radicar: {$verbo} {$pendientes} de {$total} requisitos por cumplir.");
        }

        $expediente->update([
            'estado' => ProyectoExpediente::RADICADO,
            'fecha_radicado' => now(),
            'usuario_radicado_id' => $usuario?->id,
        ]);

        return $expediente;
    }

    public function eliminar(ProyectoExpediente $expediente): void
    {
        $this->exigirBorrador($expediente);

        DB::transaction(function () use ($expediente) {
            $archivos = $expediente->requisitos()->whereNotNull('soporte_archivo')->pluck('soporte_archivo')->all();
            $expediente->delete(); // pivotes y checklist caen en cascada
            Storage::disk(self::DISCO)->delete($archivos);
        });
    }

    // ---------------------------------------------------------- recálculo

    /**
     * Alinea el checklist con la configuración actual del expediente.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        $cfg = $this->configuracionDe($expediente);

        $catalogo = RequisitoCatalogo::query()
            ->where('catalogo_version_id', $expediente->catalogo_version_id)
            ->where('activo', true)
            ->with(['sector:id,clave', 'tipo:id,clave'])
            ->orderBy('orden')
            ->get();

        $aplican = array_flip($this->evaluador->aplicables(
            $catalogo->map(fn (RequisitoCatalogo $r) => [
                'clave' => $r->clave,
                'seccion' => $r->seccion,
                'condicion' => $r->condicion,
                'sector' => $r->sector?->clave,
                'tipo' => $r->tipo?->clave,
            ]),
            $cfg
        ));

        $existentes = $expediente->requisitos()->get()->keyBy('requisito_catalogo_id');
        $vistos = [];

        foreach ($catalogo as $requisito) {
            $vistos[$requisito->id] = true;
            $aplica = isset($aplican[$requisito->clave]);
            $orden = self::ORDEN_SECCION[$requisito->seccion] * 100000 + $requisito->orden;
            $fila = $existentes->get($requisito->id);

            if ($fila) {
                if ($fila->aplica !== $aplica || $fila->orden !== $orden) {
                    $fila->update(['aplica' => $aplica, 'orden' => $orden]);
                }
            } elseif ($aplica) {
                $expediente->requisitos()->create([
                    'requisito_catalogo_id' => $requisito->id,
                    'aplica' => true,
                    'orden' => $orden,
                ]);
            }
        }

        // Filas de requisitos desactivados en el catálogo: dejan de aplicar.
        $expediente->requisitos()
            ->whereNotIn('requisito_catalogo_id', array_keys($vistos))
            ->where('aplica', true)
            ->update(['aplica' => false]);

        return [
            'aplican' => count($aplican),
            'retenidos' => $expediente->requisitos()->where('aplica', false)->count(),
        ];
    }

    public function configuracionDe(ProyectoExpediente $expediente): ConfiguracionExpediente
    {
        $expediente->loadMissing(['sectores:id,clave', 'tipos.sector:id,clave', 'componentes:id,clave', 'tipologias:id,clave']);

        return new ConfiguracionExpediente(
            $expediente->fase_sgr,
            $expediente->sectores->pluck('clave')->all(),
            $expediente->tipos->map(fn (RequisitoTipo $t) => $t->sector->clave . '::' . $t->clave)->all(),
            $expediente->componentes->pluck('clave')->all(),
            $expediente->tipologias->pluck('clave')->all(),
        );
    }

    // ------------------------------------------------------------- lectura

    public function presentar(ProyectoExpediente $expediente): array
    {
        $expediente->load([
            'proyecto:id,nombre', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);

        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(['requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name'])
            ->orderBy('orden')
            ->get();

        $cumplidos = $filas->where('cumplido', true)->count();

        return [
            ...$this->presentarCabecera($expediente),
            'configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ],
            'progreso' => $this->progreso($filas->count(), $cumplidos),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f))->values(),
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)
                ->where(fn ($q) => $q->where('cumplido', true)->orWhereNotNull('observacion')->orWhereNotNull('soporte_archivo'))
                ->count(),
        ];
    }

    /**
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance.
     * Son dos consultas sin importar cuántos proyectos haya.
     */
    public function resumenPorProyecto(): array
    {
        $cantidades = ProyectoExpediente::query()
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');

        return ProyectoExpediente::query()
            ->whereIn('id', $cantidades->keys())
            ->withCount([
                'requisitos as requisitos_aplican' => fn ($q) => $q->where('aplica', true),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->where('aplica', true)->where('cumplido', true),
            ])
            ->orderBy('proyecto_id')
            ->get()
            ->map(fn (ProyectoExpediente $e) => [
                'proyecto_id' => $e->proyecto_id,
                'cantidad_expedientes' => (int) $cantidades[$e->id],
                'expediente_id' => $e->id,
                'estado' => $e->estado,
                'fase_sgr' => $e->fase_sgr,
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
            ])
            ->all();
    }

    public function presentarCabecera(ProyectoExpediente $expediente): array
    {
        $expediente->loadMissing(['proyecto:id,nombre', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'catalogo_version' => ['id' => $expediente->version->id, 'numero' => $expediente->version->numero],
            'fase_sgr' => $expediente->fase_sgr,
            'estado' => $expediente->estado,
            'fecha_radicado' => $expediente->fecha_radicado?->toIso8601String(),
            'usuario_radico' => $expediente->usuarioRadico?->name,
            'usuario_creo' => $expediente->usuarioCreo?->name,
            'creado' => $expediente->created_at?->toIso8601String(),
            'actualizado' => $expediente->updated_at?->toIso8601String(),
        ];
    }

    public function progresoDe(ProyectoExpediente $expediente): array
    {
        $base = $expediente->requisitos()->where('aplica', true);

        return $this->progreso((clone $base)->count(), (clone $base)->where('cumplido', true)->count());
    }

    public function presentarFila(ProyectoRequisito $fila): array
    {
        $fila->loadMissing(['requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name']);
        $r = $fila->requisito;

        return [
            'id' => $fila->id,
            'cumplido' => $fila->cumplido,
            'fecha_cumplido' => $fila->fecha_cumplido?->toIso8601String(),
            'usuario_cumplido' => $fila->usuarioCumplido?->name,
            'observacion' => $fila->observacion,
            'soporte' => $fila->soporte_archivo ? ['nombre' => $fila->soporte_nombre] : null,
            'requisito' => [
                'id' => $r->id,
                'clave' => $r->clave,
                'seccion' => $r->seccion,
                'fuente' => $r->fuente,
                'sector' => $r->sector ? ['id' => $r->sector->id, 'clave' => $r->sector->clave, 'nombre' => $r->sector->nombre, 'fuente' => $r->sector->fuente] : null,
                'tipo' => $r->tipo ? ['id' => $r->tipo->id, 'clave' => $r->tipo->clave, 'nombre' => $r->tipo->nombre] : null,
                'subtitulo' => $r->subtitulo,
                'codigo' => $r->codigo,
                'texto' => $r->texto,
                'nota' => $r->nota,
            ],
        ];
    }

    // ------------------------------------------------------------ internos

    /**
     * Comprueba ids contra el catálogo. Acepta elementos inactivos solo si el
     * expediente ya los tenía (para no forzar a quitarlos al reconfigurar).
     */
    private function validarSeleccion(array $datos, ?ProyectoExpediente $expediente): array
    {
        $errores = [];
        $resultado = [];

        $grupos = [
            'sector_ids' => [RequisitoSector::class, 'sectores', 'sector'],
            'tipo_ids' => [RequisitoTipo::class, 'tipos', 'tipo de proyecto'],
            'componente_ids' => [RequisitoComponente::class, 'componentes', 'componente'],
            'tipologia_ids' => [RequisitoTipologia::class, 'tipologias', 'tipología'],
        ];

        foreach ($grupos as $campo => [$modelo, $relacion, $etiqueta]) {
            $ids = array_values(array_unique(array_map('intval', $datos[$campo] ?? [])));
            $previos = $expediente
                ? array_map('intval', $expediente->{$relacion}()->pluck($this->tablaDe($modelo) . '.id')->all())
                : [];

            $encontrados = $modelo::query()->whereIn('id', $ids)->get()->keyBy('id');
            foreach ($ids as $id) {
                $item = $encontrados->get($id);
                if (! $item) {
                    $errores[] = "El {$etiqueta} con id {$id} no existe.";
                } elseif (! $item->activo && ! in_array($id, $previos, true)) {
                    $errores[] = "El {$etiqueta} \"{$item->nombre}\" está inactivo.";
                }
            }
            $resultado[$campo] = $ids;
            $resultado['_' . $campo] = $encontrados;
        }

        // Un tipo marcado debe pertenecer a un sector marcado.
        foreach ($resultado['_tipo_ids'] as $tipo) {
            if (! in_array($tipo->sector_id, $resultado['sector_ids'], true)) {
                $errores[] = "El tipo \"{$tipo->nombre}\" pertenece a un sector que no está seleccionado.";
            }
        }

        if ($errores) {
            throw new ReglaNegocioException('La configuración del expediente no es válida.', $errores);
        }

        return array_filter($resultado, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
    }

    private function tablaDe(string $modelo): string
    {
        return (new $modelo())->getTable();
    }

    private function guardarSeleccion(ProyectoExpediente $expediente, array $seleccion): void
    {
        $expediente->sectores()->sync($seleccion['sector_ids']);
        $expediente->tipos()->sync($seleccion['tipo_ids']);
        $expediente->componentes()->sync($seleccion['componente_ids']);
        $expediente->tipologias()->sync($seleccion['tipologia_ids']);
        $expediente->unsetRelation('sectores')->unsetRelation('tipos')
            ->unsetRelation('componentes')->unsetRelation('tipologias');
    }

    private function exigirBorrador(ProyectoExpediente $expediente): void
    {
        if (! $expediente->esBorrador()) {
            throw new ReglaNegocioException('El expediente está radicado: su checklist está congelado y no admite cambios.');
        }
    }

    private function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
    {
        $this->exigirBorrador($expediente);

        if ($fila->expediente_id !== $expediente->id) {
            throw new ReglaNegocioException('El requisito no pertenece a este expediente.');
        }
        if (! $fila->aplica) {
            throw new ReglaNegocioException('El requisito no aplica con la configuración actual del expediente.');
        }
    }

    private function progreso(int $total, int $cumplidos): array
    {
        return [
            'total' => $total,
            'cumplidos' => $cumplidos,
            'porcentaje' => $total > 0 ? (int) round($cumplidos / $total * 100) : 0,
        ];
    }

    private function opciones($coleccion): array
    {
        return $coleccion->map(fn ($i) => ['id' => $i->id, 'clave' => $i->clave, 'nombre' => $i->nombre])->values()->all();
    }
}
