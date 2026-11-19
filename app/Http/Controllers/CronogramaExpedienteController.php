<?php

namespace App\Http\Controllers;

use App\Models\EtapaContratacion;
use App\Models\ProyectoExpediente;
use App\Services\Contratacion\CronogramaExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fechas programada y real de una etapa de un expediente de contratación. Ruta con can:editar-datos. */
class CronogramaExpedienteController extends Controller
{
    private const MENSAJES = [
        'string' => 'Escribe :attribute como año-mes-día.',
        'date_format' => 'Escribe :attribute como año-mes-día.',
    ];

    private const ATRIBUTOS = [
        'fecha_programada' => 'la fecha programada',
        'fecha_real' => 'la fecha real',
    ];

    public function __construct(private readonly CronogramaExpediente $cronograma)
    {
    }

    /**
     * Cada fecha es opcional: la que no llega no cambia y con null se borra. Responde la etapa
     * como en avance_por_etapa y los avisos del expediente.
     */
    public function update(Request $request, ProyectoExpediente $expediente, EtapaContratacion $etapa): JsonResponse
    {
        $datos = $request->validate([
            'fecha_programada' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'fecha_real' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
        ], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->cronograma->guardar($expediente, $etapa, $datos, $request->user()));
    }
}
