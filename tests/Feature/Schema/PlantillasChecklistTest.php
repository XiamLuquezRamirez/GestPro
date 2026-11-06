<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function otraPlantilla(): int
    {
        return PlantillaChecklist::create(['nombre' => 'Otra', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id;
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
}
