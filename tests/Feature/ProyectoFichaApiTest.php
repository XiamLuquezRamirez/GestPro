<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\Municipio;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Database\Seeders\DepartamentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Ficha del proyecto: sus datos generales, su expediente, sus procesos y sus contratos. */
class ProyectoFichaApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '20045', 'nombre' => 'BECERRIL', 'activo' => true, 'departamento' => '20'])->codigo;
        $fase = DB::table('fases')->insertGetId(['nombre' => 'Ejecución', 'color' => '#00aa00', 'dashboard' => 1]);
        $estado = DB::table('estados')->insertGetId(['nombre' => 'Activo', 'color' => '#0000aa']);
        $this->proyecto = Proyecto::create([
            'nombre' => 'Acueducto veredal',
            'descripcion' => 'Construcción del acueducto',
            'municipio' => $municipio,
            'fase' => $fase,
            'estado' => $estado,
            'presupuesto' => 5000000,
            'fecha_inicio' => '2026-01-15',
        ]);
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        DB::table('presupuesto_proyecto')->insert([
            ['proyecto' => $this->proyecto->id, 'componente' => 'Obra', 'valor' => 3000000],
            ['proyecto' => $this->proyecto->id, 'componente' => 'Interventoría', 'valor' => 500000],
        ]);
        DB::table('proyecto_ubicaciones')->insert(['proyecto' => $this->proyecto->id, 'lat' => 9.7, 'lng' => -73.28]);
    }

    private function como(Rol $rol = Rol::Consulta): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function url(int|string|null $id = null): string
    {
        return '/GestPro/proyectos/' . ($id ?? $this->proyecto->id) . '/ficha';
    }

    private function proceso(array $mas = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Obra del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'estado' => 'adjudicado',
            ...$mas,
        ]);
    }

    private function contrato(array $mas = [], ?Proyecto $proyecto = null): Contrato
    {
        return Contrato::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => 'en_ejecucion',
            ...$mas,
        ]);
    }

    private function expediente(Proyecto $proyecto): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        app(ExpedienteService::class)->crear($proyecto, ['fase_sgr' => 3], null);
    }

    // ------------------------------------------------------------------ forma

    public function test_la_ficha_trae_proyecto_expediente_procesos_y_contratos(): void
    {
        $ficha = $this->como()->getJson($this->url())->assertOk()->json();

        $this->assertSame(['proyecto', 'expediente', 'procesos', 'contratos'], array_keys($ficha));
    }

    public function test_el_proyecto_es_la_fila_de_get_proyectos_sin_sus_contratos(): void
    {
        $this->contrato();
        Proyecto::create(['nombre' => 'Otro proyecto']);

        $fila = collect($this->como()->getJson('/GestPro/proyectos')->assertOk()->json())->firstWhere('id', $this->proyecto->id);
        $proyecto = $this->como()->getJson($this->url())->assertOk()->json('proyecto');

        $this->assertCount(1, $fila['contratos']);
        unset($fila['contratos']);
        $this->assertSame(array_keys($fila), array_keys($proyecto));
        $this->assertEquals($fila, $proyecto);
        $this->assertSame('Acueducto veredal', $proyecto['nombre']);
        $this->assertSame('BECERRIL', $proyecto['descripcion_municipio']);
        $this->assertSame('Ejecución', $proyecto['descripcion_fase']);
        $this->assertSame('Activo', $proyecto['descripcion_estado']);
        $this->assertCount(1, $proyecto['fuente_ids']);
        $this->assertCount(2, $proyecto['componentesPresupuesto']);
        $this->assertEquals(3500000, $proyecto['totalPresupuesto']);
        $this->assertCount(1, $proyecto['puntosUbicacion']);
    }

    public function test_un_proyecto_sin_datos_opcionales_trae_listas_vacias(): void
    {
        $vacio = Proyecto::create(['nombre' => 'Sin nada']);

        $ficha = $this->como()->getJson($this->url($vacio->id))->assertOk()->json();

        $this->assertSame([], $ficha['proyecto']['fuente_ids']);
        $this->assertSame([], $ficha['proyecto']['componentesPresupuesto']);
        $this->assertEquals(0, $ficha['proyecto']['totalPresupuesto']);
        $this->assertSame([], $ficha['proyecto']['puntosUbicacion']);
        $this->assertNull($ficha['proyecto']['descripcion_municipio']);
        $this->assertNull($ficha['expediente']);
        $this->assertSame([], $ficha['procesos']);
        $this->assertSame([], $ficha['contratos']);
    }

    // ------------------------------------------------------------------ expediente

    public function test_el_expediente_es_el_resumen_de_get_expedientes_de_ese_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Otro proyecto']);
        $this->expediente($otro);
        $this->expediente($this->proyecto);

        $resumen = collect($this->como()->getJson('/GestPro/expedientes')->assertOk()->json())->firstWhere('proyecto_id', $this->proyecto->id);
        $expediente = $this->como()->getJson($this->url())->assertOk()->json('expediente');

        $this->assertNotNull($resumen);
        $this->assertSame($resumen, $expediente);
        $this->assertSame(['total', 'cumplidos', 'porcentaje'], array_keys($expediente['progreso']));
    }

    public function test_sin_expediente_es_null(): void
    {
        $this->expediente(Proyecto::create(['nombre' => 'Otro proyecto']));

        $this->assertNull($this->como()->getJson($this->url())->assertOk()->json('expediente'));
    }

    public function test_get_expedientes_sigue_trayendo_todos_los_proyectos(): void
    {
        $this->expediente(Proyecto::create(['nombre' => 'Otro proyecto']));
        $this->expediente($this->proyecto);

        $this->como()->getJson('/GestPro/expedientes')->assertOk()->assertJsonCount(2);
    }

    // ------------------------------------------------------------------ procesos y contratos

    public function test_los_procesos_son_los_de_get_procesos_del_proyecto(): void
    {
        $proceso = $this->proceso();
        $this->contrato(['proceso_licitacion' => $proceso->id]);
        $this->proceso(['codigo_proceso' => 'MC-002-2026', 'estado' => 'preparacion']);
        $this->proceso(['codigo_proceso' => 'AJENO'], Proyecto::create(['nombre' => 'Otro proyecto']));

        $esperados = $this->como()->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');
        $procesos = $this->como()->getJson($this->url())->assertOk()->json('procesos');

        $this->assertCount(2, $procesos);
        $this->assertSame($esperados, $procesos);
        $this->assertSame(1, $procesos[0]['contratos']);
    }

    public function test_los_contratos_son_los_de_buscar_con_ese_proyecto(): void
    {
        $this->contrato(['avance_fisico' => 40, 'avance_financiero' => 30]);
        $this->contrato(['n_contrato' => 'C-002-2026', 'proceso_licitacion' => $this->proceso()->id]);
        $this->contrato(['n_contrato' => 'AJENO'], Proyecto::create(['nombre' => 'Otro proyecto']));
        $this->contrato(['n_contrato' => 'SIN PROYECTO', 'proyecto' => null]);

        $esperados = $this->como()->getJson('/GestPro/contratos/buscar?proyecto=' . $this->proyecto->id)->assertOk()->json();
        $contratos = $this->como()->getJson($this->url())->assertOk()->json('contratos');

        $this->assertSame($esperados, $contratos);
        $this->assertSame(['C-002-2026', 'C-001-2026'], array_column($contratos, 'n_contrato'));
        $this->assertSame(40, $contratos[1]['avance_fisico']);
    }

    public function test_la_ficha_no_consulta_por_cada_proceso_contrato_o_expediente(): void
    {
        $consultas = function (): int {
            $peticion = $this->como();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson($this->url())->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $this->expediente($this->proyecto);
        $this->contrato(['proceso_licitacion' => $this->proceso()->id]);
        $conUno = $consultas();

        foreach (['minima_cuantia', 'seleccion_abreviada', 'contratacion_directa'] as $i => $clave) {
            $proceso = $this->proceso(['modalidad' => Modalidad::where('clave', $clave)->firstOrFail()->id, 'codigo_proceso' => "P-{$i}"]);
            $tipo = TipoProceso::create(['nombre' => "Tipo {$i}", 'activo' => true]);
            $this->contrato(['n_contrato' => "C-{$i}", 'proceso_licitacion' => $proceso->id, 'tipo_contrato_id' => $tipo->id]);
        }

        $this->assertSame($conUno, $consultas());
    }

    // ------------------------------------------------------------------ errores, permisos y rutas

    public function test_un_proyecto_inexistente_responde_404_con_mensaje(): void
    {
        $this->como()->getJson($this->url(9999))->assertNotFound()->assertExactJson(['mensaje' => 'El proyecto no existe.']);
    }

    public function test_un_id_fuera_de_rango_responde_404_con_mensaje(): void
    {
        foreach (['99999999999999999999', '9223372036854775808'] as $id) {
            $this->como()->getJson($this->url($id))->assertNotFound()->assertExactJson(['mensaje' => 'El proyecto no existe.']);
        }
    }

    public function test_un_id_que_no_es_numero_no_llega_a_la_ficha(): void
    {
        $uri = fn (string $url) => Route::getRoutes()->match(Request::create($url))->uri();

        $this->assertSame('GestPro/proyectos/{proyecto}/ficha', $uri($this->url()));
        $this->assertNotSame('GestPro/proyectos/{proyecto}/ficha', $uri($this->url('abc')));
    }

    public function test_exige_sesion_y_la_lee_cualquier_rol(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson($this->url())->assertOk();
        }
    }

    public function test_la_ruta_del_spa_no_es_una_ruta_get_de_la_api(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('GET', $ruta->methods(), true))
            ->map(fn ($ruta) => $ruta->uri())
            ->all();

        $this->assertNotContains('GestPro/proyectos/{proyecto}', $uris);
        $this->assertNotContains('GestPro/proyectos/{id}', $uris);
        $this->assertContains('GestPro/proyectos/{proyecto}/ficha', $uris);
    }
}
