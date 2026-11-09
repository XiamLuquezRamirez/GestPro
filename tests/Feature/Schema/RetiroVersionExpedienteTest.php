<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Estas pruebas no crean filas de checklist a propósito. En SQLite, quitar o poner la clave
 * foránea reconstruye proyectos_expedientes y, dentro de la transacción de RefreshDatabase, eso
 * borraría en cascada las filas hijas. En MariaDB no pasa: se comprueba en la copia descartable.
 */
class RetiroVersionExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php');
    }

    private function version(int $plantilla): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }

    private function otraPlantilla(): int
    {
        return PlantillaChecklist::create(['nombre' => 'Agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id;
    }

    /** Con versión, la escribe en la columna: solo existe con la migración bajada. */
    private function expediente(?int $version = null): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            ...($version !== null ? ['catalogo_version_id' => $version] : []),
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
    }

    /** @param array<int, bool> $versiones catalogo_version_id => es_principal */
    private function plantillas(int $expediente, array $versiones): void
    {
        foreach ($versiones as $version => $principal) {
            DB::table('expediente_plantillas')->insert([
                'expediente_id' => $expediente, 'catalogo_version_id' => $version, 'es_principal' => $principal,
            ]);
        }
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys('proyectos_expedientes'))
            ->first(fn ($f) => $f['columns'] === ['catalogo_version_id']);
    }

    private function versionDe(int $expediente): ?int
    {
        $version = DB::table('proyectos_expedientes')->where('id', $expediente)->value('catalogo_version_id');

        return $version === null ? null : (int) $version;
    }

    private function migrarDetenido(string $mensaje): void
    {
        try {
            $this->migracion()->up();
            $this->fail('La migración debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }

        // Nada se quitó.
        $this->assertTrue(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNotNull($this->foranea());
    }

    // ---------------------------------------------------------------- subida

    public function test_tras_migrar_los_expedientes_no_tienen_version_propia(): void
    {
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNull($this->foranea());
        // La versión sigue en expediente_plantillas.
        $this->assertTrue(Schema::hasColumns('expediente_plantillas', ['expediente_id', 'catalogo_version_id', 'es_principal']));
    }

    public function test_migrar_se_detiene_si_una_version_no_figura_en_expediente_plantillas(): void
    {
        $this->migracion()->down();
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $otra = $this->version($this->otraPlantilla());

        $this->plantillas($this->expediente($sgr), [$sgr => true]); // registrada: no detiene
        $this->expediente(); // sin versión: no detiene
        $sinFila = $this->expediente($sgr);
        $otraVersion = $this->expediente($sgr);
        $this->plantillas($otraVersion, [$otra => true]);

        $this->migrarDetenido("los expedientes {$sinFila}, {$otraVersion} tienen una versión de catálogo que no figura en expediente_plantillas");
        $this->migrarDetenido('Revíselos antes de migrar.');
    }

    public function test_el_mensaje_nombra_un_solo_expediente_en_singular(): void
    {
        $this->migracion()->down();
        $sinFila = $this->expediente($this->version(PlantillaChecklist::sgr()->id));

        $this->migrarDetenido("el expediente {$sinFila} tiene una versión de catálogo que no figura");
        $this->migrarDetenido('Revíselo antes de migrar.');
    }

    public function test_el_mensaje_nombra_como_mucho_diez_expedientes(): void
    {
        $this->migracion()->down();
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $ids = array_map(fn () => $this->expediente($sgr), range(1, 12));

        $this->migrarDetenido('los expedientes ' . implode(', ', array_slice($ids, 0, 10)) . ' y 2 más tienen');
    }

    public function test_reintentar_la_migracion_no_falla(): void
    {
        $migracion = $this->migracion();

        // Ya migrada.
        $migracion->up();
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));

        // Lo que dejaría un fallo después de quitar la clave foránea y antes de la columna.
        $migracion->down();
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->dropForeign(['catalogo_version_id']);
        });
        $this->assertNull($this->foranea());

        $migracion->up();

        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNull($this->foranea());
    }

    // ---------------------------------------------------------------- bajada

    public function test_el_rollback_restaura_la_version_sgr_de_cada_expediente(): void
    {
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $otra = $this->version($this->otraPlantilla());

        $soloSgr = $this->expediente();
        $this->plantillas($soloSgr, [$sgr => true]);
        // La SGR como complementaria: cuenta el motor, no la principal.
        $complementaria = $this->expediente();
        $this->plantillas($complementaria, [$otra => true, $sgr => false]);
        $sinSgr = $this->expediente();
        $this->plantillas($sinSgr, [$otra => true]);
        $sinPlantillas = $this->expediente();

        $this->migracion()->down();

        $this->assertSame($sgr, $this->versionDe($soloSgr));
        $this->assertSame($sgr, $this->versionDe($complementaria));
        $this->assertNull($this->versionDe($sinSgr));
        $this->assertNull($this->versionDe($sinPlantillas));
    }

    public function test_el_rollback_deja_la_columna_opcional_con_su_clave_foranea_y_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracion();

        $migracion->down();
        $migracion->down(); // repetirlo no falla

        $columna = collect(Schema::getColumns('proyectos_expedientes'))->firstWhere('name', 'catalogo_version_id');
        $this->assertNotNull($columna);
        $this->assertTrue($columna['nullable']);
        $foranea = $this->foranea();
        $this->assertSame('catalogo_versiones', $foranea['foreign_table']);
        $this->assertSame(['id'], $foranea['foreign_columns']);
        $this->assertSame('restrict', strtolower($foranea['on_delete']));

        $migracion->up();
        $migracion->up(); // repetirlo no falla

        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNull($this->foranea());
    }

    public function test_tras_el_rollback_una_version_usada_por_un_expediente_no_se_puede_borrar(): void
    {
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $this->migracion()->down();
        $this->expediente($sgr);

        // Sin filas en expediente_plantillas: lo impide la clave foránea restaurada.
        $this->assertSame(0, DB::table('expediente_plantillas')->count());
        $this->expectException(QueryException::class);
        DB::table('catalogo_versiones')->where('id', $sgr)->delete();
    }

    public function test_tras_el_rollback_la_migracion_10_tambien_se_puede_revertir(): void
    {
        $sgr = $this->version(PlantillaChecklist::sgr()->id);
        $expediente = $this->expediente();
        $this->plantillas($expediente, [$sgr => true]);

        $this->migracion()->down();
        (require database_path('migrations/2026_11_08_100009_add_requisitos_propios_table.php'))->down();

        $columna = collect(Schema::getColumns('proyectos_expedientes'))->firstWhere('name', 'catalogo_version_id');
        $this->assertFalse($columna['nullable']);
        $this->assertSame($sgr, $this->versionDe($expediente));
    }
}
