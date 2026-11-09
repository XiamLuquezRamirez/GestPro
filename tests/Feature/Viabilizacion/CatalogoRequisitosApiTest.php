<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogoRequisitosApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function admin(): static
    {
        return $this->como(Rol::Administrador);
    }

    private function crearBorrador(): int
    {
        return $this->admin()->postJson('/GestPro/catalogo-requisitos/versiones', ['descripcion' => 'Ajustes 2027'])
            ->assertCreated()->json('id');
    }

    /** Id de la versión por su número: en MySQL los ids no se reinician entre tests. */
    private function version(int $numero): int
    {
        return (int) DB::table('catalogo_versiones')->where('numero', $numero)->value('id');
    }

    private function requisitoDe(int $version, string $clave): object
    {
        return DB::table('requisitos_catalogo')->where('catalogo_version_id', $version)->where('clave', $clave)->first();
    }

    private function datosDe(object $r, array $cambios = []): array
    {
        return array_merge([
            'clave' => $r->clave, 'seccion' => $r->seccion, 'fuente' => $r->fuente,
            'sector_id' => $r->sector_id, 'tipo_id' => $r->tipo_id, 'subtitulo' => $r->subtitulo,
            'codigo' => $r->codigo, 'texto' => $r->texto, 'nota' => $r->nota, 'condicion' => $r->condicion,
        ], $cambios);
    }

    // ---------------------------------------------------------------- lectura

    public function test_cualquier_usuario_autenticado_lee_el_catalogo_vigente(): void
    {
        $r = $this->como(Rol::Consulta)->getJson('/GestPro/catalogo-requisitos/vigente');

        $r->assertOk()
            ->assertJsonPath('version.numero', 1)
            ->assertJsonCount(14, 'sectores')
            ->assertJsonCount(9, 'componentes')
            ->assertJsonCount(6, 'tipologias');
        $this->assertSame(59, collect($r->json('sectores'))->sum(fn ($s) => count($s['tipos'])));
    }

    public function test_solo_el_administrador_gestiona_el_catalogo(): void
    {
        $this->como(Rol::Gestor)->getJson('/GestPro/catalogo-requisitos/versiones')->assertForbidden();
        $this->como(Rol::Gestor)->postJson('/GestPro/catalogo-requisitos/versiones')->assertForbidden();
        $this->como(Rol::Consulta)->postJson('/GestPro/catalogo-requisitos/componentes', ['clave' => 'x', 'nombre' => 'X'])->assertForbidden();
        $this->admin()->getJson('/GestPro/catalogo-requisitos/versiones')->assertOk()->assertJsonCount(1);
    }

    // -------------------------------------------------------------- versiones

    public function test_un_borrador_copia_todos_los_requisitos_de_la_vigente(): void
    {
        $borrador = $this->crearBorrador();

        $this->assertSame(207, DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador)->count());
        $this->assertDatabaseHas('catalogo_versiones', ['id' => $borrador, 'numero' => 2, 'estado' => 'borrador', 'vigente' => false]);
        $this->assertSame($this->requisitoDe($this->version(1), 'a12')->condicion, $this->requisitoDe($borrador, 'a12')->condicion);
    }

    public function test_no_puede_haber_dos_borradores_a_la_vez(): void
    {
        $this->crearBorrador();

        $this->admin()->postJson('/GestPro/catalogo-requisitos/versiones')->assertStatus(422);
    }

    public function test_una_version_publicada_es_inmutable(): void
    {
        $v1 = $this->version(1);
        $a1 = $this->requisitoDe($v1, 'a1');

        $this->admin()->putJson("/GestPro/catalogo-requisitos/versiones/{$v1}/requisitos/{$a1->id}", $this->datosDe($a1, ['texto' => 'cambiado']))
            ->assertStatus(422);
        $this->admin()->deleteJson("/GestPro/catalogo-requisitos/versiones/{$v1}/requisitos/{$a1->id}")->assertStatus(422);
        $this->admin()->deleteJson("/GestPro/catalogo-requisitos/versiones/{$v1}")->assertStatus(422);
        $this->assertSame($a1->texto, DB::table('requisitos_catalogo')->where('id', $a1->id)->value('texto'));
    }

    public function test_publicar_un_borrador_lo_vuelve_vigente(): void
    {
        $borrador = $this->crearBorrador();

        $this->admin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/publicar")
            ->assertOk()->assertJsonPath('estado', 'publicada')->assertJsonPath('vigente', true);

        $this->assertSame(1, DB::table('catalogo_versiones')->where('vigente', true)->count());
        $this->como(Rol::Consulta)->getJson('/GestPro/catalogo-requisitos/vigente')->assertJsonPath('version.numero', 2);
    }

    public function test_publicar_rechaza_un_borrador_con_condiciones_invalidas(): void
    {
        $borrador = $this->crearBorrador();
        // Corrupción directa en BD, saltándose la API, para probar la barrera final.
        DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador)->where('clave', 'a5')->update(['condicion' => 'comp=sueloo']);

        $this->admin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/publicar")
            ->assertStatus(422)
            ->assertJsonFragment(['errores' => ['a5: El componente "sueloo" no existe en el catálogo.']]);
        $this->assertDatabaseHas('catalogo_versiones', ['numero' => 1, 'vigente' => true]);
    }

    public function test_se_puede_eliminar_un_borrador(): void
    {
        $borrador = $this->crearBorrador();

        $this->admin()->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador}")->assertOk();
        $this->assertDatabaseMissing('catalogo_versiones', ['id' => $borrador]);
        $this->assertSame(207, DB::table('requisitos_catalogo')->count());
    }

    // ---------------------------------------------------------- requisitos

    public function test_editar_un_requisito_del_borrador_valida_la_condicion(): void
    {
        $borrador = $this->crearBorrador();
        $a5 = $this->requisitoDe($borrador, 'a5');
        $url = "/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos/{$a5->id}";

        $this->admin()->putJson($url, $this->datosDe($a5, ['condicion' => 'fase=9 & comp=suelo']))
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito no es válido.');

        $this->admin()->putJson($url, $this->datosDe($a5, ['condicion' => 'fase=3 & comp=suelo']))->assertOk();
        $this->assertSame('fase=3 & comp=suelo', $this->requisitoDe($borrador, 'a5')->condicion);
    }

    public function test_el_orden_de_un_requisito_sgr_no_sale_de_su_seccion(): void
    {
        $borrador = $this->crearBorrador();
        $c = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador)->where('seccion', 'C')->orderBy('id')->first();
        $url = "/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos/{$c->id}";

        $this->admin()->putJson($url, $this->datosDe($c, ['orden' => 100_000]))
            ->assertStatus(422)
            ->assertJsonPath('errors.orden.0', 'Revisa el orden: el máximo es 99999.');
        $this->admin()->putJson($url, $this->datosDe($c, ['orden' => 99_999]))->assertOk();
        $this->assertSame(99_999, (int) $this->requisitoDe($borrador, $c->clave)->orden);
    }

    public function test_un_requisito_sectorial_exige_sector_y_tipo_coherente(): void
    {
        $borrador = $this->crearBorrador();
        $url = "/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos";
        $salud = DB::table('requisitos_sectores')->where('clave', 'salud')->value('id');
        $tipoJusticia = DB::table('requisitos_tipos')->where('clave', 'casas_justicia')->value('id');
        $base = ['clave' => 'sa99', 'seccion' => 'C', 'fuente' => 'ot', 'codigo' => '7.9·9', 'texto' => 'Requisito nuevo'];

        $this->admin()->postJson($url, $base)->assertStatus(422);
        $this->admin()->postJson($url, [...$base, 'sector_id' => $salud, 'tipo_id' => $tipoJusticia])->assertStatus(422);

        $creado = $this->admin()->postJson($url, [...$base, 'sector_id' => $salud])->assertCreated();
        // En la sección C la condición es opcional: sin ella, aplica en toda fase.
        $this->assertNull($creado->json('condicion'));
        $this->assertNull($creado->json('tipo_id'));
    }

    public function test_un_requisito_sectorial_admite_una_condicion_valida(): void
    {
        $borrador = $this->crearBorrador();
        $url = "/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos";
        $salud = DB::table('requisitos_sectores')->where('clave', 'salud')->value('id');
        $base = ['seccion' => 'C', 'fuente' => 'ot', 'codigo' => '7.9·9', 'texto' => 'Requisito nuevo', 'sector_id' => $salud];

        $this->admin()->postJson($url, [...$base, 'clave' => 'sa98', 'condicion' => 'fase=9'])->assertStatus(422);

        $creado = $this->admin()->postJson($url, [...$base, 'clave' => 'sa99', 'condicion' => 'fase=3'])->assertCreated();
        $this->assertSame('fase=3', $creado->json('condicion'));

        $vacia = $this->admin()->postJson($url, [...$base, 'clave' => 'sa97', 'condicion' => '  '])->assertCreated();
        $this->assertNull($vacia->json('condicion'));
    }

    public function test_publicar_valida_las_condiciones_de_la_seccion_c(): void
    {
        $borrador = $this->crearBorrador();
        DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador)->where('clave', 'j1')->update(['condicion' => 'fase=9']);

        $this->admin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/publicar")
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'La versión tiene condiciones inválidas y no puede publicarse.');
    }

    public function test_publicar_acepta_requisitos_sectoriales_sin_condicion(): void
    {
        // La mayoría de la sección C no lleva condición: null no es un error ahí.
        $borrador = $this->crearBorrador();

        $this->admin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/publicar")->assertOk();
    }

    public function test_la_clave_de_un_requisito_no_se_repite_en_la_version(): void
    {
        $borrador = $this->crearBorrador();

        $this->admin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos", [
            'clave' => 'a1', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => 'x', 'texto' => 'x', 'condicion' => 'siempre',
        ])->assertStatus(422);
    }

    public function test_validar_condicion_sin_guardar(): void
    {
        $this->admin()->postJson('/GestPro/catalogo-requisitos/validar-condicion', ['condicion' => 'fase=3 & (comp=suelo|comp=infraestructura)'])
            ->assertOk()->assertJsonPath('valida', true);
        $this->admin()->postJson('/GestPro/catalogo-requisitos/validar-condicion', ['condicion' => 'fase=1'])
            ->assertOk()->assertJsonPath('valida', false);
    }

    // ------------------------------------------------- snapshot de expedientes

    public function test_publicar_una_version_nueva_no_altera_un_expediente_existente(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Vía terciaria']);

        $antes = $this->admin()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $textoOriginal = collect($antes->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto'];

        // v2: se reescribe a1 y a8 deja de aplicar en fase 3.
        $borrador = $this->crearBorrador();
        $a1 = $this->requisitoDe($borrador, 'a1');
        $a8 = $this->requisitoDe($borrador, 'a8');
        $this->admin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos/{$a1->id}", $this->datosDe($a1, ['texto' => 'Texto v2']))->assertOk();
        $this->admin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/requisitos/{$a8->id}", $this->datosDe($a8, ['condicion' => 'fase=1|2']))->assertOk();
        $this->admin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador}/publicar")->assertOk();

        // El expediente viejo sigue en v1, con su texto y sus 8 requisitos, aun recalculando.
        $viejo = $this->admin()->putJson("/GestPro/expedientes/{$antes->json('id')}/configuracion", ['fase_sgr' => 3])->assertOk();
        $viejo->assertJsonPath('plantillas.0.version.numero', 1)->assertJsonPath('progreso.total', 8);
        $this->assertSame($textoOriginal, collect($viejo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // Uno nuevo usa v2.
        $otroProyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Vía secundaria']);
        $nuevo = $this->admin()->postJson("/GestPro/proyectos/{$otroProyecto->id}/expedientes", ['fase_sgr' => 3])->assertCreated();
        $nuevo->assertJsonPath('plantillas.0.version.numero', 2)->assertJsonPath('progreso.total', 7);
        $this->assertSame('Texto v2', collect($nuevo->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);
    }

    // ---------------------------------------- sectores, tipos, componentes...

    public function test_crear_y_editar_un_componente_sin_cambiar_su_clave(): void
    {
        $id = $this->admin()->postJson('/GestPro/catalogo-requisitos/componentes', ['clave' => 'interventoria', 'nombre' => 'Interventoría'])
            ->assertCreated()->json('id');

        $this->admin()->postJson('/GestPro/catalogo-requisitos/componentes', ['clave' => 'interventoria', 'nombre' => 'Otra'])->assertStatus(422);
        $this->admin()->postJson('/GestPro/catalogo-requisitos/componentes', ['clave' => 'Con Espacios', 'nombre' => 'x'])->assertStatus(422);

        $this->admin()->putJson("/GestPro/catalogo-requisitos/componentes/{$id}", ['clave' => 'otra_clave', 'nombre' => 'Interventoría externa'])->assertOk();
        $this->assertDatabaseHas('requisitos_componentes', ['id' => $id, 'clave' => 'interventoria', 'nombre' => 'Interventoría externa']);
    }

    public function test_un_componente_desactivado_no_se_ofrece_ni_se_puede_elegir(): void
    {
        $predios = DB::table('requisitos_componentes')->where('clave', 'predios')->value('id');
        $this->admin()->putJson("/GestPro/catalogo-requisitos/componentes/{$predios}", ['activo' => false])->assertOk();

        $vigente = $this->como(Rol::Consulta)->getJson('/GestPro/catalogo-requisitos/vigente');
        $this->assertNotContains('predios', collect($vigente->json('componentes'))->pluck('clave'));

        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Lote']);
        $this->admin()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", ['fase_sgr' => 3, 'componente_ids' => [$predios]])
            ->assertStatus(422);
    }

    public function test_un_tipo_nuevo_pertenece_a_un_sector(): void
    {
        $salud = DB::table('requisitos_sectores')->where('clave', 'salud')->value('id');

        $this->admin()->postJson('/GestPro/catalogo-requisitos/tipos', ['clave' => 'telemedicina', 'nombre' => 'Telemedicina'])
            ->assertStatus(422)->assertJsonValidationErrors('sector_id');
        $this->admin()->postJson('/GestPro/catalogo-requisitos/tipos', ['clave' => 'telemedicina', 'nombre' => 'Telemedicina', 'sector_id' => $salud])
            ->assertCreated()->assertJsonPath('sector_id', $salud);
    }
}
