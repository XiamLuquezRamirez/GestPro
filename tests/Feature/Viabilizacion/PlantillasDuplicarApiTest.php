<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasDuplicarApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function duplicar(array $datos)
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])))
            ->postJson("/GestPro/plantillas/{$this->sgr->id}/duplicar", ['nombre' => 'Copia SGR', ...$datos]);
    }

    private function requisito(string $clave): object
    {
        return DB::table('requisitos_catalogo')
            ->where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', $clave)->first();
    }

    private function copiaDe(int $plantilla, string $clave): object
    {
        $version = PlantillaChecklist::find($plantilla)->versiones()->first();

        return DB::table('requisitos_catalogo')->where('catalogo_version_id', $version->id)->where('clave', $clave)->first();
    }

    public function test_una_copia_es_independiente_y_conserva_el_codigo_comun(): void
    {
        $respuesta = $this->duplicar(['requisito_ids' => [$this->requisito('a1')->id, $this->requisito('a2')->id]])
            ->assertCreated()
            ->assertJsonPath('motor', 'sgr')
            ->assertJsonPath('clave', null)
            ->assertJsonPath('borrador.requisitos', 2)
            ->assertJsonPath('vigente', null)
            ->assertJsonPath('fuentes.0.nombre', 'Sistema General de Regalías (SGR)');

        $copia = $this->copiaDe($respuesta->json('id'), 'a1');
        $this->assertSame('a1', $copia->codigo_comun);
        $this->assertSame(207, $this->sgr->versionVigente()->requisitos()->count()); // el origen no cambia
    }

    public function test_sin_elegir_requisitos_copia_todos_los_activos(): void
    {
        $this->duplicar([])->assertCreated()->assertJsonPath('borrador.requisitos', 207);
    }

    public function test_pasar_a_simple_vuelve_condicionadas_las_condiciones_automaticas(): void
    {
        $siempre = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->where('condicion', 'siempre')->value('clave');
        $conCondicion = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->whereNotIn('condicion', ['siempre'])->whereNotNull('condicion')->where('seccion', '!=', 'C')->first();
        $sectorial = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->where('seccion', 'C')->whereNull('condicion')->first();

        $id = $this->duplicar([
            'motor' => 'simple',
            'requisito_ids' => [$this->requisito($siempre)->id, $conCondicion->id, $sectorial->id],
        ])->assertCreated()->assertJsonPath('motor', 'simple')->json('id');

        $simple = $this->copiaDe($id, $siempre);
        $this->assertSame('obligatorio', $simple->caracter);
        $this->assertNull($simple->seccion);
        $this->assertNull($simple->condicion);

        $condicionado = $this->copiaDe($id, $conCondicion->clave);
        $this->assertSame('condicionado', $condicionado->caracter);
        $this->assertSame("Condición original: {$conCondicion->condicion}", $condicionado->condicion_descripcion);

        $porSector = $this->copiaDe($id, $sectorial->clave);
        $this->assertSame('condicionado', $porSector->caracter);
        $this->assertNull($porSector->sector_id);
        $this->assertStringStartsWith('Sector: ', $porSector->condicion_descripcion);
    }

    public function test_el_nombre_no_se_repite_y_los_requisitos_deben_ser_del_origen(): void
    {
        $this->duplicar(['nombre' => 'SGR'])->assertStatus(422);
        $this->duplicar(['requisito_ids' => [999999]])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Algunos requisitos elegidos no son de la versión vigente de la plantilla o están inactivos.');
        $this->duplicar(['requisito_ids' => []])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Elige al menos un requisito para la copia.');
    }

    public function test_una_plantilla_simple_no_se_copia_como_sgr(): void
    {
        $simple = $this->duplicar(['motor' => 'simple', 'requisito_ids' => [$this->requisito('a1')->id]])->json('id');
        $version = PlantillaChecklist::find($simple)->versiones()->first();
        $version->update(['estado' => 'publicada', 'vigente' => true]);

        $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])))
            ->postJson("/GestPro/plantillas/{$simple}/duplicar", ['nombre' => 'Otra', 'motor' => 'sgr'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'Una plantilla simple solo se puede copiar como simple.');
    }

    public function test_en_una_copia_con_motor_sgr_el_codigo_comun_se_conserva_al_editar(): void
    {
        $id = $this->duplicar(['requisito_ids' => [$this->requisito('a1')->id]])->json('id');
        $version = PlantillaChecklist::find($id)->versiones()->first();
        $a1 = $this->copiaDe($id, 'a1');

        $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])))
            ->putJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos/{$a1->id}", [
                'clave' => 'a1_copia', 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
                'texto' => $a1->texto, 'condicion' => $a1->condicion, 'requiere_aprobacion_no_aplica' => false,
            ])->assertOk();

        $editado = DB::table('requisitos_catalogo')->where('id', $a1->id)->first();
        $this->assertSame('a1', $editado->codigo_comun);
        $this->assertEquals(0, $editado->requiere_aprobacion_no_aplica);
    }

    private function admin(): string
    {
        return 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador]));
    }

    public function test_solo_se_copian_requisitos_activos(): void
    {
        $inactivo = $this->requisito('a1');
        DB::table('requisitos_catalogo')->where('id', $inactivo->id)->update(['activo' => false]);

        $this->duplicar([])->assertCreated()->assertJsonPath('borrador.requisitos', 206);
        $this->duplicar(['nombre' => 'Otra copia', 'requisito_ids' => [$inactivo->id]])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Algunos requisitos elegidos no son de la versión vigente de la plantilla o están inactivos.');
    }

    public function test_la_conversion_a_simple_de_un_requisito_sectorial_es_completa(): void
    {
        $sectorial = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->where('seccion', 'C')->whereNull('condicion')->whereNotNull('tipo_id')->first();
        $sector = DB::table('requisitos_sectores')->where('id', $sectorial->sector_id)->value('nombre');
        $tipo = DB::table('requisitos_tipos')->where('id', $sectorial->tipo_id)->value('nombre');
        $normas = ['a12' => 'Acuerdo 12 de 2024', 'ot' => 'Orientaciones Transitorias'];

        $id = $this->duplicar(['motor' => 'simple', 'requisito_ids' => [$sectorial->id]])->assertCreated()->json('id');

        $copia = $this->copiaDe($id, $sectorial->clave);
        $this->assertNull($copia->seccion);
        $this->assertNull($copia->fuente);
        $this->assertNull($copia->sector_id);
        $this->assertNull($copia->tipo_id);
        $this->assertNull($copia->condicion);
        $this->assertSame("Sector: {$sector} — {$tipo}", $copia->condicion_descripcion);
        $this->assertSame($normas[$sectorial->fuente], $copia->referencia_normativa);
        $this->assertEquals(0, $copia->requiere_aprobacion_no_aplica);
    }

    public function test_la_copia_conserva_el_orden_de_la_vigente(): void
    {
        $filas = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->whereIn('clave', ['a1', 'a2', 'a3'])->orderBy('orden')->get();
        $this->assertCount(3, $filas);

        $id = $this->duplicar(['requisito_ids' => $filas->pluck('id')->reverse()->values()->all()])->assertCreated()->json('id');

        $version = PlantillaChecklist::find($id)->versiones()->first();
        $copia = DB::table('requisitos_catalogo')->where('catalogo_version_id', $version->id)->orderBy('orden')->get();
        $this->assertSame($filas->pluck('clave')->all(), $copia->pluck('clave')->all());
        $this->assertSame([1, 2, 3], $copia->pluck('orden')->map(fn ($o) => (int) $o)->all());
    }

    public function test_sin_version_vigente_no_se_puede_copiar(): void
    {
        DB::table('catalogo_versiones')->where('id', $this->sgr->versionVigente()->id)->update(['vigente' => false]);

        $this->duplicar([])->assertStatus(422)
            ->assertJsonPath('mensaje', 'La plantilla no tiene una versión publicada que copiar.');
    }

    public function test_la_copia_solo_hereda_las_fuentes_activas(): void
    {
        $simple = PlantillaChecklist::find(
            $this->duplicar(['motor' => 'simple', 'requisito_ids' => [$this->requisito('a1')->id]])->json('id')
        );
        PlantillaChecklist::find($simple->id)->versiones()->first()->update(['estado' => 'publicada', 'vigente' => true]);
        $otra = FuenteFinanciacion::where('activo', true)->whereNotIn('id', $simple->fuentes()->pluck('fuentes_financiacion.id'))->first();
        $simple->fuentes()->attach($otra->id);
        $this->assertCount(2, $simple->fuentes()->get());
        $otra->update(['activo' => false]);

        $respuesta = $this->withHeader('Authorization', $this->admin())
            ->postJson("/GestPro/plantillas/{$simple->id}/duplicar", ['nombre' => 'Hija'])->assertCreated();

        $this->assertCount(1, $respuesta->json('fuentes'));
        $this->assertCount(1, PlantillaChecklist::find($respuesta->json('id'))->fuentes()->get());
    }

    public function test_la_copia_nace_activa_sin_clave_y_con_su_autor(): void
    {
        $administrador = User::factory()->create(['rol' => Rol::Administrador]);
        $token = 'Bearer ' . auth('api')->login($administrador);

        $id = $this->withHeader('Authorization', $token)
            ->postJson("/GestPro/plantillas/{$this->sgr->id}/duplicar", ['nombre' => 'Mi copia'])
            ->assertCreated()->json('id');

        $copia = PlantillaChecklist::find($id);
        $this->assertTrue((bool) $copia->activo);
        $this->assertNull($copia->clave);
        $this->assertSame($administrador->id, (int) $copia->usuario_creo_id);
    }

    public function test_una_condicion_original_sin_redactar_impide_publicar(): void
    {
        $conCondicion = DB::table('requisitos_catalogo')->where('catalogo_version_id', $this->sgr->versionVigente()->id)
            ->whereNotIn('condicion', ['siempre'])->whereNotNull('condicion')->where('seccion', '!=', 'C')->first();

        $id = $this->duplicar(['motor' => 'simple', 'requisito_ids' => [$conCondicion->id]])->assertCreated()->json('id');
        $version = PlantillaChecklist::find($id)->versiones()->first();
        $copia = $this->copiaDe($id, $conCondicion->clave);

        $this->withHeader('Authorization', $this->admin())
            ->postJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/publicar")
            ->assertStatus(422)
            ->assertJsonFragment(["{$copia->texto}: redacta en palabras cuándo aplica; la condición original es del evaluador SGR."]);

        $this->withHeader('Authorization', $this->admin())
            ->putJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos/{$copia->id}", [
                'texto' => $copia->texto, 'caracter' => 'condicionado',
                'condicion_descripcion' => 'Aplica cuando el proyecto tiene obras civiles.',
            ])->assertOk();

        $this->withHeader('Authorization', $this->admin())
            ->postJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/publicar")
            ->assertOk();
    }
}
