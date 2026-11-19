<?php

namespace App\Http\Controllers;

use App\Models\EtapaContratacion;
use App\Models\ProyectoExpediente;
use App\Services\Contratacion\RevisionEtapas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Revisión de una etapa de un expediente de contratación: aprobarla y devolverla. Rutas con
 * can:editar-datos. Ambas responden la etapa como en avance_por_etapa y los avisos del expediente.
 */
class RevisionEtapasController extends Controller
{
    private const MAX_OBSERVACION = 2000;

    public function __construct(private readonly RevisionEtapas $revision)
    {
    }

    /** Con requisitos pendientes en la etapa, sin `confirmar: true` responde 422 con cuántos faltan. */
    public function aprobar(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'confirmar' => ['sometimes', 'boolean'],
            'observacion' => ['sometimes', 'nullable', 'string', 'max:' . self::MAX_OBSERVACION],
        ], [
            'confirmar.boolean' => 'Indica si confirmas la aprobación con verdadero o falso.',
            'observacion.string' => 'Escribe la observación como texto.',
            'observacion.max' => 'La observación no puede tener más de ' . self::MAX_OBSERVACION . ' caracteres.',
        ]);

        return response()->json($this->revision->aprobar(
            $expediente,
            $etapa,
            (bool) ($datos['confirmar'] ?? false),
            $datos['observacion'] ?? null,
            $request->user()
        ));
    }

    public function devolver(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'observacion' => ['required', 'string', 'max:' . self::MAX_OBSERVACION],
        ], [
            'observacion.required' => 'Escribe por qué devuelves la etapa.',
            'observacion.string' => 'Escribe la observación como texto.',
            'observacion.max' => 'La observación no puede tener más de ' . self::MAX_OBSERVACION . ' caracteres.',
        ]);

        return response()->json($this->revision->devolver($expediente, $etapa, $datos['observacion'], $request->user()));
    }
}
