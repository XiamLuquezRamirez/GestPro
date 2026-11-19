<?php

namespace Tests\Feature\Schema;

use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 20: el expediente sabe si es de viabilización o de contratación y, el de
 * contratación, de qué proceso es. Las pruebas no crean filas hijas del expediente (requisitos,
 * plantillas, historial): en SQLite, poner o quitar una clave foránea reconstruye la tabla y,
 * dentro de la transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En
 * MariaDB no pasa.
 */
class ExpedientesTipoProcesoTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_18_100019_add_tipo_y_proceso_a_expedientes.php');
    }

    private function columna(string $columna): ?array
    {
        return collect(Schema::getColumns('proyectos_expedientes'))->firstWhere('name', $columna);
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys('proyectos_expedientes'))->first(fn ($f) => $f['columns'] === ['proceso_id']);
    }

    private function indice(array $columnas, bool $unico): ?array
    {
        return collect(Schema::getIndexes('proyectos_expedientes'))
            ->first(fn ($i) => $i['columns'] === $columnas && $i['unique'] === $unico);
    }

    private function proyecto(): int
    {
        return DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']);
    }

    private function proceso(?int $proyecto = null): int
    {
        return DB::table('procesos_licitacion')->insertGetId([
            'proyecto' => $proyecto ?? $this->proyecto(), 'codigo_proceso' => 'LP-001',
        ]);
    }

    /** Un expediente insertado como lo haría el código anterior a la migración: sin tipo ni proceso. */
    private function expedienteAntiguo(?int $proyecto = null): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $proyecto ?? $this->proyecto(), 'estado' => 'borrador',
        ]);
    }

    private function expedienteContratacion(int $proceso, array $cambios = []): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('procesos_licitacion')->where('id', $proceso)->value('proyecto'),
            'tipo' => 'contratacion', 'proceso_id' => $proceso, 'estado' => 'borrador', ...$cambios,
        ]);
    }

    private function asertarMigrada(): void
    {
        $tipo = $this->columna('tipo');
        $this->assertNotNull($tipo);
        $this->assertFalse($tipo['nullable']);
        $this->assertStringContainsString('viabilizacion', (string) $tipo['default']);

        $proceso = $this->columna('proceso_id');
        $this->assertNotNull($proceso);
        $this->assertTrue($proceso['nullable']);

        $foranea = $this->foranea();
        $this->assertSame('procesos_licitacion', $foranea['foreign_table']);
        $this->assertSame('restrict', strtolower($foranea['on_delete']));

        $this->assertNotNull($this->indice(['proceso_id'], true));
        $this->assertNotNull($this->indice(['proyecto_id', 'tipo'], false));
    }

    private function asertarRevertida(): void
    {
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'tipo'));
        $this->assertFalse(Schema::hasColumn('proyectos_expedientes', 'proceso_id'));
        $this->assertNull($this->foranea());
        $this->assertNull($this->indice(['proceso_id'], true));
        $this->assertNull($this->indice(['proyecto_id', 'tipo'], false));
    }

    // ------------------------------------------------------------- esquema

    public function test_el_expediente_tiene_tipo_y_proceso_con_su_clave_foranea_e_indices(): void
    {
        $this->asertarMigrada();
    }

    public function test_los_expedientes_existentes_quedan_de_viabilizacion_y_sin_proceso(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = $this->expedienteAntiguo();

        $migracion->up();

        $fila = DB::table('proyectos_expedientes')->where('id', $id)->first();
        $this->assertSame('viabilizacion', $fila->tipo);
        $this->assertNull($fila->proceso_id);
        $this->assertSame('viabilizacion', DB::table('proyectos_expedientes')->where('id', $this->expedienteAntiguo())->value('tipo'));
    }

    public function test_un_proceso_tiene_como_maximo_un_expediente(): void
    {
        $proceso = $this->proceso();
        $this->expedienteContratacion($proceso);

        $this->expectException(QueryException::class);
        $this->expedienteContratacion($proceso);
    }

    public function test_un_proceso_con_expediente_no_se_borra(): void
    {
        $proceso = $this->proceso();
        $this->expedienteContratacion($proceso);
        $libre = $this->proceso();

        DB::table('procesos_licitacion')->where('id', $libre)->delete();
        $this->assertFalse(DB::table('procesos_licitacion')->where('id', $libre)->exists());

        $this->expectException(QueryException::class);
        DB::table('procesos_licitacion')->where('id', $proceso)->delete();
    }

    // ------------------------------------------------------------- rollback

    public function test_el_rollback_se_detiene_si_hay_expedientes_de_contratacion(): void
    {
        $id = $this->expedienteContratacion($this->proceso());

        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('contratación', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->asertarMigrada();

        // Un expediente sin tipo de contratación pero con proceso también se perdería.
        DB::table('proyectos_expedientes')->where('id', $id)->update(['tipo' => 'viabilizacion']);
        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->asertarMigrada();

        DB::table('proyectos_expedientes')->where('id', $id)->update(['proceso_id' => null]);
        $this->migracion()->down();
        $this->asertarRevertida();
        $this->assertTrue(DB::table('proyectos_expedientes')->where('id', $id)->exists());
    }

    public function test_en_ambos_sentidos_se_puede_repetir_y_conserva_las_filas(): void
    {
        $migracion = $this->migracion();
        $id = $this->expedienteAntiguo();

        $migracion->up(); // ya migrada: no falla
        $this->asertarMigrada();

        $migracion->down();
        $migracion->down();
        $this->asertarRevertida();

        $migracion->up();
        $migracion->up();
        $this->asertarMigrada();
        $this->assertSame('viabilizacion', DB::table('proyectos_expedientes')->where('id', $id)->value('tipo'));
    }

    public function test_reintentar_tras_un_fallo_a_medias_completa_la_migracion_y_la_reversion(): void
    {
        $migracion = $this->migracion();
        $migracion->down();
        $id = $this->expedienteAntiguo();

        // Lo que dejaría un fallo de la subida: las columnas y el índice único, sin la clave
        // foránea ni el índice por proyecto y tipo.
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->string('tipo', 20)->default('viabilizacion');
            $table->unsignedBigInteger('proceso_id')->nullable();
        });
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->unique('proceso_id');
        });
        $this->assertNull($this->foranea());

        $migracion->up();
        $this->asertarMigrada();
        $this->assertSame('viabilizacion', DB::table('proyectos_expedientes')->where('id', $id)->value('tipo'));

        // Lo que dejaría un fallo de la bajada: la clave foránea y el índice por proyecto y
        // tipo ya quitados.
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->dropForeign(['proceso_id']);
        });
        $indice = $this->indice(['proyecto_id', 'tipo'], false)['name'];
        Schema::table('proyectos_expedientes', function (Blueprint $table) use ($indice) {
            $table->dropIndex($indice);
        });

        $migracion->down();
        $this->asertarRevertida();
        $this->assertTrue(DB::table('proyectos_expedientes')->where('id', $id)->exists());
    }

    // ------------------------------------------------------------- modelos

    public function test_el_modelo_conoce_tipos_estados_proceso_y_scopes(): void
    {
        $this->assertSame('viabilizacion', ProyectoExpediente::TIPO_VIABILIZACION);
        $this->assertSame('contratacion', ProyectoExpediente::TIPO_CONTRATACION);
        $this->assertSame(['viabilizacion', 'contratacion'], ProyectoExpediente::TIPOS);
        $this->assertSame('cerrado', ProyectoExpediente::CERRADO);

        $proyecto = $this->proyecto();
        $proceso = $this->proceso($proyecto);
        $viabilizacion = ProyectoExpediente::create(['proyecto_id' => $proyecto, 'estado' => 'borrador'])->refresh();
        $contratacion = ProyectoExpediente::create([
            'proyecto_id' => $proyecto, 'estado' => 'borrador',
            'tipo' => ProyectoExpediente::TIPO_CONTRATACION, 'proceso_id' => $proceso,
        ])->refresh();

        $this->assertSame('viabilizacion', $viabilizacion->tipo);
        $this->assertTrue($viabilizacion->esViabilizacion());
        $this->assertFalse($viabilizacion->esContratacion());
        $this->assertNull($viabilizacion->proceso);

        $this->assertTrue($contratacion->esContratacion());
        $this->assertFalse($contratacion->esViabilizacion());
        $this->assertSame($proceso, $contratacion->proceso_id);
        $this->assertSame($proceso, $contratacion->proceso->id);

        $this->assertSame([$viabilizacion->id], ProyectoExpediente::viabilizacion()->pluck('id')->all());
        $this->assertSame([$contratacion->id], ProyectoExpediente::contratacion()->pluck('id')->all());

        $modelo = Proyecto::findOrFail($proyecto);
        $this->assertSame([$viabilizacion->id], $modelo->expedientes()->pluck('id')->all());
        $this->assertSame([$viabilizacion->id], $modelo->expedientes->pluck('id')->all());
        $this->assertSame([$contratacion->id], $modelo->expedientesContratacion()->pluck('id')->all());

        $this->assertSame($contratacion->id, ProcesoLicitacion::findOrFail($proceso)->expediente->id);
        $this->assertNull(ProcesoLicitacion::findOrFail($this->proceso($proyecto))->expediente);
    }
}
