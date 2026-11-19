<?php

namespace App\Http\Controllers;

use App\Models\ProcesoLicitacion;
use App\Services\Contratacion\TableroContratacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vista "Contratación" del tablero: todos los procesos, con filtros. Lectura: cualquier usuario
 * autenticado.
 */
class TableroContratacionController extends Controller
{
    private const MENSAJES = [
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'in' => 'Revisa :attribute: no es un valor válido.',
    ];

    private const ATRIBUTOS = [
        'modalidad_id' => 'la modalidad',
        'plataforma' => 'la plataforma',
        'fuente_id' => 'la fuente de financiación',
        'responsable_id' => 'el responsable',
        'estado' => 'el estado',
        'semaforo' => 'el semáforo',
    ];

    public function __construct(private readonly TableroContratacion $tablero)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'modalidad_id' => ['sometimes', 'nullable', 'integer'],
            'plataforma' => ['sometimes', 'nullable', 'string', Rule::in(ProcesoLicitacion::PLATAFORMAS)],
            'fuente_id' => ['sometimes', 'nullable', 'integer'],
            'responsable_id' => ['sometimes', 'nullable', 'integer'],
            'estado' => ['sometimes', 'nullable', 'string', Rule::in(ProcesoLicitacion::ESTADOS)],
            'semaforo' => ['sometimes', 'nullable', 'string', Rule::in(TableroContratacion::SEMAFOROS)],
        ], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->tablero->presentar($filtros));
    }
}
