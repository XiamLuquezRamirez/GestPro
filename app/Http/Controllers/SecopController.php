<?php

namespace App\Http\Controllers;

use App\Services\Contratacion\SecopII;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta de contratos en SECOP II para importarlos (can:editar-datos). Cada resultado tiene
 * `contrato` (campos para el formulario), `secop` (lo informativo) y `registrado`; ver SecopII.
 * Si SECOP II falla responde 502 {mensaje} (SecopNoDisponibleException).
 */
class SecopController extends Controller
{
    private const MENSAJES = [
        'q.required' => 'Escribe el número, el ID o el enlace del contrato.',
        'q.string' => 'Revisa la búsqueda: debe ser un texto.',
        'q.max' => 'En la búsqueda caben hasta 500 caracteres.',
        'nit.required' => 'Escribe el NIT de la empresa.',
        'nit.string' => SecopII::NIT_INVALIDO,
        'nit.max' => SecopII::NIT_INVALIDO,
        'nit.regex' => SecopII::NIT_INVALIDO,
    ];

    public function __construct(private readonly SecopII $secop)
    {
    }

    /** `q`: número, ID o enlace del contrato (un enlace puede ser largo); `nit`: de la entidad, opcional. */
    public function buscar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'q' => ['bail', 'required', 'string', 'max:500'],
            'nit' => ['sometimes', 'nullable', 'bail', 'string', 'max:20', 'regex:' . SecopII::FORMA_NIT],
        ], self::MENSAJES);

        return response()->json(['resultados' => $this->secop->buscar($datos['q'], $datos['nit'] ?? null)]);
    }

    /** Los contratos de la empresa (el proveedor) por su NIT. */
    public function deEmpresa(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nit' => ['bail', 'required', 'string', 'max:20', 'regex:' . SecopII::FORMA_NIT],
        ], self::MENSAJES);

        return response()->json(['resultados' => $this->secop->deEmpresa($datos['nit'])]);
    }

    /** Uno por su ID de SECOP II, para "Actualizar desde SECOP II". */
    public function porId(string $idContrato): JsonResponse
    {
        $resultado = $this->secop->porId($idContrato);
        if ($resultado === null) {
            return response()->json(['mensaje' => 'SECOP II no tiene ese contrato.'], 404);
        }

        return response()->json(['resultado' => $resultado]);
    }
}
