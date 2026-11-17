<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\EtapaContratacion;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EtapasContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private const LISTAR = '/GestPro/etapasContratacion';
    private const GUARDAR = '/GestPro/guardarEtapaContratacion';
    private const ACTIVAR = '/GestPro/activarEtapaContratacion';
    private const ORDENAR = '/GestPro/etapasContratacion/orden';

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function agregar(string $nombre, array $mas = []): EtapaContratacion
    {
        $this->como(Rol::Administrador)
            ->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre, ...$mas])
            ->assertOk();

        return EtapaContratacion::where('nombre', $nombre)->firstOrFail();
    }

    /** @return list<int> */
    private function idsEnOrden(): array
    {
        return EtapaContratacion::orderBy('orden')->pluck('id')->all();
    }

    // ------------------------------------------------------------- listar

    public function test_cualquier_usuario_autenticado_lista_todas_las_etapas_en_orden(): void
    {
        $this->etapa('adendas')->update(['activo' => false]);

        $this->como(Rol::Consulta)->getJson(self::LISTAR)
            ->assertOk()
            ->assertJsonCount(11)
            ->assertJsonPath('0.clave', 'planeacion')
            ->assertJsonPath('0.nombre', 'Planeación y estudios previos')
            ->assertJsonPath('0.orden', 1)
            ->assertJsonPath('0.activo', true)
            ->assertJsonPath('5.clave', 'adendas')
            ->assertJsonPath('5.activo', false)
            ->assertJsonPath('10.clave', 'contrato');
    }

    public function test_exige_autenticacion(): void
    {
        $this->getJson(self::LISTAR)->assertUnauthorized();
        $this->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertUnauthorized();
        $this->postJson(self::ACTIVAR, ['id' => 1])->assertUnauthorized();
        $this->putJson(self::ORDENAR, ['ids' => [1]])->assertUnauthorized();
    }

    // ------------------------------------------------------------- guardar

    public function test_agregar_crea_la_etapa_al_final_con_clave_generada_del_nombre(): void
    {
        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Agregar',
            'nombre' => 'Liquidación del contrato',
            'descripcion' => 'Acta de liquidación y cierre del expediente.',
            'clave' => 'otra',
            'orden' => 1,
        ])->assertOk()
            ->assertJsonPath('success', 'Etapa de contratación guardada correctamente')
            ->assertJsonPath('etapa.clave', 'liquidacion_del_contrato');

        $etapa = EtapaContratacion::where('nombre', 'Liquidación del contrato')->firstOrFail();
        $this->assertSame('liquidacion_del_contrato', $etapa->clave);
        $this->assertSame('Acta de liquidación y cierre del expediente.', $etapa->descripcion);
        $this->assertSame(12, $etapa->orden);
        $this->assertTrue($etapa->activo);
    }

    public function test_la_clave_generada_quita_tildes_y_simbolos(): void
    {
        $this->assertSame('reunion_de_diseno_y_cotizacion_urgente', $this->agregar('Reunión de diseño   y cotización (¡urgente!)')->clave);
        $this->assertSame('pago_al_100_contratista', $this->agregar('Pago al 100 % @ contratista')->clave);
        $this->assertSame('visita_al_sitio_de_obra', $this->agregar('VISITA AL SITIO DE OBRA')->clave);
        $this->assertSame('etapa', $this->agregar('¿?')->clave);
    }

    public function test_la_clave_generada_se_desambigua_con_un_sufijo(): void
    {
        // "cierre" y "evaluacion" ya son claves sembradas.
        $this->assertSame('cierre_2', $this->agregar('Cierre')->clave);
        $this->assertSame('cierre_3', $this->agregar('¡Cierre!')->clave);
        $this->assertSame('evaluacion_2', $this->agregar('Evaluación')->clave);
    }

    public function test_la_clave_generada_no_pasa_de_cuarenta_caracteres(): void
    {
        $nombre = str_repeat('Revisión jurídica ', 6) . 'final';
        $primera = $this->agregar($nombre);
        $segunda = $this->agregar($nombre . ' bis');

        $this->assertSame(40, strlen($primera->clave));
        $this->assertStringStartsWith('revision_juridica_revision', $primera->clave);
        $this->assertLessThanOrEqual(40, strlen($segunda->clave));
        $this->assertStringEndsWith('_2', $segunda->clave);
        $this->assertNotSame($primera->clave, $segunda->clave);
    }

    public function test_editar_cambia_nombre_y_descripcion_y_conserva_clave_orden_y_estado(): void
    {
        $etapa = $this->etapa('adendas');
        $etapa->update(['activo' => false]);

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, [
            'accion' => 'Editar',
            'id' => $etapa->id,
            'nombre' => 'Adendas al pliego',
            'descripcion' => null,
            'clave' => 'otra',
            'orden' => 1,
        ])->assertOk()->assertJsonPath('etapa.clave', 'adendas');

        $etapa = $etapa->fresh();
        $this->assertSame('Adendas al pliego', $etapa->nombre);
        $this->assertNull($etapa->descripcion);
        $this->assertSame('adendas', $etapa->clave);
        $this->assertSame(6, $etapa->orden);
        $this->assertFalse($etapa->activo);
    }

    public function test_rechaza_datos_invalidos_con_mensajes_en_espanol(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ''])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la etapa.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 121)])
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', 'El nombre admite hasta 120 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva', 'descripcion' => str_repeat('a', 2001)])
            ->assertStatus(422)->assertJsonPath('errors.descripcion.0', 'La descripción admite hasta 2000 caracteres.');
        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => ['Nueva']])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertDatabaseCount('etapas_contratacion', 11);

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => str_repeat('a', 120), 'descripcion' => str_repeat('a', 2000)])
            ->assertOk();
    }

    public function test_el_nombre_no_se_repite_sin_distinguir_mayusculas_ni_tildes(): void
    {
        $admin = $this->como(Rol::Administrador);

        foreach (['Adendas', 'ADENDAS', 'Evaluacion y subsanaciones'] as $nombre) {
            $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => $nombre])
                ->assertStatus(422)
                ->assertJsonPath('errors.nombre.0', 'Ya existe una etapa con ese nombre.');
        }

        $otra = $this->etapa('cierre');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $otra->id, 'nombre' => 'adendas'])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->assertSame('Cierre y apertura de ofertas', $otra->fresh()->nombre);
        $this->assertDatabaseCount('etapas_contratacion', 11);
    }

    public function test_editar_sin_cambiar_el_nombre_o_solo_sus_mayusculas_no_choca_consigo_misma(): void
    {
        $etapa = $this->etapa('adendas');
        $descripcion = $etapa->descripcion;
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $etapa->id, 'nombre' => 'Adendas'])->assertOk();
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $etapa->id, 'nombre' => 'ADENDAS'])->assertOk();

        $this->assertSame('ADENDAS', $etapa->fresh()->nombre);
        // Sin descripción en la petición, se conserva la que tenía.
        $this->assertSame($descripcion, $etapa->fresh()->descripcion);
    }

    public function test_rechaza_acciones_e_ids_invalidos(): void
    {
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::GUARDAR, ['nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('accion');
        $admin->postJson(self::GUARDAR, ['accion' => 'Borrar', 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'nombre' => 'Nueva'])->assertStatus(422)->assertJsonValidationErrors('id');
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => [1], 'nombre' => 'Nueva'])->assertStatus(422);
        $admin->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => 999999, 'nombre' => 'Nueva'])->assertNotFound();

        $this->assertDatabaseCount('etapas_contratacion', 11);
    }

    public function test_el_id_debe_ser_un_entero_de_verdad(): void
    {
        $primera = EtapaContratacion::orderBy('id')->firstOrFail();
        $nombre = $primera->nombre;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El id de la etapa debe ser un número entero.';

        // true o "1" no son la etapa 1.
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

    public function test_el_alta_lee_nombres_claves_y_orden_dentro_de_su_transaccion(): void
    {
        // Lo primero que lee está dentro de la transacción del bloqueo: dos altas a la vez se ordenan.
        $admin = $this->como(Rol::Administrador);
        $fuera = DB::transactionLevel();
        $niveles = [];
        DB::listen(function (QueryExecuted $consulta) use (&$niveles) {
            if (preg_match('/^select .* from "etapas_contratacion"/i', ltrim($consulta->sql))) {
                $niveles[] = DB::transactionLevel();
            }
        });

        $admin->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Cierre'])->assertOk();

        $this->assertGreaterThan($fuera, $niveles[0] ?? $fuera);
        $this->assertSame(12, $this->etapa('cierre_2')->orden);
    }

    public function test_si_la_clave_se_ocupa_a_la_vez_responde_422_sin_guardar(): void
    {
        // Algo que no pasa por esta ruta toma la clave justo antes de insertar.
        EtapaContratacion::creating(function (EtapaContratacion $etapa) {
            DB::table('etapas_contratacion')->insert(['clave' => $etapa->clave, 'nombre' => 'Intrusa', 'orden' => 99]);
        });

        $this->como(Rol::Administrador)->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Liquidación'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre' => 'Otra etapa se guardó al mismo tiempo con la misma clave. Vuelve a intentarlo.']);

        $this->assertDatabaseCount('etapas_contratacion', 11);
    }

    // ------------------------------------------------------------- activar

    public function test_activar_alterna_el_estado_segun_la_base_de_datos(): void
    {
        $etapa = $this->etapa('adendas');
        $admin = $this->como(Rol::Administrador);

        $admin->postJson(self::ACTIVAR, ['id' => $etapa->id])
            ->assertOk()->assertJsonPath('success', 'Etapa de contratación actualizada correctamente');
        $this->assertFalse($etapa->fresh()->activo);

        $admin->postJson(self::ACTIVAR, ['id' => $etapa->id])->assertOk();
        $this->assertTrue($etapa->fresh()->activo);

        $admin->postJson(self::ACTIVAR, [])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => [1]])->assertStatus(422);
        $admin->postJson(self::ACTIVAR, ['id' => 999999])->assertNotFound();
    }

    // ------------------------------------------------------------- ordenar

    public function test_ordenar_reescribe_el_orden_con_la_lista_completa(): void
    {
        $this->etapa('contrato')->update(['orden' => 40]); // huecos o repetidos se normalizan
        $ids = array_reverse($this->idsEnOrden());

        $this->como(Rol::Administrador)->putJson(self::ORDENAR, ['ids' => $ids])
            ->assertOk()->assertJsonPath('success', 'Orden de las etapas guardado correctamente');

        $this->assertSame($ids, $this->idsEnOrden());
        $this->assertSame(range(1, 11), EtapaContratacion::orderBy('orden')->pluck('orden')->all());
        $this->assertSame('contrato', EtapaContratacion::where('orden', 1)->value('clave'));
    }

    public function test_ordenar_usa_una_sola_actualizacion(): void
    {
        $ids = array_reverse($this->idsEnOrden());
        $actualizaciones = 0;
        DB::listen(function (QueryExecuted $consulta) use (&$actualizaciones) {
            if (str_starts_with(strtolower(ltrim($consulta->sql)), 'update')) {
                $actualizaciones++;
            }
        });

        $this->como(Rol::Administrador)->putJson(self::ORDENAR, ['ids' => $ids])->assertOk();

        $this->assertSame(1, $actualizaciones);
        $this->assertSame($ids, $this->idsEnOrden());
    }

    public function test_ordenar_incluye_las_etapas_inactivas_y_las_nuevas(): void
    {
        $this->etapa('adendas')->update(['activo' => false]);
        $nueva = $this->agregar('Liquidación');
        $ids = $this->idsEnOrden();
        array_unshift($ids, array_pop($ids));

        $this->como(Rol::Administrador)->putJson(self::ORDENAR, ['ids' => $ids])->assertOk();

        $this->assertSame(1, $nueva->fresh()->orden);
        $this->assertSame(7, $this->etapa('adendas')->orden);
    }

    public function test_ordenar_rechaza_listas_incompletas_repetidas_o_con_extranos(): void
    {
        $ids = $this->idsEnOrden();
        $antes = $ids;
        $admin = $this->como(Rol::Administrador);
        $mensaje = 'El orden debe incluir todas las etapas, una vez cada una.';

        $admin->putJson(self::ORDENAR, ['ids' => array_slice($ids, 1)])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', $mensaje);
        $admin->putJson(self::ORDENAR, ['ids' => [...$ids, 999999]])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', $mensaje);
        $admin->putJson(self::ORDENAR, ['ids' => [...array_slice($ids, 1), 999999]])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', $mensaje);
        $admin->putJson(self::ORDENAR, ['ids' => [...$ids, $ids[0]]])
            ->assertStatus(422)->assertJsonValidationErrors(['ids.11' => 'Una etapa aparece más de una vez en el orden.']);

        $this->assertSame($antes, $this->idsEnOrden());
    }

    public function test_ordenar_exige_una_lista_de_enteros(): void
    {
        $ids = $this->idsEnOrden();
        $admin = $this->como(Rol::Administrador);

        $admin->putJson(self::ORDENAR, [])
            ->assertStatus(422)->assertJsonPath('errors.ids.0', 'Envía el orden de las etapas.');
        $admin->putJson(self::ORDENAR, ['ids' => []])
            ->assertStatus(422)->assertJsonValidationErrors('ids');
        $admin->putJson(self::ORDENAR, ['ids' => '1,2,3'])
            ->assertStatus(422)->assertJsonValidationErrors('ids');

        foreach (['3', true, 3.5, null, ['3']] as $raro) {
            $lista = $ids;
            $lista[0] = $raro;
            $admin->putJson(self::ORDENAR, ['ids' => $lista])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['ids.0' => 'Cada etapa del orden debe ser un número entero.']);
        }
    }

    // ------------------------------------------------------------- roles

    public function test_solo_el_administrador_modifica_el_catalogo(): void
    {
        $etapa = $this->etapa('adendas');
        $ids = $this->idsEnOrden();

        foreach ([Rol::Gestor, Rol::Consulta] as $rol) {
            $usuario = $this->como($rol);

            $usuario->postJson(self::GUARDAR, ['accion' => 'Agregar', 'nombre' => 'Nueva'])->assertForbidden();
            $usuario->postJson(self::GUARDAR, ['accion' => 'Editar', 'id' => $etapa->id, 'nombre' => 'Otra'])->assertForbidden();
            $usuario->postJson(self::ACTIVAR, ['id' => $etapa->id])->assertForbidden();
            $usuario->putJson(self::ORDENAR, ['ids' => array_reverse($ids)])->assertForbidden();
        }

        $this->assertDatabaseCount('etapas_contratacion', 11);
        $this->assertSame('Adendas', $etapa->fresh()->nombre);
        $this->assertTrue($etapa->fresh()->activo);
        $this->assertSame($ids, $this->idsEnOrden());
    }
}
