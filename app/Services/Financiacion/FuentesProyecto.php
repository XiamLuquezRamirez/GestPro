<?php

namespace App\Services\Financiacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\FuenteFinanciacion;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de la financiación de un proyecto: una o varias fuentes del catálogo, o
 * "por definir". `proyectos.fuente_financiacion` guarda los nombres unidos para las
 * vistas que lo muestran; el dato real está en `proyecto_fuentes`.
 */
final class FuentesProyecto
{
    public const TEXTO_POR_DEFINIR = 'Por definir';

    /**
     * @param array{fuenteIds?: array, financiacionPorDefinir?: mixed} $datos
     * @return array{ids: list<int>, por_definir: bool, texto: string}
     */
    public function validar(array $datos, ?int $proyectoId = null): array
    {
        $porDefinir = filter_var($datos['financiacionPorDefinir'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $ids = $this->idsDe($datos['fuenteIds'] ?? []);

        if ($porDefinir && $ids !== []) {
            throw new ReglaNegocioException('Un proyecto con fuentes de financiación no puede estar también por definir.');
        }
        if (! $porDefinir && $ids === []) {
            throw new ReglaNegocioException('Seleccione al menos una fuente de financiación o marque que está por definir.');
        }
        if ($porDefinir) {
            return ['ids' => [], 'por_definir' => true, 'texto' => self::TEXTO_POR_DEFINIR];
        }

        $fuentes = FuenteFinanciacion::query()->whereIn('id', $ids)->orderBy('orden')->orderBy('nombre')->get();
        $this->exigirValidas($ids, $fuentes, $proyectoId);

        return ['ids' => $ids, 'por_definir' => false, 'texto' => $this->textoDe($fuentes)];
    }

    /** Recalcula el texto de los proyectos que tienen la fuente, p. ej. tras renombrarla. */
    public function refrescarTextos(FuenteFinanciacion $fuente): void
    {
        $proyectos = DB::table('proyecto_fuentes')->where('fuente_id', $fuente->id)->pluck('proyecto_id');

        foreach ($proyectos as $proyectoId) {
            $fuentes = FuenteFinanciacion::query()
                ->whereIn('id', DB::table('proyecto_fuentes')->where('proyecto_id', $proyectoId)->select('fuente_id'))
                ->orderBy('orden')->orderBy('nombre')->get();

            DB::table('proyectos')->where('id', $proyectoId)->update(['fuente_financiacion' => $this->textoDe($fuentes)]);
        }
    }

    private function textoDe($fuentes): string
    {
        return $fuentes->pluck('nombre')->implode(', ');
    }

    /** @param array{ids: list<int>, por_definir: bool, texto: string} $financiacion */
    public function guardar(int $proyectoId, array $financiacion): void
    {
        DB::table('proyecto_fuentes')->where('proyecto_id', $proyectoId)->delete();
        foreach ($financiacion['ids'] as $id) {
            DB::table('proyecto_fuentes')->insert(['proyecto_id' => $proyectoId, 'fuente_id' => $id]);
        }

        DB::table('proyectos')->where('id', $proyectoId)->update([
            'financiacion_por_definir' => $financiacion['por_definir'],
            'fuente_financiacion' => $financiacion['texto'],
        ]);
    }

    /** @return list<int> */
    private function idsDe(mixed $valor): array
    {
        if (! is_array($valor)) {
            throw new ReglaNegocioException('Las fuentes de financiación no son válidas.');
        }

        $ids = [];
        foreach ($valor as $elemento) {
            if (is_int($elemento) || (is_string($elemento) && ctype_digit($elemento))) {
                $ids[] = (int) $elemento;
            } else {
                throw new ReglaNegocioException('Las fuentes de financiación no son válidas.');
            }
        }

        return array_values(array_unique($ids));
    }

    /** Una fuente inactiva solo se admite si el proyecto ya la tenía. */
    private function exigirValidas(array $ids, $fuentes, ?int $proyectoId): void
    {
        $previas = $proyectoId
            ? DB::table('proyecto_fuentes')->where('proyecto_id', $proyectoId)->pluck('fuente_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $porId = $fuentes->keyBy('id');
        $errores = [];

        foreach ($ids as $id) {
            $fuente = $porId->get($id);
            if (! $fuente) {
                $errores[] = "La fuente de financiación con id {$id} no existe.";
            } elseif (! $fuente->activo && ! in_array($id, $previas, true)) {
                $errores[] = "La fuente de financiación \"{$fuente->nombre}\" está inactiva.";
            }
        }

        if ($errores) {
            throw new ReglaNegocioException('Las fuentes de financiación no son válidas.', $errores);
        }
    }
}
