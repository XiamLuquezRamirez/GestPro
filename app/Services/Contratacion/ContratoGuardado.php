<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;

/**
 * Lo que deja guardar un contrato: el contrato como quedó, los avisos que no impiden guardar y si
 * conviene proponer pasar su proceso a celebrado (la pantalla pregunta; el estado no cambia solo).
 */
final class ContratoGuardado
{
    /** @param list<string> $avisos */
    public function __construct(
        public readonly Contrato $contrato,
        public readonly array $avisos,
        public readonly bool $proponerCelebrado,
    ) {
    }
}
