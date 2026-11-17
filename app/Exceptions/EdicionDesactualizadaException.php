<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Alguien envió un cambio sobre una versión que ya no es la última: otra persona guardó entretanto.
 * Laravel llama a render() automáticamente: responde 409 (conflicto) con un mensaje legible. No es
 * un 422 porque el pedido es válido; el front lo distingue por el código, avisa y recarga.
 */
class EdicionDesactualizadaException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => $this->getMessage()], 409);
    }
}
