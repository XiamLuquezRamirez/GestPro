<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Violación de una regla de negocio (p. ej. editar un expediente radicado).
 * Laravel llama a render() automáticamente: responde 422 con un mensaje legible
 * en vez de un 500.
 *
 * `errores` es la lista completa de lo que impide la operación. `campos`, opcional, dice además
 * a qué campo del formulario corresponde alguno de esos mensajes (campo => mensajes): viaja como
 * `errors`, con la misma forma que la validación de Laravel, para que la pantalla lo muestre bajo
 * el campo. Quien lo usa repite esos mensajes en `errores`, que es lo que lee el resto.
 */
class ReglaNegocioException extends RuntimeException
{
    /**
     * @param list<string> $errores
     * @param array<string, list<string>> $campos
     */
    public function __construct(
        string $mensaje,
        private readonly array $errores = [],
        private readonly array $campos = [],
    ) {
        parent::__construct($mensaje);
    }

    public function errores(): array
    {
        return $this->errores;
    }

    /** @return array<string, list<string>> */
    public function campos(): array
    {
        return $this->campos;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'mensaje' => $this->getMessage(),
            'errores' => $this->errores ?: null,
            'errors' => array_filter($this->campos) ?: null,
        ]), 422);
    }
}
