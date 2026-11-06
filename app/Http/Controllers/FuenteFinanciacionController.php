<?php

namespace App\Http\Controllers;

use App\Models\FuenteFinanciacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de fuentes de financiación. Las fuentes no se eliminan: se desactivan,
 * y una desactivada se conserva en los proyectos que ya la tienen.
 */
class FuenteFinanciacionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            FuenteFinanciacion::query()->orderBy('orden')->orderBy('nombre')->get()
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $fuente = $request->input('accion') === 'Editar'
            ? FuenteFinanciacion::findOrFail($request->input('id'))
            : new FuenteFinanciacion(['orden' => (int) FuenteFinanciacion::max('orden') + 1, 'activo' => true]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:199', Rule::unique('fuentes_financiacion', 'nombre')->ignore($fuente->id)],
            'descripcion' => ['nullable', 'string'],
        ]);

        $fuente->fill($datos)->save();

        return response()->json(['success' => 'Fuente de financiación guardada correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $fuente = FuenteFinanciacion::findOrFail($request->input('id'));
        $fuente->update(['activo' => ! $fuente->activo]);

        return response()->json(['success' => 'Fuente de financiación actualizada correctamente']);
    }
}
