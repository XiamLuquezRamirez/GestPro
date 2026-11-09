<?php

namespace Tests\Feature\Schema;

use App\Models\Municipio;
use App\Models\Proyecto;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Database\Seeders\MapeoSectoresSgrSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogoRequisitosTest extends TestCase
{
    use RefreshDatabase;

    private function crearProyecto(): Proyecto
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);

        return Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);
    }

    private function crearExpediente(int $proyectoId): int
    {
        $expediente = DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $proyectoId,
            'fase_sgr' => 3,
            'estado' => 'borrador',
        ]);
        DB::table('expediente_plantillas')->insert([
            'expediente_id' => $expediente,
            'catalogo_version_id' => DB::table('catalogo_versiones')->where('vigente', true)->value('id'),
            'es_principal' => true,
        ]);

        return $expediente;
    }

    public function test_tablas_del_modulo_existen_con_sus_columnas(): void
    {
        $esperadas = [
            'catalogo_versiones' => ['numero', 'estado', 'fecha_publicacion', 'vigente'],
            'requisitos_sectores' => ['clave', 'nombre', 'fuente', 'activo'],
            'requisitos_tipos' => ['sector_id', 'clave', 'nombre', 'activo'],
            'requisitos_componentes' => ['clave', 'nombre', 'activo'],
            'requisitos_tipologias' => ['clave', 'nombre', 'activo'],
            'requisitos_catalogo' => ['catalogo_version_id', 'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id',
                'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo'],
            'proyectos_expedientes' => ['proyecto_id', 'fase_sgr', 'estado',
                'fecha_radicado', 'usuario_radicado_id'],
            'expediente_sectores' => ['expediente_id', 'sector_id'],
            'expediente_tipos' => ['expediente_id', 'tipo_id'],
            'expediente_componentes' => ['expediente_id', 'componente_id'],
            'expediente_tipologias' => ['expediente_id', 'tipologia_id'],
            'proyectos_requisitos' => ['expediente_id', 'requisito_catalogo_id', 'aplica', 'cumplido',
                'fecha_cumplido', 'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'orden'],
        ];

        foreach ($esperadas as $tabla => $columnas) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
            $this->assertTrue(Schema::hasColumns($tabla, $columnas), "Faltan columnas en {$tabla}");
        }
        $this->assertTrue(Schema::hasColumn('sectores', 'requisitos_sector_id'));
    }

    public function test_seeder_crea_la_version_1_vigente_y_publicada(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $version = DB::table('catalogo_versiones')->first();
        $this->assertSame(1, (int) $version->numero);
        $this->assertSame('publicada', $version->estado);
        $this->assertEquals(1, $version->vigente);
        $this->assertNotNull($version->fecha_publicacion);
    }

    public function test_seeder_carga_los_207_requisitos_con_su_distribucion(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertDatabaseCount('requisitos_catalogo', 207);
        foreach (['A' => 20, 'B' => 15, 'C' => 165, 'D' => 7] as $seccion => $cantidad) {
            $this->assertSame($cantidad, DB::table('requisitos_catalogo')->where('seccion', $seccion)->count(), "Sección {$seccion}");
        }
        $this->assertSame(80, DB::table('requisitos_catalogo')->where('fuente', 'a12')->count());
        $this->assertSame(127, DB::table('requisitos_catalogo')->where('fuente', 'ot')->count());
    }

    public function test_seeder_carga_los_catalogos(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertDatabaseCount('requisitos_sectores', 14);
        $this->assertDatabaseCount('requisitos_tipos', 59);
        $this->assertDatabaseCount('requisitos_componentes', 9);
        $this->assertDatabaseCount('requisitos_tipologias', 6);
        // "ninguno" del prototipo no es un sector real: no se siembra.
        $this->assertDatabaseMissing('requisitos_sectores', ['clave' => 'ninguno']);
        // Los tres sectores regidos por el Acuerdo 12 conservan su fuente.
        $this->assertSame(3, DB::table('requisitos_sectores')->whereNotNull('fuente')->count());
    }

    public function test_resembrar_completa_una_version_que_quedo_sin_requisitos(): void
    {
        // Pasa tras un rollback parcial: catalogo_versiones sobrevive y las demás
        // tablas se recrean vacías. No hay snapshot que proteger en esa versión.
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $version = DB::table('catalogo_versiones')->where('numero', 1)->value('id');
        DB::table('requisitos_catalogo')->delete();
        DB::table('requisitos_tipos')->delete();

        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertSame(1, DB::table('catalogo_versiones')->count());
        $this->assertSame(207, DB::table('requisitos_catalogo')->where('catalogo_version_id', $version)->count());
        $this->assertSame(59, DB::table('requisitos_tipos')->count());
    }

    public function test_resembrar_no_altera_una_version_con_requisitos(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        DB::table('requisitos_catalogo')->where('clave', 'a1')->update(['texto' => 'Texto publicado']);

        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertSame(207, DB::table('requisitos_catalogo')->count());
        $this->assertSame('Texto publicado', DB::table('requisitos_catalogo')->where('clave', 'a1')->value('texto'));
    }

    public function test_secciones_a_b_d_llevan_condicion_y_la_c_sector(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertSame(0, DB::table('requisitos_catalogo')->whereIn('seccion', ['A', 'B', 'D'])->whereNull('condicion')->count());
        $this->assertSame(0, DB::table('requisitos_catalogo')->where('seccion', 'C')->whereNull('sector_id')->count());
        // 5 requisitos C aplican a todo su sector (tipo '*' en el prototipo).
        $this->assertSame(5, DB::table('requisitos_catalogo')->where('seccion', 'C')->whereNull('tipo_id')->count());
    }

    public function test_en_la_seccion_c_solo_llevan_fase_las_filas_que_la_indican_en_el_catalogo(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $conFase = DB::table('requisitos_catalogo')->where('seccion', 'C')->whereNotNull('condicion')
            ->orderBy('clave')->pluck('condicion', 'clave')->all();

        $this->assertSame([
            'mi2a' => 'fase=3', 'mi2c' => 'fase=3', 'mi2d' => 'fase=3', 'mi2e' => 'fase=3',
            'mi2f' => 'fase=3', 'mi2g' => 'fase=3', 'mi2h' => 'fase=3', 'mi2i' => 'fase=3',
            'tr10b' => 'fase=2', 'vi4a' => 'fase=2', 'vi4b' => 'fase=3', 'vi7b' => 'fase=2',
        ], $conFase);

        // La nota sobre fases I y II debe verse en esas fases: va en el requisito que
        // sigue aplicando en ellas (certificado CREG), no en uno exclusivo de fase III.
        $nota = fn ($clave) => DB::table('requisitos_catalogo')->where('clave', $clave)->value('nota');
        $this->assertStringContainsString('Fase I y II', $nota('mi2b'));
        $this->assertNull($nota('mi2a'));
    }

    public function test_las_lambdas_del_prototipo_quedaron_traducidas_al_dsl(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $condicion = fn ($clave) => DB::table('requisitos_catalogo')->where('clave', $clave)->value('condicion');
        $this->assertSame('siempre', $condicion('a1'));
        $this->assertSame('comp=suelo', $condicion('a5'));
        $this->assertSame('fase=1|2', $condicion('a6'));
        $this->assertSame('fase=3 & (comp=suelo|comp=infraestructura)', $condicion('a12'));
        $this->assertSame('comp=predios & fase!=1', $condicion('b1'));
        $this->assertSame('esp=desastre', $condicion('d1'));
        $this->assertSame(19, DB::table('requisitos_catalogo')->whereIn('seccion', ['A', 'B', 'D'])->distinct()->count('condicion'));
    }

    public function test_requisito_sectorial_queda_ligado_a_su_sector_y_tipo(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $req = DB::table('requisitos_catalogo')->where('clave', 'j1')->first();
        $this->assertSame('justicia', DB::table('requisitos_sectores')->where('id', $req->sector_id)->value('clave'));
        $this->assertSame('casas_justicia', DB::table('requisitos_tipos')->where('id', $req->tipo_id)->value('clave'));
    }

    public function test_seeder_es_idempotente_y_no_duplica_ni_reescribe_la_version(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $idAntes = DB::table('requisitos_catalogo')->where('clave', 'a1')->value('id');

        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->assertDatabaseCount('catalogo_versiones', 1);
        $this->assertDatabaseCount('requisitos_catalogo', 207);
        $this->assertDatabaseCount('requisitos_sectores', 14);
        $this->assertDatabaseCount('requisitos_tipos', 59);
        $this->assertSame($idAntes, DB::table('requisitos_catalogo')->where('clave', 'a1')->value('id'));
    }

    public function test_mapeo_de_sectores_solo_preselecciona_los_reconocidos_sin_pisar_manuales(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $salud = DB::table('requisitos_sectores')->where('clave', 'salud')->value('id');

        $vias = DB::table('sectores')->insertGetId(['nombre' => 'Vías', 'activo' => true]);
        $agua = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $otro = DB::table('sectores')->insertGetId(['nombre' => 'Domótica', 'activo' => true]);
        // Mapeado a mano a salud aunque su nombre sugiera otra cosa: no debe tocarse.
        $manual = DB::table('sectores')->insertGetId(['nombre' => 'Educación', 'activo' => true, 'requisitos_sector_id' => $salud]);

        $this->seed(MapeoSectoresSgrSeeder::class);

        $claveDe = fn ($id) => DB::table('requisitos_sectores')
            ->where('id', DB::table('sectores')->where('id', $id)->value('requisitos_sector_id'))->value('clave');
        $this->assertSame('transporte', $claveDe($vias));
        $this->assertSame('vivienda', $claveDe($agua));
        $this->assertNull(DB::table('sectores')->where('id', $otro)->value('requisitos_sector_id'));
        $this->assertSame('salud', $claveDe($manual));
    }

    public function test_no_se_puede_borrar_un_sector_sgr_que_tiene_tipos(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        $this->expectException(QueryException::class);
        DB::table('requisitos_sectores')->where('clave', 'justicia')->delete();
    }

    public function test_un_proyecto_puede_tener_varios_expedientes(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $proyecto = $this->crearProyecto();

        $this->crearExpediente($proyecto->id);
        $this->crearExpediente($proyecto->id);

        $this->assertSame(2, DB::table('proyectos_expedientes')->where('proyecto_id', $proyecto->id)->count());
    }

    public function test_un_requisito_no_se_repite_en_el_mismo_expediente(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $expediente = $this->crearExpediente($this->crearProyecto()->id);
        $requisito = DB::table('requisitos_catalogo')->where('clave', 'a1')->value('id');

        DB::table('proyectos_requisitos')->insert(['expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito]);

        $this->expectException(QueryException::class);
        DB::table('proyectos_requisitos')->insert(['expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito]);
    }

    public function test_no_se_puede_borrar_un_requisito_del_catalogo_usado_en_un_expediente(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $expediente = $this->crearExpediente($this->crearProyecto()->id);
        $requisito = DB::table('requisitos_catalogo')->where('clave', 'a1')->value('id');
        DB::table('proyectos_requisitos')->insert(['expediente_id' => $expediente, 'requisito_catalogo_id' => $requisito]);

        $this->expectException(QueryException::class);
        DB::table('requisitos_catalogo')->where('id', $requisito)->delete();
    }

    public function test_borrar_el_proyecto_elimina_sus_expedientes_y_checklist(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $proyecto = $this->crearProyecto();
        $expediente = $this->crearExpediente($proyecto->id);
        DB::table('proyectos_requisitos')->insert([
            'expediente_id' => $expediente,
            'requisito_catalogo_id' => DB::table('requisitos_catalogo')->where('clave', 'a1')->value('id'),
        ]);
        DB::table('expediente_sectores')->insert([
            'expediente_id' => $expediente,
            'sector_id' => DB::table('requisitos_sectores')->where('clave', 'salud')->value('id'),
        ]);

        $proyecto->delete();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
        $this->assertDatabaseCount('proyectos_requisitos', 0);
        $this->assertDatabaseCount('expediente_sectores', 0);
        // El catálogo no se ve afectado.
        $this->assertDatabaseCount('requisitos_catalogo', 207);
    }
}
