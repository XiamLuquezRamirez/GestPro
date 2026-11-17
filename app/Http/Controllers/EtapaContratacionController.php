<?php

namespace App\Http\Controllers;

use App\Models\EtapaContratacion;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de etapas de contratación. Las etapas no se eliminan: se desactivan. La clave se
 * genera del nombre al crear la etapa y no cambia después, porque la usarán las plantillas.
 */
class EtapaContratacionController extends Controller
{
    private const LARGO_CLAVE = 40;

    public function index(): JsonResponse
    {
        return response()->json(
            EtapaContratacion::query()->orderBy('orden')->orderBy('id')->get()
        );
    }

    public function guardar(Request $request): JsonResponse
    {
        $request->validate([
            'accion' => ['required', Rule::in(['Agregar', 'Editar'])],
            'id' => ['bail', 'required_if:accion,Editar', $this->entero('El id de la etapa debe ser un número entero.')],
        ]);

        $etapa = $request->input('accion') === 'Editar'
            ? EtapaContratacion::findOrFail($request->input('id'))
            : new EtapaContratacion(['activo' => true]);

        $datos = $request->validate([
            'nombre' => ['bail', 'required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ], [
            'nombre.required' => 'Escribe el nombre de la etapa.',
            'nombre.string' => 'Escribe el nombre de la etapa.',
            'nombre.max' => 'El nombre admite hasta 120 caracteres.',
            'descripcion.string' => 'La descripción debe ser un texto.',
            'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
        ]);

        try {
            // Con todas las etapas bloqueadas, dos altas o renombres a la vez no eligen el mismo
            // nombre, clave ni orden: el segundo espera y ve lo que guardó el primero.
            DB::transaction(function () use ($etapa, $datos) {
                $todas = EtapaContratacion::query()->lockForUpdate()->get(['id', 'clave', 'nombre', 'orden']);
                $this->exigirNombreLibre($etapa, $datos['nombre'], $todas);

                // Solo nombre y descripción, y la descripción solo si llegó: la clave y el orden no
                // se editan por aquí.
                $etapa->fill($datos);
                if (! $etapa->exists) {
                    $etapa->clave = $this->claveNueva($datos['nombre'], $todas->pluck('clave'));
                    $etapa->orden = (int) $todas->max('orden') + 1;
                }
                $etapa->save();
            });
        } catch (UniqueConstraintViolationException) {
            // La clave la tomó a la vez algo que no pasa por esta ruta: se pide repetir, sin un 500.
            throw ValidationException::withMessages([
                'nombre' => 'Otra etapa se guardó al mismo tiempo con la misma clave. Vuelve a intentarlo.',
            ]);
        }

        return response()->json([
            'success' => 'Etapa de contratación guardada correctamente',
            'etapa' => $etapa->fresh(),
        ]);
    }

    public function activar(Request $request): JsonResponse
    {
        $request->validate(['id' => ['bail', 'required', $this->entero('El id de la etapa debe ser un número entero.')]]);

        $etapa = EtapaContratacion::findOrFail($request->input('id'));
        $etapa->update(['activo' => ! $etapa->activo]);

        return response()->json(['success' => 'Etapa de contratación actualizada correctamente']);
    }

    /** Reescribe el orden 1..n con la lista completa de etapas, en una sola actualización. */
    public function ordenar(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['bail', $this->entero('Cada etapa del orden debe ser un número entero.'), 'distinct:strict'],
        ], [
            'ids.required' => 'Envía el orden de las etapas.',
            'ids.array' => 'Envía el orden de las etapas.',
            'ids.*.distinct' => 'Una etapa aparece más de una vez en el orden.',
        ])['ids'];

        DB::transaction(function () use ($ids) {
            $todas = EtapaContratacion::query()->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if (collect($ids)->sort()->values()->all() !== $todas) {
                throw ValidationException::withMessages(['ids' => 'El orden debe incluir todas las etapas, una vez cada una.']);
            }

            // Los ids ya son enteros validados: se escriben en la sentencia sin riesgo de inyección.
            $casos = collect($ids)->map(fn (int $id, int $posicion) => 'when ' . $id . ' then ' . ($posicion + 1))->implode(' ');
            EtapaContratacion::query()->update(['orden' => DB::raw("case id {$casos} end")]);
        });

        return response()->json(['success' => 'Orden de las etapas guardado correctamente']);
    }

    /** Enteros de verdad: `integer` admitiría true como 1 o "1". */
    private function entero(string $mensaje): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor) || $falla($mensaje);
    }

    /**
     * El nombre no se repite sin contar mayúsculas ni tildes. Se compara en PHP para que MariaDB y
     * SQLite lo hagan igual; el catálogo es pequeño.
     *
     * @param Collection<int, EtapaContratacion> $todas
     */
    private function exigirNombreLibre(EtapaContratacion $etapa, string $nombre, Collection $todas): void
    {
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii(trim((string) $texto)));
        $repetido = $todas->contains(fn (EtapaContratacion $otra) => $otra->id !== $etapa->id
            && $normalizar($otra->nombre) === $normalizar($nombre));

        if ($repetido) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una etapa con ese nombre.']);
        }
    }

    /**
     * Clave del nombre en minúsculas, sin tildes y con guion bajo, de hasta 40 caracteres. Si ya
     * está entre `$claves`, se le agrega _2, _3... recortando la base para no pasar del largo.
     *
     * @param Collection<int, string> $claves
     */
    private function claveNueva(string $nombre, Collection $claves): string
    {
        $base = rtrim(substr(Str::slug($nombre, '_', 'en', []), 0, self::LARGO_CLAVE), '_') ?: 'etapa';
        $existentes = $claves->flip();

        $clave = $base;
        for ($numero = 2; $existentes->has($clave); $numero++) {
            $sufijo = '_' . $numero;
            $clave = rtrim(substr($base, 0, self::LARGO_CLAVE - strlen($sufijo)), '_') . $sufijo;
        }

        return $clave;
    }
}
