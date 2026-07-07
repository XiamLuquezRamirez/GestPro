<?php

namespace Tests\Feature\Schema;

use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvancesTest extends TestCase
{
    use RefreshDatabase;

    private function crearContrato(): Contrato
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);
        return Contrato::create(['proyecto' => $proyecto->id, 'n_contrato' => 'C-001']);
    }

    public function test_avance_financiero_casts_decimals_and_percentage(): void
    {
        $contrato = $this->crearContrato();

        $avance = AvanceFinanciero::create([
            'contrato_id' => $contrato->id,
            'descripcion' => 'Acta 1',
            'fecha_acta' => '2026-02-01',
            'valor_facturado' => 1000000,
            'porcentaje_ejecutado' => 25,
        ]);

        $fresco = $avance->fresh();
        $this->assertSame('1000000.00', $fresco->valor_facturado);
        $this->assertSame(25, $fresco->porcentaje_ejecutado);
        $this->assertTrue($fresco->contrato->is($contrato));
    }

    public function test_avance_fisico_belongs_to_contrato(): void
    {
        $contrato = $this->crearContrato();

        $avance = AvanceFisico::create([
            'contrato_id' => $contrato->id,
            'descripcion_avance_fisico' => 'Excavación completa',
            'fecha_avance_fisico' => '2026-02-15',
            'valor_avance_fisico' => 60,
        ]);

        $this->assertTrue($avance->fresh()->contrato->is($contrato));
        $this->assertSame(60, $avance->fresh()->valor_avance_fisico);
    }
}
