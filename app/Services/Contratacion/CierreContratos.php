<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cierre de un contrato (fase 7A): terminarlo y liquidarlo, cada uno con su acción.
 *
 * Reglas:
 * - Terminar pide la fecha de terminación real. Se termina un contrato celebrado, en ejecución,
 *   suspendido o ya terminado (corrige la fecha); uno liquidado se corrige desde liquidar y uno
 *   anulado no se termina.
 * - Liquidar pide la fecha de liquidación y el valor liquidado (no negativo, dos decimales), y la
 *   fecha de terminación si el contrato no la tiene. Se liquida desde cualquier estado salvo
 *   anulado; desde liquidado completa o corrige los datos.
 * - Ninguna fecha de cierre es posterior a hoy (en config('contratacion.zona')); la de terminación
 *   no es anterior a la de inicio, si la hay, y la de liquidación no es anterior a la de terminación.
 * - Aviso que no impide: el valor liquidado supera el valor vigente (`valor`).
 * - Solo cambian el estado y los datos de cierre: el valor, la fecha fin (vigentes e iniciales) y
 *   los avances físico y financiero no se tocan (R13).
 * - Al terminar, la fecha de terminación tampoco es posterior a la de liquidación que conserve el
 *   contrato (uno que salió de liquidado por el formulario).
 * - Traen `actualizado`, como editar: si ya no es el de la fila, se rechaza (409). Cada cambio
 *   adelanta updated_at y queda en checklist_historial (entidad contrato): `contrato_terminado` y
 *   `contrato_liquidado`, con antes y después (R15).
 * - Sin ningún cambio (mismo estado y mismos datos) y sin observación no se guarda nada: ni versión
 *   nueva ni historial; responde el contrato tal cual y sus avisos. Con observación sí se registra.
 * - Fase 7B: un contrato con una suspensión abierta no se termina ni se liquida: primero se
 *   reinicia (SuspensionContratos). Uno suspendido sin suspensión abierta sigue estas reglas.
 * - aviso() dice qué datos de cierre le faltan a un contrato según su estado, sin consultas.
 */
final class CierreContratos
{
    use ControlDeVersion;

    public const TERMINAR_LIQUIDADO = 'El contrato está liquidado: corrige la terminación desde «Liquidar».';

    public const ANULADO = 'El contrato está anulado.';

    public const SIN_ESTADO = 'Elige primero un estado válido para el contrato.';

    public const SIN_TERMINACION = 'Escribe la fecha de terminación real.';

    public const TERMINACION_FUTURA = 'La fecha de terminación no puede ser posterior a hoy.';

    public const TERMINACION_ANTERIOR = 'La fecha de terminación no puede ser anterior a la de inicio.';

    public const TERMINACION_TRAS_LIQUIDACION = 'La fecha de terminación no puede ser posterior a la de liquidación.';

    public const LIQUIDACION_FUTURA = 'La fecha de liquidación no puede ser posterior a hoy.';

    public const LIQUIDACION_ANTERIOR = 'La fecha de liquidación no puede ser anterior a la de terminación.';

    public const SUSPENSION_ABIERTA = 'Reinicia el contrato antes de terminarlo o liquidarlo.';

    public const SUPERA_VIGENTE = 'El valor liquidado supera el valor vigente del contrato.';

    // Estados desde los que se termina un contrato.
    private const TERMINABLES = [Contrato::CELEBRADO, Contrato::EN_EJECUCION, Contrato::SUSPENDIDO, Contrato::TERMINADO];

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /** @param string $fecha año-mes-día */
    public function terminar(Contrato $contrato, string $fecha, ?string $observacion, ?string $actualizado, ?User $usuario): CierreGuardado
    {
        $observacion = $this->texto($observacion);

        return DB::transaction(function () use ($contrato, $fecha, $observacion, $actualizado, $usuario) {
            // Primero la versión: con un formulario viejo, el estado que se ve engañaría.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            $this->exigirSinSuspensionAbierta($actual);
            if ($actual->estado === Contrato::LIQUIDADO) {
                throw new ReglaNegocioException(self::TERMINAR_LIQUIDADO);
            }
            if ($actual->estado === Contrato::ANULADO) {
                throw new ReglaNegocioException(self::ANULADO);
            }
            // Un estado fuera de la lista (un contrato antiguo sin convertir) tampoco se termina.
            if (! in_array($actual->estado, self::TERMINABLES, true)) {
                throw new ReglaNegocioException(self::SIN_ESTADO);
            }
            $this->exigirTerminacion($actual, $fecha);
            $liquidacion = $this->fecha($actual->fecha_liquidacion);
            if ($liquidacion !== null && $fecha > $liquidacion) {
                throw ValidationException::withMessages(['fecha_terminacion_real' => self::TERMINACION_TRAS_LIQUIDACION]);
            }

            $antes = ['estado' => $actual->estado, 'fecha_terminacion_real' => $this->fecha($actual->fecha_terminacion_real)];
            $despues = ['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => $fecha];
            if ($antes === $despues && $observacion === null) {
                return new CierreGuardado($actual, []);
            }
            $this->adelantarActualizado($actual);
            $actual->fill($despues)->save();
            $this->historial->registrarContrato($actual, 'contrato_terminado', $antes, [...$despues, 'observacion' => $observacion], $usuario);

            return new CierreGuardado($actual->refresh(), []);
        });
    }

    /**
     * @param ?string $terminacion año-mes-día, o null para conservar la que tiene
     * @param string $liquidacion año-mes-día
     * @param string $valor numérico, no negativo, hasta dos decimales (lo valida el controlador)
     */
    public function liquidar(Contrato $contrato, ?string $terminacion, string $liquidacion, string $valor, ?string $observacion, ?string $actualizado, ?User $usuario): CierreGuardado
    {
        $observacion = $this->texto($observacion);
        $valor = (string) BigDecimal::of($valor)->toScale(2);

        return DB::transaction(function () use ($contrato, $terminacion, $liquidacion, $valor, $observacion, $actualizado, $usuario) {
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, ContratosService::DESACTUALIZADO);
            $this->exigirSinSuspensionAbierta($actual);
            if ($actual->estado === Contrato::ANULADO) {
                throw new ReglaNegocioException(self::ANULADO);
            }
            $terminacion ??= $this->fecha($actual->fecha_terminacion_real);
            if ($terminacion === null) {
                throw ValidationException::withMessages(['fecha_terminacion_real' => self::SIN_TERMINACION]);
            }
            $this->exigirTerminacion($actual, $terminacion);
            if ($liquidacion > $this->hoy()) {
                throw ValidationException::withMessages(['fecha_liquidacion' => self::LIQUIDACION_FUTURA]);
            }
            if ($liquidacion < $terminacion) {
                throw ValidationException::withMessages(['fecha_liquidacion' => self::LIQUIDACION_ANTERIOR]);
            }

            $antes = [
                'estado' => $actual->estado,
                'fecha_terminacion_real' => $this->fecha($actual->fecha_terminacion_real),
                'fecha_liquidacion' => $this->fecha($actual->fecha_liquidacion),
                'valor_liquidado' => $actual->valor_liquidado !== null ? (string) BigDecimal::of((string) $actual->valor_liquidado)->toScale(2) : null,
            ];
            $despues = [
                'estado' => Contrato::LIQUIDADO,
                'fecha_terminacion_real' => $terminacion,
                'fecha_liquidacion' => $liquidacion,
                'valor_liquidado' => $valor,
            ];
            $supera = $actual->valor !== null && BigDecimal::of($valor)->isGreaterThan(BigDecimal::of((string) $actual->valor));
            $avisos = $supera ? [self::SUPERA_VIGENTE] : [];
            if ($antes === $despues && $observacion === null) {
                return new CierreGuardado($actual, $avisos);
            }
            $this->adelantarActualizado($actual);
            $actual->fill($despues)->save();
            $this->historial->registrarContrato($actual, 'contrato_liquidado', $antes, [...$despues, 'observacion' => $observacion], $usuario);

            return new CierreGuardado($actual->refresh(), $avisos);
        });
    }

    /**
     * Los datos de cierre que le faltan a un contrato según su estado: terminado, la fecha de
     * terminación; liquidado, las tres que falten. null si no falta nada o el estado no los pide.
     *
     * @return ?array{faltan: list<string>}
     */
    public static function aviso(?string $estado, mixed $terminacion, mixed $liquidacion, mixed $valor): ?array
    {
        $datos = match ($estado) {
            Contrato::TERMINADO => ['fecha_terminacion_real' => $terminacion],
            Contrato::LIQUIDADO => ['fecha_terminacion_real' => $terminacion, 'fecha_liquidacion' => $liquidacion, 'valor_liquidado' => $valor],
            default => [],
        };
        $faltan = array_keys(array_filter($datos, fn ($valor) => $valor === null || $valor === ''));

        return $faltan === [] ? null : ['faltan' => $faltan];
    }

    /** La fecha de terminación no es posterior a hoy ni anterior a la de inicio, si la hay. */
    private function exigirTerminacion(Contrato $contrato, string $fecha): void
    {
        if ($fecha > $this->hoy()) {
            throw ValidationException::withMessages(['fecha_terminacion_real' => self::TERMINACION_FUTURA]);
        }
        $inicio = $this->fecha($contrato->fecha_inicio);
        if ($inicio !== null && $fecha < $inicio) {
            throw ValidationException::withMessages(['fecha_terminacion_real' => self::TERMINACION_ANTERIOR]);
        }
    }

    private function exigirSinSuspensionAbierta(Contrato $contrato): void
    {
        if (ModificacionContrato::query()->where('contrato_id', $contrato->id)->suspensionesAbiertas()->exists()) {
            throw new ReglaNegocioException(self::SUSPENSION_ABIERTA);
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

    private function texto(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : null;
    }
}
