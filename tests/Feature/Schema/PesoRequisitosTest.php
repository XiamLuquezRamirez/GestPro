<?php

namespace Tests\Feature\Schema;

use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 21: peso de cada requisito de plantilla (requisitos_catalogo.peso). Las pruebas no
 * crean filas hijas del requisito: en SQLite, quitar una columna reconstruye la tabla y, dentro
 * de la transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En MariaDB no pasa.
 */
class PesoRequisitosTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100020_add_peso_a_requisitos_catalogo.php');
    }

    private function columna(): ?array
    {
        return collect(Schema::getColumns('requisitos_catalogo'))->firstWhere('name', 'peso');
    }

    /** Un requisito insertado como lo haría el código anterior a la migración: sin peso. */
    private function requisito(array $cambios = []): int
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => 'Plantilla ' . (DB::table('plantillas_checklist')->count() + 1), 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla->id, 'numero' => 1, 'estado' => CatalogoVersion::BORRADOR, 'vigente' => false,
        ]);

        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'r1', 'texto' => 'Estudios previos', ...$cambios,
        ]);
    }

    private function asertarMigrada(): void
    {
        $peso = $this->columna();
        $this->assertNotNull($peso);
        $this->assertFalse($peso['nullable']);
        $this->assertStringContainsString('1', (string) $peso['default']);
    }

    public function test_los_requisitos_tienen_peso_obligatorio_que_por_defecto_vale_1(): void
    {
        $this->asertarMigrada();

        $id = $this->requisito();
        $this->assertSame(1, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));
    }

    public function test_los_requisitos_existentes_quedan_con_peso_1(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = $this->requisito();

        $migracion->up();

        $this->assertSame(1, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));
    }

    public function test_el_modelo_conoce_el_peso_como_entero(): void
    {
        $requisito = RequisitoCatalogo::findOrFail($this->requisito());
        $this->assertSame(1, $requisito->peso);

        $requisito->update(['peso' => '3']);
        $this->assertSame(3, $requisito->fresh()->peso);
    }

    public function test_el_rollback_se_detiene_si_algun_peso_es_distinto_de_1(): void
    {
        $id = $this->requisito(['peso' => 3]);

        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('peso', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->asertarMigrada();
        $this->assertSame(3, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));

        DB::table('requisitos_catalogo')->where('id', $id)->update(['peso' => 1]);
        $this->migracion()->down();
        $this->assertFalse(Schema::hasColumn('requisitos_catalogo', 'peso'));
        $this->assertTrue(DB::table('requisitos_catalogo')->where('id', $id)->exists());
    }

    public function test_en_ambos_sentidos_se_puede_repetir_y_conserva_los_requisitos(): void
    {
        $migracion = $this->migracion();
        $id = $this->requisito();

        $migracion->up(); // ya migrada: no falla
        $this->asertarMigrada();

        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('requisitos_catalogo', 'peso'));

        $migracion->up();
        $migracion->up();
        $this->asertarMigrada();
        $this->assertSame('Estudios previos', DB::table('requisitos_catalogo')->where('id', $id)->value('texto'));
        $this->assertSame(1, (int) DB::table('requisitos_catalogo')->where('id', $id)->value('peso'));
    }
}
