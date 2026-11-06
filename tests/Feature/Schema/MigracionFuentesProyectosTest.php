<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigracionFuentesProyectosTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_06_100002_add_financiacion_to_proyectos_table.php');
    }

    /** Crea un proyecto con el esquema anterior, convierte y devuelve el resultado. */
    private function convertir(?string $texto): object
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = DB::table('proyectos')->insertGetId(['nombre' => 'Prueba', 'fuente_financiacion' => $texto]);
        $migracion->up();

        return DB::table('proyectos')->where('id', $id)->first();
    }

    private function clavesDe(object $proyecto): array
    {
        return DB::table('proyecto_fuentes')
            ->join('fuentes_financiacion', 'fuentes_financiacion.id', 'proyecto_fuentes.fuente_id')
            ->where('proyecto_id', $proyecto->id)
            ->orderBy('orden')
            ->pluck('clave')
            ->all();
    }

    public function test_el_texto_mas_comun_se_convierte_en_dos_fuentes(): void
    {
        $proyecto = $this->convertir('Recursos propios y regalías');

        $this->assertSame([FuenteFinanciacion::SGR, FuenteFinanciacion::RECURSOS_PROPIOS], $this->clavesDe($proyecto));
        $this->assertSame('Sistema General de Regalías (SGR), Recursos propios', $proyecto->fuente_financiacion);
        $this->assertSame('Recursos propios y regalías', $proyecto->fuente_financiacion_original);
        $this->assertEquals(0, $proyecto->financiacion_por_definir);
    }

    public function test_reconoce_regalias_sin_importar_tildes_ni_mayusculas(): void
    {
        foreach (['REGALÍAS', 'regalias directas', 'Sistema General de Regalias', 'Recursos SGR'] as $texto) {
            $this->assertSame([FuenteFinanciacion::SGR], $this->clavesDe($this->convertir($texto)), $texto);
        }
    }

    public function test_reconoce_recursos_propios(): void
    {
        $this->assertSame([FuenteFinanciacion::RECURSOS_PROPIOS], $this->clavesDe($this->convertir('RECURSOS PROPIOS del municipio')));
    }

    public function test_un_texto_sin_reconocer_queda_por_definir_y_conserva_lo_escrito(): void
    {
        $proyecto = $this->convertir('Convenio con la gobernación');

        $this->assertSame([], $this->clavesDe($proyecto));
        $this->assertEquals(1, $proyecto->financiacion_por_definir);
        $this->assertSame('Convenio con la gobernación', $proyecto->fuente_financiacion);
        $this->assertSame('Convenio con la gobernación', $proyecto->fuente_financiacion_original);
    }

    public function test_un_proyecto_sin_texto_queda_por_definir(): void
    {
        $proyecto = $this->convertir(null);

        $this->assertSame([], $this->clavesDe($proyecto));
        $this->assertEquals(1, $proyecto->financiacion_por_definir);
        $this->assertNull($proyecto->fuente_financiacion_original);
    }

    public function test_el_rollback_restaura_el_texto_y_quita_las_columnas(): void
    {
        $proyecto = $this->convertir('Recursos propios y regalías');

        $this->migracion()->down();

        $this->assertSame('Recursos propios y regalías', DB::table('proyectos')->where('id', $proyecto->id)->value('fuente_financiacion'));
        $this->assertFalse(Schema::hasColumn('proyectos', 'financiacion_por_definir'));
        $this->assertFalse(Schema::hasColumn('proyectos', 'fuente_financiacion_original'));
        $this->assertDatabaseCount('proyecto_fuentes', 0);
    }

    public function test_sgr_solo_se_reconoce_como_palabra_completa(): void
    {
        foreach (['Desgravación tributaria', 'Transgresión'] as $texto) {
            $proyecto = $this->convertir($texto);

            $this->assertSame([], $this->clavesDe($proyecto), $texto);
            $this->assertEquals(1, $proyecto->financiacion_por_definir, $texto);
        }

        $this->assertSame([FuenteFinanciacion::SGR], $this->clavesDe($this->convertir('Recursos SGR')));
    }

    public function test_si_falla_a_medias_no_cambia_nada_y_se_puede_reintentar(): void
    {
        $migracion = $this->migracion();
        $migracion->down();

        $a = DB::table('proyectos')->insertGetId(['nombre' => 'A', 'fuente_financiacion' => 'Recursos propios y regalías']);
        $b = DB::table('proyectos')->insertGetId(['nombre' => 'B', 'fuente_financiacion' => 'Regalías']);
        $sgr = DB::table('fuentes_financiacion')->where('clave', FuenteFinanciacion::SGR)->value('id');
        DB::table('proyecto_fuentes')->insert(['proyecto_id' => $b, 'fuente_id' => $sgr]);

        try {
            $migracion->up();
            $this->fail('Se esperaba una QueryException por la llave primaria repetida.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame('Recursos propios y regalías', DB::table('proyectos')->where('id', $a)->value('fuente_financiacion'));
        $this->assertSame(0, DB::table('proyecto_fuentes')->where('proyecto_id', $a)->count());
        $this->assertNull(DB::table('proyectos')->where('id', $a)->value('fuente_financiacion_original'));

        DB::table('proyecto_fuentes')->where('proyecto_id', $b)->delete();
        $migracion->up();

        $proyectoA = DB::table('proyectos')->where('id', $a)->first();
        $proyectoB = DB::table('proyectos')->where('id', $b)->first();
        $this->assertSame([FuenteFinanciacion::SGR, FuenteFinanciacion::RECURSOS_PROPIOS], $this->clavesDe($proyectoA));
        $this->assertSame([FuenteFinanciacion::SGR], $this->clavesDe($proyectoB));
        $this->assertSame('Recursos propios y regalías', $proyectoA->fuente_financiacion_original);
        $this->assertSame('Regalías', $proyectoB->fuente_financiacion_original);
    }

    public function test_el_rollback_conserva_lo_editado_despues_de_migrar(): void
    {
        $editado = $this->convertir('Recursos propios y regalías');
        DB::table('proyectos')->where('id', $editado->id)->update(['fuente_financiacion' => 'Recursos propios']);

        $migracion = $this->migracion();
        $intacto = DB::table('proyectos')->insertGetId(['nombre' => 'Intacto', 'fuente_financiacion' => 'Sistema General de Regalías (SGR), Recursos propios', 'fuente_financiacion_original' => 'Recursos propios y regalías']);
        $nuevo = DB::table('proyectos')->insertGetId(['nombre' => 'Nuevo', 'fuente_financiacion' => 'Crédito público']);

        $migracion->down();

        $texto = fn (int $id) => DB::table('proyectos')->where('id', $id)->value('fuente_financiacion');
        $this->assertSame('Recursos propios', $texto($editado->id));
        $this->assertSame('Crédito público', $texto($nuevo));
        $this->assertSame('Recursos propios y regalías', $texto($intacto));
    }
}
