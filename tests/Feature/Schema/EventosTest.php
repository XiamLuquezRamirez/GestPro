<?php

namespace Tests\Feature\Schema;

use App\Models\Evento;
use App\Models\Municipio;
use App\Models\Prioridad;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventosTest extends TestCase
{
    use RefreshDatabase;

    public function test_evento_resolves_all_optional_relationships(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->id, 'nombre' => 'Proyecto de prueba']);
        $tipo = TipoEvento::create(['nombre' => 'Reunión', 'activo' => true]);
        $prioridad = Prioridad::create(['nombre' => 'Alta', 'activo' => true]);
        $responsable = Responsable::create(['nombre' => 'Juan Pérez', 'activo' => true]);

        $evento = Evento::create([
            'titulo' => 'Visita de obra',
            'fecha' => '2026-08-01',
            'tipo_eventos' => $tipo->id,
            'prioridad' => $prioridad->id,
            'estado_evento' => 'pendiente',
            'proyecto' => $proyecto->id,
            'responsable' => $responsable->id,
        ]);

        $fresco = $evento->fresh();
        $this->assertTrue($fresco->tipoEvento->is($tipo));
        $this->assertTrue($fresco->prioridadRel->is($prioridad));
        $this->assertTrue($fresco->proyectoRel->is($proyecto));
        $this->assertTrue($fresco->responsableRel->is($responsable));
    }

    public function test_evento_can_exist_without_a_proyecto(): void
    {
        $tipo = TipoEvento::create(['nombre' => 'Recordatorio', 'activo' => true]);

        $evento = Evento::create(['titulo' => 'Evento sin proyecto', 'tipo_eventos' => $tipo->id]);

        $this->assertNull($evento->fresh()->proyectoRel);
    }
}
