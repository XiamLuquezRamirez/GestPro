<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El expediente como seguimiento de la formulación: nace solo al crear el proyecto,
 * se configura una vez y desde ahí solo se entra a chequear.
 */
class ExpedienteAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    private const FUENTE_SGR = 'Recursos propios y regalías';

    private int $formulacion;
    private int $licitacion;
    private string $municipio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $this->municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05'])->codigo;
        $this->formulacion = DB::table('fases')->insertGetId(['nombre' => 'Formulación']);
        $this->licitacion = DB::table('fases')->insertGetId(['nombre' => 'Licitación']);
        Storage::fake('local');
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function formulario(array $cambios = []): array
    {
        return [
            'accion' => 'Agregar',
            'municipio' => $this->municipio,
            'nombre' => 'Acueducto veredal',
            'descripcion' => 'Prueba',
            'fechaInicio' => '2026-01-01',
            'estado' => null,
            'fase' => $this->formulacion,
            'presupuesto' => null,
            'entidadPresenta' => null,
            'entidadFinancia' => null,
            'fuenteFinanciamiento' => self::FUENTE_SGR,
            ...$cambios,
        ];
    }

    private function guardar(array $cambios = [])
    {
        return $this->como(Rol::Gestor)->postJson('/GestPro/guardarProyecto', $this->formulario($cambios));
    }

    private function proyecto(array $cambios = []): Proyecto
    {
        return Proyecto::create([
            'municipio' => $this->municipio,
            'nombre' => 'Proyecto',
            'fase' => $this->formulacion,
            'fuente_financiacion' => self::FUENTE_SGR,
            ...$cambios,
        ]);
    }

    private function expedienteSinConfigurar(?Proyecto $proyecto = null): ProyectoExpediente
    {
        return app(ExpedienteService::class)->crear($proyecto ?? $this->proyecto(), ['fase_sgr' => 3], null);
    }

    private function confirmar(int $id, array $config = ['fase_sgr' => 3])
    {
        return $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$id}/configuracion/confirmar", $config);
    }

    // ------------------------------------------------------- creación automática

    public function test_un_proyecto_nuevo_en_formulacion_con_regalias_nace_con_su_expediente(): void
    {
        $this->guardar()->assertOk();

        $proyecto = Proyecto::where('nombre', 'Acueducto veredal')->firstOrFail();
        $expediente = $proyecto->expedientes()->sole();
        $this->assertSame('borrador', $expediente->estado);
        $this->assertNull($expediente->fecha_configuracion);
        $this->assertNotNull($expediente->usuario_creo_id);

        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")
            ->assertOk()
            ->assertJsonPath('configurado', false);
    }

    public function test_reconoce_la_fuente_sin_importar_tildes_ni_mayusculas(): void
    {
        foreach (['REGALÍAS', 'regalias directas', 'Sistema General de Regalias', 'Recursos SGR'] as $i => $fuente) {
            $this->guardar(['nombre' => "Fuente {$i}", 'fuenteFinanciamiento' => $fuente])->assertOk();
            $this->assertSame(1, Proyecto::where('nombre', "Fuente {$i}")->firstOrFail()->expedientes()->count(), $fuente);
        }
    }

    public function test_no_se_crea_si_no_corresponde(): void
    {
        $this->guardar(['nombre' => 'En licitacion', 'fase' => $this->licitacion])->assertOk();
        $this->guardar(['nombre' => 'Recursos propios', 'fuenteFinanciamiento' => 'Recursos propios'])->assertOk();
        $this->guardar(['nombre' => 'Sin fuente', 'fuenteFinanciamiento' => null])->assertOk();
        $this->guardar(['nombre' => 'Sin fase', 'fase' => null])->assertOk();

        $this->assertDatabaseCount('proyectos', 4);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_editar_un_proyecto_no_crea_expediente(): void
    {
        $proyecto = $this->proyecto();

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id])->assertOk();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_si_falla_la_creacion_el_proyecto_se_guarda_igual(): void
    {
        DB::table('catalogo_versiones')->update(['vigente' => false]);

        $this->guardar()->assertOk();

        $this->assertDatabaseHas('proyectos', ['nombre' => 'Acueducto veredal']);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_preselecciona_el_sector_sugerido_del_proyecto(): void
    {
        $vivienda = DB::table('requisitos_sectores')->where('clave', 'vivienda')->value('id');
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true, 'requisitos_sector_id' => $vivienda]);

        $expediente = app(ExpedienteService::class)->crearAutomatico($this->proyecto(['sector' => $sector]), null);

        $this->assertSame([$vivienda], $expediente->sectores()->pluck('requisitos_sectores.id')->all());
    }

    // ---------------------------------------------------- un expediente activo

    public function test_no_admite_dos_expedientes_en_borrador_en_un_proyecto(): void
    {
        $expediente = $this->expedienteSinConfigurar();

        $this->como(Rol::Gestor)->postJson("/GestPro/proyectos/{$expediente->proyecto_id}/expedientes", ['fase_sgr' => 3])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', "El proyecto ya tiene el expediente #{$expediente->id} en borrador. Radícalo o elimínalo antes de crear otro.");
        $this->assertDatabaseCount('proyectos_expedientes', 1);
    }

    public function test_tras_eliminar_el_borrador_se_puede_crear_otro(): void
    {
        $expediente = $this->expedienteSinConfigurar();
        $this->como(Rol::Gestor)->deleteJson("/GestPro/expedientes/{$expediente->id}")->assertOk();

        $this->como(Rol::Gestor)->postJson("/GestPro/proyectos/{$expediente->proyecto_id}/expedientes", ['fase_sgr' => 3])
            ->assertCreated()
            ->assertJsonPath('configurado', false);
    }

    // ------------------------------------------------- configuración de una vez

    public function test_sin_confirmar_no_se_puede_chequear_ni_radicar(): void
    {
        $expediente = $this->expedienteSinConfigurar();
        $fila = $expediente->requisitos()->where('aplica', true)->firstOrFail();
        $g = $this->como(Rol::Gestor);
        $mensaje = 'Confirma la configuración del expediente antes de chequear sus requisitos.';

        $g->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])
            ->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $g->post("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}/soporte", [
            'archivo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $g->postJson("/GestPro/expedientes/{$expediente->id}/radicar")
            ->assertStatus(422)->assertJsonPath('mensaje', $mensaje);

        $this->assertDatabaseHas('proyectos_requisitos', ['id' => $fila->id, 'cumplido' => false]);
    }

    public function test_sin_confirmar_se_puede_ajustar_la_configuracion_y_sigue_pendiente(): void
    {
        $expediente = $this->expedienteSinConfigurar();

        $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente->id}/configuracion", ['fase_sgr' => 2])
            ->assertOk()
            ->assertJsonPath('fase_sgr', 2)
            ->assertJsonPath('configurado', false);
    }

    public function test_confirmar_guarda_la_seleccion_y_habilita_el_chequeo(): void
    {
        $expediente = $this->expedienteSinConfigurar();
        $predios = DB::table('requisitos_componentes')->where('clave', 'predios')->value('id');

        $r = $this->confirmar($expediente->id, ['fase_sgr' => 2, 'componente_ids' => [$predios]]);

        $r->assertOk()->assertJsonPath('configurado', true)->assertJsonPath('fase_sgr', 2);
        $this->assertContains('b1', collect($r->json('requisitos'))->pluck('requisito.clave')->all());

        $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisitos.0.id')}", ['cumplido' => true])
            ->assertOk();
    }

    public function test_cambiar_la_configuracion_despues_no_la_devuelve_a_pendiente(): void
    {
        $expediente = $this->expedienteSinConfigurar();
        $this->confirmar($expediente->id)->assertOk();

        $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente->id}/configuracion", ['fase_sgr' => 2])
            ->assertOk()
            ->assertJsonPath('configurado', true);
    }

    public function test_confirmar_exige_permiso_y_valida(): void
    {
        $expediente = $this->expedienteSinConfigurar();

        $this->como(Rol::Consulta)->postJson("/GestPro/expedientes/{$expediente->id}/configuracion/confirmar", ['fase_sgr' => 3])
            ->assertForbidden();
        $this->confirmar($expediente->id, ['fase_sgr' => 9])->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
        $this->assertNull($expediente->refresh()->fecha_configuracion);
    }

    public function test_el_resumen_distingue_lo_que_falta_por_configurar(): void
    {
        $expediente = $this->expedienteSinConfigurar();
        $url = '/GestPro/expedientes';

        $this->como(Rol::Consulta)->getJson($url)->assertJsonPath('0.configurado', false);

        $this->confirmar($expediente->id)->assertOk();
        $this->como(Rol::Consulta)->getJson($url)->assertJsonPath('0.configurado', true);
    }

    // -------------------------------------------- salida de la fase Formulación

    public function test_avisa_si_el_proyecto_sale_de_formulacion_sin_radicar(): void
    {
        $proyecto = $this->proyecto();
        $expediente = $this->expedienteSinConfigurar($proyecto);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fase' => $this->licitacion])
            ->assertOk()
            ->assertJsonPath('advertencia', "El proyecto salió de Formulación con el expediente de viabilización #{$expediente->id} sin radicar.");

        $this->assertDatabaseHas('proyectos', ['id' => $proyecto->id, 'fase' => $this->licitacion]);
    }

    public function test_no_avisa_si_no_hay_nada_pendiente(): void
    {
        $sinExpediente = $this->proyecto();
        $this->guardar(['accion' => 'Editar', 'id' => $sinExpediente->id, 'fase' => $this->licitacion])
            ->assertOk()->assertJsonMissingPath('advertencia');

        $sigueEnFormulacion = $this->proyecto();
        $this->expedienteSinConfigurar($sigueEnFormulacion);
        $this->guardar(['accion' => 'Editar', 'id' => $sigueEnFormulacion->id])
            ->assertOk()->assertJsonMissingPath('advertencia');

        $radicado = $this->proyecto();
        $expediente = $this->expedienteSinConfigurar($radicado);
        DB::table('proyectos_expedientes')->where('id', $expediente->id)->update(['estado' => 'radicado']);
        $this->guardar(['accion' => 'Editar', 'id' => $radicado->id, 'fase' => $this->licitacion])
            ->assertOk()->assertJsonMissingPath('advertencia');
    }

    // -------------------------------------------------- proyectos ya existentes

    public function test_el_comando_crea_los_que_faltan_y_se_puede_repetir(): void
    {
        $falta = $this->proyecto(['nombre' => 'Falta']);
        $tiene = $this->proyecto(['nombre' => 'Ya tiene']);
        $previo = $this->expedienteSinConfigurar($tiene);
        $this->proyecto(['nombre' => 'Otra fase', 'fase' => $this->licitacion]);
        $this->proyecto(['nombre' => 'Sin regalías', 'fuente_financiacion' => 'Recursos propios']);

        $this->artisan('expedientes:crear-faltantes')->assertSuccessful();

        $this->assertSame(1, $falta->expedientes()->count());
        $this->assertNull($falta->expedientes()->sole()->fecha_configuracion);
        $this->assertSame([$previo->id], $tiene->expedientes()->pluck('id')->all());
        $this->assertDatabaseCount('proyectos_expedientes', 2);

        $this->artisan('expedientes:crear-faltantes')->assertSuccessful();
        $this->assertDatabaseCount('proyectos_expedientes', 2);
    }

    public function test_el_comando_en_simulacion_no_crea_nada(): void
    {
        $this->proyecto(['nombre' => 'Falta']);

        $this->artisan('expedientes:crear-faltantes', ['--simular' => true])->assertSuccessful();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }
}
