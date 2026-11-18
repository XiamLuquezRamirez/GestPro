<?php

namespace Tests\Feature\Schema;

use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migraciones 13 a 15: catálogos de modalidades y etapas de contratación, y columnas nuevas de
 * procesos_licitacion. Las pruebas de la 15 no crean contratos ni eventos a propósito: en SQLite,
 * quitar la clave foránea reconstruye procesos_licitacion y, dentro de la transacción de
 * RefreshDatabase, eso afectaría en cascada a las filas hijas. En MariaDB no pasa.
 */
class ContratacionCatalogosTest extends TestCase
{
    use RefreshDatabase;

    private const MODALIDADES = [
        'licitacion_publica' => 'Licitación pública',
        'seleccion_abreviada' => 'Selección abreviada',
        'concurso_meritos' => 'Concurso de méritos',
        'contratacion_directa' => 'Contratación directa',
        'minima_cuantia' => 'Mínima cuantía',
        'regimen_especial' => 'Régimen especial',
    ];

    private const ETAPAS = [
        'planeacion' => 'Planeación y estudios previos',
        'pliego_borrador' => 'Creación del proceso y pliego borrador',
        'publicacion_borrador' => 'Publicación del proyecto de pliego y aviso de convocatoria',
        'observaciones_borrador' => 'Observaciones al proyecto de pliego',
        'pliego_definitivo' => 'Apertura y pliego definitivo',
        'adendas' => 'Adendas',
        'cierre' => 'Cierre y apertura de ofertas',
        'evaluacion' => 'Evaluación y subsanaciones',
        'observaciones_informe' => 'Observaciones al informe de evaluación',
        'adjudicacion' => 'Adjudicación o declaratoria de desierto',
        'contrato' => 'Contrato y garantías',
    ];

    private const COLUMNAS_PROCESO = ['objeto', 'plataforma', 'url_proceso', 'estado', 'usuario_creo_id', 'created_at', 'updated_at'];

    private function migracionModalidades(): object
    {
        return require database_path('migrations/2026_11_17_100012_siembra_modalidades.php');
    }

    private function migracionEtapas(): object
    {
        return require database_path('migrations/2026_11_17_100013_create_etapas_contratacion_table.php');
    }

    private function migracionProcesos(): object
    {
        return require database_path('migrations/2026_11_17_100014_amplia_procesos_licitacion.php');
    }

    private function indiceUnico(string $tabla, string $columna): ?array
    {
        return collect(Schema::getIndexes($tabla))->first(fn ($i) => $i['unique'] && $i['columns'] === [$columna]);
    }

    private function foraneaUsuario(): ?array
    {
        return collect(Schema::getForeignKeys('procesos_licitacion'))->first(fn ($f) => $f['columns'] === ['usuario_creo_id']);
    }

    private function proceso(array $cambios = []): int
    {
        return DB::table('procesos_licitacion')->insertGetId([
            'proyecto' => DB::table('proyectos')->insertGetId(['nombre' => 'Acueducto']),
            'codigo_proceso' => 'LP-001',
            ...$cambios,
        ]);
    }

    // ------------------------------------------------------------- modalidades (13)

    private function asertarModalidadesMigradas(): void
    {
        $this->assertTrue(Schema::hasColumns('modalidades', ['clave', 'orden']));
        $this->assertTrue(collect(Schema::getColumns('modalidades'))->firstWhere('name', 'clave')['nullable']);
        $this->assertNotNull($this->indiceUnico('modalidades', 'clave'));
    }

    private function asertarModalidadesRevertidas(): void
    {
        $this->assertFalse(Schema::hasColumn('modalidades', 'clave'));
        $this->assertFalse(Schema::hasColumn('modalidades', 'orden'));
    }

    public function test_siembra_las_seis_modalidades_con_clave_y_en_orden(): void
    {
        $this->asertarModalidadesMigradas();

        $modalidades = Modalidad::orderBy('orden')->get();
        $this->assertSame(self::MODALIDADES, $modalidades->pluck('nombre', 'clave')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6], $modalidades->pluck('orden')->all());
        $this->assertTrue($modalidades->every->activo);
    }

    public function test_la_clave_de_una_modalidad_es_opcional_y_no_se_repite(): void
    {
        $sinClave = Modalidad::create(['nombre' => 'Obra por administración']);
        $this->assertNull($sinClave->fresh()->clave);
        Modalidad::create(['nombre' => 'Convenio']);

        $this->expectException(QueryException::class);
        Modalidad::create(['nombre' => 'Repetida', 'clave' => 'minima_cuantia']);
    }

    public function test_una_modalidad_existente_con_el_mismo_nombre_recibe_la_clave_sin_duplicarse(): void
    {
        $migracion = $this->migracionModalidades();
        $migracion->down();
        DB::table('modalidades')->delete();

        $licitacion = DB::table('modalidades')->insertGetId(['nombre' => 'LICITACION PUBLICA', 'activo' => false]);
        $otra = DB::table('modalidades')->insertGetId(['nombre' => 'Obra por administración', 'activo' => true]);
        $minima = DB::table('modalidades')->insertGetId(['nombre' => '  mínima  CUANTÍA ', 'activo' => true]);

        $migracion->up();

        $this->assertSame(7, DB::table('modalidades')->count()); // 3 existentes + 4 sembradas nuevas
        $fila = DB::table('modalidades')->where('id', $licitacion)->first();
        $this->assertSame('licitacion_publica', $fila->clave);
        $this->assertSame('LICITACION PUBLICA', $fila->nombre);
        $this->assertSame(1, (int) $fila->orden);
        $this->assertFalse((bool) $fila->activo);
        $this->assertSame('minima_cuantia', DB::table('modalidades')->where('id', $minima)->value('clave'));
        $this->assertSame(5, (int) DB::table('modalidades')->where('id', $minima)->value('orden'));

        // La que no corresponde a ninguna queda sin clave, después de las sembradas.
        $this->assertNull(DB::table('modalidades')->where('id', $otra)->value('clave'));
        $this->assertSame(7, (int) DB::table('modalidades')->where('id', $otra)->value('orden'));
        $this->assertSame(6, DB::table('modalidades')->whereNotNull('clave')->count());
    }

    public function test_el_rollback_de_modalidades_quita_las_columnas_y_conserva_las_filas(): void
    {
        Modalidad::create(['nombre' => 'Convenio']);

        $this->migracionModalidades()->down();

        $this->asertarModalidadesRevertidas();
        $this->assertSame(7, DB::table('modalidades')->count());
    }

    public function test_volver_a_subir_modalidades_no_las_duplica(): void
    {
        $migracion = $this->migracionModalidades();

        $migracion->up(); // ya migrada: no falla ni duplica
        $this->assertSame(6, DB::table('modalidades')->count());

        $migracion->down();
        $migracion->down();
        $this->asertarModalidadesRevertidas();

        $migracion->up();
        $migracion->up();
        $this->asertarModalidadesMigradas();
        $this->assertSame(6, DB::table('modalidades')->count());
        $this->assertSame(self::MODALIDADES, Modalidad::orderBy('orden')->pluck('nombre', 'clave')->all());
    }

    public function test_reintentar_modalidades_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionModalidades();
        $migracion->down();

        // Lo que dejaría un fallo entre la columna y su índice único.
        Schema::table('modalidades', function (Blueprint $table) {
            $table->string('clave', 40)->nullable();
        });

        $migracion->up();
        $this->asertarModalidadesMigradas();
        $this->assertSame(6, DB::table('modalidades')->count());

        // Lo que dejaría un fallo de la bajada: el índice ya quitado.
        Schema::table('modalidades', function (Blueprint $table) {
            $table->dropUnique(['clave']);
        });
        $migracion->down();
        $this->asertarModalidadesRevertidas();
    }

    // ------------------------------------------------------------- etapas (14)

    public function test_el_catalogo_de_etapas_tiene_sus_columnas(): void
    {
        $this->assertTrue(Schema::hasColumns('etapas_contratacion', [
            'id', 'clave', 'nombre', 'descripcion', 'orden', 'activo', 'created_at', 'updated_at',
        ]));
        $this->assertNotNull($this->indiceUnico('etapas_contratacion', 'clave'));
    }

    public function test_siembra_las_once_etapas_en_orden(): void
    {
        $etapas = EtapaContratacion::orderBy('orden')->get();

        $this->assertSame(self::ETAPAS, $etapas->pluck('nombre', 'clave')->all());
        $this->assertSame(range(1, 11), $etapas->pluck('orden')->all());
        $this->assertTrue($etapas->every->activo);
        $this->assertTrue($etapas->every(fn ($e) => filled($e->descripcion)));
    }

    public function test_la_clave_de_una_etapa_no_se_repite(): void
    {
        $this->expectException(QueryException::class);
        EtapaContratacion::create(['clave' => 'adendas', 'nombre' => 'Otra', 'orden' => 12]);
    }

    public function test_el_rollback_de_etapas_borra_el_catalogo_sin_cambios(): void
    {
        $this->migracionEtapas()->down();

        $this->assertFalse(Schema::hasTable('etapas_contratacion'));
    }

    public function test_el_rollback_de_etapas_se_detiene_si_hay_una_etapa_nueva(): void
    {
        EtapaContratacion::create(['clave' => 'liquidacion', 'nombre' => 'Liquidación', 'orden' => 12]);

        try {
            $this->migracionEtapas()->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Liquidación', $e->getMessage());
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }

        $this->assertSame(12, EtapaContratacion::count());
    }

    public function test_el_rollback_de_etapas_se_detiene_si_una_sembrada_fue_editada(): void
    {
        foreach ([
            'nombre' => 'Adendas al pliego',
            'descripcion' => 'Otra descripción',
            'orden' => 20,
            'activo' => false,
        ] as $campo => $valor) {
            $etapa = EtapaContratacion::where('clave', 'adendas')->firstOrFail();
            $original = $etapa->getAttributes();
            $etapa->update([$campo => $valor]);

            try {
                $this->migracionEtapas()->down();
                $this->fail("El rollback debía detenerse con {$campo} editado.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString("adendas: {$campo}", $e->getMessage());
                $this->assertStringContainsStringIgnoringCase('restaure un respaldo.', $e->getMessage());
            }

            $this->assertTrue(Schema::hasTable('etapas_contratacion'));
            DB::table('etapas_contratacion')->where('id', $etapa->id)->update($original);
        }

        $this->migracionEtapas()->down();
        $this->assertFalse(Schema::hasTable('etapas_contratacion'));
    }

    public function test_volver_a_subir_etapas_no_las_duplica(): void
    {
        $migracion = $this->migracionEtapas();

        $migracion->up(); // ya migrada: no falla ni duplica
        $this->assertSame(11, EtapaContratacion::count());

        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasTable('etapas_contratacion'));

        $migracion->up();
        $migracion->up();
        $this->assertSame(self::ETAPAS, EtapaContratacion::orderBy('orden')->pluck('nombre', 'clave')->all());
    }

    public function test_reintentar_etapas_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionEtapas();
        $migracion->down();

        // Lo que dejaría un fallo entre el CREATE y el índice único.
        Schema::create('etapas_contratacion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40);
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $migracion->up();

        $this->assertNotNull($this->indiceUnico('etapas_contratacion', 'clave'));
        $this->assertSame(self::ETAPAS, EtapaContratacion::orderBy('orden')->pluck('nombre', 'clave')->all());
    }

    // ------------------------------------------------------------- procesos (15)

    private function asertarProcesosMigrados(): void
    {
        $this->assertTrue(Schema::hasColumns('procesos_licitacion', self::COLUMNAS_PROCESO));
        $foranea = $this->foraneaUsuario();
        $this->assertNotNull($foranea);
        $this->assertSame('users', $foranea['foreign_table']);
        $this->assertSame('set null', strtolower($foranea['on_delete']));
    }

    private function asertarProcesosRevertidos(): void
    {
        foreach (self::COLUMNAS_PROCESO as $columna) {
            $this->assertFalse(Schema::hasColumn('procesos_licitacion', $columna), $columna);
        }
        $this->assertNull($this->foraneaUsuario());
    }

    public function test_los_procesos_tienen_las_columnas_nuevas_y_el_estado_por_defecto(): void
    {
        $this->asertarProcesosMigrados();

        $fila = DB::table('procesos_licitacion')->where('id', $this->proceso())->first();
        $this->assertSame(ProcesoLicitacion::PREPARACION, $fila->estado);
        $this->assertNull($fila->objeto);
        $this->assertNull($fila->plataforma);
    }

    public function test_el_modelo_de_proceso_registra_fechas_y_relaciones(): void
    {
        $usuario = User::factory()->create();
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $modalidad = Modalidad::where('clave', 'licitacion_publica')->firstOrFail();

        $proceso = ProcesoLicitacion::create([
            'proyecto' => $proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $modalidad->id,
            'plataforma' => ProcesoLicitacion::SECOP_II,
            'url_proceso' => 'https://community.secop.gov.co/proceso/1',
            'usuario_creo_id' => $usuario->id,
        ]);

        $proceso = $proceso->fresh();
        $this->assertSame(ProcesoLicitacion::PREPARACION, $proceso->estado);
        $this->assertNotNull($proceso->created_at);
        $this->assertNotNull($proceso->updated_at);
        $this->assertTrue($proceso->modalidadRel->is($modalidad));
        $this->assertTrue($proceso->proyectoRel->is($proyecto));
        $this->assertTrue($proceso->usuarioCreo->is($usuario));
        $this->assertSame(['secop_ii', 'secop_i', 'tvec', 'ninguna'], ProcesoLicitacion::PLATAFORMAS);
        $this->assertSame(
            ['preparacion', 'publicado', 'evaluacion', 'adjudicado', 'desierto', 'celebrado', 'cancelado', 'revocado'],
            ProcesoLicitacion::ESTADOS
        );
    }

    public function test_borrar_el_usuario_que_creo_el_proceso_lo_deja_en_null(): void
    {
        $usuario = User::factory()->create();
        $id = $this->proceso(['usuario_creo_id' => $usuario->id]);

        $usuario->delete();

        $this->assertNull(DB::table('procesos_licitacion')->where('id', $id)->value('usuario_creo_id'));
    }

    public function test_el_rollback_de_procesos_se_detiene_si_alguno_tiene_datos_nuevos(): void
    {
        foreach ([
            'objeto' => 'Obra',
            'plataforma' => ProcesoLicitacion::TVEC,
            'url_proceso' => 'https://example.com',
            'estado' => ProcesoLicitacion::PUBLICADO,
            'usuario_creo_id' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ] as $columna => $valor) {
            DB::table('procesos_licitacion')->delete();
            $this->proceso([$columna => $valor]);

            try {
                $this->migracionProcesos()->down();
                $this->fail("El rollback debía detenerse con {$columna}.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('procesos', $e->getMessage());
                $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
            }

            $this->asertarProcesosMigrados();
        }
    }

    public function test_el_rollback_de_procesos_sin_datos_nuevos_conserva_los_procesos(): void
    {
        $id = $this->proceso();

        $this->migracionProcesos()->down();

        $this->asertarProcesosRevertidos();
        $this->assertSame('LP-001', DB::table('procesos_licitacion')->where('id', $id)->value('codigo_proceso'));
    }

    public function test_procesos_en_ambos_sentidos_se_pueden_repetir(): void
    {
        $migracion = $this->migracionProcesos();

        $migracion->up(); // ya migrada: no falla
        $this->asertarProcesosMigrados();

        $migracion->down();
        $migracion->down();
        $this->asertarProcesosRevertidos();

        $migracion->up();
        $migracion->up();
        $this->asertarProcesosMigrados();
    }

    public function test_reintentar_procesos_tras_un_fallo_a_medias_completa_la_migracion(): void
    {
        $migracion = $this->migracionProcesos();
        $migracion->down();

        // Lo que dejaría un fallo de la subida: algunas columnas, y la del usuario sin clave foránea.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->text('objeto')->nullable();
        });
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_creo_id')->nullable();
        });

        $migracion->up();
        $this->asertarProcesosMigrados();

        // Lo que dejaría un fallo de la bajada: la clave foránea ya quitada.
        Schema::table('procesos_licitacion', function (Blueprint $table) {
            $table->dropForeign(['usuario_creo_id']);
        });
        $migracion->down();
        $this->asertarProcesosRevertidos();
    }
}
