<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administración del catálogo de sectores. No se eliminan: se desactivan, y uno
 * desactivado se conserva en los proyectos que ya lo tienen.
 */
class SectorController extends Controller
{
    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['required_if:accion,Editar', 'integer'],
        ]);

        $sector = $request->input('accion') === 'Editar'
            ? Sector::findOrFail($request->input('id'))
            : new Sector(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:199', Rule::unique('sectores', 'nombre')->ignore($sector->id)],
        ], [
            'nombre.required' => 'Escriba el nombre del sector.',
            'nombre.max' => 'El nombre admite hasta 199 caracteres.',
            'nombre.unique' => 'Ya existe un sector con ese nombre.',
        ]);

        $sector->fill($datos)->save();

        return response()->json(['success' => 'Sector guardado correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['required', 'integer']]);

        $sector = Sector::findOrFail($request->input('id'));
        $sector->update(['activo' => ! $sector->activo]);

        return response()->json(['success' => 'Sector actualizado correctamente']);
    }
}
