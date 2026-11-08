<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasRequisitosApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $plantilla;
    private CatalogoVersion $borrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->plantilla = PlantillaChecklist::create([
            'nombre' => 'Inversión pública', 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $this->borrador = app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
    }

    private function comoAdmin(): static
    {
        return $this->como(Rol::Administrador);
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function url(string $resto = ''): string
    {
        return "/GestPro/catalogo-requisitos/versiones/{$this->borrador->id}{$resto}";
    }

    private function crear(array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->url('/requisitos'), ['texto' => 'Certificado de disponibilidad presupuestal', ...$datos]);
    }

    // ------------------------------------------------------------ versiones

    public function test_la_primera_version_de_una_plantilla_nueva_nace_vacia(): void
    {
        $this->assertSame(1, $this->borrador->numero);
        $this->assertSame(0, $this->borrador->requisitos()->count());
    }

    public function test_con_versiones_publicadas_y_ninguna_vigente_no_se_crea_un_borrador(): void
    {
        $this->borrador->update(['estado' => CatalogoVersion::PUBLICADA, 'vigente' => false]);

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('No hay una versión vigente que copiar.');
        app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
    }

    // ------------------------------------------------------------ requisitos

    public function test_crea_un_requisito_simple_con_los_campos_de_la_propuesta(): void
    {
        $datos = [
            'descripcion' => 'Expedido por la secretaría de hacienda', 'categoria' => 'financiero',
            'tipo_requisito' => 'documental', 'caracter' => 'opcional', 'cargo_cumplimiento' => 'Secretario de hacienda',
            'cargo_revision' => 'Jefe jurídico', 'soporte_requerido' => 'CDP firmado', 'referencia_normativa' => 'Decreto 111 de 1996',
            'nota' => 'Vigencia no mayor a 30 días', 'requiere_aprobacion_no_aplica' => true, 'codigo_comun' => 'cdp',
        ];

        $this->crear($datos)->assertCreated()->assertJsonPath('clave', 'r1');

        $fila = $this->borrador->requisitos()->first();
        foreach ($datos as $campo => $valor) {
            $this->assertEquals($valor, $fila->{$campo}, $campo);
        }
        $this->assertNull($fila->seccion);
        $this->assertSame(1, $fila->orden);
    }

    public function test_la_clave_y_el_orden_se_generan_si_no_se_indican(): void
    {
        $this->crear()->assertCreated();
        $this->crear(['texto' => 'Otro'])->assertCreated();

        $this->assertSame(['r1' => 1, 'r2' => 2], $this->borrador->requisitos()->orderBy('orden')->pluck('orden', 'clave')->all());
    }

    public function test_un_condicionado_exige_describir_cuando_aplica(): void
    {
        $this->crear(['caracter' => 'condicionado'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Un requisito condicionado necesita describir cuándo aplica.');

        $this->crear(['caracter' => 'condicionado', 'condicion_descripcion' => 'Si el proyecto compra predios'])->assertCreated();
    }

    public function test_el_codigo_comun_no_se_repite_en_una_version(): void
    {
        $this->crear(['codigo_comun' => 'cdp'])->assertCreated();

        $this->crear(['texto' => 'Otro', 'codigo_comun' => 'cdp'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Ya existe un requisito con el código común "cdp" en esta versión.');
    }

    public function test_categoria_tipo_y_caracter_se_validan(): void
    {
        $this->crear(['categoria' => 'cualquiera', 'tipo_requisito' => 'otro', 'caracter' => 'urgente'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['categoria', 'tipo_requisito', 'caracter'])
            ->assertJsonPath('errors.categoria.0', 'Elige una categoría de la lista.');
    }

    public function test_editar_conserva_lo_que_no_se_envia(): void
    {
        $id = $this->crear(['categoria' => 'tecnico', 'codigo_comun' => 'cdp'])->json('id');

        $this->comoAdmin()->putJson($this->url("/requisitos/{$id}"), ['texto' => 'Nombre nuevo'])->assertOk();

        $fila = $this->borrador->requisitos()->find($id);
        $this->assertSame('Nombre nuevo', $fila->texto);
        $this->assertSame('tecnico', $fila->categoria);
        $this->assertSame('cdp', $fila->codigo_comun);
        $this->assertSame('r1', $fila->clave);
    }

    public function test_ordenar_requisitos(): void
    {
        $ids = collect(['A', 'B', 'C'])->map(fn ($t) => $this->crear(['texto' => $t])->json('id'))->all();

        $this->comoAdmin()->putJson($this->url('/requisitos-orden'), ['ids' => array_reverse($ids)])->assertOk();
        $this->assertSame(['C', 'B', 'A'], $this->borrador->requisitos()->orderBy('orden')->pluck('texto')->all());

        $this->comoAdmin()->putJson($this->url('/requisitos-orden'), ['ids' => [$ids[0]]])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El orden debe incluir todos los requisitos de la versión, una vez cada uno.');
    }

    public function test_solo_un_administrador_administra_requisitos(): void
    {
        $this->como(Rol::Gestor)->postJson($this->url('/requisitos'), ['texto' => 'x'])->assertForbidden();
    }

    // ------------------------------------------------------------ publicar

    public function test_no_se_publica_una_version_sin_requisitos_activos(): void
    {
        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'La versión no tiene requisitos activos y no puede publicarse.');
    }

    public function test_no_se_publica_sin_fuentes_ni_sectores(): void
    {
        $this->crear()->assertCreated();
        $this->plantilla->update(['todas_fuentes' => false]);

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Asocia la plantilla a sus fuentes y sectores antes de publicar.');
    }

    public function test_no_se_publica_un_condicionado_sin_descripcion(): void
    {
        $this->crear()->assertCreated();
        DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->borrador->id)
            ->update(['caracter' => 'condicionado', 'condicion_descripcion' => null]);

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('errores.0', 'Certificado de disponibilidad presupuestal: describe cuándo aplica este requisito condicionado.');
    }

    public function test_las_escrituras_se_validan_sobre_la_fila_releida_de_la_version(): void
    {
        $servicio = app(CatalogoService::class);
        $this->crear()->assertCreated();
        $requisito = $this->borrador->requisitos()->firstOrFail();
        $obsoleta = CatalogoVersion::findOrFail($this->borrador->id); // aún figura como borrador en memoria

        // Otra petición la publica entre la lectura y la escritura.
        $servicio->publicar($this->borrador);
        $this->assertTrue($obsoleta->esBorrador());

        $mensaje = 'Esta versión del catálogo ya fue publicada y es inmutable. Crea un borrador para hacer cambios.';
        $acciones = [
            'publicar' => fn () => $servicio->publicar($obsoleta),
            'guardar' => fn () => $servicio->guardarRequisito($obsoleta, ['texto' => 'Otro requisito']),
            'ordenar' => fn () => $servicio->ordenarRequisitos($obsoleta, [$requisito->id]),
            'eliminar' => fn () => $servicio->eliminarRequisito($obsoleta, $requisito),
        ];
        foreach ($acciones as $nombre => $accion) {
            try {
                $accion();
                $this->fail("{$nombre} no debió aplicarse a una versión ya publicada.");
            } catch (ReglaNegocioException $e) {
                $this->assertSame($mensaje, $e->getMessage(), $nombre);
            }
        }
        $this->assertSame(1, $this->borrador->requisitos()->count());
    }

    public function test_publicar_una_plantilla_simple_no_toca_la_sgr(): void
    {
        $this->crear()->assertCreated();
        $sgr = PlantillaChecklist::sgr()->versionVigente();

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertOk()->assertJsonPath('vigente', true);

        $this->assertSame($this->borrador->id, $this->plantilla->versionVigente()->id);
        $this->assertSame($sgr->id, PlantillaChecklist::sgr()->versionVigente()->id);
    }

    // ------------------------------------------------------------- motor sgr

    public function test_en_el_sgr_se_guardan_los_campos_de_seguimiento_pero_no_el_caracter(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);
        $a1 = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador->id)->where('clave', 'a1')->first();

        $this->comoAdmin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$a1->id}", [
            'clave' => $a1->clave, 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
            'texto' => $a1->texto, 'condicion' => $a1->condicion, 'categoria' => 'tecnico', 'caracter' => 'opcional',
            'cargo_revision' => 'Oficina jurídica',
        ])->assertOk();

        $editado = DB::table('requisitos_catalogo')->where('id', $a1->id)->first();
        $this->assertSame('tecnico', $editado->categoria);
        $this->assertSame('Oficina jurídica', $editado->cargo_revision);
        $this->assertSame('obligatorio', $editado->caracter);
    }

    // ------------------------------------------------------------ revisión

    public function test_una_clave_nueva_no_reutiliza_las_de_versiones_anteriores(): void
    {
        foreach (['A', 'B', 'C'] as $t) {
            $this->crear(['texto' => $t])->assertCreated();
        }
        $this->comoAdmin()->postJson($this->url('/publicar'))->assertOk();
        $v2 = app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
        $r3 = $v2->requisitos()->where('clave', 'r3')->first();
        $this->comoAdmin()->deleteJson("/GestPro/catalogo-requisitos/versiones/{$v2->id}/requisitos/{$r3->id}")->assertOk();

        $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$v2->id}/requisitos", ['texto' => 'Nuevo'])
            ->assertCreated()->assertJsonPath('clave', 'r4');
    }

    public function test_editar_conserva_lo_que_no_se_envia_y_vacia_lo_que_llega_vacio(): void
    {
        $id = $this->crear(['nota' => 'Una nota', 'subtitulo' => 'Sub', 'codigo' => 'C-1'])->json('id');
        $this->comoAdmin()->putJson($this->url("/requisitos/{$id}"), ['texto' => 'X', 'activo' => false])->assertOk();

        $this->comoAdmin()->putJson($this->url("/requisitos/{$id}"), ['texto' => 'Y'])->assertOk();
        $fila = $this->borrador->requisitos()->find($id);
        $this->assertSame('Una nota', $fila->nota);
        $this->assertFalse((bool) $fila->activo);
        $this->assertSame(1, $fila->orden);

        $this->comoAdmin()->putJson($this->url("/requisitos/{$id}"), ['texto' => 'Y', 'subtitulo' => null, 'codigo' => ''])->assertOk();
        $fila = $this->borrador->requisitos()->find($id);
        $this->assertNull($fila->subtitulo);
        $this->assertNull($fila->codigo);
    }

    public function test_el_codigo_comun_se_normaliza_y_se_valida(): void
    {
        $this->crear(['codigo_comun' => '  CDP '])->assertCreated();
        $this->assertSame('cdp', $this->borrador->requisitos()->first()->codigo_comun);

        $this->crear(['texto' => 'Otro', 'codigo_comun' => 'Cdp'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Ya existe un requisito con el código común "cdp" en esta versión.');

        $this->crear(['texto' => 'Otro', 'codigo_comun' => 'con espacios'])->assertStatus(422)
            ->assertJsonValidationErrors(['codigo_comun']);
    }

    public function test_una_copia_con_motor_sgr_no_repite_el_codigo_comun(): void
    {
        $copia = PlantillaChecklist::create(['nombre' => 'Copia SGR', 'motor' => 'sgr', 'todas_fuentes' => true, 'todos_sectores' => true]);
        $borrador = app(CatalogoService::class)->crearBorrador(null, $copia);
        $url = "/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos";
        $datos = ['seccion' => 'A', 'fuente' => 'a12', 'codigo' => 'X', 'texto' => 'Uno', 'condicion' => 'siempre', 'codigo_comun' => 'dup'];

        $this->comoAdmin()->postJson($url, [...$datos, 'clave' => 'x1'])->assertCreated();
        $this->comoAdmin()->postJson($url, [...$datos, 'clave' => 'x2', 'texto' => 'Dos'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Ya existe un requisito con el código común "dup" en esta versión.');
    }

    public function test_los_mensajes_de_validacion_estan_en_espanol(): void
    {
        $this->crear(['cargo_cumplimiento' => str_repeat('a', 151)])->assertStatus(422)
            ->assertJsonPath('errors.cargo_cumplimiento.0', 'En el cargo de cumplimiento caben hasta 150 caracteres.');
    }

    public function test_el_orden_rechaza_ids_repetidos(): void
    {
        [$a, $b] = collect(['A', 'B', 'C'])->map(fn ($t) => $this->crear(['texto' => $t])->json('id'))->all();

        $this->comoAdmin()->putJson($this->url('/requisitos-orden'), ['ids' => [$a, $a, $b]])->assertStatus(422);
    }

    public function test_no_se_publica_sin_sectores(): void
    {
        $this->crear()->assertCreated();
        $this->plantilla->update(['todos_sectores' => false]);

        $this->comoAdmin()->postJson($this->url('/publicar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Asocia la plantilla a sus fuentes y sectores antes de publicar.');
    }

    public function test_editar_en_el_sgr_conserva_activo_y_nota_si_no_se_envian(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);
        $a1 = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador->id)->where('clave', 'a1')->first();
        DB::table('requisitos_catalogo')->where('id', $a1->id)->update(['activo' => false, 'nota' => 'Nota previa']);

        $this->comoAdmin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$a1->id}", [
            'clave' => $a1->clave, 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
            'texto' => 'Texto nuevo', 'condicion' => $a1->condicion,
        ])->assertOk();

        $editado = DB::table('requisitos_catalogo')->where('id', $a1->id)->first();
        $this->assertEquals(0, $editado->activo);
        $this->assertSame('Nota previa', $editado->nota);
    }

    public function test_las_reglas_genericas_string_y_min_salen_en_espanol(): void
    {
        $this->crear(['descripcion' => ['no es texto']])->assertStatus(422)
            ->assertJsonPath('errors.descripcion.0', 'Revisa la descripción: debe ser un texto.');
        $this->crear(['orden' => -1])->assertStatus(422)
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el mínimo es 0.');
    }

    public function test_el_orden_de_un_requisito_tiene_tope_para_no_invadir_la_plantilla_siguiente(): void
    {
        $this->crear(['orden' => 10_000_000])->assertStatus(422)
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el máximo es 9999999.');
        $this->crear(['orden' => 9_999_999])->assertCreated();
    }

    public function test_ordenar_con_un_id_que_no_es_entero_nombra_el_campo_en_espanol(): void
    {
        $errores = $this->comoAdmin()->putJson($this->url('/requisitos-orden'), ['ids' => ['x']])->assertStatus(422)->json('errors');

        $this->assertSame('Revisa cada requisito: debe ser un número entero.', $errores['ids.0'][0]);
    }

    public function test_editar_en_el_sgr_conserva_el_subtitulo_si_no_se_envia(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);
        $a1 = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador->id)->where('clave', 'a1')->first();
        DB::table('requisitos_catalogo')->where('id', $a1->id)->update(['subtitulo' => 'Subtítulo previo']);

        $this->comoAdmin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$a1->id}", [
            'clave' => $a1->clave, 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
            'texto' => 'Texto nuevo', 'condicion' => $a1->condicion,
        ])->assertOk();

        $this->assertSame('Subtítulo previo', DB::table('requisitos_catalogo')->where('id', $a1->id)->value('subtitulo'));
    }
}
