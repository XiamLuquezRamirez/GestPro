<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use DateTimeInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suspensión y reinicio de un contrato (fase 7B), cada uno con su acción.
 *
 * Reglas:
 * - Una suspensión es una fila de modificaciones_contrato con tipo = 'suspension':
 *   `fecha_modificacion` es la fecha de suspensión, `numero_otrosi` el número del acta,
 *   `justificacion` el motivo y `valor_adicion` null. Está abierta mientras no tiene
 *   `fecha_reinicio`; entonces `dias_prorroga` es null. Al reiniciar, `dias_prorroga` son los días
 *   calendario entre la suspensión y el reinicio: ContratosService::recalcular() corre la fecha fin
 *   igual que con una prórroga. Mientras está suspendido, la fecha fin no cambia.
 * - Suspender: solo un contrato en ejecución y sin otra suspensión abierta. La fecha de suspensión
 *   no es posterior a hoy (en config('contratacion.zona')), ni anterior a la de inicio, si la hay,
 *   ni al reinicio de la última suspensión cerrada. La fecha prevista de reinicio es opcional y no
 *   es anterior a la de suspensión. El contrato pasa a suspendido.
 * - Reiniciar: solo un contrato suspendido. Cierra la suspensión abierta; si no tiene (un contrato
 *   que llegó suspendido, p. ej. de SECOP II), pide la fecha de suspensión (con las reglas de
 *   suspender) y crea la suspensión ya cerrada. La fecha de reinicio no es posterior a hoy ni
 *   anterior a la de suspensión (el mismo día suma 0 días). El contrato vuelve a en ejecución y se
 *   recalcula.
 * - Solo cambian el estado y, al reiniciar, la fecha fin vigente: el valor, los iniciales y los
 *   avances físico y financiero no se tocan (R13).
 * - Traen `actualizado`, como editar: si ya no es el de la fila, se rechaza (409). Cada cambio
 *   adelanta updated_at del contrato (la tabla de modificaciones no tiene fechas) y queda en
 *   checklist_historial (entidad contrato): `contrato_suspendido` y `contrato_reiniciado`, con
 *   antes y después (R15).
 * - Eliminar una suspensión (pestaña Modificaciones) también trae `actualizado` (409 si es viejo,
 *   antes que las demás reglas; 404 si la fila ya no existe) y solo la más reciente del contrato. Si estaba
 *   abierta y el contrato suspendido, vuelve a en ejecución. Recalcula, adelanta updated_at y deja
 *   `suspension_eliminada` en el historial con los datos de la fila.
 * - aviso() dice desde cuándo está suspendido un contrato y si su reinicio previsto ya pasó, sin
 *   consultas; avisosDe() lo calcula para muchos contratos con una sola consulta.
 */
final class SuspensionContratos
{
    use ControlDeVersion;

    public const NO_EN_EJECUCION = 'Solo se suspende un contrato en ejecución.';

    public const YA_ABIERTA = 'El contrato ya tiene una suspensión abierta.';

    public const NO_SUSPENDIDO = 'El contrato no está suspendido.';

    public const SIN_SUSPENSION = 'Escribe la fecha de suspensión.';

    public const SUSPENSION_FUTURA = 'La fecha de suspensión no puede ser posterior a hoy.';

    public const SUSPENSION_ANTES_DE_INICIO = 'La fecha de suspensión no puede ser anterior a la de inicio.';

    public const SUSPENSION_ANTES_DE_REINICIO = 'La fecha de suspensión no puede ser anterior al último reinicio.';

    public const PREVISTA_ANTERIOR = 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.';

    public const REINICIO_FUTURO = 'La fecha de reinicio no puede ser posterior a hoy.';

    public const REINICIO_ANTERIOR = 'La fecha de reinicio no puede ser anterior a la de suspensión.';

    public const SOLO_LA_ULTIMA = 'Solo se puede eliminar la última suspensión.';

    public const YA_NO_EXISTE = 'La suspensión ya no existe.';

    public const POR_FORMULARIO = 'Las suspensiones se registran con «Suspender».';

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
        private readonly ContratosService $contratos,
    ) {
    }

    /**
     * @param string $fecha año-mes-día
     * @param ?string $prevista año-mes-día
     */
    public function suspender(Contrato $contrato, string $fecha, ?string $prevista, ?string $acta, ?string $motivo, ?string $actualizado, ?User $usuario): SuspensionGuardada
    {
        $acta = $this->texto($acta);
        $motivo = $this->texto($motivo);

        return DB::transaction(function () use ($contrato, $fecha, $prevista, $acta, $motivo, $actualizado, $usuario) {
            // Primero la versión: con un formulario viejo, el estado que se ve engañaría.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if (self::abierta($actual->id) !== null) {
                throw new ReglaNegocioException(self::YA_ABIERTA);
            }
            if ($actual->estado !== Contrato::EN_EJECUCION) {
                throw new ReglaNegocioException(self::NO_EN_EJECUCION);
            }
            $this->exigirSuspension($actual, $fecha);
            if ($prevista !== null && $prevista < $fecha) {
                throw ValidationException::withMessages(['fecha_reinicio_prevista' => self::PREVISTA_ANTERIOR]);
            }

            $id = DB::table('modificaciones_contrato')->insertGetId([
                'contrato_id' => $actual->id,
                'numero_otrosi' => $acta,
                'tipo' => ModificacionContrato::SUSPENSION,
                'valor_adicion' => null,
                'dias_prorroga' => null,
                'fecha_modificacion' => $fecha,
                'fecha_reinicio' => null,
                'fecha_reinicio_prevista' => $prevista,
                'justificacion' => $motivo,
            ]);
            $antes = ['estado' => $actual->estado];
            $this->adelantarActualizado($actual);
            $actual->fill(['estado' => Contrato::SUSPENDIDO])->save();
            $this->historial->registrarContrato($actual, 'contrato_suspendido', $antes, [
                'estado' => Contrato::SUSPENDIDO,
                'fecha_suspension' => $fecha,
                'fecha_reinicio_prevista' => $prevista,
                'numero_acta' => $acta,
                'motivo' => $motivo,
                'modificacion_id' => $id,
            ], $usuario);

            return new SuspensionGuardada($actual->refresh(), []);
        });
    }

    /**
     * @param string $reinicio año-mes-día
     * @param ?string $suspension año-mes-día; solo cuenta si el contrato no tiene suspensión abierta
     */
    public function reiniciar(Contrato $contrato, string $reinicio, ?string $suspension, ?string $observacion, ?string $actualizado, ?User $usuario): SuspensionGuardada
    {
        $observacion = $this->texto($observacion);

        return DB::transaction(function () use ($contrato, $reinicio, $suspension, $observacion, $actualizado, $usuario) {
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            if ($actual->estado !== Contrato::SUSPENDIDO) {
                throw new ReglaNegocioException(self::NO_SUSPENDIDO);
            }
            $abierta = self::abierta($actual->id);
            if ($abierta !== null) {
                $suspension = $this->dia($abierta->fecha_modificacion);
            } else {
                if ($suspension === null) {
                    throw ValidationException::withMessages(['fecha_suspension' => self::SIN_SUSPENSION]);
                }
                $this->exigirSuspension($actual, $suspension);
            }
            if ($reinicio > $this->hoy()) {
                throw ValidationException::withMessages(['fecha_reinicio' => self::REINICIO_FUTURO]);
            }
            if ($reinicio < $suspension) {
                throw ValidationException::withMessages(['fecha_reinicio' => self::REINICIO_ANTERIOR]);
            }
            $dias = self::dias($suspension, $reinicio);

            if ($abierta !== null) {
                $id = (int) $abierta->id;
                DB::table('modificaciones_contrato')->where('id', $id)->update(['fecha_reinicio' => $reinicio, 'dias_prorroga' => $dias]);
            } else {
                $id = DB::table('modificaciones_contrato')->insertGetId([
                    'contrato_id' => $actual->id,
                    'numero_otrosi' => null,
                    'tipo' => ModificacionContrato::SUSPENSION,
                    'valor_adicion' => null,
                    'dias_prorroga' => $dias,
                    'fecha_modificacion' => $suspension,
                    'fecha_reinicio' => $reinicio,
                    'fecha_reinicio_prevista' => null,
                    'justificacion' => null,
                ]);
            }
            $antes = ['estado' => $actual->estado, 'fecha_fin' => $this->fecha($actual->fecha_fin)];
            $this->adelantarActualizado($actual);
            $actual->fill(['estado' => Contrato::EN_EJECUCION])->save();
            $resumen = $this->contratos->recalcular($actual);
            $actual->refresh();
            $this->historial->registrarContrato($actual, 'contrato_reiniciado', $antes, [
                'estado' => Contrato::EN_EJECUCION,
                'fecha_fin' => $this->fecha($actual->fecha_fin),
                'fecha_suspension' => $suspension,
                'fecha_reinicio' => $reinicio,
                'dias_suspendido' => $dias,
                'observacion' => $observacion,
                'modificacion_id' => $id,
            ], $usuario);

            return new SuspensionGuardada($actual, [], $resumen);
        });
    }

    /**
     * Elimina una suspensión desde la pestaña Modificaciones: solo la más reciente del contrato.
     * Devuelve el resumen de ContratosService::recalcular().
     *
     * @return array{valor_inicial: float, valor_vigente: float, fecha_fin_inicial: ?string, fecha_fin_vigente: ?string, porcentaje_adicionado: float, supera_limite: bool}
     */
    public function eliminar(int $modificacionId, int $contratoId, ?string $actualizado, ?User $usuario): array
    {
        return DB::transaction(function () use ($modificacionId, $contratoId, $actualizado, $usuario) {
            // Primero la versión, con el contrato bloqueado: con una lista vieja se desharía en
            // silencio un reinicio u otra suspensión que alguien guardó entretanto.
            $actual = Contrato::query()->whereKey($contratoId)->lockForUpdate()->firstOrFail();
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            $fila = DB::table('modificaciones_contrato')
                ->where('id', $modificacionId)
                ->where('contrato_id', $actual->id)
                ->where('tipo', ModificacionContrato::SUSPENSION)
                ->first();
            if ($fila === null) {
                throw new HttpResponseException(response()->json(['mensaje' => self::YA_NO_EXISTE], 404));
            }
            $ultima = DB::table('modificaciones_contrato')
                ->where('contrato_id', $actual->id)
                ->where('tipo', ModificacionContrato::SUSPENSION)
                ->orderByDesc('fecha_modificacion')
                ->orderByDesc('id')
                ->first();
            if ($ultima === null || (int) $ultima->id !== (int) $fila->id) {
                throw new ReglaNegocioException(self::SOLO_LA_ULTIMA);
            }

            $abierta = $fila->fecha_reinicio === null;
            $antes = [
                'modificacion_id' => (int) $fila->id,
                'fecha_suspension' => $this->dia($fila->fecha_modificacion),
                'fecha_reinicio' => $this->dia($fila->fecha_reinicio),
                'fecha_reinicio_prevista' => $this->dia($fila->fecha_reinicio_prevista),
                'dias_suspendido' => $fila->dias_prorroga === null ? null : (int) $fila->dias_prorroga,
                'numero_acta' => $fila->numero_otrosi,
                'motivo' => $fila->justificacion,
                'estado' => $actual->estado,
                'fecha_fin' => $this->fecha($actual->fecha_fin),
            ];
            DB::table('modificaciones_contrato')->where('id', $fila->id)->delete();
            $this->adelantarActualizado($actual);
            if ($abierta && $actual->estado === Contrato::SUSPENDIDO) {
                $actual->estado = Contrato::EN_EJECUCION;
            }
            $actual->save();
            $resumen = $this->contratos->recalcular($actual);
            $actual->refresh();
            $this->historial->registrarContrato($actual, 'suspension_eliminada', $antes, [
                'estado' => $actual->estado,
                'fecha_fin' => $this->fecha($actual->fecha_fin),
            ], $usuario);

            return $resumen;
        });
    }

    /** La suspensión abierta del contrato (la más reciente, si por error hubiera más), o null. */
    public static function abierta(int $contratoId): ?object
    {
        return DB::table('modificaciones_contrato')
            ->where('contrato_id', $contratoId)
            ->where('tipo', ModificacionContrato::SUSPENSION)
            ->whereNull('fecha_reinicio')
            ->orderByDesc('fecha_modificacion')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Las suspensiones abiertas de los contratos suspendidos, en una sola consulta.
     *
     * @param array<int, ?string> $estados contrato_id => estado
     * @return array<int, object> contrato_id => suspensión abierta (la más reciente)
     */
    public static function abiertasDe(array $estados): array
    {
        $ids = array_keys(array_filter($estados, fn (?string $estado) => $estado === Contrato::SUSPENDIDO));
        if ($ids === []) {
            return [];
        }
        $abiertas = [];
        DB::table('modificaciones_contrato')
            ->whereIn('contrato_id', $ids)
            ->where('tipo', ModificacionContrato::SUSPENSION)
            ->whereNull('fecha_reinicio')
            ->orderBy('fecha_modificacion')
            ->orderBy('id')
            ->get()
            ->each(function ($fila) use (&$abiertas) {
                $abiertas[(int) $fila->contrato_id] = $fila;
            });

        return $abiertas;
    }

    /**
     * Desde cuándo está suspendido un contrato y si su reinicio previsto ya pasó: null si no está
     * suspendido o no tiene suspensión abierta.
     *
     * @param ?object $abierta la fila de su suspensión abierta
     * @param string $hoy año-mes-día
     * @return ?array{desde: string, dias: int, reinicio_previsto: ?string, vencido: bool}
     */
    public static function aviso(?string $estado, ?object $abierta, string $hoy): ?array
    {
        if ($estado !== Contrato::SUSPENDIDO || $abierta === null) {
            return null;
        }
        $desde = substr((string) $abierta->fecha_modificacion, 0, 10);
        $prevista = $abierta->fecha_reinicio_prevista !== null ? substr((string) $abierta->fecha_reinicio_prevista, 0, 10) : null;

        return [
            'desde' => $desde,
            'dias' => self::dias($desde, $hoy),
            'reinicio_previsto' => $prevista,
            'vencido' => $prevista !== null && $prevista < $hoy,
        ];
    }

    /** Días calendario entre dos fechas año-mes-día (negativo si la segunda es anterior). */
    private static function dias(string $desde, string $hasta): int
    {
        return (int) round(Carbon::parse($desde, 'UTC')->diffInDays(Carbon::parse($hasta, 'UTC'), false));
    }

    /**
     * La fecha de suspensión no es posterior a hoy, ni anterior a la de inicio, si la hay, ni al
     * reinicio de la última suspensión cerrada.
     */
    private function exigirSuspension(Contrato $contrato, string $fecha): void
    {
        if ($fecha > $this->hoy()) {
            throw ValidationException::withMessages(['fecha_suspension' => self::SUSPENSION_FUTURA]);
        }
        $inicio = $this->fecha($contrato->fecha_inicio);
        if ($inicio !== null && $fecha < $inicio) {
            throw ValidationException::withMessages(['fecha_suspension' => self::SUSPENSION_ANTES_DE_INICIO]);
        }
        $ultimoReinicio = $this->dia(DB::table('modificaciones_contrato')
            ->where('contrato_id', $contrato->id)
            ->where('tipo', ModificacionContrato::SUSPENSION)
            ->max('fecha_reinicio'));
        if ($ultimoReinicio !== null && $fecha < $ultimoReinicio) {
            throw ValidationException::withMessages(['fecha_suspension' => self::SUSPENSION_ANTES_DE_REINICIO]);
        }
    }

    private function hoy(): string
    {
        return $this->avisos->hoy()->toDateString();
    }

    private function bloquear(Contrato $contrato): Contrato
    {
        return Contrato::query()->whereKey($contrato->id)->lockForUpdate()->firstOrFail();
    }

    private function fecha(?DateTimeInterface $valor): ?string
    {
        return $valor?->format('Y-m-d');
    }

    /** Una fecha leída con el constructor de consultas (año-mes-día, con o sin hora) como año-mes-día. */
    private function dia(mixed $valor): ?string
    {
        return $valor === null ? null : substr((string) $valor, 0, 10);
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }
}
