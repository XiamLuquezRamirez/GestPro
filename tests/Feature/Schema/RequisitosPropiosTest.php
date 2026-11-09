<?php

namespace Tests\Feature\Schema;

use App\Models\PlantillaChecklist;
use App\Models\ProyectoRequisito;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class RequisitosPropiosTest extends TestCase
{
    use RefreshDatabase;

    /*
     * Estas pruebas ensayan la migración 10 sobre el esquema que ella deja, con
     * proyectos_expedientes.catalogo_version_id. La migración 11 la retira: se baja antes de
     * crear filas, así su reconstrucción de la tabla en SQLite no borra nada en cascada.
     */
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_11_09_100010_drop_catalogo_version_de_expedientes.php'))->down();
    }

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_08_100009_add_requisitos_propios_table.php');
    }

    private function version(?int $plantilla = null): int
    {
        return DB::table('catalogo_versiones')->insertGetId([
            'plantilla_id' => $plantilla ?? PlantillaChecklist::sgr()->id, 'numero' => 1, 'estado' => 'publicada', 'vigente' => true,
        ]);
    }

    private function requisito(int $version, string $clave = 'a1'): int
    {
        return DB::table('requisitos_catalogo')->insertGetId([
            'catalogo_version_id' => $version, 'clave' => $clave, 'seccion' => 'A', 'fuente' => 'a12',
            'codigo' => '1', 'texto' => 'Texto', 'codigo_comun' => $clave,
        ]);
    }

    private function expediente(?int $version): int
    {
        return DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => DB::table('proyectos')->insertGetId(['nombre' => 'Prueba']),
            'catalogo_version_id' => $version, 'fase_sgr' => 3, 'estado' => 'borrador',
        ]);
    }

    private function fila(int $expediente, ?int $requisito, array $cambios = []): int
    {
        return DB::table('proyectos_requisitos')->insertGetId([
            'expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito, ...$cambios,
        ]);
    }

    private function nullable(string $tabla, string $columna): bool
    {
        return collect(Schema::getColumns($tabla))->firstWhere('name', $columna)['nullable'];
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function restrict(): void
    {
        foreach ([['proyectos_requisitos', 'requisito_catalogo_id'], ['proyectos_expedientes', 'catalogo_version_id']] as [$tabla, $columna]) {
            $this->assertSame('restrict', strtolower($this->foranea($tabla, $columna)['on_delete']), "$tabla.$columna");
        }
    }

    private function rollbackDetenido(string $mensaje): void
    {
        try {
            $this->migracion()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($mensaje, $e->getMessage());
        }

        // Nada se revirtió.
        $this->assertTrue(Schema::hasTable('proyecto_requisito_origenes'));
        $this->assertTrue(Schema::hasColumn('proyectos_requisitos', 'titulo_propio'));
        $this->assertTrue($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
    }

    // ---------------------------------------------------------------- columnas

    public function test_agrega_los_textos_propios_y_vuelve_opcionales_las_referencias(): void
    {
        $this->assertTrue(Schema::hasColumns('proyectos_requisitos', ['titulo_propio', 'descripcion_propia', 'categoria_propia']));
        $this->assertTrue(Schema::hasColumns('proyecto_requisito_origenes', ['proyecto_requisito_id', 'requisito_catalogo_id']));
        $this->assertTrue($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertTrue($this->nullable('proyectos_expedientes', 'catalogo_version_id'));

        // Las claves foráneas siguen en su sitio.
        $this->assertSame('requisitos_catalogo', $this->foranea('proyectos_requisitos', 'requisito_catalogo_id')['foreign_table']);
        $this->assertSame('catalogo_versiones', $this->foranea('proyectos_expedientes', 'catalogo_version_id')['foreign_table']);
        $this->assertTrue(Schema::hasIndex('proyectos_requisitos', 'proy_req_expediente_requisito_unique'));
        $this->restrict();
    }

    public function test_un_expediente_admite_varios_requisitos_propios(): void
    {
        $expediente = $this->expediente(null);

        $this->fila($expediente, null, ['titulo_propio' => 'Acta de vecinos', 'categoria_propia' => 'social']);
        $this->fila($expediente, null, ['titulo_propio' => 'Permiso de la junta']);

        $filas = ProyectoRequisito::where('expediente_id', $expediente)->orderBy('id')->get();
        $this->assertCount(2, $filas);
        $this->assertTrue($filas[0]->esPropio());
        $this->assertSame('social', $filas[0]->categoria_propia);
        $this->assertNull($filas[0]->requisito_catalogo_id);
    }

    public function test_un_requisito_del_catalogo_no_es_propio_y_sigue_siendo_unico_en_el_expediente(): void
    {
        $requisito = $this->requisito($this->version());
        $expediente = $this->expediente(null);
        $fila = ProyectoRequisito::find($this->fila($expediente, $requisito));

        $this->assertFalse($fila->esPropio());
        $this->expectException(QueryException::class);
        $this->fila($expediente, $requisito);
    }

    // ---------------------------------------------------------------- orígenes

    public function test_los_origenes_de_una_fila_se_leen_por_la_relacion(): void
    {
        $version = $this->version();
        $uno = $this->requisito($version, 'a1');
        $dos = $this->requisito($version, 'a2');
        $fila = $this->fila($this->expediente($version), $uno);
        DB::table('proyecto_requisito_origenes')->insert([
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $uno],
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $dos],
        ]);

        $this->assertEqualsCanonicalizing([$uno, $dos], ProyectoRequisito::find($fila)->origenes()->pluck('requisitos_catalogo.id')->all());
    }

    public function test_un_origen_no_se_repite_en_la_misma_fila(): void
    {
        $version = $this->version();
        $requisito = $this->requisito($version);
        $fila = $this->fila($this->expediente($version), $requisito);
        DB::table('proyecto_requisito_origenes')->insert(['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito]);

        $this->expectException(QueryException::class);
        DB::table('proyecto_requisito_origenes')->insert(['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito]);
    }

    public function test_borrar_la_fila_borra_sus_origenes_y_un_requisito_con_origenes_no_se_borra(): void
    {
        $version = $this->version();
        $requisito = $this->requisito($version);
        $otra = $this->requisito($version, 'a2');
        $expediente = $this->expediente($version);
        $fila = $this->fila($expediente, $requisito);
        $conOrigen = $this->fila($expediente, $otra);
        DB::table('proyecto_requisito_origenes')->insert([
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito],
            ['proyecto_requisito_id' => $conOrigen, 'requisito_catalogo_id' => $requisito],
        ]);

        DB::table('proyectos_requisitos')->where('id', $fila)->delete();
        $this->assertSame([$conOrigen], DB::table('proyecto_requisito_origenes')->pluck('proyecto_requisito_id')->map(fn ($id) => (int) $id)->all());

        $this->expectException(QueryException::class);
        DB::table('requisitos_catalogo')->where('id', $requisito)->delete();
    }

    /*
     * Esta prueba no pasa por down(). En SQLite, cambiar una columna reconstruye la tabla y,
     * dentro de la transacción de RefreshDatabase, eso borraría en cascada las filas hijas. Se
     * prueba el reintento que deja un intento fallido antes de crear la tabla de orígenes: las
     * columnas ya son opcionales, así que up() no reconstruye nada. En MariaDB no pasa: se
     * comprueba en la copia descartable.
     */
    public function test_migrar_registra_un_origen_por_cada_fila_que_aplica(): void
    {
        $version = $this->version();
        $a1 = $this->requisito($version, 'a1');
        $a2 = $this->requisito($version, 'a2');
        $a3 = $this->requisito($version, 'a3');
        $expediente = $this->expediente($version);
        $aplica = $this->fila($expediente, $a1);
        $tambien = $this->fila($expediente, $a2, ['cumplido' => true]);
        $this->fila($expediente, $a3, ['aplica' => false, 'cumplido' => true]);
        Schema::drop('proyecto_requisito_origenes');

        $this->migracion()->up();

        $origenes = DB::table('proyecto_requisito_origenes')->orderBy('proyecto_requisito_id')->get();
        $this->assertSame(
            [[$aplica, $a1], [$tambien, $a2]],
            $origenes->map(fn ($o) => [(int) $o->proyecto_requisito_id, (int) $o->requisito_catalogo_id])->all()
        );
        $this->assertSame(3, DB::table('proyectos_requisitos')->count());
    }

    // ---------------------------------------------------------------- rollback

    public function test_el_rollback_se_detiene_si_hay_requisitos_propios(): void
    {
        $this->fila($this->expediente($this->version()), null, ['titulo_propio' => 'Acta de vecinos']);

        $this->rollbackDetenido('hay requisitos propios');
    }

    public function test_el_rollback_se_detiene_si_hay_expedientes_sin_version_sgr(): void
    {
        $this->expediente(null);

        $this->rollbackDetenido('expedientes sin versión SGR');
    }

    public function test_el_rollback_se_detiene_si_un_expediente_tiene_mas_de_una_plantilla(): void
    {
        $sgr = $this->version();
        $otra = $this->version(PlantillaChecklist::create(['nombre' => 'Agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id);
        $expediente = $this->expediente($sgr);
        DB::table('expediente_plantillas')->insert([
            ['expediente_id' => $expediente, 'catalogo_version_id' => $sgr, 'es_principal' => true],
            ['expediente_id' => $expediente, 'catalogo_version_id' => $otra, 'es_principal' => false],
        ]);

        $this->rollbackDetenido('más de una plantilla');
    }

    public function test_el_rollback_se_detiene_si_una_fila_tiene_mas_de_un_origen(): void
    {
        $version = $this->version();
        $a1 = $this->requisito($version, 'a1');
        $a2 = $this->requisito($version, 'a2');
        $fila = $this->fila($this->expediente($version), $a1);
        DB::table('proyecto_requisito_origenes')->insert([
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $a1],
            ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $a2],
        ]);

        $this->rollbackDetenido('más de un origen');
    }

    public function test_el_rollback_se_detiene_si_una_fila_viene_de_una_plantilla_distinta_de_la_sgr(): void
    {
        // La fila nació de una plantilla que el expediente ya no tiene: solo le queda la SGR.
        $sgr = $this->version();
        $otra = $this->version(PlantillaChecklist::create(['nombre' => 'Agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE])->id);
        $expediente = $this->expediente($sgr);
        $this->fila($expediente, $this->requisito($sgr, 'a1'));
        $this->fila($expediente, $this->requisito($otra, 'agua1'), ['aplica' => false, 'cumplido' => true]);

        $this->rollbackDetenido('plantillas distintas de la SGR');
    }

    /*
     * Sin filas a propósito: en SQLite, restaurar el NOT NULL reconstruye las tablas y,
     * dentro de la transacción de RefreshDatabase, borraría en cascada las filas hijas.
     */
    public function test_el_rollback_restaura_el_esquema_y_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracion();

        $migracion->down();
        $migracion->down(); // repetirlo no falla

        $this->assertFalse(Schema::hasTable('proyecto_requisito_origenes'));
        foreach (['titulo_propio', 'descripcion_propia', 'categoria_propia'] as $columna) {
            $this->assertFalse(Schema::hasColumn('proyectos_requisitos', $columna), $columna);
        }
        $this->assertFalse($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertFalse($this->nullable('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNotNull($this->foranea('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertNotNull($this->foranea('proyectos_expedientes', 'catalogo_version_id'));
        $this->restrict();

        $migracion->up();
        $migracion->up(); // repetirlo no falla

        $this->assertTrue(Schema::hasTable('proyecto_requisito_origenes'));
        $this->assertTrue(Schema::hasColumns('proyectos_requisitos', ['titulo_propio', 'descripcion_propia', 'categoria_propia']));
        $this->assertTrue($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertTrue($this->nullable('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertNotNull($this->foranea('proyectos_requisitos', 'requisito_catalogo_id'));
        $this->assertNotNull($this->foranea('proyectos_expedientes', 'catalogo_version_id'));
        $this->assertTrue(Schema::hasIndex('proyectos_requisitos', 'proy_req_expediente_requisito_unique'));
        $this->restrict();
    }

    // ------------------------------------------------- guardas y reintentos

    public function test_el_rollback_no_se_detiene_si_las_filas_solo_vienen_de_la_sgr(): void
    {
        $sgr = $this->version();
        $this->fila($this->expediente($sgr), $this->requisito($sgr, 'a1'));

        // Con una sola plantilla y todo anclado en la SGR, ninguna guarda se dispara.
        $this->migracion()->down();

        $this->assertFalse(Schema::hasTable('proyecto_requisito_origenes'));
        $this->assertFalse($this->nullable('proyectos_requisitos', 'requisito_catalogo_id'));
    }

    public function test_el_rollback_se_detiene_con_un_propio_sin_textos(): void
    {
        $this->fila($this->expediente($this->version()), null);

        $this->rollbackDetenido('hay requisitos propios');
    }

    public function test_el_rollback_se_detiene_con_un_texto_propio_en_una_fila_de_catalogo(): void
    {
        $version = $this->version();
        $this->fila($this->expediente($version), $this->requisito($version), ['descripcion_propia' => 'Detalle']);

        $this->rollbackDetenido('hay requisitos propios');
    }

    public function test_migrar_reconstruye_una_tabla_de_origenes_sin_claves_foraneas(): void
    {
        // Resto de un intento fallido: la tabla existe pero sin sus claves foráneas.
        Schema::drop('proyecto_requisito_origenes');
        Schema::create('proyecto_requisito_origenes', function ($table) {
            $table->unsignedBigInteger('proyecto_requisito_id');
            $table->unsignedBigInteger('requisito_catalogo_id');
        });
        $this->assertNull($this->foranea('proyecto_requisito_origenes', 'proyecto_requisito_id'));

        $this->migracion()->up();

        $this->assertSame('proyectos_requisitos', $this->foranea('proyecto_requisito_origenes', 'proyecto_requisito_id')['foreign_table']);
        $this->assertSame('requisitos_catalogo', $this->foranea('proyecto_requisito_origenes', 'requisito_catalogo_id')['foreign_table']);
    }
}
