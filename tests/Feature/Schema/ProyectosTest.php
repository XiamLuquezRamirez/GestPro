<?php

namespace Tests\Feature\Schema;

use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Fase;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProyectosTest extends TestCase
{
    use RefreshDatabase;

    private function crearCatalogosBase(): array
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $fase = Fase::create(['nombre' => 'Ejecución', 'activo' => true, 'dashboard' => true]);
        $estado = Estado::create(['nombre' => 'Aprobado', 'activo' => true, 'fase' => $fase->id]);
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);
        $entidad = Entidad::create(['nombre' => 'Alcaldía de Medellín', 'activo' => true]);

        return compact('municipio', 'fase', 'estado', 'sector', 'entidad');
    }

    public function test_proyectos_table_has_expected_columns_and_types(): void
    {
        $this->assertTrue(Schema::hasColumns('proyectos', [
            'id', 'municipio', 'nombre', 'descripcion', 'fecha_inicio', 'estado', 'fase',
            'presupuesto', 'entidad_presenta', 'entidad_financia', 'fuente_financiacion', 'progreso', 'sector',
        ]));
    }

    public function test_proyecto_resolves_all_its_relationships(): void
    {
        $c = $this->crearCatalogosBase();

        $proyecto = Proyecto::create([
            'municipio' => $c['municipio']->id,
            'nombre' => 'Pavimentación vía rural',
            'descripcion' => 'Prueba',
            'fecha_inicio' => '2026-01-01',
            'estado' => $c['estado']->id,
            'fase' => $c['fase']->id,
            'presupuesto' => 1500000.50,
            'entidad_presenta' => $c['entidad']->id,
            'entidad_financia' => $c['entidad']->id,
            'progreso' => 42,
            'sector' => $c['sector']->id,
        ]);

        $fresco = $proyecto->fresh();
        $this->assertSame('1500000.50', $fresco->presupuesto);
        $this->assertSame(42, $fresco->progreso);
        $this->assertTrue($fresco->municipioRel->is($c['municipio']));
        $this->assertTrue($fresco->estadoRel->is($c['estado']));
        $this->assertTrue($fresco->faseRel->is($c['fase']));
        $this->assertTrue($fresco->sectorRel->is($c['sector']));
        $this->assertTrue($fresco->entidadPresenta->is($c['entidad']));
    }

    public function test_cannot_delete_a_municipio_with_proyectos(): void
    {
        $c = $this->crearCatalogosBase();
        Proyecto::create(['municipio' => $c['municipio']->id, 'nombre' => 'X']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $c['municipio']->delete();
    }
}
