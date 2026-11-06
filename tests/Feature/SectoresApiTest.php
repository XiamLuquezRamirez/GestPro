<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectoresApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    public function test_el_administrador_agrega_y_edita_un_sector(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'id' => 1730000000000, 'nombre' => 'Cultura'])->assertOk();
        $sector = Sector::where('nombre', 'Cultura')->firstOrFail();
        $this->assertTrue($sector->activo);

        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Editar', 'id' => $sector->id, 'nombre' => 'Cultura y patrimonio'])->assertOk();
        $this->assertSame('Cultura y patrimonio', $sector->fresh()->nombre);
    }

    public function test_rechaza_nombres_vacios_o_repetidos(): void
    {
        Sector::create(['nombre' => 'Salud', 'activo' => true]);
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $admin->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'nombre' => 'Salud'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre')
            ->assertJsonPath('errors.nombre.0', 'Ya existe un sector con ese nombre.');
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);
        $url = '/GestPro/guardarSector';
        $antes = Sector::count();

        $admin->postJson($url, ['nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson($url, ['accion' => 'Borrar', 'nombre' => 'Nuevo'])->assertStatus(422);
        $this->assertSame($antes, Sector::count());

        $admin->postJson($url, ['accion' => 'Editar', 'nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson($url, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson('/GestPro/activarSector', ['id' => [1]])->assertStatus(422);
        $admin->postJson('/GestPro/activarSector', ['id' => 999999])->assertNotFound();
    }

    public function test_no_se_puede_renombrar_con_el_nombre_de_otro_sector(): void
    {
        Sector::create(['nombre' => 'Salud', 'activo' => true]);
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);

        $this->como(Rol::Administrador)->postJson('/GestPro/guardarSector', [
            'accion' => 'Editar', 'id' => $sector->id, 'nombre' => 'Salud',
        ])->assertStatus(422);

        $this->assertSame('Vías', $sector->fresh()->nombre);
    }

    public function test_desactivar_conserva_el_sector_en_sus_proyectos(): void
    {
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'sector' => $sector->id]);

        $this->como(Rol::Administrador)->postJson('/GestPro/activarSector', ['id' => $sector->id])->assertOk();

        $this->assertFalse($sector->fresh()->activo);
        $this->assertSame($sector->id, (int) $proyecto->fresh()->sector);
    }

    public function test_solo_el_administrador_modifica_los_sectores(): void
    {
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $this->como($rol)->postJson('/GestPro/guardarSector', ['accion' => 'Agregar', 'nombre' => 'Nuevo'])->assertForbidden();
            $this->como($rol)->postJson('/GestPro/activarSector', ['id' => $sector->id])->assertForbidden();
        }
    }

    public function test_los_sectores_no_se_eliminan(): void
    {
        $sector = Sector::create(['nombre' => 'Vías', 'activo' => true]);

        // La ruta no existe. routes/web.php tiene un GET comodín, así que puede responder 405.
        $respuesta = $this->como(Rol::Administrador)->postJson('/GestPro/eliminarSector', ['id' => $sector->id]);

        $this->assertContains($respuesta->status(), [404, 405]);
        $this->assertNotNull($sector->fresh());
    }
}
