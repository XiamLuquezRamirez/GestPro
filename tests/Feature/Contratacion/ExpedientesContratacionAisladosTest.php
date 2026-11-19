<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\Municipio;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Database\Seeders\DepartamentosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La viabilización no cambia con los expedientes de contratación: cada lectura y cada regla de
 * viabilización da exactamente lo mismo con ellos que sin ellos. Los expedientes de
 * contratación se insertan directamente: su creación es de otro servicio.
 */
class ExpedientesContratacionAisladosTest extends TestCase
{
    use RefreshDatabase;

    private int $formulacion;
    private int $licitacion;
    private string $municipio;

    /** Proyecto con expedientes de viabilización: uno radicado y uno en borrador, el más reciente. */
    private Proyecto $conExpedientes;
    private ProyectoExpediente $borrador;

    /** Proyecto en Formulación con fuente, todavía sin expediente de viabilización. */
    private Proyecto $sinExpediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(DepartamentosSeeder::class);
        $this->municipio = Municipio::create(['codigo' => '20045', 'nombre' => 'BECERRIL', 'activo' => true, 'departamento' => '20'])->codigo;
        $this->formulacion = DB::table('fases')->insertGetId(['nombre' => 'Formulación']);
        $this->licitacion = DB::table('fases')->insertGetId(['nombre' => 'Licitación']);

        $servicio = app(ExpedienteService::class);
        $this->conExpedientes = $this->proyecto('Acueducto');
        $radicado = $servicio->crear($this->conExpedientes, ['fase_sgr' => 3], null);
        DB::table('proyectos_expedientes')->where('id', $radicado->id)->update(['estado' => ProyectoExpediente::RADICADO]);
        $this->borrador = $servicio->crear($this->conExpedientes, ['fase_sgr' => 2], null);
        $this->sinExpediente = $this->proyecto('Parque');

        // Los procesos existen desde antes: lo único que cambia entre las dos lecturas son los
        // expedientes de contratación.
        foreach ([$this->conExpedientes, $this->conExpedientes, $this->sinExpediente] as $i => $proyecto) {
            $this->proceso($proyecto, 'LP-00' . ($i + 1));
        }
    }

    private function proyecto(string $nombre): Proyecto
    {
        $proyecto = Proyecto::create(['municipio' => $this->municipio, 'nombre' => $nombre, 'fase' => $this->formulacion]);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function proceso(Proyecto $proyecto, string $codigo): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $proyecto->id,
            'objeto' => 'Obra',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => $codigo,
        ]);
    }

    /** Un expediente de contratación por proceso, más reciente que los de viabilización y en borrador. */
    private function agregarExpedientesContratacion(): array
    {
        return ProcesoLicitacion::orderBy('id')->get()->map(fn (ProcesoLicitacion $p) => DB::table('proyectos_expedientes')->insertGetId([
            'proyecto_id' => $p->proyecto,
            'tipo' => ProyectoExpediente::TIPO_CONTRATACION,
            'proceso_id' => $p->id,
            'estado' => ProyectoExpediente::BORRADOR,
            'fecha_configuracion' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]))->all();
    }

    private function como(Rol $rol = Rol::Consulta): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    /** Todo lo que la viabilización lee de los expedientes de un proyecto. */
    private function lecturas(): array
    {
        $servicio = app(ExpedienteService::class);
        $lecturas = [
            'resumen' => $this->como()->getJson('/GestPro/expedientes')->assertOk()->json(),
            'simular' => $this->simular(),
        ];

        foreach (['con' => $this->conExpedientes, 'sin' => $this->sinExpediente] as $clave => $proyecto) {
            $proyecto = $proyecto->fresh();
            $saliendo = (clone $proyecto)->forceFill(['fase' => $this->licitacion]);
            $lecturas[$clave] = [
                'index' => $this->como()->getJson("/GestPro/proyectos/{$proyecto->id}/expedientes")->assertOk()->json(),
                'resumen' => $servicio->resumenPorProyecto($proyecto->id),
                'ficha' => $this->como()->getJson("/GestPro/proyectos/{$proyecto->id}/ficha")->assertOk()->json('expediente'),
                'corresponde' => $servicio->correspondeAutomatico($proyecto),
                'advertencia' => $servicio->advertenciaAlSalirDeFormulacion($saliendo, $this->formulacion),
                'compatibles' => $this->como()->getJson("/GestPro/proyectos/{$proyecto->id}/plantillas-compatibles")->assertOk()->json(),
            ];
        }

        return $lecturas;
    }

    private function simular(): string
    {
        Artisan::call('expedientes:crear-faltantes', ['--simular' => true]);

        return Artisan::output();
    }

    public function test_las_lecturas_de_viabilizacion_no_cambian_con_expedientes_de_contratacion(): void
    {
        $sin = $this->lecturas();
        // Lo que se compara no está vacío: hay expedientes, resumen y un proyecto por crear.
        $this->assertCount(2, $sin['con']['index']['expedientes']);
        $this->assertSame($this->borrador->id, $sin['con']['ficha']['expediente_id']);
        $this->assertSame(2, $sin['con']['ficha']['cantidad_expedientes']);
        $this->assertTrue($sin['sin']['corresponde']);
        $this->assertNull($sin['sin']['ficha']);
        $this->assertStringContainsString("#{$this->sinExpediente->id} Parque", $sin['simular']);
        $this->assertSame("El proyecto salió de Formulación con el expediente #{$this->borrador->id} sin radicar.", $sin['con']['advertencia']);

        $contratacion = $this->agregarExpedientesContratacion();
        $this->assertGreaterThan($this->borrador->id, min($contratacion));

        $this->assertSame($sin, $this->lecturas());
    }

    public function test_el_expediente_de_contratacion_no_impide_crear_el_de_viabilizacion(): void
    {
        $this->agregarExpedientesContratacion();
        $servicio = app(ExpedienteService::class);

        // El borrador de contratación no cuenta como borrador del proyecto.
        $creado = $servicio->crearAutomatico($this->sinExpediente->fresh(), null);
        $this->assertNotNull($creado);
        $this->assertSame(ProyectoExpediente::TIPO_VIABILIZACION, $creado->refresh()->tipo);
        $this->assertNull($creado->proceso_id);
        $this->assertSame([$creado->id], $this->sinExpediente->expedientes()->pluck('id')->all());

        // El de viabilización en borrador sí lo impide, con el mismo mensaje.
        try {
            $servicio->crear($this->conExpedientes, ['fase_sgr' => 3], null);
            $this->fail('Debía impedirse un segundo borrador.');
        } catch (ReglaNegocioException $e) {
            $this->assertSame(
                "El proyecto ya tiene el expediente #{$this->borrador->id} en borrador. Radícalo o elimínalo antes de crear otro.",
                $e->getMessage()
            );
        }
    }

    public function test_la_api_tambien_crea_el_de_viabilizacion_junto_a_uno_de_contratacion(): void
    {
        $this->agregarExpedientesContratacion();

        $id = $this->como(Rol::Gestor)->postJson("/GestPro/proyectos/{$this->sinExpediente->id}/expedientes", ['fase_sgr' => 3])
            ->assertCreated()
            ->json('id');

        $this->assertSame(ProyectoExpediente::TIPO_VIABILIZACION, ProyectoExpediente::findOrFail($id)->tipo);
    }

    public function test_las_plantillas_compatibles_no_aceptan_un_expediente_de_contratacion(): void
    {
        [$contratacion] = $this->agregarExpedientesContratacion();

        $this->como()->getJson("/GestPro/proyectos/{$this->conExpedientes->id}/plantillas-compatibles?expediente={$contratacion}")
            ->assertNotFound();
        $this->como()->getJson("/GestPro/proyectos/{$this->conExpedientes->id}/plantillas-compatibles?expediente={$this->borrador->id}")
            ->assertOk();
    }

    public function test_la_configuracion_sgr_y_la_radicacion_rechazan_un_expediente_de_contratacion(): void
    {
        [$contratacion] = $this->agregarExpedientesContratacion();
        $antes = DB::table('proyectos_expedientes')->where('id', $contratacion)->first();
        $historial = DB::table('checklist_historial')->count();
        $mensaje = 'El expediente es de contratación: no tiene configuración SGR ni se radica.';

        $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$contratacion}/configuracion", ['fase_sgr' => 2])
            ->assertStatus(422)->assertExactJson(['mensaje' => $mensaje]);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$contratacion}/configuracion/confirmar", ['fase_sgr' => 2])
            ->assertStatus(422)->assertExactJson(['mensaje' => $mensaje]);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$contratacion}/radicar")
            ->assertStatus(422)->assertExactJson(['mensaje' => $mensaje]);

        $this->assertEquals($antes, DB::table('proyectos_expedientes')->where('id', $contratacion)->first());
        $this->assertSame($historial, DB::table('checklist_historial')->count());
    }
}
