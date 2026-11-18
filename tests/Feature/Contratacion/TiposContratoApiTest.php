<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TiposContratoApiTest extends TestCase
{
    use RefreshDatabase;

    private const LISTAR = '/GestPro/tiposProcesos';
    private const GUARDAR = '/GestPro/guardarTipoProceso';
    private const ACTIVAR = '/GestPro/activarTipoProceso';

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function tipo(string $nombre): TipoProceso
    {
        return TipoProceso::where('nombre', $nombre)->firstOrFail();
    }

    public function test_cualquier_usuario_autenticado_lista_los_tipos_ordenados_por_nombre_con_inactivos(): void
    {
        $this->tipo('Obra')->update(['activo' => false]);

        $respuesta = $this->como(Rol::Consulta)->getJson(self::LISTAR)
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonStructure([['id', 'nombre', 'activo']]);

        $nombres = array_column($respuesta->json(), 'nombre');
        $this->assertSame(
            ['Consultoría', 'Interventoría', 'Obra', 'Orden de compra', 'Prestación de servicios', 'Suministro'],
            $nombres
        );
        $this->assertFalse(collect($respuesta->json())->firstWhere('nombre', 'Obra')['activo']);
    }

    public function test_el_administrador_agrega_un_tipo_activo(): void
    {
        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Agregar',
            'nombre' => '  Concesión ',
            'activo' => false,
        ])->assertOk()->assertJsonPath('success', 'Tipo de contrato guardado correctamente');

        $tipo = TipoProceso::where('nombre', 'Concesión')->firstOrFail();
        $this->assertTrue($tipo->activo);
        $this->assertDatabaseCount('tipos_procesos', 7);
    }

    public function test_editar_cambia_el_nombre_y_conserva_el_estado(): void
    {
        $tipo = $this->tipo('Suministro');
        $tipo->update(['activo' => false]);

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'Suministro de bienes', 'activo' => true,
        ])->assertOk();

        $tipo->refresh();
        $this->assertSame('Suministro de bienes', $tipo->nombre);
        $this->assertFalse($tipo->activo);
    }

    public function test_rechaza_nombres_vacios_o_largos_con_mensajes_en_espanol(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre del tipo de contrato.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 121)])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'El nombre admite hasta 120 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 120)])->assertOk();
    }

    public function test_el_nombre_no_se_repite_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $admin = $this->como(Rol::Administrador);

        foreach (['Interventoría', 'INTERVENTORÍA', 'interventoria', ' interventoria '] as $nombre) {
            $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre])
                ->assertStatus(422)
                ->assertJsonPath('errors.nombre.0', 'Ya existe un tipo de contrato con ese nombre.');
        }

        $this->assertDatabaseCount('tipos_procesos', 6);
    }

    public function test_editar_sin_cambiar_el_nombre_o_solo_sus_mayusculas_no_choca_consigo_mismo(): void
    {
        $tipo = $this->tipo('Consultoría');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'Consultoría'])->assertOk();
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'CONSULTORIA'])->assertOk();

        $this->assertSame('CONSULTORIA', $tipo->fresh()->nombre);
    }

    public function test_no_se_puede_renombrar_con_el_nombre_de_otro_tipo(): void
    {
        $tipo = $this->tipo('Consultoría');

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'orden de compra',
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertSame('Consultoría', $tipo->fresh()->nombre);
    }

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $tipo = $this->tipo('Obra');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::ACTIVAR, ['id' => $tipo->id])
            ->assertOk()->assertJsonPath('success', 'Tipo de contrato actualizado correctamente');
        $this->assertFalse($tipo->fresh()->activo);

        $admin->postJson(self::ACTIVAR, ['id' => $tipo->id])->assertOk();
        $this->assertTrue($tipo->fresh()->activo);
    }

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $tipo = $this->tipo('Obra');

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $usuario = $this->como($rol);

            $usuario->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nuevo'])->assertForbidden();
            $usuario->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $tipo->id, 'nombre' => 'Otro'])->assertForbidden();
            $usuario->postJson(self::ACTIVAR, ['id' => $tipo->id])->assertForbidden();
        }

        $this->assertDatabaseCount('tipos_procesos', 6);
        $this->assertSame('Obra', $tipo->fresh()->nombre);
        $this->assertTrue($tipo->fresh()->activo);
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['nombre' => 'Nuevo'])->assertStatus(422)->assertJsonValidationErrors('accion');
        $admin->postJson(self::GUARDAR, ['accion' => 'Borrar', 'nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'nombre' => 'Nuevo'])->assertStatus(422)->assertJsonValidationErrors('id');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nuevo'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => 999999, 'nombre' => 'Nuevo'])->assertNotFound();
        $admin->postJson(self::ACTIVAR, [])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => [1]])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => 999999])->assertNotFound();

        $this->assertDatabaseCount('tipos_procesos', 6);
    }

    public function test_el_id_debe_ser_un_entero_de_verdad(): void
    {
        $primero = TipoProceso::orderBy('id')->firstOrFail();
        $nombre = $primero->nombre;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El id del tipo de contrato debe ser un número entero.';

        foreach ([true, '1', '01'] as $raro) {
            $admin->postJson(self::ACTIVAR, ['id' => $raro])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
            $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $raro, 'nombre' => 'Otro'])
                ->assertStatus(422)->assertJsonValidationErrors(['id' => $mensaje]);
        }

        $primero->refresh();
        $this->assertTrue($primero->activo);
        $this->assertSame($nombre, $primero->nombre);
    }

    public function test_el_alta_lee_los_nombres_dentro_de_su_transaccion(): void
    {
        // La unicidad se valida en código (nombre es TEXT, sin índice único): la lectura de los
        // nombres debe estar dentro de la transacción del bloqueo.
        $admin = $this->como(Rol::Administrador);
        $fuera = DB::transactionLevel();
        $niveles = [];
        DB::listen(function (QueryExecuted $consulta) use (&$niveles) {
            if (preg_match('/^select .* from "tipos_procesos"/i', ltrim($consulta->sql))) {
                $niveles[] = DB::transactionLevel();
            }
        });

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Concesión'])->assertOk();

        $this->assertNotEmpty($niveles);
        $this->assertGreaterThan($fuera, $niveles[0]);
    }

    public function test_las_tres_rutas_apuntan_al_controlador_de_tipos_de_contrato(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($r) => $r->methods()[0] . ' ' . $r->uri());

        $this->assertStringContainsString('TipoContratoController@index', $rutas['GET GestPro/tiposProcesos']->getActionName());
        $this->assertStringContainsString('TipoContratoController@guardar', $rutas['POST GestPro/guardarTipoProceso']->getActionName());
        $this->assertStringContainsString('TipoContratoController@activar', $rutas['POST GestPro/activarTipoProceso']->getActionName());
        // Fuera de alcance: eliminar sigue donde estaba.
        $this->assertStringContainsString('ProyectoController@eliminarTipoProceso', $rutas['POST GestPro/eliminarTipoProceso']->getActionName());
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson(self::LISTAR)->assertUnauthorized();
        $this->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nuevo'])->assertUnauthorized();
        $this->postJson(self::ACTIVAR, ['id' => 1])->assertUnauthorized();
    }
}
