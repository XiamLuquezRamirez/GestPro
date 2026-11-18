<?php

namespace App\Http\Controllers;

use App\Models\TipoProceso;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de tipos de contrato (tabla `tipos_procesos`, modelo `TipoProceso`). Los tipos no se
 * eliminan por aquí: se desactivan, y uno desactivado se conserva en los contratos que ya lo tienen.
 */
class TipoContratoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TipoProceso::query()->orderBy('nombre')->get());
    }

    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['bail', 'required_if:accion,Editar', $this->entero()],
        ]);

        $tipo = $request->input('accion') === 'Editar'
            ? TipoProceso::findOrFail($request->input('id'))
            : new TipoProceso(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['bail', 'required', 'string', 'max:120'],
        ], [
            'nombre.required' => 'Escribe el nombre del tipo de contrato.',
            'nombre.string' => 'Escribe el nombre del tipo de contrato.',
            'nombre.max' => 'El nombre admite hasta 120 caracteres.',
        ]);
        $datos['nombre'] = trim($datos['nombre']);

        // Con todos los tipos bloqueados, dos altas o renombres a la vez no eligen el mismo nombre:
        // el segundo espera y ve lo que guardó el primero. `nombre` es TEXT y no tiene índice único
        // que lo respalde.
        DB::transaction(function () use ($tipo, $datos) {
            $todos = TipoProceso::query()->lockForUpdate()->get(['id', 'nombre']);
            $this->exigirNombreLibre($tipo, $datos['nombre'], $todos);
            $tipo->fill($datos)->save();
        });

        return response()->json(['success' => 'Tipo de contrato guardado correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['bail', 'required', $this->entero()]]);

        $tipo = TipoProceso::findOrFail($request->input('id'));
        $tipo->update(['activo' => ! $tipo->activo]);

        return response()->json(['success' => 'Tipo de contrato actualizado correctamente']);
    }

    /** Enteros de verdad: `integer` admitiría true como 1 o "1". */
    private function entero(): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('El id del tipo de contrato debe ser un número entero.');
    }

    /**
     * El nombre no se repite sin contar mayúsculas ni tildes. Se compara en PHP para que MariaDB y
     * SQLite lo hagan igual; el catálogo es pequeño.
     *
     * @param Collection<int, TipoProceso> $todos
     */
    private function exigirNombreLibre(TipoProceso $tipo, string $nombre, Collection $todos): void
    {
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii(trim((string) $texto)));
        $repetido = $todos->contains(fn (TipoProceso $otro) => $otro->id !== $tipo->id
            && $normalizar($otro->nombre) === $normalizar($nombre));

        if ($repetido) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un tipo de contrato con ese nombre.']);
        }
    }
}
