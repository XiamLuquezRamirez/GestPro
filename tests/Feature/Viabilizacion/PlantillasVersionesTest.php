<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasVersionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['rol' => Rol::Administrador]);
    }

    private function comoAdmin(?User $usuario = null): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario ?? $this->admin()));
    }

    /** Otra plantilla con su versión 1 vigente y sin requisitos. */
    private function otraPlantilla(): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create(['nombre' => 'Otra', 'motor' => PlantillaChecklist::MOTOR_SIMPLE]);
        $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        return $plantilla;
    }

    private function publicarNuevaSgr(): CatalogoVersion
    {
        $servicio = app(CatalogoService::class);

        return $servicio->publicar($servicio->crearBorrador('Ajustes'));
    }

    public function test_cada_requisito_sembrado_usa_su_clave_como_codigo_comun(): void
    {
        $this->assertSame(207, DB::table('requisitos_catalogo')->whereColumn('codigo_comun', 'clave')->count());
    }

    public function test_todos_los_requisitos_sgr_exigen_aprobacion_del_no_aplica(): void
    {
        $this->assertSame(207, DB::table('requisitos_catalogo')->where('requiere_aprobacion_no_aplica', true)->count());
    }

    public function test_un_borrador_copia_los_datos_de_seguimiento(): void
    {
        $datos = [
            'descripcion' => 'Descripción', 'categoria' => 'Técnico', 'tipo_requisito' => 'Documental',
            'caracter' => 'opcional', 'cargo_cumplimiento' => 'Ingeniero', 'cargo_revision' => 'Jurídica',
            'soporte_requerido' => 'Acta firmada', 'referencia_normativa' => 'Art. 1',
            'condicion_descripcion' => 'Si hay predios', 'codigo_comun' => 'comun',
        ];
        DB::table('requisitos_catalogo')->where('clave', 'a1')->update([...$datos, 'requiere_aprobacion_no_aplica' => true]);

        $copia = app(CatalogoService::class)->crearBorrador(null)->requisitos()->where('clave', 'a1')->first();

        foreach ($datos as $columna => $valor) {
            $this->assertSame($valor, $copia->{$columna}, $columna);
        }
        $this->assertTrue($copia->requiere_aprobacion_no_aplica);
    }

    public function test_un_requisito_nuevo_del_sgr_usa_su_clave_como_codigo_comun(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);

        $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
            'clave' => 'nuevo', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '9.9', 'texto' => 'Nuevo', 'condicion' => 'siempre',
        ])->assertCreated();

        $nuevo = $borrador->requisitos()->where('clave', 'nuevo')->first();
        $this->assertSame('nuevo', $nuevo->codigo_comun);
        $this->assertTrue($nuevo->requiere_aprobacion_no_aplica);
    }

    public function test_editar_un_requisito_sgr_aplica_las_reglas_del_sgr(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador(null);
        $consulta = fn () => DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador->id)->where('clave', 'a1');
        $consulta()->update(['codigo_comun' => 'comun', 'requiere_aprobacion_no_aplica' => false]);
        $a1 = $consulta()->first();

        $this->comoAdmin()->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$a1->id}", [
            'clave' => $a1->clave, 'seccion' => $a1->seccion, 'fuente' => $a1->fuente,
            'sector_id' => $a1->sector_id, 'tipo_id' => $a1->tipo_id, 'subtitulo' => $a1->subtitulo,
            'codigo' => $a1->codigo, 'texto' => 'Texto editado', 'nota' => $a1->nota, 'condicion' => $a1->condicion,
        ])->assertOk();

        $editado = $consulta()->first();
        $this->assertSame('Texto editado', $editado->texto);
        $this->assertSame('a1', $editado->codigo_comun);
        $this->assertEquals(1, $editado->requiere_aprobacion_no_aplica);
    }

    public function test_la_version_sembrada_pertenece_a_la_plantilla_sgr(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->assertSame(1, $sgr->versionVigente()->numero);
        $this->assertSame(207, $sgr->versionVigente()->requisitos()->count());
    }

    public function test_el_seeder_no_confunde_la_version_1_de_otra_plantilla(): void
    {
        DB::table('requisitos_catalogo')->delete();
        DB::table('catalogo_versiones')->delete();
        $otra = $this->otraPlantilla();

        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertSame(0, $otra->versionVigente()->requisitos()->count());
        $this->assertSame(207, PlantillaChecklist::sgr()->versionVigente()->requisitos()->count());
    }

    public function test_cada_plantilla_numera_sus_versiones_y_tiene_su_propio_borrador(): void
    {
        $otra = $this->otraPlantilla();
        $servicio = app(CatalogoService::class);

        $deOtra = $servicio->crearBorrador(null, $otra);
        $deSgr = $servicio->crearBorrador(null);

        $this->assertSame($otra->id, $deOtra->plantilla_id);
        $this->assertSame(2, $deOtra->numero);
        $this->assertSame(PlantillaChecklist::sgr()->id, $deSgr->plantilla_id);
        $this->assertSame(2, $deSgr->numero);
        $this->assertSame(207, $deSgr->requisitos()->count());
    }

    public function test_crear_una_version_por_la_api_la_asocia_a_sgr(): void
    {
        $this->comoAdmin()->postJson('/GestPro/catalogo-requisitos/versiones', ['descripcion' => 'Ajustes'])
            ->assertCreated()
            ->assertJsonPath('plantilla_id', PlantillaChecklist::sgr()->id)
            ->assertJsonPath('numero', 2);
    }

    public function test_publicar_no_afecta_la_version_vigente_de_otra_plantilla(): void
    {
        $otra = $this->otraPlantilla();
        $anterior = PlantillaChecklist::sgr()->versionVigente();

        $nueva = $this->publicarNuevaSgr();

        $this->assertSame($nueva->id, PlantillaChecklist::sgr()->versionVigente()->id);
        $this->assertFalse($anterior->fresh()->vigente);
        $this->assertSame(1, $otra->versionVigente()->numero);
    }

    public function test_el_catalogo_vigente_y_los_expedientes_nuevos_usan_la_version_sgr(): void
    {
        // La otra plantilla queda con un id menor que la nueva vigente de SGR: sin filtrar
        // por plantilla, tomar la primera vigente daría la versión equivocada.
        $this->otraPlantilla();
        $sgr = $this->publicarNuevaSgr();

        $this->comoAdmin()->getJson('/GestPro/catalogo-requisitos/vigente')
            ->assertOk()->assertJsonPath('version.id', $sgr->id);

        $expediente = app(ExpedienteService::class)
            ->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $this->assertSame($sgr->id, $expediente->catalogo_version_id);
    }

    public function test_la_lista_de_versiones_solo_muestra_las_de_sgr(): void
    {
        $this->otraPlantilla();

        $versiones = $this->comoAdmin()->getJson('/GestPro/catalogo-requisitos/versiones')->assertOk()->json();

        $this->assertCount(1, $versiones);
        $this->assertSame(207, $versiones[0]['requisitos']);
    }

    public function test_publicar_registra_quien_publico(): void
    {
        $admin = $this->admin();
        $borrador = app(CatalogoService::class)->crearBorrador(null);

        $this->comoAdmin($admin)->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $this->assertSame($admin->id, $borrador->fresh()->usuario_publico_id);
    }

    public function test_si_falta_la_plantilla_sgr_responde_con_un_mensaje_de_negocio(): void
    {
        DB::table('plantillas_checklist')->where('clave', 'sgr')->update(['clave' => null]);

        $this->comoAdmin()->getJson('/GestPro/catalogo-requisitos/vigente')
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Falta la plantilla SGR: ejecute las migraciones.');
    }

    public function test_un_expediente_nuevo_registra_su_version_como_principal(): void
    {
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);

        $version = $expediente->versionesPlantilla()->sole();
        $this->assertSame($expediente->catalogo_version_id, $version->id);
        $this->assertEquals(1, $version->pivot->es_principal);

        $servicio->eliminar($expediente);
        $this->assertDatabaseCount('expediente_plantillas', 0);
    }
}
