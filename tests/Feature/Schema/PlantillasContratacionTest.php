<?php

namespace Tests\Feature\Schema;

use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migraciones 16 y 17: uso y modalidades de las plantillas, y etapa de los requisitos. Las
 * pruebas que bajan la 17 no dejan filas en proyectos_requisitos: en SQLite, poner o quitar una
 * clave foránea reconstruye requisitos_catalogo y proyectos_requisitos y, dentro de la
 * transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En MariaDB no pasa.
 */
class PlantillasContratacionTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS_ETAPA = [
        ['requisitos_catalogo', 'etapa_id'],
        ['proyectos_requisitos', 'etapa_propia_id'],
    ];

    private function migracionUso(): object
    {
        return require database_path('migrations/2026_11_17_100015_add_uso_y_modalidades_a_plantillas.php');
    }

    private function migracionEtapa(): object
    {
        return require database_path('migrations/2026_11_17_100016_add_etapa_a_requisitos.php');
    }

    private function columna(string $tabla, string $columna): ?array
    {
        return collect(Schema::getColumns($tabla))->firstWhere('name', $columna);
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function plantilla(array $cambios = []): PlantillaChecklist
    {
        return PlantillaChecklist::create([
            'nombre' => 'Licitación pública', 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true, ...$cambios,
        ]);
    }

    private function modalidad(string $clave = 'licitacion_publica'): int
    {
        return (int) Modalidad::where('clave', $clave)->value('id');
    }

    private function etapa(string $clave = 'planeacion'): int
    {
        return (int) EtapaContratacion::where('clave', $clave)->value('id');
    }

    private function requisito(array $cambios = []): int
    {
        $version = DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $this->plantilla(['nombre' => 'Plantilla ' . (DB::table('plantillas_checklist')->count() + 1)])->id,
            'numero' => 1, 'estado' => CatalogoVersion::BORRADOR, 'vigente' => false,
        ]);

        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => 'r1', 'texto' => 'Estudios previos', ...$cambios,
        ]);
    }

    // ------------------------------------------------------------- uso y modalidades (16)

    private function asertarUsoMigrado(): void
    {
        $uso = $this->columna('plantillas_checklist', 'uso');
        $this->assertNotNull($uso);
        $this->assertFalse($uso['nullable']);
        $this->assertStringContainsString('viabilizacion', (string) $uso['default']);

        $todas = $this->columna('plantillas_checklist', 'todas_modalidades');
        $this->assertNotNull($todas);
        $this->assertFalse($todas['nullable']);

        $this->assertTrue(Schema::hasTable('plantilla_modalidades'));
        $primaria = collect(Schema::getIndexes('plantilla_modalidades'))->firstWhere('primary', true);
        $this->assertSame(['plantilla_id', 'modalidad_id'], $primaria['columns']);

        $plantilla = $this->foranea('plantilla_modalidades', 'plantilla_id');
        $this->assertSame('plantillas_checklist', $plantilla['foreign_table']);
        $this->assertSame('cascade', strtolower($plantilla['on_delete']));
        $modalidad = $this->foranea('plantilla_modalidades', 'modalidad_id');
        $this->assertSame('modalidades', $modalidad['foreign_table']);
        $this->assertSame('restrict', strtolower($modalidad['on_delete']));
    }

    private function asertarUsoRevertido(): void
    {
        $this->assertFalse(Schema::hasColumn('plantillas_checklist', 'uso'));
        $this->assertFalse(Schema::hasColumn('plantillas_checklist', 'todas_modalidades'));
        $this->assertFalse(Schema::hasTable('plantilla_modalidades'));
    }

    public function test_las_plantillas_tienen_uso_y_todas_las_modalidades_con_sus_valores_por_defecto(): void
    {
        $this->asertarUsoMigrado();

        $sgr = DB::table('plantillas_checklist')->where('clave', PlantillaChecklist::CLAVE_SGR)->first();
        $this->assertSame(PlantillaChecklist::USO_VIABILIZACION, $sgr->uso);
        $this->assertFalse((bool) $sgr->todas_modalidades);

        $id = DB::table('plantillas_checklist')->insertGetId(['nombre' => 'Sin uso', 'motor' => 'simple']);
        $fila = DB::table('plantillas_checklist')->where('id', $id)->first();
        $this->assertSame('viabilizacion', $fila->uso);
        $this->assertFalse((bool) $fila->todas_modalidades);
    }

    public function test_el_modelo_de_plantilla_conoce_el_uso_y_sus_modalidades(): void
    {
        $this->assertSame(['viabilizacion', 'contratacion'], PlantillaChecklist::USOS);
        $this->assertSame(PlantillaChecklist::USO_VIABILIZACION, $this->plantilla()->uso);

        $plantilla = $this->plantilla(['nombre' => 'Contratación', 'uso' => PlantillaChecklist::USO_CONTRATACION, 'todas_modalidades' => true]);
        $plantilla->modalidades()->sync([$this->modalidad(), $this->modalidad('minima_cuantia')]);

        $plantilla = $plantilla->fresh();
        $this->assertSame(PlantillaChecklist::USO_CONTRATACION, $plantilla->uso);
        $this->assertTrue($plantilla->todas_modalidades);
        $this->assertSame(['licitacion_publica', 'minima_cuantia'], $plantilla->modalidades->pluck('clave')->sort()->values()->all());
    }

    public function test_borrar_la_plantilla_borra_sus_modalidades_y_una_modalidad_en_uso_no_se_borra(): void
    {
        $plantilla = $this->plantilla(['uso' => PlantillaChecklist::USO_CONTRATACION]);
        $plantilla->modalidades()->sync([$this->modalidad()]);

        try {
            DB::table('modalidades')->where('id', $this->modalidad())->delete();
            $this->fail('La modalidad en uso no debía borrarse.');
        } catch (QueryException) {
            $this->assertSame(1, DB::table('plantilla_modalidades')->count());
        }

        $plantilla->delete();
        $this->assertSame(0, DB::table('plantilla_modalidades')->count());
    }

    public function test_el_rollback_del_uso_se_detiene_si_hay_datos_de_contratacion(): void
    {
        $plantilla = $this->plantilla();
        foreach ([
            'una plantilla de contratación' => fn () => $plantilla->update(['uso' => PlantillaChecklist::USO_CONTRATACION]),
            'una plantilla con todas las modalidades' => fn () => $plantilla->update(['todas_modalidades' => true]),
            'una modalidad asociada' => fn () => $plantilla->modalidades()->sync([$this->modalidad()]),
        ] as $caso => $preparar) {
            $preparar();

            try {
                $this->migracionUso()->down();
                $this->fail("El rollback debía detenerse con {$caso}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('contratación', $e->getMessage(), $caso);
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage(), $caso);
            }

            $this->asertarUsoMigrado();
            $plantilla->update(['uso' => PlantillaChecklist::USO_VIABILIZACION, 'todas_modalidades' => false]);
            $plantilla->modalidades()->sync([]);
        }

        $this->migracionUso()->down();
        $this->asertarUsoRevertido();
        $this->assertTrue(DB::table('plantillas_checklist')->where('id', $plantilla->id)->exists());
    }

    public function test_el_uso_en_ambos_sentidos_se_puede_repetir(): void
    {
        $migracion = $this->migracionUso();

        $migracion->up(); // ya migrada: no falla
        $this->asertarUsoMigrado();

        $migracion->down();
        $migracion->down();
        $this->asertarUsoRevertido();

        $migracion->up();
        $migracion->up();
        $this->asertarUsoMigrado();
        $this->assertSame(PlantillaChecklist::USO_VIABILIZACION, DB::table('plantillas_checklist')->where('clave', 'sgr')->value('uso'));
    }

    public function test_reintentar_el_uso_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionUso();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: una columna y el pivote sin sus claves foráneas.
        Schema::table('plantillas_checklist', function (Blueprint $table) {
            $table->string('uso', 20)->default('viabilizacion');
        });
        Schema::create('plantilla_modalidades', function (Blueprint $table) {
            $table->unsignedBigInteger('plantilla_id');
            $table->unsignedBigInteger('modalidad_id');
            $table->primary(['plantilla_id', 'modalidad_id']);
        });

        $migracion->up();
        $this->asertarUsoMigrado();

        // Lo que dejaría un fallo de la bajada: el pivote ya borrado.
        Schema::drop('plantilla_modalidades');
        $migracion->down();
        $this->asertarUsoRevertido();
    }

    // ------------------------------------------------------------- etapa de los requisitos (17)

    private function asertarEtapaMigrada(): void
    {
        foreach (self::COLUMNAS_ETAPA as [$tabla, $columna]) {
            $definicion = $this->columna($tabla, $columna);
            $this->assertNotNull($definicion, "{$tabla}.{$columna}");
            $this->assertTrue($definicion['nullable'], "{$tabla}.{$columna}");
            $foranea = $this->foranea($tabla, $columna);
            $this->assertNotNull($foranea, "{$tabla}.{$columna}");
            $this->assertSame('etapas_contratacion', $foranea['foreign_table']);
            $this->assertSame(['id'], $foranea['foreign_columns']);
            $this->assertSame('restrict', strtolower($foranea['on_delete']));
        }
    }

    private function asertarEtapaRevertida(): void
    {
        foreach (self::COLUMNAS_ETAPA as [$tabla, $columna]) {
            $this->assertFalse(Schema::hasColumn($tabla, $columna), "{$tabla}.{$columna}");
            $this->assertNull($this->foranea($tabla, $columna), "{$tabla}.{$columna}");
        }
    }

    public function test_los_requisitos_tienen_una_etapa_opcional_con_clave_foranea(): void
    {
        $this->asertarEtapaMigrada();

        $sin = $this->requisito();
        $this->assertNull(DB::table('requisitos_catalogo')->where('id', $sin)->value('etapa_id'));
    }

    public function test_los_modelos_conocen_la_etapa_del_requisito_y_la_del_propio(): void
    {
        $requisito = RequisitoCatalogo::findOrFail($this->requisito(['etapa_id' => $this->etapa('adendas')]));
        $this->assertSame($this->etapa('adendas'), $requisito->etapa_id);
        $this->assertSame('adendas', $requisito->etapa->clave);

        $propio = new ProyectoRequisito(['etapa_propia_id' => $this->etapa('cierre')]);
        $this->assertSame($this->etapa('cierre'), $propio->etapa_propia_id);
        $this->assertSame('cierre', $propio->etapaPropia->clave);
    }

    public function test_una_etapa_asignada_a_un_requisito_no_se_borra(): void
    {
        $this->requisito(['etapa_id' => $this->etapa()]);

        $this->expectException(QueryException::class);
        DB::table('etapas_contratacion')->where('id', $this->etapa())->delete();
    }

    public function test_el_rollback_de_la_etapa_se_detiene_si_algun_requisito_la_tiene(): void
    {
        $this->requisito(['etapa_id' => $this->etapa()]);

        try {
            $this->migracionEtapa()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('etapa', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarEtapaMigrada();
    }

    public function test_el_rollback_de_la_etapa_se_detiene_si_algun_requisito_propio_la_tiene(): void
    {
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']), 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);
        DB::table('proyectos_requisitos')->insert([
            'expediente_id' => $expediente, 'titulo_propio' => 'Acta de cierre', 'etapa_propia_id' => $this->etapa('cierre'),
        ]);

        try {
            $this->migracionEtapa()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('propios', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarEtapaMigrada();
    }

    public function test_la_etapa_en_ambos_sentidos_se_puede_repetir_y_conserva_los_requisitos(): void
    {
        $migracion = $this->migracionEtapa();
        $id = $this->requisito();

        $migracion->up(); // ya migrada: no falla
        $this->asertarEtapaMigrada();

        $migracion->down();
        $migracion->down();
        $this->asertarEtapaRevertida();

        $migracion->up();
        $migracion->up();
        $this->asertarEtapaMigrada();
        $this->assertSame('Estudios previos', DB::table('requisitos_catalogo')->where('id', $id)->value('texto'));
    }

    public function test_reintentar_la_etapa_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionEtapa();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: la primera columna con su clave foránea y la
        // segunda sin ella.
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->foreignId('etapa_id')->nullable()->constrained('etapas_contratacion')->restrictOnDelete();
        });
        Schema::table('proyectos_requisitos', function (Blueprint $table) {
            $table->unsignedBigInteger('etapa_propia_id')->nullable();
        });
        $this->assertNull($this->foranea('proyectos_requisitos', 'etapa_propia_id'));

        $migracion->up();
        $this->asertarEtapaMigrada();

        // Lo que dejaría un fallo de la bajada: una columna ya sin clave foránea.
        Schema::table('proyectos_requisitos', function (Blueprint $table) {
            $table->dropForeign(['etapa_propia_id']);
        });
        $migracion->down();
        $this->asertarEtapaRevertida();
    }
}
