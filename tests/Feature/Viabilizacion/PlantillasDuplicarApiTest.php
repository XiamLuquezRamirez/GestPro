<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
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
            ->assertJsonPath('mensaje', 'Algunos requisitos elegidos no son de la versión vigente de la plantilla.');
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
}
