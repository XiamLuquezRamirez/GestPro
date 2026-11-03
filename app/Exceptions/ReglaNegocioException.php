<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violación de una regla de negocio (p. ej. editar un expediente radicado).
 * Laravel llama a render() automáticamente: responde 422 con un mensaje legible
 * en vez de un 500.
 */
class ReglaNegocioException extends RuntimeException
{
    /** @param list<string> $errores */
    public function __construct(string $mensaje, private readonly array $errores = [])
    {
        parent::__construct($mensaje);
    }

    public function errores(): array
    {
        return $this->errores;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'mensaje' => $this->getMessage(),
            'errores' => $this->errores ?: null,
        ]), 422);
    }
}
