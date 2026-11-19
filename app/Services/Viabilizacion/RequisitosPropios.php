<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\EtapaContratacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Requisitos propios del proyecto: filas del checklist sin plantilla, con sus textos en la fila.
 *
 * Reglas:
 * - Se agregan, editan y eliminan solo en un borrador con la configuración confirmada.
 * - Aplican siempre y `sincronizar` no los toca; tienen el mismo seguimiento que los demás.
 * - Su carácter es obligatorio u opcional (por defecto, obligatorio). Nunca piden aprobación
 *   del "no aplica".
 * - Van al final del checklist, en el orden en que se agregaron.
 * - En un expediente de contratación llevan su etapa (etapa_propia_id), obligatoria: una etapa
 *   activa o la que ya tienen, aunque esté inactiva; sin el campo al editar, no cambia. En uno de
 *   viabilización no se acepta.
 * - Solo se elimina uno que no esté cumplido ni tenga soporte.
 * - Todo queda en el historial de la fila.
 */
final class RequisitosPropios
{
    public const CARACTERES = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::OPCIONAL];

    // Después de cualquier plantilla: ExpedienteService ordena las suyas de 10_000_000 en 10_000_000.
    private const ORDEN_BASE = 900_000_000;

    /** Campo de la API => columna de la fila. */
    private const CAMPOS = [
        'titulo' => 'titulo_propio',
        'descripcion' => 'descripcion_propia',
        'categoria' => 'categoria_propia',
        'caracter' => 'caracter',
    ];

    /** Los de un expediente de contratación llevan además su etapa. */
    private const CAMPOS_CONTRATACION = [...self::CAMPOS, 'etapa_propia_id' => 'etapa_propia_id'];

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
    ) {
    }

    /** @param array{titulo: string, descripcion?: ?string, categoria?: ?string, caracter?: string, etapa_propia_id?: ?int} $datos */
    public function crear(ProyectoExpediente $expediente, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->expedientes->exigirChecklistEditable($expediente);
        $valores = $this->valores($expediente, [
            'descripcion' => null, 'categoria' => null, 'caracter' => RequisitoCatalogo::OBLIGATORIO, 'etapa_propia_id' => null, ...$datos,
        ]);
        $this->exigirEtapa($expediente, $datos, $valores, null);

        return DB::transaction(function () use ($expediente, $valores, $usuario) {
            // Con el expediente bloqueado: una radicación simultánea espera y ya cuenta este
            // propio, o termina antes y aquí se ve radicado.
            $this->expedientes->bloquearChecklistEditable($expediente);
            $fila = $expediente->requisitos()->create([
                ...$this->columnas($valores),
                'requisito_catalogo_id' => null,
                'aplica' => true,
                'estado_aplicabilidad' => ProyectoRequisito::APLICA,
            ]);
            $fila->update(['orden' => self::ORDEN_BASE + $fila->id]);
            $this->historial->registrarFila($fila, 'requisito_propio_agregado', [], $valores, $usuario);
            $expediente->touch();

            return $fila;
        });
    }

    /**
     * Cambia los campos que trae el pedido; los demás no cambian.
     *
     * @param array{titulo: string, descripcion?: ?string, categoria?: ?string, caracter?: string, etapa_propia_id?: ?int} $datos
     */
    public function editar(ProyectoExpediente $expediente, ProyectoRequisito $fila, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->exigirPropio($expediente, $fila, 'solo se editan los requisitos propios del proyecto.');
        $valores = $this->valores($expediente, $datos);

        DB::transaction(function () use ($expediente, $fila, $datos, $valores, $usuario) {
            // Primero el expediente y luego la fila, el orden de toda escritura del checklist.
            // Compara con el estado actual de la fila: dos ediciones no se pisan en el historial.
            $this->expedientes->bloquearChecklistEditable($expediente);
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            // La etapa inactiva que ya tiene se decide sobre la fila bloqueada.
            $this->exigirEtapa($expediente, $datos, $valores, $actual);
            $antes = $this->valoresDe($expediente, $actual);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios === []) {
                return;
            }

            $actual->update($this->columnas($cambios));
            $this->historial->registrarFila($actual, 'requisito_propio_editado', array_intersect_key($antes, $cambios), $cambios, $usuario);
            $expediente->touch();
        });

        return $fila->refresh();
    }

    public function eliminar(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?User $usuario): void
    {
        $this->exigirPropio($expediente, $fila, 'solo se eliminan los requisitos propios del proyecto.');

        DB::transaction(function () use ($expediente, $fila, $usuario) {
            // Expediente y luego fila: una marca o una subida simultánea no puede perderse con ella.
            $this->expedientes->bloquearChecklistEditable($expediente);
            $actual = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            if ($actual->cumplido) {
                throw new ReglaNegocioException('El requisito está cumplido: desmárcalo antes de eliminarlo.');
            }
            if ($actual->soporte_archivo) {
                throw new ReglaNegocioException('El requisito tiene un soporte: quítalo antes de eliminarlo.');
            }

            // El registro sobrevive a la fila, con sus textos.
            $this->historial->registrarFila($actual, 'requisito_propio_eliminado', [
                ...$this->valoresDe($expediente, $actual),
                'observacion' => $actual->observacion,
                'responsable_id' => $actual->responsable_id,
                'responsable' => $actual->responsable?->nombre,
                'estado_aplicabilidad' => $actual->estado_aplicabilidad,
                'justificacion_no_aplica' => $actual->justificacion_no_aplica,
            ], [], $usuario);
            $actual->delete();
            $expediente->touch();
        });
    }

    private function exigirPropio(ProyectoExpediente $expediente, ProyectoRequisito $fila, string $motivo): void
    {
        // Primero la naturaleza de la fila: una de plantilla retirada debe decir que viene de la plantilla.
        if (! $fila->esPropio()) {
            throw new ReglaNegocioException("Este requisito viene de una plantilla: {$motivo}");
        }
        $this->expedientes->exigirEditable($expediente, $fila);
    }

    /**
     * Etapa del propio, solo en uno de contratación: obligatoria, y activa salvo que sea la que ya
     * tiene. Sin el campo al editar, no cambia. En uno de viabilización no se acepta.
     *
     * @param array $datos el pedido, para saber qué trae; $valores, lo que se guardaría
     */
    private function exigirEtapa(ProyectoExpediente $expediente, array $datos, array $valores, ?ProyectoRequisito $actual): void
    {
        if (! $expediente->esContratacion()) {
            if (($datos['etapa_propia_id'] ?? null) !== null) {
                throw ValidationException::withMessages(['etapa_propia_id' => 'Los requisitos propios de un expediente de viabilización no llevan etapa de contratación.']);
            }

            return;
        }
        if (! array_key_exists('etapa_propia_id', $valores)) {
            return;
        }

        $id = $valores['etapa_propia_id'];
        $etapa = $id !== null ? EtapaContratacion::find($id) : null;
        $error = match (true) {
            $id === null => 'Elige la etapa de contratación del requisito.',
            ! $etapa => "La etapa de contratación con id {$id} no existe.",
            ! $etapa->activo && $etapa->id !== $actual?->etapa_propia_id => "La etapa \"{$etapa->nombre}\" está inactiva.",
            default => null,
        };
        if ($error !== null) {
            throw ValidationException::withMessages(['etapa_propia_id' => $error]);
        }
    }

    /** @return array<string, string> campo de la API => columna, según el tipo del expediente */
    private function campos(ProyectoExpediente $expediente): array
    {
        return $expediente->esContratacion() ? self::CAMPOS_CONTRATACION : self::CAMPOS;
    }

    /** Valores del pedido en el orden de los campos, con los textos recortados y los vacíos como null. */
    private function valores(ProyectoExpediente $expediente, array $datos): array
    {
        $campos = $this->campos($expediente);
        $valores = array_intersect_key(array_replace($campos, $datos), $datos, $campos);
        foreach (['titulo', 'descripcion'] as $campo) {
            if (array_key_exists($campo, $valores)) {
                $texto = trim((string) $valores[$campo]);
                $valores[$campo] = $texto !== '' ? $texto : null;
            }
        }
        if (array_key_exists('titulo', $valores) && $valores['titulo'] === null) {
            throw new ReglaNegocioException('Escribe el título del requisito.');
        }

        return $valores;
    }

    /** @return array{titulo: ?string, descripcion: ?string, categoria: ?string, caracter: string, etapa_propia_id?: ?int} */
    private function valoresDe(ProyectoExpediente $expediente, ProyectoRequisito $fila): array
    {
        return array_map(fn (string $columna) => $fila->{$columna}, $this->campos($expediente));
    }

    private function columnas(array $valores): array
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS_CONTRATACION[$campo]] = $valor;
        }

        return $columnas;
    }
}
