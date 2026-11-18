<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * SECOP II no respondió o respondió algo que no sirve (sin conexión, tiempo agotado, HTTP distinto
 * de 200 o un cuerpo que no es una lista). Laravel llama a render(): responde 502 con un mensaje
 * para la pantalla; el detalle queda en el mensaje de la excepción para el registro.
 */
class SecopNoDisponibleException extends RuntimeException
{
    public const MENSAJE = 'No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.';

    public function render(): JsonResponse
    {
        return response()->json(['mensaje' => self::MENSAJE], 502);
    }
}
