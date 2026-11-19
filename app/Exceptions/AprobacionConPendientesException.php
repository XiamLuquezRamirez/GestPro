<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * Aprobar una etapa de un expediente de contratación con requisitos pendientes, sin confirmarlo.
 * No es un bloqueo: la pantalla muestra cuántos faltan y se aprueba confirmando. Responde 422 con
 * el mensaje y `pendientes` (cuántos requisitos que cuentan faltan en la etapa).
 */
class AprobacionConPendientesException extends ReglaNegocioException
{
    public function __construct(string $mensaje, private readonly int $pendientes)
    {
        parent::__construct($mensaje);
    }

    public function pendientes(): int
    {
        return $this->pendientes;
    }

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage(), 'pendientes' => $this->pendientes], 422);
    }
}
