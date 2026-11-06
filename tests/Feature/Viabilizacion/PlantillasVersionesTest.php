<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
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
}
