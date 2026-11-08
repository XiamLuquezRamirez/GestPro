<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Plantillas que se ofrecen al configurar el expediente de un proyecto. Lectura: autenticado. */
class PlantillasExpedienteController extends Controller
{
    public function __construct(private readonly PlantillasExpediente $plantillas)
    {
    }

    /** Con ?expediente={id}, agrega las que ese expediente ya tiene, aunque ya no se ofrezcan. */
    public function compatibles(Request $request, Proyecto $proyecto): JsonResponse
    {
        $datos = $request->validate(['expediente' => ['sometimes', 'integer']]);

        $expediente = null;
        if (isset($datos['expediente'])) {
            $expediente = $proyecto->expedientes()->find((int) $datos['expediente']);
            if (! $expediente) {
                return response()->json(['mensaje' => 'El expediente no es de este proyecto.'], 404);
            }
        }

        return response()->json($this->plantillas->disponibles($proyecto, $expediente));
    }
}
