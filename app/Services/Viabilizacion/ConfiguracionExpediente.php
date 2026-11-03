<?php

namespace App\Services\Viabilizacion;

/**
 * Configuración de un expediente expresada en claves del catálogo, que es el
 * idioma del DSL (comp=<clave>, esp=<clave>) y de los requisitos sectoriales.
 *
 * - $tipos usa el formato "sector::tipo" del prototipo, para que un tipo marcado
 *   en un sector no acote a otro.
 */
final class ConfiguracionExpediente
{
    /** @var array<string, true> */
    private array $sectores;
    /** @var array<string, array<string, true>> sector => tipos marcados */
    private array $tiposPorSector = [];
    /** @var array<string, true> */
    private array $componentes;
    /** @var array<string, true> */
    private array $tipologias;

    public function __construct(
        public readonly int $fase,
        array $sectores = [],
        array $tipos = [],
        array $componentes = [],
        array $tipologias = [],
    ) {
        $this->sectores = array_fill_keys($sectores, true);
        $this->componentes = array_fill_keys($componentes, true);
        $this->tipologias = array_fill_keys($tipologias, true);

        foreach ($tipos as $tipo) {
            [$sector, $clave] = array_pad(explode('::', $tipo, 2), 2, null);
            if ($sector !== null && $clave !== null) {
                $this->tiposPorSector[$sector][$clave] = true;
            }
        }
    }

    public function tieneSector(string $sector): bool
    {
        return isset($this->sectores[$sector]);
    }

    /** @return array<string, true> tipos marcados del sector (vacío = sector completo) */
    public function tiposDe(string $sector): array
    {
        return $this->tiposPorSector[$sector] ?? [];
    }

    public function tieneComponente(string $clave): bool
    {
        return isset($this->componentes[$clave]);
    }

    public function tieneTipologia(string $clave): bool
    {
        return isset($this->tipologias[$clave]);
    }

    public function esDesastre(): bool
    {
        return $this->tieneTipologia('desastre');
    }
}
