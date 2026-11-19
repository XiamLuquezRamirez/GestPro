<?php

namespace App\Services\Contratacion;

use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use Illuminate\Support\Collection;

/**
 * Vista "Contratación" del tablero: todos los procesos, de todos los proyectos. Solo lee.
 *
 * Reglas:
 * - Cada proceso trae su expediente resumido y sus avisos, los mismos de la lista de procesos del
 *   proyecto (ExpedientesContratacion::resumenDeProcesos), su etapa actual y su semáforo.
 * - Etapa actual: la primera etapa, en el orden del catálogo, con requisitos que cuentan y sin
 *   aprobar (ni aprobada ni aprobada con cambios), con su estado de revisión visible. Null sin
 *   expediente, con el expediente cerrado o con todas aprobadas.
 * - Semáforo, en este orden: terminado (proceso en un estado final o expediente cerrado);
 *   sin_expediente; vencida (etapas vencidas o cierre vencido); proxima (etapas próximas o cierre
 *   próximo); si no, al_dia.
 * - Filtros (modalidad, plataforma, fuente, responsable, estado y semáforo) en el servidor; el
 *   resumen (conteos por estado, por etapa actual y por semáforo) es el de los procesos filtrados.
 *   Las opciones de filtro son las presentes en todos los procesos: no dependen de los filtros.
 * - Ningún avance se suma ni promedia con otro (R13): el resumen solo cuenta procesos.
 * - Las consultas no crecen con los procesos.
 */
final class TableroContratacion
{
    public const VENCIDA = 'vencida';
    public const PROXIMA = 'proxima';
    public const AL_DIA = 'al_dia';
    public const SIN_EXPEDIENTE = 'sin_expediente';
    public const TERMINADO = 'terminado';
    public const SEMAFOROS = [self::VENCIDA, self::PROXIMA, self::AL_DIA, self::SIN_EXPEDIENTE, self::TERMINADO];

    /** Filtro de la API => columna del proceso. */
    private const FILTROS = [
        'modalidad_id' => 'modalidad',
        'plataforma' => 'plataforma',
        'fuente_id' => 'fuente_id',
        'responsable_id' => 'responsable_id',
        'estado' => 'estado',
    ];

    public function __construct(
        private readonly ExpedientesContratacion $expedientes,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /**
     * @param array{modalidad_id?: ?int, plataforma?: ?string, fuente_id?: ?int, responsable_id?: ?int, estado?: ?string, semaforo?: ?string} $filtros
     * @return array{procesos: list<array>, resumen: array, opciones: array}
     */
    public function presentar(array $filtros): array
    {
        $todos = ProcesoLicitacion::query()
            ->with(['modalidadRel:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre', 'proyectoRel:id,nombre'])
            ->orderBy('id')
            ->get();
        $procesos = $todos->filter(function (ProcesoLicitacion $proceso) use ($filtros) {
            foreach (self::FILTROS as $filtro => $columna) {
                $valor = $filtros[$filtro] ?? null;
                if ($valor !== null && (string) $proceso->{$columna} !== (string) $valor) {
                    return false;
                }
            }

            return true;
        })->values();

        $resumenes = $this->expedientes->resumenDeProcesos($procesos, $avances);
        $filas = $procesos
            ->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso, $resumenes[$proceso->id], $avances[$proceso->id] ?? []))
            ->filter(fn (array $fila) => ($filtros['semaforo'] ?? null) === null || $fila['semaforo'] === $filtros['semaforo'])
            ->values()
            ->all();

        return [
            'procesos' => $filas,
            'resumen' => $this->resumen($filas),
            'opciones' => $this->opciones($todos),
        ];
    }

    /** @param list<array> $avancePorEtapa el del expediente del proceso, presentado; [] sin expediente */
    private function forma(ProcesoLicitacion $proceso, array $resumen, array $avancePorEtapa): array
    {
        $opcion = fn ($fila) => $fila ? ['id' => $fila->id, 'nombre' => $fila->nombre] : null;
        $expediente = $resumen['expediente'];

        return [
            'id' => $proceso->id,
            'codigo_proceso' => $proceso->codigo_proceso,
            'objeto' => $proceso->objeto,
            'modalidad' => $opcion($proceso->modalidadRel),
            'plataforma' => $proceso->plataforma,
            'fuente' => $opcion($proceso->fuente),
            'responsable' => $opcion($proceso->responsable),
            'monto' => $proceso->monto,
            'estado' => $proceso->estado,
            'fecha_cierre' => $proceso->fecha_cierre?->format('Y-m-d'),
            'proyecto' => $opcion($proceso->proyectoRel),
            'expediente' => $expediente,
            'avisos' => $resumen['avisos'],
            'etapa_actual' => $expediente && $expediente['estado'] !== ProyectoExpediente::CERRADO ? $this->etapaActual($avancePorEtapa) : null,
            'semaforo' => $this->semaforo($proceso, $expediente, $resumen['avisos']),
        ];
    }

    /** La primera etapa con requisitos que cuentan sin aprobar; null si todas están aprobadas. */
    private function etapaActual(array $avancePorEtapa): ?array
    {
        foreach ($avancePorEtapa as $etapa) {
            if ($etapa['etapa'] !== null && $etapa['total'] > 0 && ! $this->avisos->aprobada($etapa['revision'])) {
                return [...$etapa['etapa'], 'estado_revision' => $etapa['revision']['estado']];
            }
        }

        return null;
    }

    private function semaforo(ProcesoLicitacion $proceso, ?array $expediente, array $avisos): string
    {
        return match (true) {
            in_array($proceso->estado, AvisosContratacion::ESTADOS_FINALES, true),
            $expediente !== null && $expediente['estado'] === ProyectoExpediente::CERRADO => self::TERMINADO,
            $expediente === null => self::SIN_EXPEDIENTE,
            $avisos['etapas_vencidas'] > 0 || $avisos['cierre_vencido'] => self::VENCIDA,
            $avisos['etapas_proximas'] > 0 || $avisos['cierre_proximo'] => self::PROXIMA,
            default => self::AL_DIA,
        };
    }

    /** @param list<array> $filas las del tablero, ya filtradas */
    private function resumen(array $filas): array
    {
        $porEtapa = [];
        foreach ($filas as $fila) {
            $etapa = $fila['etapa_actual'];
            if ($etapa === null) {
                continue;
            }
            $porEtapa[$etapa['id']] ??= ['id' => $etapa['id'], 'nombre' => $etapa['nombre'], 'orden' => $etapa['orden'], 'procesos' => 0];
            $porEtapa[$etapa['id']]['procesos']++;
        }
        usort($porEtapa, fn (array $a, array $b) => [$a['orden'], $a['id']] <=> [$b['orden'], $b['id']]);
        $contar = fn (array $claves, string $campo) => array_replace(
            array_fill_keys($claves, 0),
            array_intersect_key(array_count_values(array_column($filas, $campo)), array_flip($claves))
        );

        return [
            'total' => count($filas),
            'por_estado' => $contar(ProcesoLicitacion::ESTADOS, 'estado'),
            'por_semaforo' => $contar(self::SEMAFOROS, 'semaforo'),
            'por_etapa' => $porEtapa,
        ];
    }

    /** @param Collection<int, ProcesoLicitacion> $procesos todos, sin filtrar */
    private function opciones(Collection $procesos): array
    {
        $lista = fn (string $relacion) => $procesos->pluck($relacion)->filter()->unique('id')
            ->sortBy([['nombre', 'asc'], ['id', 'asc']])
            ->map(fn ($fila) => ['id' => $fila->id, 'nombre' => $fila->nombre])
            ->values()->all();
        $plataformas = $procesos->pluck('plataforma')->filter()->unique()->all();

        return [
            'modalidades' => $lista('modalidadRel'),
            'plataformas' => array_values(array_filter(ProcesoLicitacion::PLATAFORMAS, fn (string $p) => in_array($p, $plataformas, true))),
            'fuentes' => $lista('fuente'),
            'responsables' => $lista('responsable'),
        ];
    }
}
