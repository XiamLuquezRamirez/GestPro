<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\User;
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

    private function migracionFilas(): object
    {
        return require database_path('migrations/2026_11_06_100007_add_seguimiento_to_proyectos_requisitos_table.php');
    }

    private function migracionHistorial(): object
    {
        return require database_path('migrations/2026_11_06_100008_create_checklist_historial_table.php');
    }

    private function fila(array $cambios = []): int
    {
        $version = DB::table('catalogo_versiones')->value('id') ?? $this->versionSgr();
        $requisito = DB::table('requisitos_catalogo')->value('id') ?? $this->requisito($version);
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'catalogo_version_id' => $version, 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);

        return DB::table('proyectos_requisitos')->insertGetId([
            'expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito, ...$cambios,
        ]);
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

    // ------------------------------------------------------------ checklist

    public function test_migrar_deja_las_filas_existentes_en_aplica_y_obligatorias(): void
    {
        $migracion = $this->migracionFilas();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('proyectos_requisitos', 'estado_aplicabilidad'));
        $id = $this->fila(['cumplido' => true]);

        $migracion->up();
        $migracion->up(); // repetirla no falla

        $fila = DB::table('proyectos_requisitos')->where('id', $id)->first();
        $this->assertSame('aplica', $fila->estado_aplicabilidad);
        $this->assertSame('obligatorio', $fila->caracter);
        $this->assertEquals(1, $fila->cumplido);
    }

    public function test_borrar_un_responsable_o_un_usuario_conserva_la_fila(): void
    {
        $responsable = DB::table('responsable')->insertGetId(['nombre' => 'Ana', 'activo' => true]);
        $usuario = User::factory()->create();
        $id = $this->fila(['responsable_id' => $responsable, 'usuario_no_aplica_id' => $usuario->id, 'usuario_aprobo_id' => $usuario->id]);

        DB::table('responsable')->where('id', $responsable)->delete();
        DB::table('users')->where('id', $usuario->id)->delete();

        $fila = DB::table('proyectos_requisitos')->where('id', $id)->first();
        $this->assertNull($fila->responsable_id);
        $this->assertNull($fila->usuario_no_aplica_id);
        $this->assertNull($fila->usuario_aprobo_id);
    }

    public function test_el_rollback_del_checklist_se_detiene_si_se_perderian_datos(): void
    {
        $responsable = DB::table('responsable')->insertGetId(['nombre' => 'Ana', 'activo' => true]);
        foreach ([['estado_aplicabilidad' => 'no_aplica'], ['caracter' => 'opcional'], ['responsable_id' => $responsable]] as $datos) {
            DB::table('proyectos_requisitos')->delete();
            $this->fila($datos);

            try {
                $this->migracionFilas()->down();
                $this->fail('El rollback debía detenerse con ' . json_encode($datos));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('se perderían', $e->getMessage());
            }
        }

        $this->assertTrue(Schema::hasColumn('proyectos_requisitos', 'estado_aplicabilidad'));
    }

    public function test_el_rollback_del_checklist_avanza_sin_datos_de_seguimiento(): void
    {
        $this->fila();

        $this->migracionFilas()->down();

        $this->assertFalse(Schema::hasColumn('proyectos_requisitos', 'responsable_id'));
    }

    // ------------------------------------------------------------- historial

    public function test_el_historial_sobrevive_a_la_eliminacion_de_lo_que_registra(): void
    {
        $id = $this->fila();
        DB::table('checklist_historial')->insert([
            'entidad_tipo' => 'requisito', 'entidad_id' => $id, 'expediente_id' => 1, 'evento' => 'cumplido_marcado',
        ]);

        DB::table('proyectos_expedientes')->delete();

        $this->assertDatabaseCount('checklist_historial', 1);
    }

    public function test_reintentar_la_migracion_del_historial_rehace_lo_que_quedo_a_medias(): void
    {
        $migracion = $this->migracionHistorial();
        $migracion->down();
        Schema::create('checklist_historial', function ($table) {
            $table->id();
        });

        $migracion->up();

        $this->assertTrue(Schema::hasColumns('checklist_historial', ['entidad_tipo', 'antes', 'despues', 'usuario_id']));
    }

    public function test_el_rollback_del_historial_se_detiene_si_tiene_registros(): void
    {
        DB::table('checklist_historial')->insert(['entidad_tipo' => 'expediente', 'entidad_id' => 1, 'evento' => 'expediente_creado']);

        try {
            $this->migracionHistorial()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('se perderían', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('checklist_historial'));
    }

    public function test_migrar_de_nuevo_no_borra_un_historial_con_registros(): void
    {
        DB::table('checklist_historial')->insert(['entidad_tipo' => 'expediente', 'entidad_id' => 1, 'evento' => 'expediente_creado']);

        try {
            $this->migracionHistorial()->up();
            $this->fail('La migración debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('ya tiene registros', $e->getMessage());
        }

        $this->assertDatabaseCount('checklist_historial', 1);
    }
}
