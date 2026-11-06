<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuentesFinanciacionApiTest extends TestCase
{
    use RefreshDatabase;

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    public function test_cualquier_usuario_autenticado_lista_el_catalogo_en_orden(): void
    {
        $this->como(Rol::Consulta)->getJson('/GestPro/fuentesFinanciacion')
            ->assertOk()
            ->assertJsonCount(10)
            ->assertJsonPath('0.clave', 'sgr')
            ->assertJsonPath('0.activo', true)
            ->assertJsonPath('9.clave', 'otras');
    }

    public function test_el_administrador_agrega_una_fuente_al_final(): void
    {
        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Agregar',
            'id' => 1730000000000,
            'nombre' => 'Fondo de Adaptación',
            'descripcion' => 'Fondo nacional',
        ])->assertOk();

        $fuente = FuenteFinanciacion::where('nombre', 'Fondo de Adaptación')->firstOrFail();
        $this->assertSame(11, $fuente->orden);
        $this->assertTrue($fuente->activo);
        $this->assertNull($fuente->clave);
        $this->assertSame('Fondo nacional', $fuente->descripcion);
    }

    public function test_editar_cambia_el_nombre_y_conserva_la_clave(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'pgn')->firstOrFail();

        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Editar',
            'id' => $fuente->id,
            'nombre' => 'PGN',
            'clave' => 'otra',
        ])->assertOk();

        $this->assertSame('PGN', $fuente->fresh()->nombre);
        $this->assertSame('pgn', $fuente->fresh()->clave);
    }

    public function test_rechaza_nombres_vacios_o_repetidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/guardarFuenteFinanciacion', ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $admin->postJson('/GestPro/guardarFuenteFinanciacion', ['accion' => 'Agregar', 'nombre' => 'Recursos propios'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_editar_sin_cambiar_el_nombre_no_choca_consigo_mismo(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'sgp')->firstOrFail();

        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Editar', 'id' => $fuente->id, 'nombre' => $fuente->nombre,
        ])->assertOk();
    }

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'otras')->firstOrFail();
        $admin = $this->como(Rol::Administrador);

        $admin->postJson('/GestPro/activarFuenteFinanciacion', ['id' => $fuente->id])->assertOk();
        $this->assertFalse($fuente->fresh()->activo);

        $admin->postJson('/GestPro/activarFuenteFinanciacion', ['id' => $fuente->id])->assertOk();
        $this->assertTrue($fuente->fresh()->activo);
    }

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $fuente = FuenteFinanciacion::first();

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $this->como($rol)->postJson('/GestPro/guardarFuenteFinanciacion', ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertForbidden();
            $this->como($rol)->postJson('/GestPro/activarFuenteFinanciacion', ['id' => $fuente->id])->assertForbidden();
        }
    }
}
