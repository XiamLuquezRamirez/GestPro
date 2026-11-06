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
}
