<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SeguimientoRequisitoTest extends TestCase
{
    use RefreshDatabase;

    private function migracionCatalogo(): object
    {
        return require database_path('migrations/2026_11_06_100006_add_seguimiento_to_requisitos_catalogo_table.php');
    }

    private function versionSgr(): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => PlantillaChecklist::sgr()->id, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }

    private function requisito(int $version, array $cambios = []): int
    {
        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'a1', 'seccion' => 'A', 'fuente' => 'a12',
            'codigo' => '1', 'texto' => 'Texto', ...$cambios,
        ]);
    }

    // ---------------------------------------------------------------- catálogo

    /*
     * Estas pruebas no crean filas de checklist a propósito. En SQLite, cambiar una columna
     * reconstruye la tabla y, dentro de la transacción de RefreshDatabase, eso afectaría a las
     * filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
     */
    public function test_migrar_da_los_valores_por_defecto_y_el_codigo_comun_a_los_requisitos(): void
    {
        $migracion = $this->migracionCatalogo();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('requisitos_catalogo', 'codigo_comun'));
        $id = $this->requisito($this->versionSgr());

        $migracion->up();

        $fila = RequisitoCatalogo::find($id);
        $this->assertSame('a1', $fila->codigo_comun);
        $this->assertSame(RequisitoCatalogo::OBLIGATORIO, $fila->caracter);
        $this->assertFalse($fila->requiere_aprobacion_no_aplica);
        $this->assertNull($fila->categoria);
    }

    public function test_la_migracion_del_catalogo_se_puede_repetir_sin_pisar_un_codigo_comun(): void
    {
        $id = $this->requisito($this->versionSgr(), ['codigo_comun' => 'propio']);

        $this->migracionCatalogo()->up();

        $this->assertSame('propio', DB::table('requisitos_catalogo')->where('id', $id)->value('codigo_comun'));
    }

    public function test_un_requisito_puede_no_tener_seccion_fuente_ni_codigo(): void
    {
        $id = $this->requisito($this->versionSgr(), ['seccion' => null, 'fuente' => null, 'codigo' => null]);

        $this->assertNull(DB::table('requisitos_catalogo')->where('id', $id)->value('seccion'));
    }

    public function test_el_rollback_se_detiene_si_hay_requisitos_sin_seccion(): void
    {
        $this->requisito($this->versionSgr(), ['seccion' => null]);

        try {
            $this->migracionCatalogo()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sin sección, fuente o código', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('requisitos_catalogo', 'codigo_comun'));
    }

    public function test_el_rollback_se_detiene_si_se_perderian_datos_de_seguimiento(): void
    {
        foreach ([['categoria' => 'Técnico'], ['caracter' => 'opcional'], ['requiere_aprobacion_no_aplica' => true]] as $indice => $datos) {
            DB::table('requisitos_catalogo')->delete();
            $version = DB::table('catalogo_versiones')->value('id') ?? $this->versionSgr();
            $this->requisito($version, ['clave' => "r{$indice}", ...$datos]);

            try {
                $this->migracionCatalogo()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($datos));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('se perderían', $e->getMessage());
            }
        }
    }
}
