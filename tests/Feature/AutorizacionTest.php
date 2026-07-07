<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrador_can_manage_catalogos_and_usuarios(): void
    {
        $admin = User::factory()->create(['rol' => Rol::Administrador]);

        $this->assertTrue($admin->can('gestionar-catalogos'));
        $this->assertTrue($admin->can('gestionar-usuarios'));
        $this->assertTrue($admin->can('editar-datos'));
    }

    public function test_gestor_can_edit_data_but_not_catalogos_or_usuarios(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);

        $this->assertTrue($gestor->can('editar-datos'));
        $this->assertFalse($gestor->can('gestionar-catalogos'));
        $this->assertFalse($gestor->can('gestionar-usuarios'));
    }

    public function test_consulta_cannot_write_anything(): void
    {
        $consulta = User::factory()->create(['rol' => Rol::Consulta]);

        $this->assertFalse($consulta->can('editar-datos'));
        $this->assertFalse($consulta->can('gestionar-catalogos'));
        $this->assertFalse($consulta->can('gestionar-usuarios'));
    }

    public function test_gestor_gets_403_when_creating_a_municipio_via_api(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);
        $token = auth('api')->login($gestor);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/GestPro/guardarMunicipio', ['nombre' => 'Prueba']);

        $response->assertStatus(403);
    }

    public function test_gestor_can_create_a_proyecto_via_api(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = \App\Models\Municipio::create(['nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);
        $token = auth('api')->login($gestor);

        // ProyectoController::guardarProyecto (app/Http/Controllers/ProyectoController.php:530-549) lee estas
        // claves directamente de $request->all() sin isset(), incluida 'accion' => 'Agregar' para la rama de inserción.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/GestPro/guardarProyecto', [
                'accion' => 'Agregar',
                'municipio' => $municipio->id,
                'nombre' => 'Proyecto vía API',
                'descripcion' => 'Creado desde el test de autorización',
                'fechaInicio' => '2026-01-01',
                'estado' => null,
                'fase' => null,
                'presupuesto' => null,
                'entidadPresenta' => null,
                'entidadFinancia' => null,
                'fuenteFinanciamiento' => null,
                'sector' => null,
            ]);

        $response->assertStatus(200);
    }
}
