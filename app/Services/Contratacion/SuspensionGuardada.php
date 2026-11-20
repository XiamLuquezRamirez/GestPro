<?php

namespace App\Services\Contratacion;

use App\Models\Contrato;

/**
 * Lo que deja suspender o reiniciar un contrato: el contrato como quedó, los avisos que no impiden
 * guardar y, al reiniciar, el resumen de ContratosService::recalcular().
 */
final class SuspensionGuardada
{
    /**
     * @param list<string> $avisos
     * @param ?array{valor_inicial: float, valor_vigente: float, fecha_fin_inicial: ?string, fecha_fin_vigente: ?string, porcentaje_adicionado: float, supera_limite: bool} $resumen
     */
    public function __construct(
        public readonly Contrato $contrato,
        public readonly array $avisos,
        public readonly ?array $resumen = null,
    ) {
    }
}
