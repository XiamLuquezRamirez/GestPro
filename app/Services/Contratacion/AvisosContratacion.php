<?php

namespace App\Services\Contratacion;

use App\Models\ExpedienteEtapa;
use App\Models\ProcesoLicitacion;
use App\Models\ProyectoExpediente;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avisos de contratación (R14): el semáforo de cada etapa de un expediente y el resumen de un
 * proceso. Solo lee: no guarda nada.
 *
 * Reglas:
 * - Días calendario en la zona `contratacion.zona` (America/Bogota por defecto), no en la de la
 *   aplicación (UTC): "hoy" es now() en esa zona, y los momentos (updated_at, el último evento del
 *   historial) se pasan a esa zona antes de tomar su fecha. Los umbrales están en
 *   config/contratacion.php: `dias_proxima` (3) y `dias_sin_actualizacion` (15).
 * - Una etapa está completa si tiene requisitos que cuentan y todos están cumplidos (la misma
 *   definición del avance, sin pesos). Una etapa aprobada (también con cambios después) avisa
 *   `aprobada` y ya no vence: lo que deja de vencer es la aprobación, no completarla. Si no está
 *   aprobada, en un expediente en borrador: vencida si su fecha programada ya pasó (aunque esté
 *   completa); próxima si cae entre hoy y hoy + dias_proxima; si no, completa (lista para
 *   revisar) si lo está, o al día con una fecha más lejana y sin fecha si no tiene. Un expediente
 *   cerrado no avisa vencimientos: sus etapas sin aprobar quedan completas o sin completar.
 * - Del proceso: en un estado final (celebrado, desierto, cancelado, revocado) no cuenta etapas
 *   vencidas ni próximas: ya terminó. El cierre es próximo si su fecha de cierre cae entre hoy y hoy + dias_proxima, y
 *   vencido si ya pasó, mientras siga en preparación o publicado. Está sin actualización si desde
 *   su última actividad (el máximo entre su updated_at y el último evento del historial de su
 *   expediente) pasaron dias_sin_actualizacion días o más, salvo en un estado final o con el
 *   expediente cerrado.
 * - Hitos: etapas aprobadas de las que tienen requisitos que cuentan. Por revisar: las que tienen
 *   requisitos que cuentan y están listas (completas, sin aprobar ni devueltas); devueltas: las
 *   devueltas sin cambios después. Con el expediente cerrado no hay nada por revisar ni devuelto:
 *   ya no se revisa. Las filas sin etapa no son una etapa: no cuentan como hito ni avisan.
 * - Revisión de una etapa (R15, solo avisa), el estado que ve la pantalla: aprobada, o aprobada
 *   con cambios si después de aprobarla hubo un evento del checklist en un requisito de la etapa
 *   o un cambio de los requisitos del expediente (una versión nueva); devuelta, mientras no haya
 *   un evento del checklist de la etapa después de devolverla; si no, pendiente (le faltan
 *   requisitos que cuentan) o lista (no le falta nada). Ni la aprobación ni la devolución se
 *   pierden por los cambios: solo cambia lo que se muestra. "Después" se decide por el id del
 *   historial, no por la hora. Una etapa sin requisitos que cuenten (total 0: todos opcionales o
 *   no aplica) muestra sin_requisitos, neutro: ni lista ni aprobada, no se revisa ni cuenta.
 */
final class AvisosContratacion
{
    public const VENCIDA = 'vencida';
    public const PROXIMA = 'proxima';
    public const AL_DIA = 'al_dia';
    public const COMPLETA = 'completa';
    public const SIN_FECHA = 'sin_fecha';
    /** Etapa que no se completó en un expediente ya cerrado. */
    public const SIN_COMPLETAR = 'sin_completar';
    /** Etapa aprobada en su revisión: ya no avisa. */
    public const APROBADA = 'aprobada';

    /** Estados visibles de la revisión de una etapa. */
    public const REVISION_PENDIENTE = 'pendiente';
    public const REVISION_LISTA = 'lista';
    public const REVISION_APROBADA = 'aprobada';
    public const REVISION_CON_CAMBIOS = 'aprobada_con_cambios';
    public const REVISION_DEVUELTA = 'devuelta';
    /** La etapa está en el avance pero nada de ella cuenta (total 0): no hay nada que revisar. */
    public const REVISION_SIN_REQUISITOS = 'sin_requisitos';

    /** Estados en los que el proceso ya terminó: no avisa etapas vencidas ni próximas ni que está sin actualización. */
    public const ESTADOS_FINALES = [
        ProcesoLicitacion::CELEBRADO, ProcesoLicitacion::DESIERTO, ProcesoLicitacion::CANCELADO, ProcesoLicitacion::REVOCADO,
    ];

    /** Estados en los que el cierre del proceso todavía no ha ocurrido. */
    public const ANTES_DEL_CIERRE = [ProcesoLicitacion::PREPARACION, ProcesoLicitacion::PUBLICADO];

    /** Zona horaria de los días de contratación. */
    public function zona(): string
    {
        return (string) config('contratacion.zona', 'America/Bogota');
    }

    /** El comienzo de hoy en la zona de contratación. */
    public function hoy(): Carbon
    {
        return Carbon::now($this->zona())->startOfDay();
    }

    /** La fecha (año-mes-día) de un momento en la zona de contratación. */
    public function fechaDe(CarbonInterface $momento): string
    {
        return Carbon::instance($momento)->setTimezone($this->zona())->toDateString();
    }

    public function diasProxima(): int
    {
        return (int) config('contratacion.dias_proxima', 3);
    }

    public function diasSinActualizacion(): int
    {
        return (int) config('contratacion.dias_sin_actualizacion', 15);
    }

    /** @param array{total: int, cumplidos: int} $avance el de una etapa en avance_por_etapa */
    public function completa(array $avance): bool
    {
        return $avance['total'] > 0 && $avance['cumplidos'] === $avance['total'];
    }

    /**
     * Semáforo de una etapa.
     *
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?string $programada su fecha programada, año-mes-día
     * @param bool $vigila el expediente está en borrador: avisa vencimientos
     * @param bool $aprobada la revisión de la etapa la aprobó (con o sin cambios después)
     */
    public function alerta(array $avance, ?string $programada, bool $vigila, bool $aprobada = false): string
    {
        if ($aprobada) {
            return self::APROBADA;
        }
        $completa = $this->completa($avance);
        if (! $vigila) {
            return $completa ? self::COMPLETA : self::SIN_COMPLETAR;
        }
        if ($programada === null) {
            return $completa ? self::COMPLETA : self::SIN_FECHA;
        }
        $hoy = $this->hoy();
        if ($programada < $hoy->toDateString()) {
            return self::VENCIDA;
        }
        if ($programada <= $hoy->copy()->addDays($this->diasProxima())->toDateString()) {
            return self::PROXIMA;
        }

        return $completa ? self::COMPLETA : self::AL_DIA;
    }

    /**
     * La revisión de una etapa, con su estado visible. Espera cargado `usuarioReviso` en la fila.
     *
     * @param array{total: int, cumplidos: int} $avance el de la etapa en avance_por_etapa
     * @param ?ExpedienteEtapa $fila la de la etapa en el cronograma, o null si no tiene
     * @param bool $cambioEtapa hubo un evento del checklist en un requisito de la etapa después de su revisión vigente
     * @param bool $cambioRequisitos cambiaron los requisitos del expediente (versión nueva) después de su revisión vigente
     * @return array{estado: string, usuario: ?string, fecha: ?string, observacion: ?string, pendientes_al_aprobar: ?int, devuelta_at: ?string}
     */
    public function revision(array $avance, ?ExpedienteEtapa $fila, bool $cambioEtapa = false, bool $cambioRequisitos = false): array
    {
        $fecha = $fila?->fecha_revision;
        if ($avance['total'] === 0) {
            $estado = self::REVISION_SIN_REQUISITOS;
        } elseif ($fila?->aprobada()) {
            $estado = $cambioEtapa || $cambioRequisitos ? self::REVISION_CON_CAMBIOS : self::REVISION_APROBADA;
        } elseif ($fila?->devuelta() && ! $cambioEtapa) {
            $estado = self::REVISION_DEVUELTA;
        } else {
            $estado = $avance['total'] > $avance['cumplidos'] ? self::REVISION_PENDIENTE : self::REVISION_LISTA;
        }

        return [
            'estado' => $estado,
            'usuario' => $fila?->usuarioReviso?->name,
            'fecha' => $fecha?->toIso8601String(),
            'observacion' => $fila?->observacion_revision,
            'pendientes_al_aprobar' => $fila?->pendientes_al_aprobar,
            'devuelta_at' => $fila?->devuelta() ? $fecha?->toIso8601String() : null,
        ];
    }

    /** La revisión dice que la etapa está aprobada (con o sin cambios después). */
    public function aprobada(?array $revision): bool
    {
        return in_array($revision['estado'] ?? null, [self::REVISION_APROBADA, self::REVISION_CON_CAMBIOS], true);
    }

    /**
     * Resumen de avisos de un proceso.
     *
     * @param ?ProyectoExpediente $expediente el de contratación del proceso, o null
     * @param list<array> $avancePorEtapa el de su expediente, ya presentado (con `alerta`, `completa` y `revision`); [] sin expediente
     * @param ?CarbonInterface $ultimoEvento el último evento del historial de su expediente, o null
     * @return array{etapas_vencidas: int, etapas_proximas: int, cierre_proximo: bool, cierre_vencido: bool, dias_para_cierre: ?int,
     *               sin_actualizacion: bool, dias_sin_actualizacion: ?int, hitos: array{completadas: int, total: int},
     *               etapas_por_revisar: int, etapas_devueltas: int}
     */
    public function resumen(ProcesoLicitacion $proceso, ?ProyectoExpediente $expediente, array $avancePorEtapa, ?CarbonInterface $ultimoEvento): array
    {
        $hoy = $this->hoy();
        $etapas = array_values(array_filter($avancePorEtapa, fn (array $a) => $a['etapa'] !== null));
        $alertas = array_count_values(array_column($etapas, 'alerta'));

        $diasCierre = $proceso->fecha_cierre ? $this->dias($hoy, $proceso->fecha_cierre) : null;
        $antesDelCierre = in_array($proceso->estado, self::ANTES_DEL_CIERRE, true);

        $ultima = collect([$proceso->updated_at, $ultimoEvento])->filter()->max();
        $diasSin = $ultima ? max(0, $this->dias(Carbon::parse($this->fechaDe($ultima)), $hoy)) : null;
        $terminado = in_array($proceso->estado, self::ESTADOS_FINALES, true);
        $cerrado = $expediente?->estado === ProyectoExpediente::CERRADO;
        $vigente = ! $terminado && ! $cerrado;
        $conRequisitos = array_values(array_filter($etapas, fn (array $a) => $a['total'] > 0));
        $revisiones = array_count_values(array_map(fn (array $a) => $a['revision']['estado'] ?? '', $conRequisitos));

        return [
            'etapas_vencidas' => $terminado ? 0 : $alertas[self::VENCIDA] ?? 0,
            'etapas_proximas' => $terminado ? 0 : $alertas[self::PROXIMA] ?? 0,
            'cierre_proximo' => $antesDelCierre && $diasCierre !== null && $diasCierre >= 0 && $diasCierre <= $this->diasProxima(),
            'cierre_vencido' => $antesDelCierre && $diasCierre !== null && $diasCierre < 0,
            'dias_para_cierre' => $diasCierre,
            'sin_actualizacion' => $vigente && $diasSin !== null && $diasSin >= $this->diasSinActualizacion(),
            'dias_sin_actualizacion' => $diasSin,
            'hitos' => [
                'completadas' => count(array_filter($conRequisitos, fn (array $a) => $this->aprobada($a['revision'] ?? null))),
                'total' => count($conRequisitos),
            ],
            'etapas_por_revisar' => $cerrado ? 0 : $revisiones[self::REVISION_LISTA] ?? 0,
            'etapas_devueltas' => $cerrado ? 0 : $revisiones[self::REVISION_DEVUELTA] ?? 0,
        ];
    }

    /**
     * El último evento del historial de cada expediente, en una sola consulta.
     *
     * @param iterable<int> $expedienteIds
     * @return array<int, Carbon> expediente_id => fecha; sin eventos, no aparece
     */
    public function ultimosEventos(iterable $expedienteIds): array
    {
        $ids = collect($expedienteIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('checklist_historial')
            ->whereIn('expediente_id', $ids)
            ->groupBy('expediente_id')
            ->get(['expediente_id', DB::raw('MAX(created_at) AS ultimo')])
            ->filter(fn (object $fila) => $fila->ultimo !== null)
            ->mapWithKeys(fn (object $fila) => [(int) $fila->expediente_id => Carbon::parse($fila->ultimo)])
            ->all();
    }

    /** Días calendario de `$desde` a `$hasta`: negativos si `$hasta` es anterior. */
    private function dias(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        return (int) round(Carbon::parse($desde->toDateString())->diffInDays(Carbon::parse($hasta->toDateString()), false));
    }
}
