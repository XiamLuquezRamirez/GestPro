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
        $this->expedientes->exigirEditable($expediente, $fila);

        $justificacion = trim($justificacion);
        if ($justificacion === '') {
            throw new ReglaNegocioException('Explique por qué el requisito no aplica.');
        }
        if ($fila->cumplido) {
            throw new ReglaNegocioException('El requisito está cumplido: desmárquelo antes de indicar que no aplica.');
        }
        if (! in_array($fila->estado_aplicabilidad, [ProyectoRequisito::APLICA, ProyectoRequisito::POR_DEFINIR], true)) {
            throw new ReglaNegocioException('El requisito ya está marcado como no aplica.');
        }

        $fila->loadMissing('requisito');
        $estado = $fila->requisito->requiere_aprobacion_no_aplica
            ? ProyectoRequisito::NO_APLICA_PENDIENTE
            : ProyectoRequisito::NO_APLICA;

        return $this->guardar($expediente, $fila, 'no_aplica_marcado', [
            ...self::SIN_NO_APLICA,
            'estado_aplicabilidad' => $estado,
            'justificacion_no_aplica' => $justificacion,
            'usuario_no_aplica_id' => $usuario->id,
        ], $usuario, ['justificacion' => $justificacion]);
    }

    /** Quien se equivocó lo deshace: el requisito vuelve a aplicar. */
    public function retirar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);

        $marcado = [ProyectoRequisito::NO_APLICA_PENDIENTE, ProyectoRequisito::NO_APLICA];
        if (! in_array($fila->estado_aplicabilidad, $marcado, true)) {
            throw new ReglaNegocioException('El requisito no está marcado como no aplica.');
        }

        return $this->guardar($expediente, $fila, 'no_aplica_retirado', self::SIN_NO_APLICA, $usuario);
    }

    public function aprobar(ProyectoExpediente $expediente, ProyectoRequisito $fila, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);
        $this->exigirRevisor($fila, $usuario);

        return $this->guardar($expediente, $fila, 'no_aplica_aprobado', [
            'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA,
            'usuario_aprobo_id' => $usuario->id,
            'fecha_aprobacion' => now(),
        ], $usuario, ['autoaprobado' => $fila->usuario_no_aplica_id === $usuario->id]);
    }

    public function rechazar(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?string $motivo, User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirEditable($expediente, $fila);
        $this->exigirRevisor($fila, $usuario);

        $motivo = trim((string) $motivo);

        return $this->guardar($expediente, $fila, 'no_aplica_rechazado', self::SIN_NO_APLICA, $usuario, [
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);
    }

    private function exigirRevisor(ProyectoRequisito $fila, User $usuario): void
    {
        if ($fila->estado_aplicabilidad !== ProyectoRequisito::NO_APLICA_PENDIENTE) {
            throw new ReglaNegocioException('Este requisito no tiene un "no aplica" por aprobar.');
        }
        // Un Administrador puede revisar lo que él mismo marcó, y queda registrado: con un
        // solo usuario, el flujo se bloquearía.
        if ($usuario->rol !== Rol::Administrador && $fila->usuario_no_aplica_id === $usuario->id) {
            throw new ReglaNegocioException('Otra persona debe revisar este "no aplica": usted lo marcó.');
        }
    }

    private function guardar(ProyectoExpediente $expediente, ProyectoRequisito $fila, string $evento, array $cambios, User $usuario, array $extra = []): ProyectoRequisito
    {
        DB::transaction(function () use ($expediente, $fila, $evento, $cambios, $usuario, $extra) {
            $antes = ['estado' => $fila->estado_aplicabilidad, 'justificacion' => $fila->justificacion_no_aplica];
            $fila->update($cambios);
            $this->historial->registrarFila($fila, $evento, $antes, ['estado' => $fila->estado_aplicabilidad, ...$extra], $usuario);
            $expediente->touch();
        });

        return $fila;
    }
}
