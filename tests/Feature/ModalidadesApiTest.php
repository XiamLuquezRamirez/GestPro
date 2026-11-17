<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Modalidad;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModalidadesApiTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDAR = '/GestPro/guardarModalidad';
    private const ACTIVAR = '/GestPro/activarModalidad';

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function modalidad(string $clave): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    public function test_cualquier_usuario_autenticado_lista_el_catalogo_en_orden(): void
    {
        $this->modalidad('regimen_especial')->update(['activo' => false]);

        $this->como(Rol::Consulta)->getJson('/GestPro/modalidades')
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonPath('0.clave', 'licitacion_publica')
            ->assertJsonPath('0.nombre', 'Licitación pública')
            ->assertJsonPath('0.orden', 1)
            ->assertJsonPath('0.activo', true)
            ->assertJsonPath('5.clave', 'regimen_especial')
            ->assertJsonPath('5.activo', false);
    }

    public function test_a_igual_orden_lista_por_nombre(): void
    {
        Modalidad::create(['nombre' => 'Zeta', 'orden' => 7]);
        Modalidad::create(['nombre' => 'Alfa', 'orden' => 7]);

        $this->como(Rol::Consulta)->getJson('/GestPro/modalidades')
            ->assertOk()
            ->assertJsonPath('6.nombre', 'Alfa')
            ->assertJsonPath('7.nombre', 'Zeta');
    }

    public function test_el_administrador_agrega_una_modalidad_al_final_sin_clave(): void
    {
        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Agregar',
            'nombre' => 'Convenio interadministrativo',
            'clave' => 'convenio',
            'orden' => 1,
        ])->assertOk()->assertJsonPath('success', 'Modalidad guardada correctamente');

        $modalidad = Modalidad::where('nombre', 'Convenio interadministrativo')->firstOrFail();
        $this->assertSame(7, $modalidad->orden);
        $this->assertTrue($modalidad->activo);
        $this->assertNull($modalidad->clave);
    }

    public function test_editar_cambia_el_nombre_y_conserva_clave_orden_y_estado(): void
    {
        $modalidad = $this->modalidad('minima_cuantia');
        $modalidad->update(['activo' => false]);

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar',
            'id' => $modalidad->id,
            'nombre' => 'Mínima cuantía (hasta 10 % de la menor cuantía)',
            'clave' => 'otra',
            'orden' => 1,
        ])->assertOk();

        $modalidad = $modalidad->fresh();
        $this->assertSame('Mínima cuantía (hasta 10 % de la menor cuantía)', $modalidad->nombre);
        $this->assertSame('minima_cuantia', $modalidad->clave);
        $this->assertSame(5, $modalidad->orden);
        $this->assertFalse($modalidad->activo);
    }

    public function test_rechaza_nombres_vacios_o_largos_con_mensajes_en_espanol(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la modalidad.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 200)])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'El nombre admite hasta 199 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 199)])->assertOk();
    }

    public function test_el_nombre_no_se_repite_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $admin = $this->como(Rol::Administrador);

        foreach (['Licitación pública', 'LICITACIÓN PÚBLICA', 'licitacion publica'] as $nombre) {
            $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre])
                ->assertStatus(422)
                ->assertJsonPath('errors.nombre.0', 'Ya existe una modalidad con ese nombre.');
        }

        $this->assertDatabaseCount('modalidades', 6);
    }

    public function test_editar_sin_cambiar_el_nombre_o_solo_sus_mayusculas_no_choca_consigo_misma(): void
    {
        $modalidad = $this->modalidad('concurso_meritos');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => $modalidad->nombre])->assertOk();
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => 'CONCURSO DE MÉRITOS'])->assertOk();

        $this->assertSame('CONCURSO DE MÉRITOS', $modalidad->fresh()->nombre);
    }

    public function test_no_se_puede_renombrar_con_el_nombre_de_otra_modalidad(): void
    {
        $modalidad = $this->modalidad('concurso_meritos');

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => 'contratación DIRECTA',
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertSame('Concurso de méritos', $modalidad->fresh()->nombre);
    }

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $modalidad = $this->modalidad('regimen_especial');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::ACTIVAR, ['id' => $modalidad->id])
            ->assertOk()->assertJsonPath('success', 'Modalidad actualizada correctamente');
        $this->assertFalse($modalidad->fresh()->activo);

        $admin->postJson(self::ACTIVAR, ['id' => $modalidad->id])->assertOk();
        $this->assertTrue($modalidad->fresh()->activo);
    }

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $modalidad = $this->modalidad('licitacion_publica');

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $usuario = $this->como($rol);

            $usuario->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertForbidden();
            $usuario->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $modalidad->id, 'nombre' => 'Otra'])->assertForbidden();
            $usuario->postJson(self::ACTIVAR, ['id' => $modalidad->id])->assertForbidden();
        }

        $this->assertDatabaseCount('modalidades', 6);
        $this->assertSame('Licitación pública', $modalidad->fresh()->nombre);
        $this->assertTrue($modalidad->fresh()->activo);
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('accion');
        $admin->postJson(self::GUARDAR, ['accion' => 'Borrar', 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('id');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => 999999, 'nombre' => 'Nueva'])->assertNotFound();
        $admin->postJson(self::ACTIVAR, [])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => [1]])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => 999999])->assertNotFound();

        $this->assertDatabaseCount('modalidades', 6);
    }

    public function test_el_id_debe_ser_un_entero_de_verdad(): void
    {
        $primera = Modalidad::orderBy('id')->firstOrFail();
        $nombre = $primera->nombre;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El id de la modalidad debe ser un número entero.';

        // true o "1" no son la modalidad 1.
        foreach ([true, '1', '01'] as $raro) {
            $admin->postJson(self::ACTIVAR, ['id' => $raro])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
            $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $raro, 'nombre' => 'Otra'])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
        }

        $primera->refresh();
        $this->assertTrue($primera->activo);
        $this->assertSame($nombre, $primera->nombre);
    }

    public function test_el_alta_lee_nombres_y_orden_dentro_de_su_transaccion(): void
    {
        // Lo primero que lee está dentro de la transacción del bloqueo: dos altas a la vez se ordenan.
        $admin = $this->como(Rol::Administrador);
        $fuera = DB::transactionLevel();
        $niveles = [];
        DB::listen(function (QueryExecuted $consulta) use (&$niveles) {
            if (preg_match('/^select .* from "modalidades"/i', ltrim($consulta->sql))) {
                $niveles[] = DB::transactionLevel();
            }
        });

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Convenio'])->assertOk();

        $this->assertGreaterThan($fuera, $niveles[0] ?? $fuera);
        $this->assertSame(7, Modalidad::where('nombre', 'Convenio')->value('orden'));
    }

    public function test_las_modalidades_no_se_eliminan(): void
    {
        $this->assertFalse(
            collect(Route::getRoutes()->getRoutes())->contains(fn ($ruta) => $ruta->uri() === 'GestPro/eliminarModalidad'),
            'La ruta para eliminar modalidades debía retirarse.'
        );

        // Solo la atrapa la ruta GET del front, que no acepta POST.
        $this->como(Rol::Administrador)
            ->postJson('/GestPro/eliminarModalidad', ['id' => $this->modalidad('licitacion_publica')->id])
            ->assertStatus(405);
        $this->assertDatabaseCount('modalidades', 6);
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson('/GestPro/modalidades')->assertUnauthorized();
        $this->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertUnauthorized();
        $this->postJson(self::ACTIVAR, ['id' => 1])->assertUnauthorized();
    }
}
