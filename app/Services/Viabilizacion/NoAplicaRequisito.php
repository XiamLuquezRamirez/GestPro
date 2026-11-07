<?php

namespace App\Services\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "No aplica" de un requisito: lo marca una persona con justificación y, si el
 * requisito lo pide, otra lo aprueba. Mientras espera aprobación cuenta como pendiente.
 */
final class NoAplicaRequisito
{
    private const SIN_NO_APLICA = [
        'estado_aplicabilidad' => ProyectoRequisito::APLICA,
        'justificacion_no_aplica' => null,
        'usuario_no_aplica_id' => null,
        'usuario_aprobo_id' => null,
        'fecha_aprobacion' => null,
    ];

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
    ) {
    }

    public function marcar(ProyectoExpediente $expediente, ProyectoRequisito $fila, string $justificacion, User $usuario): ProyectoRequisito
    {
        $justificacion = trim($justificacion);
        if ($justificacion === '') {
            throw new ReglaNegocioException('Explica por qué el requisito no aplica.');
        }

        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) use ($justificacion, $usuario) {
            if ($actual->cumplido) {
                throw new ReglaNegocioException('El requisito está cumplido: desmárcalo antes de indicar que no aplica.');
            }
            if (! in_array($actual->estado_aplicabilidad, [ProyectoRequisito::APLICA, ProyectoRequisito::POR_DEFINIR], true)) {
                throw new ReglaNegocioException('El requisito ya está marcado como no aplica.');
            }

            $estado = $actual->requisito->requiere_aprobacion_no_aplica
                ? ProyectoRequisito::NO_APLICA_PENDIENTE
                : ProyectoRequisito::NO_APLICA;

            return ['no_aplica_marcado', [
                ...self::SIN_NO_APLICA,
                'estado_aplicabilidad' => $estado,
                'justificacion_no_aplica' => $justificacion,
                'usuario_no_aplica_id' => $usuario->id,
            ], ['justificacion' => $justificacion]];
        });
    }

    /** Quien se equivocó lo deshace: el requisito vuelve a aplicar. */
    public function retirar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) {
            $marcado = [ProyectoRequisito::NO_APLICA_PENDIENTE, ProyectoRequisito::NO_APLICA];
            if (! in_array($actual->estado_aplicabilidad, $marcado, true)) {
                throw new ReglaNegocioException('El requisito no está marcado como no aplica.');
            }

            return ['no_aplica_retirado', self::SIN_NO_APLICA];
        });
    }

    public function aprobar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) use ($usuario) {
            $this->exigirRevisor($actual, $usuario);

            return ['no_aplica_aprobado', [
                'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA,
                'usuario_aprobo_id' => $usuario->id,
                'fecha_aprobacion' => now(),
            ], ['autoaprobado' => $actual->usuario_no_aplica_id === $usuario->id]];
        });
    }

    public function rechazar(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?string $motivo, User $usuario): ProyectoRequisito
    {
        $motivo = trim((string) $motivo);

        return $this->guardar($expediente, $fila, $usuario, function (ProyectoRequisito $actual) use ($usuario, $motivo) {
            $this->exigirRevisor($actual, $usuario);

            return ['no_aplica_rechazado', self::SIN_NO_APLICA, ['motivo' => $motivo !== '' ? $motivo : null]];
        });
    }

    private function exigirRevisor(ProyectoRequisito $fila, User $usuario): void
    {
        if ($fila->estado_aplicabilidad !== ProyectoRequisito::NO_APLICA_PENDIENTE) {
            throw new ReglaNegocioException('Este requisito no tiene un "no aplica" por aprobar.');
        }
        // Un Administrador puede revisar lo que él mismo marcó, y queda registrado: con un
        // solo usuario, el flujo se bloquearía.
        if ($usuario->rol !== Rol::Administrador && $fila->usuario_no_aplica_id === $usuario->id) {
            throw new ReglaNegocioException('Otra persona debe revisar este "no aplica": tú lo marcaste.');
        }
    }

    /**
     * Bloquea la fila y decide sobre su estado actual: dos acciones simultáneas no pueden
     * actuar sobre una lectura vieja.
     *
     * @param callable(ProyectoRequisito): array $decidir devuelve [evento, cambios, datos extra opcionales]
     */
    private function guardar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario, callable $decidir): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);

        DB::transaction(function () use ($expediente, $fila, $usuario, $decidir) {
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->with('requisito')->firstOrFail();
            [$evento, $cambios, $extra] = $decidir($actual) + [2 => []];

            $antes = ['estado' => $actual->estado_aplicabilidad, 'justificacion' => $actual->justificacion_no_aplica];
            $actual->forceFill($cambios)->save();
            $this->historial->registrarFila($actual, $evento, $antes, ['estado' => $actual->estado_aplicabilidad, ...$extra], $usuario);
            $expediente->touch();
        });

        return $fila->refresh();
    }
}
