<?php

namespace Tests\Feature\Schema;

use App\Models\Contrato;
use App\Models\FuenteFinanciacion;
use App\Models\ProcesoLicitacion;
use App\Models\Responsable;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migraciones 18 y 19: campos nuevos de procesos_licitacion y de contratos, estados controlados
 * del contrato y tipos de contrato sembrados. Las pruebas no crean filas hijas (actas, anexos,
 * eventos): en SQLite, poner o quitar una clave foránea reconstruye la tabla y, dentro de la
 * transacción de RefreshDatabase, eso borraría en cascada las filas hijas. En MariaDB no pasa.
 */
class ProcesosContratosAmpliadosTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS_PROCESO = ['fuente_id', 'fecha_apertura', 'fecha_cierre', 'responsable_id', 'observaciones'];

    private const COLUMNAS_CONTRATO = [
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor', 'numero_rp',
        'fecha_rp', 'fecha_acta_inicio', 'estado_original', 'usuario_creo_id', 'created_at', 'updated_at',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    private const TIPOS = ['Obra', 'Interventoría', 'Consultoría', 'Suministro', 'Prestación de servicios', 'Orden de compra'];

    private function migracionProcesos(): object
    {
        return require database_path('migrations/2026_11_18_100017_amplia_procesos_licitacion_contratos.php');
    }

    private function migracionContratos(): object
    {
        return require database_path('migrations/2026_11_18_100018_amplia_contratos.php');
    }

    private function columna(string $tabla, string $columna): ?array
    {
        return collect(Schema::getColumns($tabla))->firstWhere('name', $columna);
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }

    private function proyecto(): int
    {
        return DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']);
    }

    private function proceso(array $cambios = []): int
    {
        return DB::table('procesos_licitacion')->insertGetId([
            'proyecto' => $this->proyecto(), 'codigo_proceso' => 'LP-001', ...$cambios,
        ]);
    }

    private function contrato(array $cambios = []): int
    {
        return DB::table('contratos')->insertGetId([
            'proyecto' => $this->proyecto(), 'n_contrato' => 'C-001', ...$cambios,
        ]);
    }

    private function usuario(): int
    {
        return User::factory()->create()->id;
    }

    /** Deja el esquema como estaba antes de la migración 19, con la tabla de tipos vacía. */
    private function contratosSinMigrar(): object
    {
        $migracion = $this->migracionContratos();
        $migracion->down();
        DB::table('tipos_procesos')->delete();

        return $migracion;
    }

    // ------------------------------------------------------------- procesos (18)

    private function asertarProcesosMigrados(): void
    {
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $definicion = $this->columna('procesos_licitacion', $columna);
            $this->assertNotNull($definicion, $columna);
            $this->assertTrue($definicion['nullable'], $columna);
        }

        $fuente = $this->foranea('procesos_licitacion', 'fuente_id');
        $this->assertSame('fuentes_financiacion', $fuente['foreign_table']);
        $this->assertSame('restrict', strtolower($fuente['on_delete']));

        $responsable = $this->foranea('procesos_licitacion', 'responsable_id');
        $this->assertSame('responsable', $responsable['foreign_table']);
        $this->assertSame('set null', strtolower($responsable['on_delete']));
    }

    private function asertarProcesosRevertidos(): void
    {
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $this->assertFalse(Schema::hasColumn('procesos_licitacion', $columna), $columna);
        }
        $this->assertNull($this->foranea('procesos_licitacion', 'fuente_id'));
        $this->assertNull($this->foranea('procesos_licitacion', 'responsable_id'));
    }

    public function test_el_proceso_tiene_fuente_fechas_responsable_y_observaciones_opcionales(): void
    {
        $this->asertarProcesosMigrados();

        $id = $this->proceso();
        $fila = DB::table('procesos_licitacion')->where('id', $id)->first();
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $this->assertNull($fila->{$columna}, $columna);
        }
    }

    public function test_una_fuente_asignada_a_un_proceso_no_se_borra_y_un_responsable_si_deja_el_campo_nulo(): void
    {
        $fuente = FuenteFinanciacion::first();
        $responsable = Responsable::create(['nombre' => 'Ana', 'activo' => true]);
        $id = $this->proceso(['fuente_id' => $fuente->id, 'responsable_id' => $responsable->id]);

        $responsable->delete();
        $this->assertNull(DB::table('procesos_licitacion')->where('id', $id)->value('responsable_id'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('fuentes_financiacion')->where('id', $fuente->id)->delete();
    }

    public function test_el_rollback_de_procesos_se_detiene_si_alguna_columna_tiene_datos(): void
    {
        $fuente = FuenteFinanciacion::first()->id;
        $responsable = Responsable::create(['nombre' => 'Ana', 'activo' => true])->id;
        $id = $this->proceso();

        foreach ([
            'fuente_id' => $fuente, 'fecha_apertura' => '2026-03-01', 'fecha_cierre' => '2026-03-20',
            'responsable_id' => $responsable, 'observaciones' => 'Nota',
        ] as $columna => $valor) {
            DB::table('procesos_licitacion')->where('id', $id)->update([$columna => $valor]);

            try {
                $this->migracionProcesos()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('procesos de contratación', $e->getMessage(), $columna);
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage(), $columna);
            }

            $this->asertarProcesosMigrados();
            DB::table('procesos_licitacion')->where('id', $id)->update([$columna => null]);
        }

        $this->migracionProcesos()->down();
        $this->asertarProcesosRevertidos();
        $this->assertTrue(DB::table('procesos_licitacion')->where('id', $id)->exists());
    }

    public function test_los_procesos_en_ambos_sentidos_se_pueden_repetir_y_conservan_las_filas(): void
    {
        $migracion = $this->migracionProcesos();
        $id = $this->proceso(['codigo_proceso' => 'LP-777']);

        $migracion->up(); // ya migrada: no falla
        $this->asertarProcesosMigrados();

        $migracion->down();
        $migracion->down();
        $this->asertarProcesosRevertidos();

        $migracion->up();
        $migracion->up();
        $this->asertarProcesosMigrados();
        $this->assertSame('LP-777', DB::table('procesos_licitacion')->where('id', $id)->value('codigo_proceso'));
    }

    public function test_reintentar_procesos_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionProcesos();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: una columna con su clave foránea, otra sin ella
        // y otra de datos.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->foreignId('fuente_id')->nullable()->constrained('fuentes_financiacion')->restrictOnDelete();
        });
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->date('fecha_apertura')->nullable();
        });
        $this->assertNull($this->foranea('procesos_licitacion', 'responsable_id'));

        $migracion->up();
        $this->asertarProcesosMigrados();

        // Lo que dejaría un fallo de la bajada: una columna ya sin clave foránea.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->dropForeign(['responsable_id']);
        });
        $migracion->down();
        $this->asertarProcesosRevertidos();
    }

    // ------------------------------------------------------------- contratos (19): esquema

    private function asertarContratosMigrados(): void
    {
        foreach (self::COLUMNAS_CONTRATO as $columna) {
            $definicion = $this->columna('contratos', $columna);
            $this->assertNotNull($definicion, $columna);
            $this->assertTrue($definicion['nullable'], $columna);
        }

        $tipo = $this->foranea('contratos', 'tipo_contrato_id');
        $this->assertSame('tipos_procesos', $tipo['foreign_table']);
        $this->assertSame('restrict', strtolower($tipo['on_delete']));

        $usuario = $this->foranea('contratos', 'usuario_creo_id');
        $this->assertSame('users', $usuario['foreign_table']);
        $this->assertSame('set null', strtolower($usuario['on_delete']));

        // El proyecto es opcional: la columna admite nulo y borrar el proyecto deja el contrato.
        $this->assertTrue($this->columna('contratos', 'proyecto')['nullable']);
        $proyecto = $this->foranea('contratos', 'proyecto');
        $this->assertSame('proyectos', $proyecto['foreign_table']);
        $this->assertSame('set null', strtolower($proyecto['on_delete']));

        $unico = collect(Schema::getIndexes('contratos'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['secop_id_contrato']);
        $this->assertNotNull($unico);
    }

    private function asertarContratosRevertidos(): void
    {
        foreach (self::COLUMNAS_CONTRATO as $columna) {
            $this->assertFalse(Schema::hasColumn('contratos', $columna), $columna);
        }
        $this->assertNull($this->foranea('contratos', 'tipo_contrato_id'));
        $this->assertNull($this->foranea('contratos', 'usuario_creo_id'));

        $this->assertFalse($this->columna('contratos', 'proyecto')['nullable']);
        $this->assertSame('cascade', strtolower($this->foranea('contratos', 'proyecto')['on_delete']));
    }

    public function test_el_contrato_tiene_las_columnas_nuevas_nulables_con_sus_claves_foraneas(): void
    {
        $this->asertarContratosMigrados();

        $id = $this->contrato();
        $fila = DB::table('contratos')->where('id', $id)->first();
        foreach (self::COLUMNAS_CONTRATO as $columna) {
            $this->assertNull($fila->{$columna}, $columna);
        }
    }

    public function test_un_tipo_asignado_a_un_contrato_no_se_borra_y_un_usuario_si_deja_el_campo_nulo(): void
    {
        $tipo = TipoProceso::first()->id;
        $usuario = $this->usuario();
        $id = $this->contrato(['tipo_contrato_id' => $tipo, 'usuario_creo_id' => $usuario]);

        DB::table('users')->where('id', $usuario)->delete();
        $this->assertNull(DB::table('contratos')->where('id', $id)->value('usuario_creo_id'));

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('tipos_procesos')->where('id', $tipo)->delete();
    }

    // ------------------------------------------------------------- contratos (19): estados

    public function test_convierte_los_estados_reales_y_conserva_el_texto_original(): void
    {
        $migracion = $this->contratosSinMigrar();

        $casos = [
            'En ejecución' => 'en_ejecucion',
            'vigente' => 'en_ejecucion',
            'liquidado' => 'liquidado',
            'ejecutado' => 'terminado',
            'Suspendido ' => 'suspendido',
            '  ANULADO' => 'anulado',
            'Terminado' => 'terminado',
            'celebrado' => 'celebrado',
            'En   Ejecucion' => 'en_ejecucion',
            '' => 'en_ejecucion',
            'Cualquier cosa' => 'en_ejecucion',
        ];
        $ids = [];
        foreach (array_keys($casos) as $original) {
            $ids[$original] = $this->contrato(['estado' => $original]);
        }
        $nulo = $this->contrato(['estado' => null]);

        $migracion->up();

        foreach ($casos as $original => $esperado) {
            $fila = DB::table('contratos')->where('id', $ids[$original])->first();
            $this->assertSame($esperado, $fila->estado, "'{$original}'");
            $this->assertSame($original, $fila->estado_original, "'{$original}'");
        }
        $fila = DB::table('contratos')->where('id', $nulo)->first();
        $this->assertSame('en_ejecucion', $fila->estado);
        $this->assertNull($fila->estado_original);
    }

    public function test_los_contratos_antiguos_quedan_sin_fechas_de_registro_ni_datos_nuevos(): void
    {
        $migracion = $this->contratosSinMigrar();
        $id = $this->contrato(['estado' => 'vigente', 'valor' => 1000]);

        $migracion->up();

        $fila = DB::table('contratos')->where('id', $id)->first();
        $this->assertNull($fila->created_at);
        $this->assertNull($fila->updated_at);
        $this->assertNull($fila->tipo_contrato_id);
        $this->assertNull($fila->usuario_creo_id);
        $this->assertEquals(1000, $fila->valor);
    }

    public function test_avisa_de_los_estados_no_reconocidos_con_su_id_y_su_texto(): void
    {
        $migracion = $this->contratosSinMigrar();
        $raro = $this->contrato(['estado' => 'Quien sabe']);
        $this->contrato(['estado' => 'vigente']);
        Log::spy();

        $migracion->up();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $mensaje) => str_contains($mensaje, "#{$raro}") && str_contains($mensaje, 'Quien sabe')
        );
    }

    public function test_no_avisa_si_todos_los_estados_se_reconocen(): void
    {
        $migracion = $this->contratosSinMigrar();
        $this->contrato(['estado' => 'En ejecución']);
        $this->contrato(['estado' => null]);
        Log::spy();

        $migracion->up();

        Log::shouldNotHaveReceived('warning');
    }

    // ------------------------------------------------------------- contratos (19): tipos

    public function test_siembra_los_seis_tipos_de_contrato_solo_si_la_tabla_esta_vacia(): void
    {
        $this->assertSame(self::TIPOS, TipoProceso::orderBy('id')->pluck('nombre')->all());
        $this->assertTrue(TipoProceso::all()->every->activo);

        $migracion = $this->contratosSinMigrar();
        TipoProceso::create(['nombre' => 'Otro', 'activo' => true]);
        $migracion->up();
        $this->assertSame(['Otro'], TipoProceso::pluck('nombre')->all());

        $migracion->down();
        TipoProceso::query()->delete();
        $migracion->up();
        $migracion->up(); // repetida: la tabla ya no está vacía
        $this->assertSame(self::TIPOS, TipoProceso::orderBy('id')->pluck('nombre')->all());
    }

    // ------------------------------------------------------------- contratos (19): modelos

    public function test_el_modelo_de_contrato_conoce_estados_campos_relaciones_y_marcas_de_tiempo(): void
    {
        $this->assertSame(
            ['celebrado', 'en_ejecucion', 'suspendido', 'terminado', 'liquidado', 'anulado'],
            Contrato::ESTADOS
        );
        $this->assertTrue((new Contrato())->usesTimestamps());

        $tipo = TipoProceso::first();
        $usuario = $this->usuario();
        $proyecto = $this->proyecto();
        $contrato = Contrato::create([
            'proyecto' => $proyecto, 'n_contrato' => 'C-9', 'estado' => 'celebrado',
            'tipo_contrato_id' => $tipo->id, 'contratista_identificacion' => '900123456-1',
            'fecha_suscripcion' => '2026-02-10', 'supervisor' => 'Luis Pérez', 'numero_rp' => 'RP-55',
            'fecha_rp' => '2026-02-12', 'fecha_acta_inicio' => '2026-02-20', 'usuario_creo_id' => $usuario,
        ])->fresh();

        $this->assertSame('2026-02-10', $contrato->fecha_suscripcion->toDateString());
        $this->assertSame('2026-02-12', $contrato->fecha_rp->toDateString());
        $this->assertSame('2026-02-20', $contrato->fecha_acta_inicio->toDateString());
        $this->assertSame('900123456-1', $contrato->contratista_identificacion);
        $this->assertSame($tipo->nombre, $contrato->tipoContrato->nombre);
        $this->assertSame($usuario, $contrato->usuario_creo_id);
        $this->assertNotNull($contrato->created_at);
        $this->assertNotNull($contrato->updated_at);
    }

    public function test_el_modelo_de_proceso_acepta_revocado_y_conoce_los_campos_nuevos(): void
    {
        $this->assertContains('revocado', ProcesoLicitacion::ESTADOS);
        $this->assertSame('revocado', ProcesoLicitacion::REVOCADO);

        $fuente = FuenteFinanciacion::first();
        $responsable = Responsable::create(['nombre' => 'Ana', 'activo' => true]);
        $proceso = ProcesoLicitacion::create([
            'proyecto' => $this->proyecto(), 'codigo_proceso' => 'LP-5', 'estado' => 'revocado',
            'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-03-01', 'fecha_cierre' => '2026-03-20', 'observaciones' => 'Nota',
        ])->fresh();

        $this->assertSame('revocado', $proceso->estado);
        $this->assertSame('2026-03-01', $proceso->fecha_apertura->toDateString());
        $this->assertSame('2026-03-20', $proceso->fecha_cierre->toDateString());
        $this->assertSame($fuente->id, $proceso->fuente->id);
        $this->assertSame('Ana', $proceso->responsable->nombre);
        $this->assertSame('Nota', $proceso->observaciones);
    }

    // ------------------------------------------------------------- contratos (19): rollback

    public function test_el_rollback_restaura_los_estados_borra_los_tipos_sembrados_y_quita_las_columnas(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'En ejecución']);
        $b = $this->contrato(['estado' => 'vigente']);
        $c = $this->contrato(['estado' => 'Suspendido ']);
        $d = $this->contrato(['estado' => null]);
        $e = $this->contrato(['estado' => 'Cualquier cosa']);
        $migracion->up();
        $this->assertSame(6, TipoProceso::count());

        $migracion->down();

        $this->asertarContratosRevertidos();
        $this->assertSame(
            ['En ejecución', 'vigente', 'Suspendido ', null, 'Cualquier cosa'],
            collect([$a, $b, $c, $d, $e])->map(fn ($id) => DB::table('contratos')->where('id', $id)->value('estado'))->all()
        );
        $this->assertSame(0, TipoProceso::count());
    }

    public function test_el_rollback_conserva_los_tipos_que_alguien_usa_y_los_que_no_son_sembrados(): void
    {
        $migracion = $this->migracionContratos();
        $obra = TipoProceso::where('nombre', 'Obra')->value('id');
        $otro = TipoProceso::create(['nombre' => 'Convenio', 'activo' => true])->id;
        $enProceso = TipoProceso::where('nombre', 'Suministro')->value('id');
        $this->proceso(['tipo_proceso' => $enProceso]);

        $migracion->down();

        $this->assertSame(['Convenio', 'Suministro'], TipoProceso::orderBy('nombre')->pluck('nombre')->all());
        $this->assertNull(TipoProceso::find($obra));
        $this->assertNotNull(TipoProceso::find($otro));
    }

    public function test_el_rollback_se_detiene_si_algun_contrato_tiene_datos_nuevos(): void
    {
        $id = $this->contrato(['estado' => 'en_ejecucion']);
        $tipo = TipoProceso::first()->id;
        $usuario = $this->usuario();

        foreach ([
            'tipo_contrato_id' => $tipo, 'contratista_identificacion' => '900', 'fecha_suscripcion' => '2026-01-01',
            'supervisor' => 'Luis', 'numero_rp' => 'RP-1', 'fecha_rp' => '2026-01-02',
            'fecha_acta_inicio' => '2026-01-03', 'usuario_creo_id' => $usuario,
            'contratante_nit' => '800.1', 'secop_id_contrato' => 'CO1.PCCNTR.1', 'secop_proceso' => 'CO1.REQ.1',
            'url_secop' => 'https://example.test/c', 'secop_consultado_at' => '2026-01-04 08:00:00',
        ] as $columna => $valor) {
            DB::table('contratos')->where('id', $id)->update([$columna => $valor]);

            try {
                $this->migracionContratos()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('contratos', $e->getMessage(), $columna);
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage(), $columna);
            }

            $this->asertarContratosMigrados();
            DB::table('contratos')->where('id', $id)->update([$columna => null]);
        }

        $this->migracionContratos()->down();
        $this->asertarContratosRevertidos();
    }

    public function test_el_rollback_se_detiene_si_algun_contrato_se_creo_despues(): void
    {
        $this->contrato(['estado' => 'en_ejecucion', 'created_at' => '2026-05-01 10:00:00']);

        try {
            $this->migracionContratos()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('después', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarContratosMigrados();
    }

    public function test_el_rollback_se_detiene_si_alguien_cambio_el_estado_de_un_contrato(): void
    {
        $migracion = $this->contratosSinMigrar();
        $id = $this->contrato(['estado' => 'vigente']);
        $migracion->up();
        DB::table('contratos')->where('id', $id)->update(['estado' => 'liquidado']);

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('estado', $e->getMessage());
            $this->assertStringContainsString("#{$id}", $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->asertarContratosMigrados();
        $this->assertSame('vigente', DB::table('contratos')->where('id', $id)->value('estado_original'));
    }

    public function test_los_contratos_en_ambos_sentidos_se_pueden_repetir_sin_perder_estados(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'vigente']);
        $b = $this->contrato(['estado' => null]);
        $c = $this->contrato(['estado' => 'liquidado']);

        $migracion->up();
        $migracion->up(); // repetida: no vuelve a convertir
        $this->asertarContratosMigrados();
        $fila = fn (int $id) => DB::table('contratos')->where('id', $id)->first();
        $this->assertSame(['vigente', null, 'liquidado'], [$fila($a)->estado_original, $fila($b)->estado_original, $fila($c)->estado_original]);
        $this->assertSame(['en_ejecucion', 'en_ejecucion', 'liquidado'], [$fila($a)->estado, $fila($b)->estado, $fila($c)->estado]);

        $migracion->down();
        $migracion->down();
        $this->asertarContratosRevertidos();
        $this->assertSame(['vigente', null, 'liquidado'], [$fila($a)->estado, $fila($b)->estado, $fila($c)->estado]);

        $migracion->up();
        $this->asertarContratosMigrados();
        $this->assertSame(['en_ejecucion', 'en_ejecucion', 'liquidado'], [$fila($a)->estado, $fila($b)->estado, $fila($c)->estado]);
    }

    public function test_reintentar_contratos_tras_un_fallo_a_medias_en_la_subida_completa_la_migracion(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'vigente']);

        // Lo que dejaría un fallo de la subida: columnas agregadas, una clave foránea puesta, la
        // otra no, y los estados sin convertir.
        Schema::table('contratos', function (Blueprint $table) {
            $table->foreignId('tipo_contrato_id')->nullable()->constrained('tipos_procesos')->restrictOnDelete();
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_creo_id')->nullable();
            $table->string('estado_original', 30)->nullable();
            $table->string('supervisor', 255)->nullable();
        });
        $this->assertNull($this->foranea('contratos', 'usuario_creo_id'));

        $migracion->up();

        $this->asertarContratosMigrados();
        $fila = DB::table('contratos')->where('id', $a)->first();
        $this->assertSame('en_ejecucion', $fila->estado);
        $this->assertSame('vigente', $fila->estado_original);
        $this->assertSame(self::TIPOS, TipoProceso::orderBy('id')->pluck('nombre')->all());
    }

    public function test_reintentar_contratos_tras_un_fallo_a_medias_en_la_bajada_completa_la_reversion(): void
    {
        $migracion = $this->contratosSinMigrar();
        $a = $this->contrato(['estado' => 'vigente']);
        $migracion->up();

        // Lo que dejaría un fallo de la bajada: estados ya restaurados, tipos borrados, una
        // clave foránea ya quitada y una columna ya borrada.
        DB::table('contratos')->where('id', $a)->update(['estado' => 'vigente']);
        DB::table('tipos_procesos')->delete();
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['usuario_creo_id']);
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('usuario_creo_id');
        });

        $migracion->down();

        $this->asertarContratosRevertidos();
        $this->assertSame('vigente', DB::table('contratos')->where('id', $a)->value('estado'));
    }

    // ------------------------------------------------------------- contratos (19): proyecto y SECOP

    public function test_borrar_un_proyecto_deja_sus_contratos_sin_proyecto(): void
    {
        // Regla nueva: un contrato no necesita proyecto, así que ya no se borra con él.
        $proyecto = $this->proyecto();
        $conProyecto = $this->contrato(['proyecto' => $proyecto]);
        $sinProyecto = $this->contrato(['proyecto' => null, 'n_contrato' => 'C-2']);

        DB::table('proyectos')->where('id', $proyecto)->delete();

        $this->assertNull(DB::table('contratos')->where('id', $conProyecto)->value('proyecto'));
        $this->assertTrue(DB::table('contratos')->where('id', $sinProyecto)->exists());
        $this->assertNull(Contrato::find($conProyecto)->proyecto);
        $this->assertNull(Contrato::find($conProyecto)->proyectoRel);
    }

    public function test_el_id_de_secop_del_contrato_no_se_repite_pero_puede_faltar(): void
    {
        $this->contrato(['secop_id_contrato' => null]);
        $this->contrato(['secop_id_contrato' => null]);
        $this->contrato(['secop_id_contrato' => 'CO1.PCCNTR.100']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->contrato(['secop_id_contrato' => 'CO1.PCCNTR.100']);
    }

    public function test_el_modelo_guarda_la_referencia_de_secop_y_el_nit(): void
    {
        $contrato = Contrato::create([
            'proyecto' => null, 'n_contrato' => 'C-7', 'contratante_nit' => '890.980.040-8',
            'secop_id_contrato' => 'CO1.PCCNTR.7', 'secop_proceso' => 'CO1.REQ.7',
            'url_secop' => 'https://community.secop.gov.co/x', 'secop_consultado_at' => '2026-11-18 09:30:00',
        ])->fresh();

        $this->assertNull($contrato->proyecto);
        $this->assertSame('890.980.040-8', $contrato->contratante_nit);
        $this->assertSame('CO1.PCCNTR.7', $contrato->secop_id_contrato);
        $this->assertSame('CO1.REQ.7', $contrato->secop_proceso);
        $this->assertSame('2026-11-18 09:30:00', $contrato->secop_consultado_at->format('Y-m-d H:i:s'));
    }

    public function test_el_rollback_se_detiene_si_algun_contrato_no_tiene_proyecto_y_despues_restaura_la_cascada(): void
    {
        $id = $this->contrato(['proyecto' => null]);

        try {
            $this->migracionContratos()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sin proyecto', $e->getMessage());
            $this->assertStringContainsString('restaure un respaldo.', $e->getMessage());
        }
        $this->asertarContratosMigrados();

        $proyecto = $this->proyecto();
        DB::table('contratos')->where('id', $id)->update(['proyecto' => $proyecto]);
        $this->migracionContratos()->down();

        $this->asertarContratosRevertidos();
        DB::table('proyectos')->where('id', $proyecto)->delete();
        $this->assertFalse(DB::table('contratos')->where('id', $id)->exists());
    }

    public function test_reintentar_el_proyecto_opcional_tras_un_fallo_a_medias(): void
    {
        $migracion = $this->contratosSinMigrar();

        // Subida: la clave foránea en cascada ya se quitó y la columna ya admite nulo.
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['proyecto']);
        });
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('proyecto')->nullable()->change();
        });
        $migracion->up();
        $this->asertarContratosMigrados();

        // Bajada: la clave foránea ya se quitó y la columna sigue admitiendo nulo.
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropForeign(['proyecto']);
        });
        $migracion->down();
        $this->asertarContratosRevertidos();
    }
}
