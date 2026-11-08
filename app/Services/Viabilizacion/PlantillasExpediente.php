<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plantillas de un expediente: cuáles se ofrecen a un proyecto, cuáles se pueden elegir
 * y con qué versión quedan.
 *
 * Reglas:
 * - Se ofrecen las plantillas activas con versión vigente de alguna fuente del proyecto
 *   (o de todas) y de su sector (o de todos). Sin sector, solo las de todos los sectores.
 * - Una plantilla nueva en el expediente entra con su versión vigente; la que ya tenía
 *   conserva su versión aunque haya dejado de ofrecerse (sin cambios retroactivos).
 * - Como máximo una plantilla con motor sgr; si hay plantillas, una es la principal.
 * - proyectos_expedientes.catalogo_version_id guarda la versión de la plantilla sgr, o null.
 */
final class PlantillasExpediente
{
    /**
     * Plantillas que se ofrecen al proyecto, y al expediente si se indica: las compatibles
     * más las que ya tiene, aunque hayan dejado de ser compatibles o estén inactivas.
     *
     * @return array{sin_sector: bool, plantillas: list<array>}
     */
    public function disponibles(Proyecto $proyecto, ?ProyectoExpediente $expediente = null): array
    {
        $compatibles = $this->idsCompatibles($proyecto);
        $enExpediente = $expediente ? $this->versionesDe($expediente)->keyBy('plantilla_id') : collect();

        $plantillas = PlantillaChecklist::query()
            ->whereIn('id', [...$compatibles, ...$enExpediente->keys()])
            ->with([
                'fuentes:id,nombre', 'sectores:id,nombre',
                'versiones' => fn ($q) => $q->vigente()->withCount(['requisitos' => fn ($r) => $r->where('activo', true)]),
            ])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return [
            'sin_sector' => $proyecto->sector === null,
            'plantillas' => $plantillas->map(function (PlantillaChecklist $p) use ($compatibles, $enExpediente) {
                $vigente = $p->versiones->first();
                $propia = $enExpediente->get($p->id);

                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'motor' => $p->motor,
                    'es_sgr' => $p->clave === PlantillaChecklist::CLAVE_SGR,
                    'activa' => $p->activo,
                    'compatible' => in_array($p->id, $compatibles, true),
                    'fuentes' => $p->fuentes->map(fn ($f) => ['id' => $f->id, 'nombre' => $f->nombre])->values()->all(),
                    'sectores' => $p->sectores->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values()->all(),
                    'todas_fuentes' => $p->todas_fuentes,
                    'todos_sectores' => $p->todos_sectores,
                    'requisitos' => (int) ($vigente?->requisitos_count ?? 0),
                    'version' => $vigente ? ['id' => $vigente->id, 'numero' => $vigente->numero] : null,
                    'en_expediente' => $propia ? [
                        'version' => ['id' => $propia->id, 'numero' => $propia->numero],
                        'es_principal' => (bool) $propia->pivot->es_principal,
                    ] : null,
                ];
            })->values()->all(),
        ];
    }

    /** @return list<int> ids de las plantillas que hoy se ofrecen al proyecto */
    public function idsCompatibles(Proyecto $proyecto): array
    {
        $fuentes = $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all();
        $sector = $proyecto->sector;

        return PlantillaChecklist::query()
            ->where('activo', true)
            ->whereHas('versiones', fn (Builder $q) => $q->vigente())
            ->where(fn (Builder $q) => $q->where('todas_fuentes', true)
                ->orWhereHas('fuentes', fn (Builder $f) => $f->whereIn('fuentes_financiacion.id', $fuentes)))
            ->where(fn (Builder $q) => $q->where('todos_sectores', true)
                ->when($sector !== null, fn (Builder $s) => $s->orWhereHas('sectores', fn (Builder $w) => $w->where('sectores.id', $sector))))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Selección con que nace un expediente si el pedido no trae plantillas: la plantilla
     * compatible con motor sgr como principal, o ninguna.
     *
     * @return array{plantilla_ids: list<int>, principal_id: ?int}
     */
    public function preseleccion(Proyecto $proyecto): array
    {
        $sgr = $this->sgrCompatible($proyecto);

        return ['plantilla_ids' => $sgr ? [$sgr->id] : [], 'principal_id' => $sgr?->id];
    }

    /** Plantilla compatible con motor sgr que se preselecciona: la de clave sgr antes que una copia. */
    public function sgrCompatible(Proyecto $proyecto): ?PlantillaChecklist
    {
        return PlantillaChecklist::query()
            ->whereIn('id', $this->idsCompatibles($proyecto))
            ->where('motor', PlantillaChecklist::MOTOR_SGR)
            ->get()
            ->sortBy(fn (PlantillaChecklist $p) => [$p->clave === PlantillaChecklist::CLAVE_SGR ? 0 : 1, $p->id])
            ->first();
    }

    /** Si alguna de las versiones es de una plantilla con motor sgr. */
    public function tieneSgr(Collection $versiones): bool
    {
        return $versiones->contains(fn (CatalogoVersion $v) => $v->plantilla->motor === PlantillaChecklist::MOTOR_SGR);
    }

    /**
     * Versiones de plantilla del expediente, con su plantilla: la principal primero y luego
     * por plantilla. Es el orden en que se arma y se muestra el checklist.
     *
     * @return Collection<int, CatalogoVersion> cada una con pivot->es_principal
     */
    public function versionesDe(ProyectoExpediente $expediente): Collection
    {
        return $expediente->versionesPlantilla()
            ->with('plantilla')
            ->get()
            ->sortBy([
                fn (CatalogoVersion $a, CatalogoVersion $b) => (int) $b->pivot->es_principal <=> (int) $a->pivot->es_principal,
                fn (CatalogoVersion $a, CatalogoVersion $b) => $a->plantilla_id <=> $b->plantilla_id,
            ])
            ->values();
    }

    /**
     * Comprueba la selección pedida y decide la versión de cada plantilla.
     *
     * @param array{plantilla_ids?: list<int>, principal_id?: ?int} $datos
     * @return array{versiones: array<int, int>, principal_id: ?int, version_sgr_id: ?int}
     *         versiones: plantilla_id => catalogo_version_id, en el orden pedido
     */
    public function validar(array $datos, Proyecto $proyecto, ?ProyectoExpediente $expediente): array
    {
        $ids = array_values(array_unique(array_map('intval', $datos['plantilla_ids'] ?? [])));
        $principal = isset($datos['principal_id']) ? (int) $datos['principal_id'] : null;
        $previas = $expediente ? $this->versionesDe($expediente)->keyBy('plantilla_id') : collect();
        $compatibles = $this->idsCompatibles($proyecto);
        $encontradas = PlantillaChecklist::query()->whereIn('id', $ids)->get()->keyBy('id');

        $errores = [];
        $versiones = [];
        $sgr = [];
        foreach ($ids as $id) {
            $plantilla = $encontradas->get($id);
            if (! $plantilla) {
                $errores[] = "La plantilla con id {$id} no existe.";
                continue;
            }

            $version = $previas->get($id) ?? $plantilla->versionVigente();
            if (! $previas->has($id)) {
                if (! $plantilla->activo) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" está inactiva.";
                } elseif (! $version) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" no tiene una versión publicada.";
                } elseif (! in_array($id, $compatibles, true)) {
                    $errores[] = "La plantilla \"{$plantilla->nombre}\" no es compatible con las fuentes o el sector del proyecto.";
                }
            }
            if ($version) {
                $versiones[$id] = $version->id;
            }
            if ($plantilla->motor === PlantillaChecklist::MOTOR_SGR && $version) {
                $sgr[] = $version->id;
            }
        }

        if (count($sgr) > 1) {
            $errores[] = 'Un expediente admite como máximo una plantilla con motor SGR.';
        }

        if ($principal === null && count($ids) === 1) {
            $principal = $ids[0];
        } elseif ($principal === null && count($ids) > 1) {
            $errores[] = 'Elige cuál de las plantillas es la principal.';
        } elseif ($principal !== null && ! in_array($principal, $ids, true)) {
            $errores[] = 'La plantilla principal debe estar entre las elegidas.';
        }

        if ($errores) {
            throw new ReglaNegocioException('La configuración del expediente no es válida.', $errores);
        }

        return ['versiones' => $versiones, 'principal_id' => $principal, 'version_sgr_id' => $sgr[0] ?? null];
    }

    /**
     * Deja el expediente con las plantillas elegidas, sus versiones y la versión SGR.
     *
     * Se llama dentro de la transacción que bloqueó el expediente y validó la selección
     * (ExpedienteService). Abre la suya, que entonces es un savepoint: el pivote y
     * catalogo_version_id nunca quedan a medias.
     */
    public function guardar(ProyectoExpediente $expediente, array $seleccion): void
    {
        DB::transaction(function () use ($expediente, $seleccion) {
            $expediente->versionesPlantilla()->sync(
                collect($seleccion['versiones'])
                    ->mapWithKeys(fn (int $version, int $plantilla) => [$version => ['es_principal' => $plantilla === $seleccion['principal_id']]])
                    ->all()
            );
            $expediente->update(['catalogo_version_id' => $seleccion['version_sgr_id']]);
        });
        $expediente->unsetRelation('versionesPlantilla')->unsetRelation('version');
    }
}
