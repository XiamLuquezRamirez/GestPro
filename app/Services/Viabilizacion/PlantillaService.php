<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoSector;
use App\Models\RequisitoTipo;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Administración de plantillas de checklist (rol Administrador): datos generales,
 * fuentes y sectores a los que aplican, y activación. Los requisitos y las versiones
 * se administran con CatalogoService.
 */
final class PlantillaService
{
    public const ALCANCES = ['general', 'especifica'];

    /** Norma que indicaba la fuente de un requisito SGR, para conservarla al pasar a simple. */
    private const NORMAS = ['a12' => 'Acuerdo 12 de 2024', 'ot' => 'Orientaciones Transitorias'];

    public function __construct(private readonly CatalogoService $catalogo)
    {
    }

    /** Cuentas de requisitos por versión: el total y solo los activos (los que verá el usuario). */
    private function cuentasRequisitos(): array
    {
        return ['requisitos', 'requisitos as requisitos_activos_count' => fn ($q) => $q->where('activo', true)];
    }

    public function listar(?string $buscar): array
    {
        $buscar = trim((string) $buscar);

        return PlantillaChecklist::query()
            ->with(['fuentes:id,nombre', 'sectores:id,nombre', 'versiones' => fn ($q) => $q->withCount($this->cuentasRequisitos())])
            ->when($buscar !== '', function ($q) use ($buscar) {
                $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
                $q->where(fn ($w) => $w->whereRaw("nombre like ? escape '!'", [$patron])->orWhereRaw("descripcion like ? escape '!'", [$patron]));
            })
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (PlantillaChecklist $p) => $this->resumen($p))
            ->all();
    }

    public function presentar(PlantillaChecklist $plantilla): array
    {
        $plantilla->load([
            'fuentes:id,nombre', 'sectores:id,nombre',
            'versiones' => fn ($q) => $q->withCount([...$this->cuentasRequisitos(), 'expedientes'])->with('usuarioPublico:id,name')->orderByDesc('numero'),
        ]);

        return [
            ...$this->resumen($plantilla),
            'versiones' => $plantilla->versiones->map(fn (CatalogoVersion $v) => [
                'id' => $v->id,
                'numero' => $v->numero,
                'estado' => $v->estado,
                'vigente' => $v->vigente,
                'descripcion' => $v->descripcion,
                'fecha_publicacion' => $v->fecha_publicacion?->toIso8601String(),
                'publicada_por' => $v->usuarioPublico?->name,
                'requisitos' => $v->requisitos_count,
                'requisitos_activos' => $v->requisitos_activos_count,
                'expedientes' => $v->expedientes_count,
            ])->values()->all(),
        ];
    }

    /** Las plantillas nuevas usan el motor simple y nacen con su primera versión en borrador. */
    public function crear(array $datos, ?User $usuario): PlantillaChecklist
    {
        $asociaciones = $this->validar($datos, null);

        return DB::transaction(function () use ($datos, $asociaciones, $usuario) {
            $plantilla = PlantillaChecklist::create([
                ...$this->datosGenerales($datos),
                'motor' => PlantillaChecklist::MOTOR_SIMPLE,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->asociar($plantilla, $asociaciones);
            $this->catalogo->crearBorrador('Versión inicial', $plantilla);

            return $plantilla;
        });
    }

    public function actualizar(PlantillaChecklist $plantilla, array $datos): PlantillaChecklist
    {
        $asociaciones = $this->validar($datos, $plantilla);

        return DB::transaction(function () use ($plantilla, $datos, $asociaciones) {
            $plantilla->update($this->datosGenerales($datos));
            $this->asociar($plantilla, $asociaciones);

            return $plantilla;
        });
    }

    public function activar(PlantillaChecklist $plantilla, bool $activo): PlantillaChecklist
    {
        if (! $activo && $plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
            throw new ReglaNegocioException('La plantilla SGR no se puede desactivar: la usan los expedientes de regalías.');
        }
        $plantilla->update(['activo' => $activo]);

        return $plantilla;
    }

    /**
     * Copia independiente de una plantilla, en borrador, con los requisitos de su versión
     * vigente que se elijan y el mismo código común. Una copia con motor sgr puede pasar a
     * simple: las condiciones automáticas se vuelven condicionados que decide una persona.
     */
    public function duplicar(PlantillaChecklist $origen, array $datos, ?User $usuario): PlantillaChecklist
    {
        $vigente = $origen->versionVigente()
            ?? throw new ReglaNegocioException('La plantilla no tiene una versión publicada que copiar.');

        $motor = $datos['motor'] ?? $origen->motor;
        if ($origen->motor === PlantillaChecklist::MOTOR_SIMPLE && $motor !== PlantillaChecklist::MOTOR_SIMPLE) {
            throw new ReglaNegocioException('Una plantilla simple solo se puede copiar como simple.');
        }

        $requisitos = $vigente->requisitos()->where('activo', true)->orderBy('orden')->orderBy('id')->get();
        if (array_key_exists('requisito_ids', $datos)) {
            $pedidos = array_map('intval', $datos['requisito_ids']);
            $ajenos = array_diff($pedidos, $requisitos->pluck('id')->all());
            if ($ajenos) {
                throw new ReglaNegocioException('Algunos requisitos elegidos no son de la versión vigente de la plantilla o están inactivos.');
            }
            $requisitos = $requisitos->whereIn('id', $pedidos)->values();
        }
        if ($requisitos->isEmpty()) {
            throw new ReglaNegocioException('Elige al menos un requisito para la copia.');
        }

        // Sin fuentes o sectores activos que heredar, el error genérico de validar() no explicaría la causa.
        $inactivas = [];
        if (! $origen->todas_fuentes && ! $origen->fuentes()->where('activo', true)->exists()) {
            $inactivas[] = 'Las fuentes de financiación de la plantilla de origen están inactivas. Actívalas, o edita la plantilla de origen para elegir otras fuentes o marcar "Todas las fuentes", y vuelve a copiarla.';
        }
        if (! $origen->todos_sectores && ! $origen->sectores()->where('activo', true)->exists()) {
            $inactivas[] = 'Los sectores de la plantilla de origen están inactivos. Actívalos, o edita la plantilla de origen para elegir otros sectores o marcar "Todos los sectores", y vuelve a copiarla.';
        }
        if ($inactivas) {
            throw new ReglaNegocioException('La plantilla no es válida.', $inactivas);
        }

        $general = [
            'nombre' => $datos['nombre'],
            'descripcion' => $origen->descripcion,
            'alcance' => $origen->alcance,
            'todas_fuentes' => $origen->todas_fuentes,
            'todos_sectores' => $origen->todos_sectores,
            'fuente_ids' => $origen->fuentes()->where('activo', true)->pluck('fuentes_financiacion.id')->all(),
            'sector_ids' => $origen->sectores()->where('activo', true)->pluck('sectores.id')->all(),
        ];
        $asociaciones = $this->validar($general, null);

        return DB::transaction(function () use ($general, $asociaciones, $motor, $requisitos, $vigente, $origen, $usuario) {
            $copia = PlantillaChecklist::create([
                ...$this->datosGenerales($general),
                'motor' => $motor,
                'activo' => true,
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->asociar($copia, $asociaciones);
            $borrador = $this->catalogo->crearBorrador("Copia de {$origen->nombre} v{$vigente->numero}", $copia);

            $aSimple = $origen->motor === PlantillaChecklist::MOTOR_SGR && $motor === PlantillaChecklist::MOTOR_SIMPLE;
            $sectores = $aSimple ? RequisitoSector::pluck('nombre', 'id')->all() : [];
            $tipos = $aSimple ? RequisitoTipo::pluck('nombre', 'id')->all() : [];

            foreach ($requisitos->values() as $posicion => $requisito) {
                $fila = [
                    ...$requisito->only([
                        'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion',
                        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                        'requiere_aprobacion_no_aplica', 'codigo_comun', 'responsable_cumplimiento_id', 'responsable_revision_id',
                    ]),
                    'catalogo_version_id' => $borrador->id,
                    'orden' => $posicion + 1,
                    'activo' => true,
                ];
                RequisitoCatalogo::create($aSimple ? $this->aSimple($fila, $sectores, $tipos) : $fila);
            }

            return $copia;
        });
    }

    // ------------------------------------------------------------- internos

    /**
     * Un requisito SGR en una plantilla simple: deja la sección y las condiciones automáticas.
     * Si dependía de una condición o de un sector, queda condicionado y la descripción guarda
     * lo que decidía el evaluador, para que el administrador la redacte antes de publicar.
     */
    private function aSimple(array $fila, array $sectores, array $tipos): array
    {
        $condicion = $fila['condicion'];
        $porCondicion = $condicion !== null && $condicion !== 'siempre';
        $porSector = $fila['sector_id'] !== null;

        $partes = [];
        if ($porSector) {
            $partes[] = 'Sector: ' . ($sectores[$fila['sector_id']] ?? '')
                . ($fila['tipo_id'] ? ' — ' . ($tipos[$fila['tipo_id']] ?? '') : '');
        }
        if ($porCondicion) {
            $partes[] = "Condición original: {$condicion}";
        }

        return [
            ...$fila,
            'seccion' => null,
            'fuente' => null,
            'sector_id' => null,
            'tipo_id' => null,
            'condicion' => null,
            'requiere_aprobacion_no_aplica' => false,
            'referencia_normativa' => $fila['referencia_normativa'] ?: (self::NORMAS[$fila['fuente']] ?? null),
            'caracter' => ($porCondicion || $porSector) ? RequisitoCatalogo::CONDICIONADO : $fila['caracter'],
            'condicion_descripcion' => $partes ? implode('. ', $partes) : $fila['condicion_descripcion'],
        ];
    }

    private function resumen(PlantillaChecklist $plantilla): array
    {
        $vigente = $plantilla->versiones->firstWhere('vigente', true);
        $borrador = $plantilla->versiones->firstWhere('estado', CatalogoVersion::BORRADOR);
        $version = fn (?CatalogoVersion $v) => $v ? ['id' => $v->id, 'numero' => $v->numero, 'requisitos' => $v->requisitos_activos_count] : null;

        return [
            'id' => $plantilla->id,
            'clave' => $plantilla->clave,
            'es_sgr' => $plantilla->clave === PlantillaChecklist::CLAVE_SGR,
            'nombre' => $plantilla->nombre,
            'descripcion' => $plantilla->descripcion,
            'alcance' => $plantilla->alcance,
            'motor' => $plantilla->motor,
            'activo' => $plantilla->activo,
            'todas_fuentes' => $plantilla->todas_fuentes,
            'todos_sectores' => $plantilla->todos_sectores,
            'fuentes' => $plantilla->fuentes->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()->all(),
            'sectores' => $plantilla->sectores->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()->all(),
            'vigente' => $version($vigente),
            'borrador' => $version($borrador),
        ];
    }

    private function datosGenerales(array $datos): array
    {
        return [
            'nombre' => trim($datos['nombre']),
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')) ?: null,
            'alcance' => $datos['alcance'] ?? null,
            'todas_fuentes' => (bool) ($datos['todas_fuentes'] ?? false),
            'todos_sectores' => (bool) ($datos['todos_sectores'] ?? false),
        ];
    }

    /** @return array{fuente_ids: list<int>, sector_ids: list<int>} */
    private function validar(array $datos, ?PlantillaChecklist $plantilla): array
    {
        $errores = [];

        $nombre = trim((string) ($datos['nombre'] ?? ''));
        // Se compara en PHP para que MariaDB y SQLite ignoren igual mayúsculas y tildes.
        $normalizar = fn (string $texto) => Str::lower(Str::ascii(trim($texto)));
        $existente = PlantillaChecklist::query()
            ->when($plantilla, fn ($q) => $q->whereKeyNot($plantilla->id))
            ->pluck('nombre')
            ->first(fn ($guardado) => $normalizar($guardado) === $normalizar($nombre));
        if ($existente !== null) {
            $errores[] = "Ya existe una plantilla llamada \"{$existente}\".";
        }

        $todasFuentes = (bool) ($datos['todas_fuentes'] ?? false);
        $todosSectores = (bool) ($datos['todos_sectores'] ?? false);
        $fuenteIds = $todasFuentes ? [] : $this->idsValidos(FuenteFinanciacion::class, $datos['fuente_ids'] ?? [], $plantilla?->fuentes()->pluck('fuentes_financiacion.id')->all() ?? [], ['La fuente', 'inactiva'], $errores);
        $sectorIds = $todosSectores ? [] : $this->idsValidos(Sector::class, $datos['sector_ids'] ?? [], $plantilla?->sectores()->pluck('sectores.id')->all() ?? [], ['El sector', 'inactivo'], $errores);

        if (! $todasFuentes && ! $fuenteIds) {
            $errores[] = 'Elige al menos una fuente de financiación o marca "Todas las fuentes".';
        }
        if (! $todosSectores && ! $sectorIds) {
            $errores[] = 'Elige al menos un sector o marca "Todos los sectores".';
        }

        // La plantilla SGR debe seguir ofreciéndose a los proyectos de regalías.
        if ($plantilla?->clave === PlantillaChecklist::CLAVE_SGR && ! $todasFuentes) {
            $sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
            if (! in_array($sgr, $fuenteIds, true)) {
                $errores[] = 'La plantilla SGR debe seguir asociada a la fuente SGR.';
            }
        }

        // En el motor sgr el sector lo decide cada requisito con el evaluador.
        if ($plantilla?->clave === PlantillaChecklist::CLAVE_SGR && ! $todosSectores) {
            $errores[] = 'La plantilla SGR debe aplicar a todos los sectores: el evaluador decide qué requisitos sectoriales aplican.';
        }

        if ($errores) {
            throw new ReglaNegocioException('La plantilla no es válida.', $errores);
        }

        return ['fuente_ids' => $fuenteIds, 'sector_ids' => $sectorIds];
    }

    /** Ids que existen; uno inactivo solo si la plantilla ya lo tenía. */
    /** @param array{0: string, 1: string} $etiqueta p. ej. ['La fuente', 'inactiva'] */
    private function idsValidos(string $modelo, array $ids, array $previos, array $etiqueta, array &$errores): array
    {
        [$nombre, $inactivo] = $etiqueta;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $previos = array_map('intval', $previos);
        $encontrados = $modelo::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $id) {
            $item = $encontrados->get($id);
            if (! $item) {
                $errores[] = "{$nombre} con id {$id} no existe.";
            } elseif (! $item->activo && ! in_array($id, $previos, true)) {
                $errores[] = "{$nombre} \"{$item->nombre}\" está {$inactivo}.";
            }
        }

        return $ids;
    }

    private function asociar(PlantillaChecklist $plantilla, array $asociaciones): void
    {
        $plantilla->fuentes()->sync($asociaciones['fuente_ids']);
        $plantilla->sectores()->sync($asociaciones['sector_ids']);
    }
}
