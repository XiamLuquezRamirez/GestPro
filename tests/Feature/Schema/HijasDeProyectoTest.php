<?php

namespace Tests\Feature\Schema;

use App\Models\CheckFormulacion;
use App\Models\EventoLicitacion;
use App\Models\Municipio;
use App\Models\PresupuestoProyecto;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HijasDeProyectoTest extends TestCase
{
    use RefreshDatabase;

    private function crearProyecto(): Proyecto
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        return Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
    }

    public function test_presupuesto_proyecto_belongs_to_proyecto(): void
    {
        $proyecto = $this->crearProyecto();
        $componente = PresupuestoProyecto::create(['proyecto' => $proyecto->id, 'componente' => 'Interventoría', 'valor' => 5000]);

        $this->assertTrue($componente->fresh()->proyectoRel->is($proyecto));
        $this->assertSame('5000.00', $componente->fresh()->valor);
    }

    public function test_check_formulacion_stores_checklist_as_json(): void
    {
        $proyecto = $this->crearProyecto();
        $checklist = ['presupuesto' => ['peso' => 30, 'items' => []]];

        $check = CheckFormulacion::create(['proyecto' => $proyecto->id, 'checklist' => $checklist]);

        $this->assertSame($checklist, $check->fresh()->checklist);
    }

    public function test_evento_licitacion_belongs_to_proceso_and_proyecto(): void
    {
        $proyecto = $this->crearProyecto();
        $proceso = ProcesoLicitacion::create(['proyecto' => $proyecto->id, 'codigo_proceso' => 'LIC-001']);
        $evento = EventoLicitacion::create(['proyecto' => $proyecto->id, 'proceso' => $proceso->id, 'cumplido' => false]);

        $this->assertTrue($evento->fresh()->procesoRel->is($proceso));
        $this->assertFalse($evento->fresh()->cumplido);
    }

    public function test_deleting_a_proyecto_cascades_to_its_children(): void
    {
        $proyecto = $this->crearProyecto();
        PresupuestoProyecto::create(['proyecto' => $proyecto->id, 'componente' => 'X', 'valor' => 100]);

        $proyecto->delete();

        $this->assertSame(0, PresupuestoProyecto::count());
    }

    public function test_deleting_a_proceso_licitacion_directly_cascades_its_own_eventos(): void
    {
        $proyecto = $this->crearProyecto();
        $proceso = ProcesoLicitacion::create(['proyecto' => $proyecto->id, 'codigo_proceso' => 'LIC-002']);
        EventoLicitacion::create(['proyecto' => $proyecto->id, 'proceso' => $proceso->id, 'cumplido' => false]);

        $proceso->delete();

        $this->assertSame(0, EventoLicitacion::count());
    }
}
