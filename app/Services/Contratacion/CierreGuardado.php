<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;

/** Lo que deja terminar o liquidar un contrato: el contrato como quedó y los avisos que no impiden guardar. */
final class CierreGuardado
{
    /** @param list<string> $avisos */
    public function __construct(
        public readonly Contrato $contrato,
        public readonly array $avisos,
    ) {
    }
}
