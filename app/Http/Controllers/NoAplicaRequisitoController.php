<?php

namespace App\Http\Controllers;

use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\NoAplicaRequisito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "No aplica" de un requisito del checklist. Rutas con can:editar-datos. */
class NoAplicaRequisitoController extends Controller
{
    private const MENSAJES = [
        'justificacion.required' => 'Explique por qué el requisito no aplica.',
        'justificacion.max' => 'La justificación admite hasta 2000 caracteres.',
        'motivo.max' => 'El motivo admite hasta 2000 caracteres.',
    ];

    public function __construct(
        private readonly NoAplicaRequisito $noAplica,
        private readonly ExpedienteService $expedientes,
    ) {
    }

    public function marcar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $datos = $request->validate(['justificacion' => ['required', 'string', 'max:2000']], self::MENSAJES);

        return $this->respuesta($expediente, $this->noAplica->marcar($expediente, $requisito, $datos['justificacion'], $request->user()));
    }

    public function retirar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->retirar($expediente, $requisito, $request->user()));
    }

    public function aprobar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return $this->respuesta($expediente, $this->noAplica->aprobar($expediente, $requisito, $request->user()));
    }

    public function rechazar(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:2000']], self::MENSAJES);

        return $this->respuesta($expediente, $this->noAplica->rechazar($expediente, $requisito, $datos['motivo'] ?? null, $request->user()));
    }

    private function respuesta(ProyectoExpediente $expediente, ProyectoRequisito $fila): JsonResponse
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            'progreso' => $this->expedientes->progresoDe($expediente),
        ]);
    }
}
