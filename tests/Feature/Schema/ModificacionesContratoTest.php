<?php

namespace Tests\Feature\Schema;

use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModificacionesContratoTest extends TestCase
{
    use RefreshDatabase;

    private function crearContrato(array $overrides = []): Contrato
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);
        return Contrato::create(array_merge([
            'proyecto' => $proyecto->id,
            'n_contrato' => 'C-001',
            'valor' => 100000000,
            'fecha_fin' => '2026-12-31',
        ], $overrides));
    }

    public function test_tabla_modificaciones_contrato_tiene_columnas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('modificaciones_contrato'));
        $this->assertTrue(Schema::hasColumns('modificaciones_contrato', [
            'id', 'contrato_id', 'numero_otrosi', 'tipo',
            'valor_adicion', 'dias_prorroga', 'fecha_modificacion', 'justificacion',
        ]));
    }
}
