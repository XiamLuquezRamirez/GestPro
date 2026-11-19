<?php

namespace App\Http\Controllers;

use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\RequisitosPropios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Requisitos propios del checklist de un expediente. Rutas con can:editar-datos. */
class RequisitoPropioController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'titulo.required' => 'Escribe el título del requisito.',
        'titulo.max' => 'El título admite hasta 500 caracteres.',
        'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
        'categoria.in' => 'Elige una categoría de la lista.',
        'caracter.in' => 'Elige si el requisito es obligatorio u opcional.',
    ];

    private const ATRIBUTOS = [
        'titulo' => 'el título',
        'descripcion' => 'la descripción',
        'categoria' => 'la categoría',
        'caracter' => 'el carácter',
        'etapa_propia_id' => 'la etapa',
    ];

    public function __construct(
        private readonly RequisitosPropios $propios,
        private readonly ExpedienteService $expedientes,
    ) {
    }

    public function store(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $fila = $this->propios->crear($expediente, $this->validar($request), $request->user());

        return $this->respuesta($expediente, $fila, 201);
    }

    public function update(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $fila = $this->propios->editar($expediente, $requisito, $this->validar($request), $request->user());

        return $this->respuesta($expediente, $fila);
    }

    public function destroy(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $this->propios->eliminar($expediente, $requisito, $request->user());

        return response()->json([
            'mensaje' => 'Requisito eliminado.',
            ...$this->expedientes->avanceDe($expediente),
        ]);
    }

    /**
     * Al editar, los campos que no vienen en el pedido no cambian; el título siempre se envía. La
     * etapa la exige el servicio, y solo en un expediente de contratación.
     */
    private function validar(Request $request): array
    {
        // Enteros de verdad: `integer` admitiría true como 1 o "1".
        $entero = fn (string $atributo, mixed $valor, \Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');

        return $request->validate([
            'titulo' => ['required', 'string', 'max:500'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'categoria' => ['sometimes', 'nullable', Rule::in(RequisitoCatalogo::CATEGORIAS)],
            'caracter' => ['sometimes', Rule::in(RequisitosPropios::CARACTERES)],
            'etapa_propia_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
        ], self::MENSAJES, self::ATRIBUTOS);
    }

    private function respuesta(ProyectoExpediente $expediente, ProyectoRequisito $fila, int $estado = 200): JsonResponse
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            ...$this->expedientes->avanceDe($expediente),
        ], $estado);
    }
}
