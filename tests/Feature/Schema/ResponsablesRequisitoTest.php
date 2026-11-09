<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Estas pruebas no crean filas de checklist a propósito. En SQLite, quitar o poner una clave
 * foránea reconstruye requisitos_catalogo y, dentro de la transacción de RefreshDatabase, eso
 * borraría en cascada las filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
 */
class ResponsablesRequisitoTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS = ['responsable_cumplimiento_id', 'responsable_revision_id'];

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_09_100011_add_responsables_a_requisitos_catalogo.php');
    }

    private function responsable(string $nombre = 'Ana', ?string $cargo = 'Interventora'): int
    {
        return DB::table('responsable')->insertGetId(['nombre' => $nombre, 'cargo' => $cargo, 'activo' => true]);
    }

    private function requisito(array $cambios = []): int
    {
        $version = DB::table('catalogo_versiones')->value('id') ?? DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => PlantillaChecklist::sgr()->id, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);

        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'a' . (DB::table('requisitos_catalogo')->count() + 1),
            'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '1', 'texto' => 'Texto', ...$cambios,
        ]);
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('requisitos_catalogo'))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function asertarMigrada(): void
    {
        foreach (self::COLUMNAS as $columna) {
            $definicion = collect(Schema::getColumns('requisitos_catalogo'))->firstWhere('name', $columna);
            $this->assertNotNull($definicion, $columna);
            $this->assertTrue($definicion['nullable'], $columna);
            $foranea = $this->foranea($columna);
            $this->assertNotNull($foranea, $columna);
            $this->assertSame('responsable', $foranea['foreign_table']);
            $this->assertSame(['id'], $foranea['foreign_columns']);
            $this->assertSame('set null', strtolower($foranea['on_delete']));
        }
    }

    private function asertarRevertida(): void
    {
        foreach (self::COLUMNAS as $columna) {
            $this->assertFalse(Schema::hasColumn('requisitos_catalogo', $columna), $columna);
            $this->assertNull($this->foranea($columna), $columna);
        }
    }

    public function test_los_requisitos_tienen_responsables_opcionales_con_clave_foranea(): void
    {
        $this->asertarMigrada();
        // Los cargos de texto se conservan.
        $this->assertTrue(Schema::hasColumns('requisitos_catalogo', ['cargo_cumplimiento', 'cargo_revision']));
    }

    public function test_borrar_el_responsable_deja_la_persona_en_null_y_conserva_el_cargo(): void
    {
        $ana = $this->responsable();
        $id = $this->requisito([
            'responsable_cumplimiento_id' => $ana, 'cargo_cumplimiento' => 'Interventora',
            'responsable_revision_id' => $ana, 'cargo_revision' => 'Interventora',
        ]);

        DB::table('responsable')->where('id', $ana)->delete();

        $fila = DB::table('requisitos_catalogo')->where('id', $id)->first();
        $this->assertNull($fila->responsable_cumplimiento_id);
        $this->assertNull($fila->responsable_revision_id);
        $this->assertSame('Interventora', $fila->cargo_cumplimiento);
        $this->assertSame('Interventora', $fila->cargo_revision);
    }

    public function test_el_rollback_se_detiene_si_algun_requisito_tiene_una_persona(): void
    {
        foreach (self::COLUMNAS as $columna) {
            DB::table('requisitos_catalogo')->delete();
            $this->requisito([$columna => $this->responsable()]);

            try {
                $this->migracion()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('responsable', $e->getMessage());
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
            }

            $this->asertarMigrada();
        }
    }

    public function test_el_rollback_sin_personas_conserva_los_cargos(): void
    {
        $id = $this->requisito(['cargo_cumplimiento' => 'Interventor', 'cargo_revision' => 'Supervisor']);

        $this->migracion()->down();

        $this->asertarRevertida();
        $fila = DB::table('requisitos_catalogo')->where('id', $id)->first();
        $this->assertSame('Interventor', $fila->cargo_cumplimiento);
        $this->assertSame('Supervisor', $fila->cargo_revision);
    }

    public function test_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracion();

        $migracion->up(); // ya migrada: no falla
        $this->asertarMigrada();

        $migracion->down();
        $migracion->down();
        $this->asertarRevertida();

        $migracion->up();
        $migracion->up();
        $this->asertarMigrada();
    }

    public function test_reintentar_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracion();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: la primera columna con su clave foránea y la
        // segunda sin ella.
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->foreignId('responsable_cumplimiento_id')->nullable()->constrained('responsable')->nullOnDelete();
        });
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->unsignedBigInteger('responsable_revision_id')->nullable();
        });
        $this->assertNull($this->foranea('responsable_revision_id'));

        $migracion->up();
        $this->asertarMigrada();

        // Lo que dejaría un fallo de la bajada: una columna ya sin clave foránea.
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->dropForeign(['responsable_revision_id']);
        });
        $migracion->down();
        $this->asertarRevertida();
    }
}
