<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PlantillasChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function otraPlantilla(): int
    {
        return PlantillaChecklist::create(['nombre' => 'Otra', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id;
    }

    private function migracionPlantillas(): object
    {
        return require database_path('migrations/2026_11_06_100003_create_plantillas_checklist_tables.php');
    }

    // ------------------------------------------------------------- plantillas

    public function test_la_plantilla_sgr_existe_al_migrar_y_solo_se_asocia_a_la_fuente_sgr(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->assertSame(PlantillaChecklist::MOTOR_SGR, $sgr->motor);
        $this->assertTrue($sgr->activo);
        $this->assertTrue($sgr->todos_sectores);
        $this->assertFalse($sgr->todas_fuentes);
        $this->assertSame([FuenteFinanciacion::SGR], $sgr->fuentes()->pluck('clave')->all());
        $this->assertSame(0, $sgr->sectores()->count());
    }

    public function test_una_fuente_asociada_a_una_plantilla_no_se_puede_borrar(): void
    {
        $this->expectException(QueryException::class);
        DB::table('fuentes_financiacion')->where('clave', FuenteFinanciacion::SGR)->delete();
    }

    public function test_un_sector_asociado_a_una_plantilla_no_se_puede_borrar(): void
    {
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua', 'activo' => true]);
        DB::table('plantilla_sectores')->insert(['plantilla_id' => $this->otraPlantilla(), 'sector_id' => $sector]);

        $this->expectException(QueryException::class);
        DB::table('sectores')->where('id', $sector)->delete();
    }

    public function test_borrar_una_plantilla_borra_sus_asociaciones(): void
    {
        $plantilla = $this->otraPlantilla();
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua', 'activo' => true]);
        $fuente = DB::table('fuentes_financiacion')->where('clave', FuenteFinanciacion::SGR)->value('id');
        DB::table('plantilla_sectores')->insert(['plantilla_id' => $plantilla, 'sector_id' => $sector]);
        DB::table('plantilla_fuentes')->insert(['plantilla_id' => $plantilla, 'fuente_id' => $fuente]);

        DB::table('plantillas_checklist')->where('id', $plantilla)->delete();

        $this->assertSame(0, DB::table('plantilla_sectores')->where('plantilla_id', $plantilla)->count());
        $this->assertSame(0, DB::table('plantilla_fuentes')->where('plantilla_id', $plantilla)->count());
    }

    public function test_la_clave_de_una_plantilla_no_se_repite(): void
    {
        $this->expectException(QueryException::class);
        PlantillaChecklist::create(['clave' => PlantillaChecklist::CLAVE_SGR, 'nombre' => 'Otra']);
    }

    public function test_reintentar_la_migracion_rehace_lo_que_quedo_a_medias(): void
    {
        $migracion = $this->migracionPlantillas();
        $migracion->down();
        $this->assertFalse(Schema::hasTable('plantillas_checklist'));

        // Lo que dejaría un fallo entre el CREATE y el índice único.
        Schema::create('plantillas_checklist', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->nullable();
        });

        $migracion->up();

        $this->assertSame(PlantillaChecklist::MOTOR_SGR, PlantillaChecklist::sgr()->motor);
        $this->expectException(QueryException::class);
        DB::table('plantillas_checklist')->insert(['clave' => 'sgr', 'nombre' => 'Repetida']);
    }

    public function test_el_rollback_se_detiene_si_hay_otras_plantillas(): void
    {
        $this->otraPlantilla();

        try {
            $this->migracionPlantillas()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plantillas distintas de SGR', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('plantillas_checklist'));
    }
}
