<?php

namespace App\Http\Controllers;

use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use App\Services\Viabilizacion\VersionesExpediente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plantillas de un expediente: las que se ofrecen al configurarlo (lectura: autenticado) y la
 * versión nueva de una de las suyas, con vista previa (can:editar-datos).
 */
class PlantillasExpedienteController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'version_id.required' => 'Indica la versión que vas a aplicar.',
    ];

    private const ATRIBUTOS = [
        'expediente' => 'el expediente',
        'version_id' => 'la versión',
    ];

    public function __construct(
        private readonly PlantillasExpediente $plantillas,
        private readonly VersionesExpediente $versiones,
        private readonly ExpedienteService $expedientes,
    ) {
    }

    /** Con ?expediente={id}, agrega las que ese expediente ya tiene, aunque ya no se ofrezcan. */
    public function compatibles(Request $request, Proyecto $proyecto): JsonResponse
    {
        $datos = $request->validate(['expediente' => ['sometimes', 'integer']], self::MENSAJES, self::ATRIBUTOS);

        $expediente = null;
        if (isset($datos['expediente'])) {
            $expediente = $proyecto->expedientes()->find((int) $datos['expediente']);
            if (! $expediente) {
                return response()->json(['mensaje' => 'El expediente no es de este proyecto.'], 404);
            }
        }

        return response()->json($this->plantillas->disponibles($proyecto, $expediente));
    }

    /** Lo que cambiaría en el checklist al aplicar la versión vigente de la plantilla. No cambia nada. */
    public function vistaPreviaVersion(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): JsonResponse
    {
        return response()->json($this->versiones->vistaPrevia($expediente, $plantilla));
    }

    /** Aplica la versión previsualizada (version_id) y responde el expediente, como al configurarlo. */
    public function aplicarVersion(Request $request, ProyectoExpediente $expediente, PlantillaChecklist $plantilla): JsonResponse
    {
        // Enteros de verdad: `integer` admitiría true como 1 o "1".
        $entero = fn (string $atributo, mixed $valor, \Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');
        $datos = $request->validate(['version_id' => ['bail', 'required', 'integer', $entero]], self::MENSAJES, self::ATRIBUTOS);

        $this->versiones->aplicar($expediente, $plantilla, $datos['version_id'], $request->user());

        return response()->json($this->expedientes->presentar($expediente->refresh()));
    }
}
