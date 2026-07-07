<?php

namespace Tests\Feature\Schema;

use App\Models\AnexoContrato;
use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use RefreshDatabase;

    private function crearProyecto(): Proyecto
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        return Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
    }

    public function test_contrato_casts_valor_and_anticipo_correctly(): void
    {
        $proyecto = $this->crearProyecto();

        $contrato = Contrato::create([
            'proyecto' => $proyecto->id,
            'n_contrato' => 'C-001',
            'valor' => 25000000,
            'anticipo' => true,
        ]);

        $fresco = $contrato->fresh();
        $this->assertSame('25000000.00', $fresco->valor);
        $this->assertTrue($fresco->anticipo);
    }

    public function test_anexo_contrato_belongs_to_contrato_and_cascades_on_delete(): void
    {
        $proyecto = $this->crearProyecto();
        $contrato = Contrato::create(['proyecto' => $proyecto->id, 'n_contrato' => 'C-002']);

        AnexoContrato::create([
            'contrato_id' => $contrato->id,
            'descripcion' => 'Acta inicial',
            'nombre_archivo' => 'acta.pdf',
            'ruta_archivo' => 'anexos_contratos/acta.pdf',
            'fecha' => '2026-01-01',
        ]);

        $contrato->delete();

        $this->assertSame(0, AnexoContrato::count());
    }
}
