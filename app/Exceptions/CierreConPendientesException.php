<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Cerrar un expediente de contratación con requisitos pendientes, sin confirmarlo. No es un
 * bloqueo: la pantalla muestra cuántos faltan y en qué etapas, y se cierra confirmando.
 * Responde 422 con el mensaje y `pendientes` ({total, por_etapa: [{etapa, pendientes}]}).
 */
class CierreConPendientesException extends ReglaNegocioException
{
    /** @param array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>} $pendientes */
    public function __construct(string $mensaje, private readonly array $pendientes)
    {
        parent::__construct($mensaje);
    }

    /** @return array{total: int, por_etapa: list<array{etapa: ?array, pendientes: int}>} */
    public function pendientes(): array
    {
        return $this->pendientes;
    }

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage(), 'pendientes' => $this->pendientes], 422);
    }
}
