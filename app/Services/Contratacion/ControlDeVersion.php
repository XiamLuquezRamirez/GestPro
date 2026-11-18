<?php

namespace App\Services\Contratacion;

use App\Exceptions\EdicionDesactualizadaException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Control de versión de una fila editada desde un formulario: el pedido trae `actualizado`, el
 * updated_at que tenía la fila cuando se abrió el formulario. Si ya no es el de la fila, otra
 * persona la cambió entretanto y se rechaza (409) en vez de deshacer en silencio lo suyo.
 */
trait ControlDeVersion
{
    /**
     * `$actualizado` cae en el mismo segundo que updated_at, o ambos son null (una fila anterior
     * a las fechas). Se llama con la fila ya bloqueada.
     */
    private function exigirVersion(Model $fila, ?string $actualizado, string $mensaje): void
    {
        $esperado = $actualizado === null ? null : Carbon::parse($actualizado)->getTimestamp();
        if ($esperado !== $fila->updated_at?->getTimestamp()) {
            throw new EdicionDesactualizadaException($mensaje);
        }
    }

    /**
     * updated_at se guarda sin fracciones de segundo: si un cambio cae en el mismo segundo que el
     * anterior, se adelanta uno para que `actualizado` cambie siempre y el control de versión no
     * deje pasar un formulario viejo.
     */
    private function adelantarActualizado(Model $fila): void
    {
        $ahora = Carbon::now();
        $siguiente = $fila->updated_at?->copy()->startOfSecond()->addSecond();
        $fila->updated_at = $siguiente !== null && $siguiente->greaterThan($ahora) ? $siguiente : $ahora;
    }
}
