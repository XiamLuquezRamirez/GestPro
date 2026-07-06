<?php

namespace Tests\Feature\Schema;

use App\Models\Estado;
use App\Models\Fase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FasesEstadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_fases_table_has_dashboard_flag(): void
    {
        $this->assertTrue(Schema::hasColumns('fases', ['id', 'nombre', 'color', 'activo', 'dashboard']));
    }

    public function test_estado_belongs_to_a_fase(): void
    {
        $fase = Fase::create(['nombre' => 'Ejecución', 'color' => '#1976d2', 'activo' => true, 'dashboard' => true]);
        $estado = Estado::create(['nombre' => 'Aprobado', 'color' => '#43a047', 'activo' => true, 'icono' => 'check', 'fase' => $fase->id]);

        $this->assertTrue($estado->fresh()->faseRel->is($fase));
        $this->assertTrue($fase->fresh()->dashboard);
    }

    public function test_cannot_delete_a_fase_referenced_by_an_estado(): void
    {
        $fase = Fase::create(['nombre' => 'Formulación', 'activo' => true, 'dashboard' => false]);
        Estado::create(['nombre' => 'En revisión', 'activo' => true, 'fase' => $fase->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $fase->delete();
    }
}
