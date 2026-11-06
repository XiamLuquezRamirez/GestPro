<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\User;
use App\Services\Financiacion\FuentesProyecto;
use Illuminate\Support\Facades\DB;
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
            ->assertStatus(422)->assertJsonValidationErrors('nombre')
            ->assertJsonPath('errors.nombre.0', 'Ya existe una fuente con ese nombre.');
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

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);
        $url = '/GestPro/guardarFuenteFinanciacion';

        $admin->postJson($url, ['nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson($url, ['accion' => 'Borrar', 'nombre' => 'Nueva'])->assertStatus(422);
        $this->assertDatabaseCount('fuentes_financiacion', 10);

        $admin->postJson($url, ['accion' => 'Editar', 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson($url, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson('/GestPro/activarFuenteFinanciacion', ['id' => [1]])->assertStatus(422);
        $admin->postJson('/GestPro/activarFuenteFinanciacion', ['id' => 999999])->assertNotFound();
    }

    public function test_no_se_puede_renombrar_con_el_nombre_de_otra_fuente(): void
    {
        $fuente = FuenteFinanciacion::where('clave', 'pgn')->firstOrFail();
        $nombre = $fuente->nombre;

        $this->como(Rol::Administrador)->postJson('/GestPro/guardarFuenteFinanciacion', [
            'accion' => 'Editar', 'id' => $fuente->id, 'nombre' => 'Recursos propios',
        ])->assertStatus(422);

        $this->assertSame($nombre, $fuente->fresh()->nombre);
    }

    public function test_renombrar_actualiza_el_texto_de_sus_proyectos(): void
    {
        $sgr = FuenteFinanciacion::where('clave', 'sgr')->firstOrFail();
        $pgn = FuenteFinanciacion::where('clave', 'pgn')->firstOrFail();
        $propios = FuenteFinanciacion::where('nombre', 'Recursos propios')->firstOrFail();
        $servicio = app(FuentesProyecto::class);

        $a = DB::table('proyectos')->insertGetId(['nombre' => 'A']);
        $b = DB::table('proyectos')->insertGetId(['nombre' => 'B']);
        $c = DB::table('proyectos')->insertGetId(['nombre' => 'C']);
        $servicio->guardar($a, $servicio->validar(['fuenteIds' => [$pgn->id, $sgr->id]]));
        $servicio->guardar($b, $servicio->validar(['fuenteIds' => [$propios->id]]));
        $servicio->guardar($c, $servicio->validar(['fuenteIds' => [$pgn->id]]));
        DB::table('proyectos')->where('id', $c)->update(['fuente_financiacion' => 'Texto manual']);

        $texto = fn (int $id) => DB::table('proyectos')->where('id', $id)->value('fuente_financiacion');
        $admin = $this->como(Rol::Administrador);
        $url = '/GestPro/guardarFuenteFinanciacion';

        // Solo cambia la descripción: no se reescribe ningún texto.
        $admin->postJson($url, ['accion' => 'Editar', 'id' => $pgn->id, 'nombre' => $pgn->nombre, 'descripcion' => 'Otra'])->assertOk();
        $this->assertSame('Texto manual', $texto($c));

        $admin->postJson($url, ['accion' => 'Editar', 'id' => $pgn->id, 'nombre' => 'Nacion PGN'])->assertOk();

        $this->assertSame('Sistema General de Regalías (SGR), Nacion PGN', $texto($a));
        $this->assertSame('Recursos propios', $texto($b));
    }

    public function test_el_listado_incluye_las_fuentes_inactivas(): void
    {
        FuenteFinanciacion::where('clave', 'otras')->update(['activo' => false]);

        $this->como(Rol::Consulta)->getJson('/GestPro/fuentesFinanciacion')
            ->assertOk()->assertJsonCount(10);
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson('/GestPro/fuentesFinanciacion')->assertUnauthorized();
    }
}
