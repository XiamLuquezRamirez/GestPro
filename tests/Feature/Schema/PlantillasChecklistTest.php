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

    private function migracionVersiones(): object
    {
        return require database_path('migrations/2026_11_06_100004_add_plantilla_to_catalogo_versiones_table.php');
    }

    private function version(int $plantilla, int $numero = 1): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla, 'numero' => $numero, 'estado' => 'publicada', 'vigente' => true,
        ]);
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
        PlantillaChecklist::create(['clave' => 'otra', 'nombre' => 'Otra con clave']);

        try {
            $this->migracionPlantillas()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plantillas distintas de SGR', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('plantillas_checklist'));
    }

    public function test_una_plantilla_con_versiones_no_se_puede_borrar(): void
    {
        $plantilla = $this->otraPlantilla();
        $this->version($plantilla);

        $this->expectException(QueryException::class);
        DB::table('plantillas_checklist')->where('id', $plantilla)->delete();
    }

    // -------------------------------------------------------------- versiones

    public function test_el_numero_de_version_se_repite_entre_plantillas_pero_no_dentro_de_una(): void
    {
        $this->version(PlantillaChecklist::sgr()->id);
        $otra = $this->otraPlantilla();
        $this->version($otra);

        $this->expectException(QueryException::class);
        $this->version($otra);
    }

    public function test_toda_version_exige_plantilla(): void
    {
        $this->expectException(QueryException::class);
        DB::table('catalogo_versiones')->insert(['numero' => 9, 'estado' => 'borrador']);
    }

    /*
     * Estas pruebas no crean requisitos ni expedientes a propósito. En SQLite, cambiar una
     * columna reconstruye la tabla y, dentro de la transacción de RefreshDatabase, eso borra
     * en cascada las filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
     */
    public function test_migrar_asigna_la_plantilla_sgr_a_las_versiones_existentes(): void
    {
        $migracion = $this->migracionVersiones();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('catalogo_versiones', 'plantilla_id'));
        $this->assertTrue(Schema::hasIndex('catalogo_versiones', 'catalogo_versiones_numero_unique'));
        $id = DB::table('catalogo_versiones')->insertGetId(['numero' => 1, 'estado' => 'publicada', 'vigente' => true]);

        $migracion->up();

        $this->assertEquals(PlantillaChecklist::sgr()->id, DB::table('catalogo_versiones')->where('id', $id)->value('plantilla_id'));
        $this->assertFalse(Schema::hasIndex('catalogo_versiones', 'catalogo_versiones_numero_unique'));
    }

    public function test_la_migracion_de_versiones_se_puede_repetir(): void
    {
        $id = $this->version(PlantillaChecklist::sgr()->id);

        $this->migracionVersiones()->up();

        $this->assertEquals(PlantillaChecklist::sgr()->id, DB::table('catalogo_versiones')->where('id', $id)->value('plantilla_id'));
    }

    public function test_el_rollback_se_detiene_si_hay_versiones_de_otras_plantillas(): void
    {
        $this->version($this->otraPlantilla());

        try {
            $this->migracionVersiones()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('plantillas distintas de SGR', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('catalogo_versiones', 'plantilla_id'));
    }
}
