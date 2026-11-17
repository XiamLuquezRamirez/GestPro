<?php

namespace App\Http\Controllers;

use App\Models\Modalidad;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de modalidades de contratación. Las modalidades no se eliminan: se desactivan, y una
 * desactivada se conserva en los procesos que ya la tienen. La clave la fija la siembra y no se
 * edita por aquí.
 */
class ModalidadController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Modalidad::query()->orderBy('orden')->orderBy('nombre')->get()
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['bail', 'required_if:accion,Editar', $this->entero()],
        ]);

        $modalidad = $request->input('accion') === 'Editar'
            ? Modalidad::findOrFail($request->input('id'))
            : new Modalidad(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['bail', 'required', 'string', 'max:199'],
        ], [
            'nombre.required' => 'Escribe el nombre de la modalidad.',
            'nombre.string' => 'Escribe el nombre de la modalidad.',
            'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        ]);

        // Con todas las modalidades bloqueadas, dos altas o renombres a la vez no eligen el mismo
        // nombre ni el mismo orden: el segundo espera y ve lo que guardó el primero. `nombre` es
        // TEXT y no tiene índice único que lo respalde.
        DB::transaction(function () use ($modalidad, $datos) {
            $todas = Modalidad::query()->lockForUpdate()->get(['id', 'nombre', 'orden']);
            $this->exigirNombreLibre($modalidad, $datos['nombre'], $todas);
            if (! $modalidad->exists) {
                $modalidad->orden = (int) $todas->max('orden') + 1;
            }
            $modalidad->fill($datos)->save();
        });

        return response()->json(['success' => 'Modalidad guardada correctamente']);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['bail', 'required', $this->entero()]]);

        $modalidad = Modalidad::findOrFail($request->input('id'));
        $modalidad->update(['activo' => ! $modalidad->activo]);

        return response()->json(['success' => 'Modalidad actualizada correctamente']);
    }

    /** Enteros de verdad: `integer` admitiría true como 1 o "1". */
    private function entero(): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('El id de la modalidad debe ser un número entero.');
    }

    /**
     * El nombre no se repite sin contar mayúsculas ni tildes. Se compara en PHP para que MariaDB y
     * SQLite lo hagan igual; el catálogo es pequeño.
     *
     * @param Collection<int, Modalidad> $todas
     */
    private function exigirNombreLibre(Modalidad $modalidad, string $nombre, Collection $todas): void
    {
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii(trim((string) $texto)));
        $repetido = $todas->contains(fn (Modalidad $otra) => $otra->id !== $modalidad->id
            && $normalizar($otra->nombre) === $normalizar($nombre));

        if ($repetido) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una modalidad con ese nombre.']);
        }
    }
}
