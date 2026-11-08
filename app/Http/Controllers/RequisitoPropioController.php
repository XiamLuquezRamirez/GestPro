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
        'titulo.required' => 'Escribe el título del requisito.',
        'titulo.max' => 'El título admite hasta 500 caracteres.',
        'descripcion.max' => 'La descripción admite hasta 2000 caracteres.',
        'categoria.in' => 'Elige una categoría de la lista.',
        'caracter.in' => 'El carácter de un requisito propio es obligatorio u opcional.',
    ];

    private const ATRIBUTOS = [
        'titulo' => 'el título',
        'descripcion' => 'la descripción',
        'categoria' => 'la categoría',
        'caracter' => 'el carácter',
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
            'progreso' => $this->expedientes->progresoDe($expediente),
        ]);
    }

    /** Al editar, los campos que no vienen en el pedido no cambian; el título siempre se envía. */
    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:500'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'categoria' => ['sometimes', 'nullable', Rule::in(RequisitoCatalogo::CATEGORIAS)],
            'caracter' => ['sometimes', Rule::in(RequisitosPropios::CARACTERES)],
        ], self::MENSAJES, self::ATRIBUTOS);
    }

    private function respuesta(ProyectoExpediente $expediente, ProyectoRequisito $fila, int $estado = 200): JsonResponse
    {
        return response()->json([
            'requisito' => $this->expedientes->presentarFila($fila->refresh()),
            'progreso' => $this->expedientes->progresoDe($expediente),
        ], $estado);
    }
}
