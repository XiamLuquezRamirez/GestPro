<?php

namespace App\Services\Contratacion;

use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qué plantillas de contratación corresponden a un proceso. Es la única definición: la usan la
 * creación de su expediente (ExpedientesContratacion), la cabecera del expediente y el aviso del
 * proceso. Las de viabilización tienen la suya (PlantillasExpediente).
 *
 * Corresponden las plantillas de contratación activas, con motor simple y versión publicada
 * vigente, que cubren la modalidad del proceso (o todas), su fuente (o todas; sin fuente, solo las
 * de todas las fuentes) y el sector del proyecto (o todos; sin sector, solo las de todos los
 * sectores). Se decide en memoria con una lectura de las candidatas y sus coberturas, así que
 * cuesta lo mismo para un proceso que para todos los de un proyecto.
 */
final class PlantillasContratacion
{
    /** @return list<int> ids de las plantillas que hoy corresponden al proceso, de menor a mayor */
    public function idsCompatibles(ProcesoLicitacion $proceso): array
    {
        return $this->idsCompatiblesPorProceso(collect([$proceso]))[$proceso->id];
    }

    /**
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @return array<int, list<int>> proceso_id => ids de sus plantillas compatibles, de menor a mayor
     */
    public function idsCompatiblesPorProceso(Collection $procesos): array
    {
        if ($procesos->isEmpty()) {
            return [];
        }
        $candidatas = PlantillaChecklist::query()
            ->where('uso', PlantillaChecklist::USO_CONTRATACION)
            ->where('motor', PlantillaChecklist::MOTOR_SIMPLE)
            ->where('activo', true)
            ->whereHas('versiones', fn ($q) => $this->publicada($q))
            ->orderBy('id')
            ->get(['id', 'todas_modalidades', 'todas_fuentes', 'todos_sectores']);

        return $this->cubiertasPorProceso($procesos, $candidatas);
    }

    /**
     * Cómo está cada una de las plantillas dadas frente a cada proceso, para avisar por qué ya no
     * le sirve: si cubre su modalidad, su fuente y su sector (sin mirar si está activa o
     * publicada; una que no es de contratación con motor simple no cubre), si está activa y si
     * tiene versión publicada vigente. Cuesta lo mismo para un proceso que para varios.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @param list<int> $plantillaIds
     * @return array<int, array<int, array{corresponde: bool, activa: bool, publicada: bool}>> proceso_id => plantilla_id => estado
     */
    public function estadoPorProceso(Collection $procesos, array $plantillaIds): array
    {
        if ($procesos->isEmpty()) {
            return [];
        }
        $plantillas = $plantillaIds === [] ? collect() : PlantillaChecklist::query()
            ->whereIn('id', $plantillaIds)
            ->withExists(['versiones as tiene_publicada' => fn ($q) => $this->publicada($q)])
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        $cubiertas = $this->cubiertasPorProceso($procesos, $plantillas->filter(
            fn (PlantillaChecklist $p) => $p->esContratacion() && $p->motor === PlantillaChecklist::MOTOR_SIMPLE
        )->values());

        return $procesos->mapWithKeys(fn (ProcesoLicitacion $proceso) => [$proceso->id => collect($plantillaIds)
            ->mapWithKeys(fn (int $id) => [$id => [
                'corresponde' => in_array($id, $cubiertas[$proceso->id], true),
                'activa' => (bool) $plantillas->get($id)?->activo,
                'publicada' => (bool) $plantillas->get($id)?->tiene_publicada,
            ]])->all()])->all();
    }

    /**
     * Por qué una plantilla ya no le sirve al proceso, con el estado de estadoPorProceso(); null si
     * le sirve. Primero la cobertura: es lo que cambia al editar el proceso o el proyecto.
     *
     * @param array{corresponde: bool, activa: bool, publicada: bool} $estado
     * @return ?string 'no_corresponde', 'inactiva' o 'sin_publicada'
     */
    public static function razon(array $estado): ?string
    {
        return match (true) {
            ! $estado['corresponde'] => 'no_corresponde',
            ! $estado['activa'] => 'inactiva',
            ! $estado['publicada'] => 'sin_publicada',
            default => null,
        };
    }

    /**
     * Las candidatas que cubren la modalidad, la fuente y el sector de cada proceso.
     *
     * @param Collection<int, ProcesoLicitacion> $procesos
     * @param Collection<int, PlantillaChecklist> $candidatas ordenadas por id
     * @return array<int, list<int>> proceso_id => ids, de menor a mayor
     */
    private function cubiertasPorProceso(Collection $procesos, Collection $candidatas): array
    {
        $ids = $candidatas->pluck('id');
        $cubre = fn (string $tabla, string $columna) => $ids->isEmpty() ? collect() : DB::table($tabla)
            ->whereIn('plantilla_id', $ids)
            ->get(['plantilla_id', $columna])
            ->groupBy('plantilla_id')
            ->map(fn (Collection $filas) => $filas->pluck($columna)->map(fn ($id) => (int) $id)->flip());
        $modalidades = $cubre('plantilla_modalidades', 'modalidad_id');
        $fuentes = $cubre('plantilla_fuentes', 'fuente_id');
        $sectores = $cubre('plantilla_sectores', 'sector_id');
        $sectorDe = Proyecto::query()->whereIn('id', $procesos->pluck('proyecto')->unique()->values())->pluck('sector', 'id');

        $cubierto = fn (bool $todas, ?Collection $elegidas, mixed $valor) => $todas
            || ($valor !== null && $elegidas?->has((int) $valor));

        return $procesos->mapWithKeys(function (ProcesoLicitacion $proceso) use ($candidatas, $modalidades, $fuentes, $sectores, $sectorDe, $cubierto) {
            $sector = $sectorDe->get($proceso->proyecto);

            return [$proceso->id => $candidatas
                ->filter(fn (PlantillaChecklist $p) => $cubierto((bool) $p->todas_modalidades, $modalidades->get($p->id), $proceso->modalidad)
                    && $cubierto((bool) $p->todas_fuentes, $fuentes->get($p->id), $proceso->fuente_id)
                    && $cubierto((bool) $p->todos_sectores, $sectores->get($p->id), $sector))
                ->pluck('id')->map(fn ($id) => (int) $id)->values()->all()];
        })->all();
    }

    /**
     * La versión vigente, si está publicada: un borrador nunca arma un expediente.
     *
     * @param Builder|HasMany $versiones consulta de versiones de plantilla
     */
    public function publicada(Builder|HasMany $versiones): Builder|HasMany
    {
        return $versiones->vigente()->where('estado', CatalogoVersion::PUBLICADA);
    }
}
