<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Tests\TestCase;

/**
 * Expediente de contratación de un proceso: plantillas compatibles, creación automática al
 * registrar o cambiar el proceso, creación manual, comando para los que faltan y su efecto al
 * eliminar el proyecto o el proceso.
 */
class ExpedienteProcesoApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private int $sgr;
    private int $propios;
    private int $vias;
    private int $agua;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
        $this->propios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
        $this->vias = DB::table('sectores')->insertGetId(['nombre' => 'Vías', 'activo' => true]);
        $this->agua = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $this->agua]);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function modalidad(string $clave = 'licitacion_publica'): int
    {
        return Modalidad::where('clave', $clave)->firstOrFail()->id;
    }

    private function etapa(string $clave = 'planeacion'): int
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail()->id;
    }

    /**
     * Plantilla de contratación creada por la API. Por defecto: licitación pública, todas las
     * fuentes y todos los sectores, con dos requisitos activos y uno inactivo, publicada.
     */
    private function plantilla(string $nombre, array $cambios = [], bool $publicar = true, array $requisitos = []): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => $nombre,
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()],
            ...$cambios,
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        $requisitos = $requisitos ?: [
            ['texto' => "{$nombre}: estudios previos"],
            ['texto' => "{$nombre}: pliego de condiciones", 'etapa_id' => $this->etapa('pliego_definitivo')],
            ['texto' => "{$nombre}: retirado", 'activo' => false],
        ];
        foreach ($requisitos as $requisito) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
                'etapa_id' => $this->etapa(), ...$requisito,
            ])->assertCreated();
        }
        if ($publicar) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();
        }

        return $plantilla->fresh();
    }

    /** Proceso creado directamente: sin pasar por la creación automática. */
    private function proceso(array $cambios = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            ...$cambios,
        ]);
    }

    /** Proceso creado por la API, como desde la pantalla. */
    private function registrar(array $cambios = [])
    {
        return $this->como()->postJson("/GestPro/proyectos/{$this->proyecto->id}/procesos", [
            'objeto' => 'Construcción del acueducto',
            'modalidad_id' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            ...$cambios,
        ]);
    }

    /** Edita como desde el formulario: los obligatorios siempre van, con lo que el proceso tiene. */
    private function editar(ProcesoLicitacion $proceso, array $datos)
    {
        $actual = $proceso->fresh();

        return $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => $actual->objeto,
            'modalidad_id' => $actual->modalidad,
            'plataforma' => $actual->plataforma,
            'actualizado' => $actual->updated_at?->toIso8601String(),
            ...$datos,
        ]);
    }

    /** @return list<int> */
    private function compatibles(ProcesoLicitacion $proceso): array
    {
        return array_column($this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")
            ->assertOk()->json('plantillas'), 'id');
    }

    private function crearManual(ProcesoLicitacion $proceso, array $plantillas, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => $plantillas]);
    }

    // ------------------------------------------------------- compatibilidad

    public function test_compatibles_por_modalidad_o_todas(): void
    {
        $licitacion = $this->plantilla('Licitación');
        $this->plantilla('Mínima cuantía', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $todas = $this->plantilla('Todas', ['modalidad_ids' => [], 'todas_modalidades' => true]);

        $this->assertSame([$licitacion->id, $todas->id], $this->compatibles($this->proceso()));
    }

    public function test_compatibles_por_fuente_o_todas_y_sin_fuente_solo_todas(): void
    {
        $regalias = $this->plantilla('Regalías', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr]]);
        $this->plantilla('Propios', ['todas_fuentes' => false, 'fuente_ids' => [$this->propios]]);
        $todas = $this->plantilla('Todas las fuentes');

        $this->assertSame([$regalias->id, $todas->id], $this->compatibles($this->proceso(['fuente_id' => $this->sgr])));
        $this->assertSame([$todas->id], $this->compatibles($this->proceso()));
    }

    public function test_compatibles_por_sector_o_todos_y_sin_sector_solo_todos(): void
    {
        $agua = $this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]]);
        $this->plantilla('Vías', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $todos = $this->plantilla('Todos los sectores');
        $sinSector = Proyecto::create(['nombre' => 'Sin sector']);

        $this->assertSame([$agua->id, $todos->id], $this->compatibles($this->proceso()));
        $this->assertSame([$todos->id], $this->compatibles($this->proceso([], $sinSector)));
    }

    public function test_solo_de_contratacion_activas_con_version_publicada_y_motor_simple(): void
    {
        $valida = $this->plantilla('Válida');
        $this->plantilla('Sin publicar', [], false);
        $inactiva = $this->plantilla('Inactiva');
        $inactiva->update(['activo' => false]);
        $sgr = $this->plantilla('Motor SGR');
        DB::table('plantillas_checklist')->where('id', $sgr->id)->update(['motor' => PlantillaChecklist::MOTOR_SGR]);
        // Una plantilla de viabilización para todas las fuentes y sectores no se ofrece.
        $viabilizacion = PlantillaChecklist::sgr();
        $viabilizacion->update(['todas_fuentes' => true, 'todos_sectores' => true]);
        // Con una versión nueva en borrador sigue ofreciéndose con la publicada.
        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$valida->id}/versiones")->assertSuccessful();

        $r = $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$this->proceso()->id}/plantillas-compatibles")->assertOk();

        $this->assertSame([$valida->id], array_column($r->json('plantillas'), 'id'));
        $this->assertSame(1, $r->json('plantillas.0.version.numero'));
    }

    public function test_la_respuesta_describe_el_proceso_y_cada_plantilla(): void
    {
        $plantilla = $this->plantilla('Licitación pública', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr]]);
        $proceso = $this->proceso(['fuente_id' => $this->sgr]);
        $version = $plantilla->versionVigente();

        $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")
            ->assertOk()
            ->assertExactJson([
                'proceso' => [
                    'id' => $proceso->id,
                    'modalidad' => ['id' => $this->modalidad(), 'nombre' => 'Licitación pública'],
                    'fuente' => ['id' => $this->sgr, 'nombre' => FuenteFinanciacion::find($this->sgr)->nombre],
                    'sector' => ['id' => $this->agua, 'nombre' => 'Agua Potable'],
                ],
                'expediente_id' => null,
                'plantillas' => [[
                    'id' => $plantilla->id,
                    'nombre' => 'Licitación pública',
                    'descripcion' => null,
                    'todas_modalidades' => false,
                    'todas_fuentes' => false,
                    'todos_sectores' => true,
                    'requisitos' => 2,
                    'version' => ['id' => $version->id, 'numero' => 1],
                ]],
            ]);
    }

    public function test_compatibles_de_un_proceso_inexistente_es_404(): void
    {
        $this->como(Rol::Consulta)->getJson('/GestPro/procesos/99999/plantillas-compatibles')->assertNotFound();
    }

    // -------------------------------------------------- creación automática

    public function test_un_proceso_nuevo_con_una_plantilla_compatible_nace_con_su_expediente(): void
    {
        $plantilla = $this->plantilla('Licitación');

        $id = $this->registrar()->assertCreated()->json('id');

        $expediente = ProcesoLicitacion::findOrFail($id)->expediente;
        $this->assertNotNull($expediente);
        $this->assertSame(ProyectoExpediente::TIPO_CONTRATACION, $expediente->tipo);
        $this->assertSame($this->proyecto->id, $expediente->proyecto_id);
        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->estado);
        $this->assertNotNull($expediente->fecha_configuracion);
        $this->assertNotNull($expediente->usuario_creo_id);
        $this->assertSame([$plantilla->versionVigente()->id], $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
        // Motor simple: todos los requisitos activos de la versión, en su orden.
        $this->assertSame(
            ['Licitación: estudios previos', 'Licitación: pliego de condiciones'],
            $expediente->requisitos()->where('aplica', true)->orderBy('orden')->with('requisito')->get()->pluck('requisito.texto')->all()
        );
        // La viabilización del proyecto no cambia: sigue sin expedientes.
        $this->assertSame(0, $this->proyecto->expedientes()->count());
    }

    public function test_sin_plantillas_compatibles_o_con_varias_no_se_crea(): void
    {
        $sin = $this->registrar()->assertCreated()->json('id');
        $this->plantilla('Una');
        $this->plantilla('Otra');
        $varias = $this->registrar()->assertCreated()->json('id');

        $this->assertNull(ProcesoLicitacion::find($sin)->expediente);
        $this->assertNull(ProcesoLicitacion::find($varias)->expediente);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_al_cambiar_la_modalidad_o_la_fuente_sin_expediente_se_crea(): void
    {
        $this->plantilla('Mínima', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $this->plantilla('Regalías', ['todas_fuentes' => false, 'fuente_ids' => [$this->sgr], 'modalidad_ids' => [$this->modalidad('seleccion_abreviada')]]);
        $porModalidad = $this->proceso();
        $porFuente = $this->proceso(['modalidad' => $this->modalidad('seleccion_abreviada')]);

        $this->editar($porModalidad, ['objeto' => 'Otro objeto'])->assertOk();
        $this->assertNull($porModalidad->fresh()->expediente);

        $this->editar($porModalidad, ['modalidad_id' => $this->modalidad('minima_cuantia')])->assertOk();
        $this->editar($porFuente, ['fuente_id' => $this->sgr])->assertOk();

        $this->assertNotNull($porModalidad->fresh()->expediente);
        $this->assertNotNull($porFuente->fresh()->expediente);
    }

    public function test_con_expediente_cambiar_la_modalidad_no_lo_toca(): void
    {
        $this->plantilla('Licitación');
        $this->plantilla('Mínima', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $proceso = ProcesoLicitacion::findOrFail($this->registrar()->assertCreated()->json('id'));
        $expediente = $proceso->expediente;
        $versiones = $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all();

        $this->editar($proceso, ['modalidad_id' => $this->modalidad('minima_cuantia')])->assertOk();

        $this->assertSame([$expediente->id], ProyectoExpediente::pluck('id')->all());
        $this->assertSame($versiones, $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
    }

    public function test_si_falla_la_creacion_el_proceso_se_guarda_igual(): void
    {
        $this->plantilla('Licitación');
        Exceptions::fake();
        DB::listen(function ($q) {
            if (str_contains($q->sql, '"plantillas_checklist"')) {
                throw new \RuntimeException('Se cayó la conexión.');
            }
        });

        $this->registrar()->assertCreated();

        $this->assertDatabaseCount('procesos_licitacion', 1);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertReported(\RuntimeException::class);
    }

    // ------------------------------------------------------ creación manual

    public function test_crear_a_mano_con_varias_plantillas_compatibles(): void
    {
        $una = $this->plantilla('Una', [], true, [['texto' => 'Estudios previos', 'codigo_comun' => 'EP'], ['texto' => 'Solo de una']]);
        $otra = $this->plantilla('Otra', [], true, [['texto' => 'Estudios previos (otra)', 'codigo_comun' => 'EP'], ['texto' => 'Solo de otra']]);
        $proceso = $this->proceso();

        $r = $this->crearManual($proceso, [$otra->id, $una->id])->assertCreated()
            ->assertJsonPath('estado', ProyectoExpediente::BORRADOR)
            ->assertJsonPath('configurado', true)
            ->assertJsonPath('proyecto.id', $this->proyecto->id)
            ->assertJsonPath('plantillas.0.plantilla_id', $otra->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('plantillas.1.plantilla_id', $una->id)
            ->assertJsonPath('plantillas.1.es_principal', false)
            ->assertJsonPath('progreso.total', 3);

        $expediente = ProyectoExpediente::findOrFail($r->json('id'));
        $this->assertSame($proceso->id, $expediente->proceso_id);
        $this->assertTrue($expediente->esContratacion());
        // El código común junta los dos "Estudios previos" en una fila con dos orígenes.
        $this->assertCount(3, $r->json('requisitos'));
        $this->assertSame('Estudios previos (otra)', $r->json('requisitos.0.requisito.texto'));
    }

    public function test_crear_a_mano_registra_el_historial(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();

        $id = $this->crearManual($proceso, [$plantilla->id])->assertCreated()->json('id');

        $historial = DB::table('checklist_historial')->where('entidad_tipo', 'expediente')->where('entidad_id', $id)->sole();
        $this->assertSame('expediente_creado', $historial->evento);
        $this->assertSame($id, (int) $historial->expediente_id);
        $this->assertSame([
            'proceso_id' => $proceso->id,
            'plantillas' => [['plantilla_id' => $plantilla->id, 'catalogo_version_id' => $plantilla->versionVigente()->id, 'es_principal' => true]],
        ], json_decode($historial->despues, true));
    }

    public function test_crear_a_mano_exige_plantillas_compatibles(): void
    {
        $compatible = $this->plantilla('Licitación');
        $minima = $this->plantilla('Mínima', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $sinPublicar = $this->plantilla('Sin publicar', [], false);
        $proceso = $this->proceso();

        $this->crearManual($proceso, [])->assertStatus(422)->assertJsonValidationErrors('plantillas');
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", [])->assertStatus(422)->assertJsonValidationErrors('plantillas');
        $this->crearManual($proceso, ['x'])->assertStatus(422)->assertJsonValidationErrors('plantillas.0');
        $this->crearManual($proceso, [$compatible->id, $minima->id, $sinPublicar->id, PlantillaChecklist::sgr()->id, 99999])
            ->assertStatus(422)
            ->assertExactJson([
                'mensaje' => 'El expediente no se puede crear.',
                'errores' => [
                    'La plantilla "Mínima" no es compatible con la modalidad, la fuente o el sector del proceso.',
                    'La plantilla "Sin publicar" no tiene una versión publicada.',
                    'La plantilla "' . PlantillaChecklist::sgr()->nombre . '" es de viabilización y no se usa en un expediente de contratación.',
                    'La plantilla con id 99999 no existe.',
                ],
            ]);

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_un_proceso_tiene_un_solo_expediente(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $id = $this->crearManual($proceso, [$plantilla->id])->assertCreated()->json('id');

        $this->crearManual($proceso, [$plantilla->id])->assertStatus(422)
            ->assertJsonPath('mensaje', "El proceso ya tiene el expediente #{$id}.");
        $this->assertDatabaseCount('proyectos_expedientes', 1);
        $this->assertSame($id, $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/plantillas-compatibles")->json('expediente_id'));
    }

    public function test_crear_a_mano_exige_permiso_de_edicion(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();

        $this->crearManual($proceso, [$plantilla->id], Rol::Consulta)->assertForbidden();
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        $this->crearManual($proceso, [$plantilla->id], Rol::Administrador)->assertCreated();
    }

    public function test_el_expediente_creado_se_lee_y_se_marca_como_los_demas(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $id = $this->crearManual($this->proceso(), [$plantilla->id])->assertCreated()->json('id');
        $fila = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->assertOk()->json('requisitos.0');

        $this->como()->patchJson("/GestPro/expedientes/{$id}/requisitos/{$fila['id']}", ['cumplido' => true])->assertOk();

        $this->assertSame(50, (int) app(ExpedienteService::class)->progresoDe(ProyectoExpediente::find($id))['porcentaje']);
    }

    // --------------------------------------------------------------- comando

    public function test_el_comando_crea_los_que_tienen_una_sola_plantilla_y_se_puede_repetir(): void
    {
        $this->plantilla('Licitación');
        $this->plantilla('Mínima A', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $this->plantilla('Mínima B', ['modalidad_ids' => [$this->modalidad('minima_cuantia')]]);
        $falta = $this->proceso(['codigo_proceso' => 'LP-001']);
        $varias = $this->proceso(['modalidad' => $this->modalidad('minima_cuantia')]);
        $ninguna = $this->proceso(['modalidad' => $this->modalidad('seleccion_abreviada')]);
        $tiene = $this->proceso();
        $previo = $this->crearManual($tiene, [PlantillaChecklist::where('nombre', 'Licitación')->value('id')])->assertCreated()->json('id');

        $this->artisan('expedientes:crear-faltantes-contratacion')
            ->expectsOutput('Expedientes creados: 1 de 1.')
            ->assertSuccessful();

        $this->assertNotNull($falta->fresh()->expediente);
        $this->assertNull($varias->fresh()->expediente);
        $this->assertNull($ninguna->fresh()->expediente);
        $this->assertSame($previo, $tiene->fresh()->expediente->id);
        $this->assertDatabaseCount('proyectos_expedientes', 2);

        $this->artisan('expedientes:crear-faltantes-contratacion')->expectsOutput('Expedientes creados: 0 de 0.')->assertSuccessful();
        $this->assertDatabaseCount('proyectos_expedientes', 2);
    }

    public function test_el_comando_en_simulacion_lista_y_no_crea_nada(): void
    {
        $this->plantilla('Licitación');
        $conCodigo = $this->proceso(['codigo_proceso' => 'LP-001']);
        $sinCodigo = $this->proceso(['objeto' => 'Interventoría']);

        $this->artisan('expedientes:crear-faltantes-contratacion', ['--simular' => true])
            ->expectsOutput("#{$conCodigo->id} LP-001 (Acueducto veredal): plantilla Licitación")
            ->expectsOutput("#{$sinCodigo->id} Interventoría (Acueducto veredal): plantilla Licitación")
            ->expectsOutput('Se crearían 2 expedientes.')
            ->assertSuccessful();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_el_comando_describe_su_regla(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('Crea el expediente de contratación a los procesos sin expediente con una sola plantilla compatible')
            ->assertSuccessful();
    }

    // ------------------------------------------------------ carreras (MariaDB)

    /**
     * Eliminar el proyecto bloquea sus procesos antes de borrar sus expedientes: así una
     * creación simultánea (que bloquea el proceso) espera y después no encuentra el proceso.
     * La carrera solo se ve en MariaDB; SQLite no admite FOR UPDATE, así que se comprueban las
     * sentencias con la gramática de MySQL sin ejecutarlas.
     */
    public function test_eliminar_el_proyecto_bloquea_sus_procesos_antes_de_borrar(): void
    {
        $conexion = DB::connection();
        $gramatica = $conexion->getQueryGrammar();
        $conexion->setQueryGrammar(new MySqlGrammar($conexion));
        try {
            $sentencias = array_column($conexion->pretend(
                fn () => app(ExpedientesContratacion::class)->eliminarDeProyecto($this->proyecto->id)
            ), 'query');
        } finally {
            $conexion->setQueryGrammar($gramatica);
        }

        $this->assertCount(2, $sentencias);
        $this->assertMatchesRegularExpression('/^select .* from `procesos_licitacion` where `proyecto` = \d+ for update$/', $sentencias[0]);
        $this->assertStringStartsWith('delete from `proyectos_expedientes`', $sentencias[1]);
    }

    /** El proceso desaparece entre leerlo y bloquearlo (lo borró la eliminación del proyecto). */
    private function borrarAlLeer(): void
    {
        $hecho = false;
        ProcesoLicitacion::retrieved(function (ProcesoLicitacion $p) use (&$hecho) {
            if (! $hecho) {
                $hecho = true;
                DB::table('procesos_licitacion')->where('id', $p->id)->delete();
            }
        });
    }

    public function test_crear_a_mano_un_proceso_que_desaparecio_responde_404(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $this->borrarAlLeer();

        $this->crearManual($proceso, [$plantilla->id])->assertNotFound();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_la_creacion_automatica_de_un_proceso_que_desaparecio_no_falla(): void
    {
        $this->plantilla('Licitación');
        $proceso = $this->proceso();
        DB::table('procesos_licitacion')->where('id', $proceso->id)->delete();
        Exceptions::fake();

        $this->assertNull(app(ExpedientesContratacion::class)->crearAutomatico($proceso, null));

        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertNothingReported();
    }

    public function test_si_a_mano_se_creo_antes_la_automatica_no_lo_reporta(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $servicio = app(ExpedientesContratacion::class);
        // La creación a mano gana la carrera justo después de que la automática comprobó que
        // el proceso no tenía expediente.
        $hecho = false;
        PlantillaChecklist::retrieved(function () use (&$hecho, $servicio, $proceso, $plantilla) {
            if (! $hecho) {
                $hecho = true;
                $servicio->crear($proceso, [$plantilla->id], null);
            }
        });
        Exceptions::fake();

        $this->assertNull($servicio->crearAutomatico($proceso, null));

        $this->assertDatabaseCount('proyectos_expedientes', 1);
        Exceptions::assertNothingReported();
    }

    public function test_la_compatibilidad_se_decide_con_el_proceso_bloqueado(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        // Otro usuario cambia la modalidad después de que se leyó el proceso de la ruta.
        $hecho = false;
        ProcesoLicitacion::retrieved(function (ProcesoLicitacion $p) use (&$hecho) {
            if (! $hecho) {
                $hecho = true;
                DB::table('procesos_licitacion')->where('id', $p->id)->update(['modalidad' => $this->modalidad('minima_cuantia')]);
            }
        });

        $this->crearManual($proceso, [$plantilla->id])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El expediente no se puede crear.',
            'errores' => ['La plantilla "Licitación" no es compatible con la modalidad, la fuente o el sector del proceso.'],
        ]);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    // ------------------------------------------- cambiar el sector del proyecto

    /** Guarda el proyecto como desde su formulario, con el sector dado y sin tocar sus fuentes. */
    private function guardarProyecto(?int $sector, ?Proyecto $proyecto = null)
    {
        $proyecto = ($proyecto ?? $this->proyecto)->fresh();

        return $this->como()->postJson('/GestPro/guardarProyecto', [
            'accion' => 'Editar',
            'id' => $proyecto->id,
            'municipio' => $proyecto->municipio,
            'nombre' => $proyecto->nombre,
            'descripcion' => $proyecto->descripcion,
            'fechaInicio' => $proyecto->fecha_inicio,
            'estado' => $proyecto->estado,
            'fase' => $proyecto->fase,
            'presupuesto' => $proyecto->presupuesto,
            'entidadPresenta' => $proyecto->entidad_presenta,
            'entidadFinancia' => $proyecto->entidad_financia,
            'sector' => $sector,
        ]);
    }

    public function test_al_cambiar_el_sector_del_proyecto_se_crea_el_expediente_de_sus_procesos(): void
    {
        $vias = $this->plantilla('Vías', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $sin = $this->proceso(['codigo_proceso' => 'LP-001']);
        $otro = $this->proceso(['codigo_proceso' => 'LP-002']);
        $tiene = $this->proceso(['codigo_proceso' => 'LP-003']);
        $previo = app(ExpedientesContratacion::class)->crear($tiene, [$this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]])->id], null);

        $this->guardarProyecto($this->vias)->assertOk();

        foreach ([$sin, $otro] as $proceso) {
            $expediente = $proceso->fresh()->expediente;
            $this->assertNotNull($expediente);
            $this->assertSame([$vias->versionVigente()->id], $expediente->versionesPlantilla()->pluck('catalogo_versiones.id')->all());
        }
        $this->assertSame($previo->id, $tiene->fresh()->expediente->id);
        $this->assertDatabaseCount('proyectos_expedientes', 3);
        $this->assertSame(0, $this->proyecto->expedientes()->count());
    }

    public function test_guardar_el_proyecto_sin_cambiar_el_sector_no_intenta_crearlo(): void
    {
        $this->plantilla('Agua', ['todos_sectores' => false, 'sector_ids' => [$this->agua]]);
        $proceso = $this->proceso();

        $this->guardarProyecto($this->agua)->assertOk();

        $this->assertNull($proceso->fresh()->expediente);
    }

    public function test_al_cambiar_el_sector_con_cero_o_varias_compatibles_no_se_crea(): void
    {
        $this->plantilla('Vías A', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $this->plantilla('Vías B', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $this->proceso();

        $this->guardarProyecto($this->vias)->assertOk();
        $this->assertDatabaseCount('proyectos_expedientes', 0);

        $this->guardarProyecto(DB::table('sectores')->insertGetId(['nombre' => 'Salud', 'activo' => true]))->assertOk();
        $this->guardarProyecto(null)->assertOk();
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_si_falla_la_creacion_al_cambiar_el_sector_el_proyecto_se_guarda_igual(): void
    {
        $this->plantilla('Vías', ['todos_sectores' => false, 'sector_ids' => [$this->vias]]);
        $this->proceso();
        Exceptions::fake();
        DB::listen(function ($q) {
            if (str_starts_with($q->sql, 'insert into "proyectos_expedientes"')) {
                throw new \RuntimeException('Se cayó la conexión.');
            }
        });

        $this->guardarProyecto($this->vias)->assertOk()->assertJsonPath('success', 'Proyecto guardado correctamente');

        $this->assertSame($this->vias, (int) $this->proyecto->fresh()->sector);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
        Exceptions::assertReported(\RuntimeException::class);
    }

    // ------------------------------------------------- eliminar proyecto o proceso

    /** Debe comprobarse también en MariaDB: allí el borrado sin este paso falla. */
    public function test_eliminar_un_proyecto_con_expedientes_de_contratacion_y_viabilizacion(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $contratacion = $this->crearManual($proceso, [$plantilla->id])->assertCreated()->json('id');
        $viabilizacion = app(ExpedienteService::class)->crear($this->proyecto, ['plantilla_ids' => []], null);
        $otro = Proyecto::create(['nombre' => 'Otro']);
        $ajeno = $this->crearManual($this->proceso([], $otro), [$plantilla->id])->assertCreated()->json('id');

        $borrados = [];
        DB::listen(function ($q) use (&$borrados) {
            if (preg_match('/^delete from "(\w+)"/', $q->sql, $m)) {
                $borrados[] = $m[1];
            }
        });

        $this->como()->postJson('/GestPro/eliminarProyecto', ['id' => $this->proyecto->id])->assertOk();

        // SQLite resuelve las cascadas sin chocar con el restrict de proceso_id; MariaDB no
        // (ERROR 1451): los expedientes de contratación se borran antes que el proyecto.
        $this->assertSame(['proyectos_expedientes', 'proyectos'], $borrados);
        $this->assertDatabaseMissing('proyectos', ['id' => $this->proyecto->id]);
        $this->assertDatabaseMissing('procesos_licitacion', ['id' => $proceso->id]);
        $this->assertDatabaseMissing('proyectos_expedientes', ['id' => $contratacion]);
        $this->assertDatabaseMissing('proyectos_expedientes', ['id' => $viabilizacion->id]);
        $this->assertDatabaseMissing('proyectos_requisitos', ['expediente_id' => $contratacion]);
        $this->assertDatabaseHas('proyectos_expedientes', ['id' => $ajeno]);
    }

    public function test_un_proceso_con_expediente_no_se_elimina(): void
    {
        $plantilla = $this->plantilla('Licitación');
        $proceso = $this->proceso();
        $this->crearManual($proceso, [$plantilla->id])->assertCreated();

        $this->como()->deleteJson("/GestPro/procesos/{$proceso->id}")->assertStatus(422)
            ->assertJsonPath('mensaje', 'El proceso tiene un expediente de contratación: no se puede eliminar.');
        $this->assertDatabaseHas('procesos_licitacion', ['id' => $proceso->id]);
    }
}
