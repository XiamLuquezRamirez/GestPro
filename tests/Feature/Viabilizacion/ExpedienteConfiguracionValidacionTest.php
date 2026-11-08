<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\Municipio;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Validación de la entrada de la configuración y casos límite de la creación con plantillas. */
class ExpedienteConfiguracionValidacionTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function proyecto(array $fuentes = ['sgr']): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync(FuenteFinanciacion::whereIn('clave', $fuentes)->pluck('id')->all());

        return $proyecto;
    }

    private function plantilla(string $nombre, array $requisitos, array $datos = []): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::unguarded(fn () => PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true, ...$datos,
        ]));
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $codigo) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $codigo, 'caracter' => RequisitoCatalogo::OBLIGATORIO,
            ]);
        }

        return $plantilla;
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function crear(Proyecto $proyecto, array $datos)
    {
        return $this->como()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", $datos);
    }

    // ------------------------------------------------- mensajes y tipos estrictos

    public function test_los_errores_de_validacion_estan_en_espanol(): void
    {
        $proyecto = $this->proyecto();

        $this->crear($proyecto, ['plantilla_ids' => 'sgr'])->assertStatus(422)
            ->assertJsonPath('errors.plantilla_ids.0', 'Revisa las plantillas: debe ser una lista.');
        $this->crear($proyecto, ['plantilla_ids' => [$this->sgr->id], 'principal_id' => 'abc'])->assertStatus(422)
            ->assertJsonPath('errors.principal_id.0', 'Revisa la plantilla principal: debe ser un número entero.');
        $this->crear($proyecto, ['fase_sgr' => 9])->assertStatus(422)
            ->assertJsonPath('errors.fase_sgr.0', 'Revisa la fase SGR: no es un valor válido.');
        $r = $this->crear($proyecto, ['plantilla_ids' => ['x']])->assertStatus(422);
        $this->assertSame(['Revisa cada plantilla: debe ser un número entero.'], $r->json('errors')['plantilla_ids.0']);
    }

    public function test_los_ids_deben_ser_enteros_de_verdad(): void
    {
        $this->crear($this->proyecto(), ['plantilla_ids' => [true], 'fase_sgr' => 3])
            ->assertStatus(422)->assertJsonValidationErrors('plantilla_ids.0');
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_una_principal_sin_plantillas_se_rechaza(): void
    {
        $this->crear($this->proyecto(), ['principal_id' => $this->sgr->id, 'fase_sgr' => 3])
            ->assertStatus(422)
            ->assertJsonPath('errors.plantilla_ids.0', 'Indica las plantillas junto con la plantilla principal.');
    }

    public function test_configurar_y_confirmar_sin_fase_sobre_un_expediente_sgr_piden_la_fase(): void
    {
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated()->json('id');

        $this->como()->putJson("/GestPro/expedientes/{$id}/configuracion", [])
            ->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
        $this->como()->postJson("/GestPro/expedientes/{$id}/configuracion/confirmar", [])
            ->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
    }

    // ------------------------------------------------------------ preselección

    public function test_con_una_copia_sgr_compatible_se_preselecciona_la_de_clave_sgr(): void
    {
        // La copia tiene un id menor que el de la SGR: solo la clave decide.
        $copia = $this->plantilla('SGR copia', ['Planos' => null], ['id' => 0, 'clave' => 'sgr-copia', 'motor' => PlantillaChecklist::MOTOR_SGR]);
        $this->assertLessThan($this->sgr->id, $copia->id);

        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated();

        $r->assertJsonCount(1, 'plantillas')->assertJsonPath('plantillas.0.plantilla_id', $this->sgr->id);
    }

    // -------------------------------------------------------- avisos duplicados

    public function test_los_avisos_ignoran_los_requisitos_propios(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados por el ingeniero civil' => 'agua-1']);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertCreated()->json('id');
        $propio = ProyectoExpediente::find($id)->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000,
            'titulo_propio' => 'Planos firmados por el ingeniero civil',
        ]);

        $r = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->assertOk();

        $r->assertJsonPath('avisos_duplicados', []);
        $this->assertContains($propio->id, collect($r->json('requisitos'))->pluck('id')->all());
    }

    public function test_los_textos_que_solo_difieren_en_tildes_generan_aviso(): void
    {
        $a = $this->plantilla('Obras de agua', ['Certificación técnica del acueducto veredal' => 'agua-1']);
        $b = $this->plantilla('Vías', ['Certificacion tecnica del acueducto veredal' => 'vias-1']);

        $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$a->id, $b->id], 'principal_id' => $a->id])
            ->assertCreated()
            ->assertJsonCount(1, 'avisos_duplicados')
            ->assertJsonPath('avisos_duplicados.0.similitud', 1);
    }

    // --------------------------------------------------------------- historial

    public function test_el_historial_de_creacion_registra_las_plantillas_y_la_version(): void
    {
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated()->json('id');
        $version = $this->sgr->versionVigente();

        $registro = DB::table('checklist_historial')->where('evento', 'expediente_creado')->where('expediente_id', $id)->sole();
        $despues = json_decode($registro->despues, true);

        $this->assertSame($version->id, $despues['catalogo_version_id']);
        $this->assertSame(
            [['plantilla_id' => $this->sgr->id, 'catalogo_version_id' => $version->id, 'es_principal' => true]],
            $despues['plantillas']
        );
    }

    // ------------------------------------------------- creación automática

    public function test_sin_version_sgr_vigente_la_creacion_automatica_deja_un_expediente_sin_plantillas(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05'])->codigo;
        $fase = DB::table('fases')->insertGetId(['nombre' => 'Formulación']);
        DB::table('catalogo_versiones')->update(['vigente' => false]);

        $this->como()->postJson('/GestPro/guardarProyecto', [
            'accion' => 'Agregar', 'municipio' => $municipio, 'nombre' => 'Acueducto veredal', 'descripcion' => 'Prueba',
            'fechaInicio' => '2026-01-01', 'estado' => null, 'fase' => $fase, 'presupuesto' => null,
            'entidadPresenta' => null, 'entidadFinancia' => null,
            'fuenteIds' => [FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')],
        ])->assertOk();

        $expediente = ProyectoExpediente::sole();
        $this->assertSame(0, $expediente->versionesPlantilla()->count());
        $this->assertNull($expediente->catalogo_version_id);
        $this->assertSame(0, app(ExpedienteService::class)->progresoDe($expediente)['total']);
    }
}
