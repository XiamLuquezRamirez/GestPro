<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Revisión por etapa de un expediente de contratación (fase 5, R15, solo avisa): aprobar y
 * devolver una etapa, la fecha real al aprobar, el historial y el estado visible de la revisión
 * en avance_por_etapa. Hoy es el 19 de noviembre de 2026 salvo que la prueba diga otra cosa.
 */
class RevisionEtapasApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private Proyecto $proyecto;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora']);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        $usuario = $rol === Rol::Gestor ? $this->gestor : User::factory()->create(['rol' => $rol]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    /** Plantilla publicada: dos requisitos de planeación, uno del pliego definitivo y uno del cierre. */
    private function plantilla(): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => 'Licitación',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [Modalidad::where('clave', 'licitacion_publica')->value('id')],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        foreach ([
            ['Estudios previos', 'planeacion'], ['CDP', 'planeacion'],
            ['Pliego de condiciones', 'pliego_definitivo'], ['Acta de cierre', 'cierre'],
        ] as [$texto, $etapa]) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
                'texto' => $texto, 'etapa_id' => $this->etapa($etapa)->id,
            ])->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    private function expediente(?PlantillaChecklist $plantilla = null): ProyectoExpediente
    {
        $plantilla ??= $this->plantilla();
        $proceso = ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->value('id'),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
        ]);
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function leer(ProyectoExpediente $expediente)
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")->assertOk();
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
    }

    private function marcar(ProyectoExpediente $expediente, string $texto, bool $cumplido = true)
    {
        return $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, $texto)->id}", ['cumplido' => $cumplido])->assertOk();
    }

    private function aprobar(ProyectoExpediente $expediente, string $etapa, array $datos = [])
    {
        return $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/aprobar", $datos);
    }

    private function devolver(ProyectoExpediente $expediente, string $etapa, array $datos = [])
    {
        return $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/devolver", $datos);
    }

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos)
    {
        return $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos)->assertOk();
    }

    /** El elemento de avance_por_etapa de la etapa dada. */
    private function deEtapa(array $avance, string $clave): array
    {
        return collect($avance)->firstWhere('etapa.id', $this->etapa($clave)->id);
    }

    private function revisionDe(ProyectoExpediente $expediente, string $clave): array
    {
        return $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), $clave)['revision'];
    }

    private function estados(ProyectoExpediente $expediente): array
    {
        return array_map(fn (array $a) => $a['revision']['estado'] ?? null, $this->leer($expediente)->json('avance_por_etapa'));
    }

    /** Entradas del historial con ese evento: [antes, despues]. */
    private function historial(string $evento): array
    {
        return DB::table('checklist_historial')->where('evento', $evento)->orderBy('id')->get()
            ->map(fn ($h) => [json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);

        return $servicio->confirmarConfiguracion($servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor), ['fase_sgr' => 3], $this->gestor);
    }

    // ------------------------------------------------------------ aprobar

    public function test_aprueba_una_etapa_completa_con_su_historial(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')->assertJsonPath('avance_por_etapa.0.revision.estado', 'lista');

        $this->travelTo(self::HOY . ' 11:30:00');
        $r = $this->aprobar($expediente, 'planeacion', ['observacion' => '  Revisado con la oficina jurídica.  '])->assertOk();
        $this->assertSame(['etapa', 'avisos'], array_keys($r->json()));
        $r->assertJsonPath('etapa.etapa.id', $planeacion->id)
            ->assertJsonPath('etapa.revision', [
                'estado' => 'aprobada',
                'usuario' => 'Gestora',
                'fecha' => now()->toIso8601String(),
                'observacion' => 'Revisado con la oficina jurídica.',
                'pendientes_al_aprobar' => 0,
                'devuelta_at' => null,
            ])
            // La sugerida pasa a ser la de la aprobación, ya no sugerida.
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);

        $fila = ExpedienteEtapa::where('expediente_id', $expediente->id)->sole();
        $this->assertSame(
            [ExpedienteEtapa::APROBADA, $this->gestor->id, 0, 'Revisado con la oficina jurídica.', false],
            [$fila->estado_revision, $fila->usuario_reviso_id, $fila->pendientes_al_aprobar, $fila->observacion_revision, $fila->fecha_real_sugerida]
        );
        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([[
            [...$base, 'estado_revision' => 'sin_revisar', 'fecha_real' => self::HOY, 'fecha_real_sugerida' => true],
            [...$base, 'estado_revision' => 'aprobada', 'observacion' => 'Revisado con la oficina jurídica.', 'pendientes_al_aprobar' => 0, 'fecha_real' => self::HOY, 'fecha_real_sugerida' => false],
        ]], $this->historial('etapa_aprobada'));
        $this->assertSame('expediente', DB::table('checklist_historial')->where('evento', 'etapa_aprobada')->value('entidad_tipo'));

        $this->assertSame('aprobada', $this->revisionDe($expediente, 'planeacion')['estado']);
        // Ya aprobada: no se aprueba otra vez.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422)->assertExactJson(['mensaje' => 'La etapa ya está aprobada.']);
        $this->assertCount(1, $this->historial('etapa_aprobada'));
    }

    public function test_aprobar_con_pendientes_pide_confirmacion_y_la_registra(): void
    {
        $expediente = $this->expediente();

        $this->aprobar($expediente, 'planeacion')->assertStatus(422)->assertExactJson([
            'mensaje' => 'Faltan 2 requisitos de esta etapa. Confirma para aprobarla de todos modos.',
            'pendientes' => 2,
        ]);
        $this->marcar($expediente, 'Estudios previos');
        $this->aprobar($expediente, 'planeacion', ['confirmar' => false])->assertStatus(422)->assertExactJson([
            'mensaje' => 'Falta 1 requisito de esta etapa. Confirma para aprobarla de todos modos.',
            'pendientes' => 1,
        ]);
        $this->assertSame(0, ExpedienteEtapa::count());
        $this->assertSame([], $this->historial('etapa_aprobada'));

        $this->aprobar($expediente, 'planeacion', ['confirmar' => 'si'])->assertStatus(422)->assertJsonValidationErrors('confirmar');
        $r = $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk()
            ->assertJsonPath('etapa.revision.estado', 'aprobada')
            ->assertJsonPath('etapa.revision.pendientes_al_aprobar', 1)
            ->assertJsonPath('etapa.revision.observacion', null)
            ->assertJsonPath('etapa.completa', false)
            // Vacía: pasa a la fecha de la aprobación.
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->assertSame(ExpedienteEtapa::APROBADA, ExpedienteEtapa::sole()->estado_revision);
        $despues = $this->historial('etapa_aprobada')[0][1];
        $this->assertSame([1, true, null], [$despues['pendientes_al_aprobar'], $despues['con_pendientes'], $despues['observacion'] ?? null]);

        // Al completarse después, la fecha de la aprobación no cambia (no es sugerida).
        $this->travelTo('2026-11-21 09:00:00');
        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', false);
        $this->assertSame(1, $r->json('etapa.revision.pendientes_al_aprobar'));
    }

    public function test_al_aprobar_se_respeta_la_fecha_real_que_puso_una_persona(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-10']);

        $this->travelTo('2026-11-20 09:00:00');
        $this->aprobar($expediente, 'planeacion')->assertOk()
            ->assertJsonPath('etapa.fecha_real', '2026-11-10')
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $base = ['etapa_id' => $this->etapa('planeacion')->id, 'etapa' => $this->etapa('planeacion')->nombre];
        $this->assertSame([[
            [...$base, 'estado_revision' => 'sin_revisar'],
            [...$base, 'estado_revision' => 'aprobada', 'pendientes_al_aprobar' => 0],
        ]], $this->historial('etapa_aprobada'));

        // Sigue siendo corregible.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-12'])->assertJsonPath('etapa.fecha_real', '2026-11-12');
    }

    public function test_la_fecha_de_aprobacion_es_la_de_hoy_en_la_zona_de_contratacion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        // Las 20:00 del 19 en Bogotá son la 01:00 del 20 en UTC; y la sugerida, vaciada por una persona.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => null]);
        $this->travelTo('2026-11-20 01:00:00');

        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.fecha_real', self::HOY);
    }

    // ------------------------------------------------------------ devolver

    public function test_devolver_exige_la_observacion_y_la_registra(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');

        $this->devolver($expediente, 'planeacion')->assertStatus(422)
            ->assertJsonValidationErrors(['observacion' => 'Escribe por qué devuelves la etapa.']);
        $this->devolver($expediente, 'planeacion', ['observacion' => '   '])->assertStatus(422)->assertJsonValidationErrors('observacion');
        $this->devolver($expediente, 'planeacion', ['observacion' => str_repeat('a', 2001)])->assertStatus(422)
            ->assertJsonValidationErrors('observacion');

        $this->travelTo(self::HOY . ' 12:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Falta firmar los estudios previos.'])->assertOk()
            ->assertJsonPath('etapa.revision', [
                'estado' => 'devuelta',
                'usuario' => 'Gestora',
                'fecha' => now()->toIso8601String(),
                'observacion' => 'Falta firmar los estudios previos.',
                'pendientes_al_aprobar' => null,
                'devuelta_at' => now()->toIso8601String(),
            ]);

        // Devolver otra vez una devuelta se permite, con la nueva observación.
        $this->travelTo(self::HOY . ' 13:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Y el CDP.'])->assertOk()
            ->assertJsonPath('etapa.revision.observacion', 'Y el CDP.')
            ->assertJsonPath('etapa.revision.devuelta_at', now()->toIso8601String());

        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([
            [[...$base, 'estado_revision' => 'sin_revisar'], [...$base, 'estado_revision' => 'devuelta', 'observacion' => 'Falta firmar los estudios previos.']],
            [[...$base, 'estado_revision' => 'devuelta', 'observacion' => 'Falta firmar los estudios previos.'], [...$base, 'estado_revision' => 'devuelta', 'observacion' => 'Y el CDP.']],
        ], $this->historial('etapa_devuelta'));
        $fila = ExpedienteEtapa::sole();
        $this->assertSame([ExpedienteEtapa::DEVUELTA, null, null], [$fila->estado_revision, $fila->pendientes_al_aprobar, $fila->fecha_real]);
    }

    public function test_devolver_una_aprobada_vuelve_la_fecha_de_aprobacion_a_la_regla_de_la_sugerida(): void
    {
        $expediente = $this->expediente();
        // Completa: la fecha de la aprobación vuelve a ser sugerida.
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->travelTo('2026-11-20 09:00:00');
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Revisar el CDP.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', self::HOY)
            ->assertJsonPath('etapa.fecha_real_sugerida', true)
            ->assertJsonPath('etapa.revision.estado', 'devuelta');
        $this->assertSame(
            ['fecha_real' => self::HOY, 'fecha_real_sugerida' => true],
            array_intersect_key($this->historial('etapa_devuelta')[0][1], ['fecha_real' => 0, 'fecha_real_sugerida' => 0])
        );
        // Y como sugerida, se borra si la etapa deja de estar completa.
        $this->marcar($expediente, 'CDP', false)->assertJsonPath('avance_por_etapa.0.fecha_real', null);

        // Sin completar: la fecha de la aprobación se vacía.
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-20');
        $this->devolver($expediente, 'pliego_definitivo', ['observacion' => 'Falta el pliego.'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', null)
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
    }

    public function test_devolver_conserva_la_fecha_real_de_una_persona(): void
    {
        $expediente = $this->expediente();
        // Puesta antes de aprobar.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-10']);
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->devolver($expediente, 'planeacion', ['observacion' => 'No.'])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-10');

        // Corregida después de aprobar.
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.fecha_real', self::HOY);
        $this->fechas($expediente, 'cierre', ['fecha_real' => '2026-11-15']);
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk()->assertJsonPath('etapa.fecha_real', '2026-11-15');
    }

    // ------------------------------------------------------------ rechazos

    public function test_rechaza_lectores_viabilizacion_etapa_sin_requisitos_y_cerrado(): void
    {
        $expediente = $this->expediente();
        $viabilizacion = $this->viabilizacion();
        $planeacion = $this->etapa('planeacion')->id;
        $devolucion = ['observacion' => 'No.'];

        foreach (['aprobar' => ['confirmar' => true], 'devolver' => $devolucion] as $accion => $datos) {
            $this->como(Rol::Consulta)->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$planeacion}/{$accion}", $datos)->assertForbidden();
            $this->como()->postJson("/GestPro/expedientes/{$viabilizacion->id}/etapas/{$planeacion}/{$accion}", $datos)->assertStatus(422)
                ->assertExactJson(['mensaje' => 'El expediente no es de contratación.']);
            $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa('adendas')->id}/{$accion}", $datos)->assertStatus(422)
                ->assertExactJson(['mensaje' => 'La etapa no tiene requisitos en este expediente.']);
            $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/99999/{$accion}", $datos)->assertNotFound();
        }

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertStatus(422)->assertExactJson(['mensaje' => 'El expediente está cerrado.']);
        $this->devolver($expediente, 'planeacion', $devolucion)->assertStatus(422)->assertExactJson(['mensaje' => 'El expediente está cerrado.']);

        $this->assertSame(0, ExpedienteEtapa::count());
        $this->assertSame(0, DB::table('checklist_historial')->whereIn('evento', ['etapa_aprobada', 'etapa_devuelta'])->count());
    }

    // ------------------------------------------------------------ estado visible

    public function test_el_estado_visible_pendiente_lista_y_aprobada(): void
    {
        $expediente = $this->expediente();
        $avance = $this->leer($expediente)->json('avance_por_etapa');
        $this->assertSame(['pendiente', 'pendiente', 'pendiente'], array_column(array_column($avance, 'revision'), 'estado'));
        $this->assertSame(
            ['estado' => 'pendiente', 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            $avance[0]['revision']
        );

        $this->marcar($expediente, 'Acta de cierre');
        $this->assertSame(['pendiente', 'pendiente', 'lista'], $this->estados($expediente));
        $this->aprobar($expediente, 'cierre')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();
        $this->assertSame(['pendiente', 'aprobada', 'aprobada'], $this->estados($expediente));
    }

    public function test_una_aprobada_con_un_cambio_en_su_checklist_pasa_a_aprobada_con_cambios(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->aprobar($expediente, 'planeacion')->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();

        // Un evento de otra etapa no la cambia; uno de la suya, sí, pero la aprobación no se pierde.
        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'Acta de cierre');
        $this->assertSame(['aprobada', 'aprobada', 'lista'], $this->estados($expediente));
        $this->travelTo(self::HOY . ' 10:10:00');
        $r = $this->marcar($expediente, 'CDP', false);
        $this->assertSame('aprobada_con_cambios', $r->json('avance_por_etapa.0.revision.estado'));
        $this->assertSame('aprobada', $r->json('avance_por_etapa.1.revision.estado'));
        $this->assertSame(ExpedienteEtapa::APROBADA, ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->value('estado_revision'));
        // Ya está aprobada: no se aprueba otra vez.
        $this->aprobar($expediente, 'planeacion')->assertStatus(422);

        // Una observación en un requisito de la etapa también es un cambio.
        $this->travelTo(self::HOY . ' 10:15:00');
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Pliego de condiciones')->id}", ['observacion' => 'Ver anexo.'])->assertOk();
        $this->assertSame('aprobada_con_cambios', $this->revisionDe($expediente, 'pliego_definitivo')['estado']);
    }

    public function test_los_propios_de_la_etapa_y_una_version_nueva_cambian_la_aprobada(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $base = "/GestPro/expedientes/{$expediente->id}";
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();
        $this->aprobar($expediente, 'cierre', ['confirmar' => true])->assertOk();

        // Un propio que se agrega a otra etapa y se elimina: su registro sobrevive a la fila.
        $this->travelTo(self::HOY . ' 10:05:00');
        $propio = $this->como()->postJson("{$base}/requisitos-propios", ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('pliego_definitivo')->id])
            ->assertCreated()->json('requisito.id');
        $this->como()->deleteJson("{$base}/requisitos-propios/{$propio}")->assertOk();
        $this->assertSame(['aprobada', 'aprobada_con_cambios', 'aprobada'], $this->estados($expediente));

        // Una versión nueva de la plantilla cambia los requisitos: todas las aprobadas avisan.
        $borrador = app(CatalogoService::class)->crearBorrador('Sin CDP', $plantilla);
        $admin = $this->como(Rol::Administrador);
        $admin->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$borrador->requisitos()->where('texto', 'CDP')->sole()->id}")->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();
        $this->travelTo(self::HOY . ' 10:10:00');
        $this->como()->postJson("{$base}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $borrador->id])->assertOk()
            ->assertJsonPath('avance_por_etapa.0.revision.estado', 'aprobada_con_cambios')
            ->assertJsonPath('avance_por_etapa.2.revision.estado', 'aprobada_con_cambios');
    }

    public function test_una_devuelta_con_un_cambio_en_su_checklist_muestra_su_estado_y_la_observacion(): void
    {
        $expediente = $this->expediente();
        $this->devolver($expediente, 'planeacion', ['observacion' => 'Faltan los estudios previos.'])->assertOk();
        $devuelta = now()->toIso8601String();
        $this->assertSame(['devuelta', 'pendiente', 'pendiente'], $this->estados($expediente));

        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->assertSame('devuelta', $this->revisionDe($expediente, 'planeacion')['estado']);

        $this->travelTo(self::HOY . ' 10:10:00');
        $this->marcar($expediente, 'Estudios previos')->assertJsonPath('avance_por_etapa.0.revision', [
            'estado' => 'pendiente',
            'usuario' => 'Gestora',
            'fecha' => $devuelta,
            'observacion' => 'Faltan los estudios previos.',
            'pendientes_al_aprobar' => null,
            'devuelta_at' => $devuelta,
        ]);
        $this->assertSame(ExpedienteEtapa::DEVUELTA, ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->value('estado_revision'));
        $this->marcar($expediente, 'CDP');
        $this->assertSame('lista', $this->revisionDe($expediente, 'planeacion')['estado']);

        // Y se puede aprobar.
        $this->aprobar($expediente, 'planeacion')->assertOk()->assertJsonPath('etapa.revision.estado', 'aprobada')
            ->assertJsonPath('etapa.revision.devuelta_at', null);
    }

    public function test_aviso_de_la_etapa_anterior_sin_aprobar(): void
    {
        $expediente = $this->expediente();
        $avisos = fn () => array_column($this->leer($expediente)->json('avance_por_etapa'), 'aviso_etapa_anterior');
        $this->assertSame([false, false, false], $avisos());

        // El cierre tiene algo cumplido y hay etapas anteriores sin aprobar.
        $this->marcar($expediente, 'Acta de cierre');
        $this->marcar($expediente, 'Estudios previos');
        $this->assertSame([false, false, true], $avisos());

        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->assertSame([false, false, true], $avisos());
        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk()->assertJsonPath('etapa.aviso_etapa_anterior', false);
        $this->assertSame([false, false, false], $avisos());
    }

    public function test_el_grupo_sin_etapa_no_tiene_revision(): void
    {
        $expediente = $this->expediente();
        // Un requisito que quedó sin etapa (datos anteriores) va al final.
        RequisitoCatalogo::where('texto', 'Acta de cierre')->update(['etapa_id' => null]);
        $this->marcar($expediente, 'Acta de cierre')
            ->assertJsonPath('avance_por_etapa.2.etapa', null)
            ->assertJsonPath('avance_por_etapa.2.revision', null)
            ->assertJsonPath('avance_por_etapa.2.aviso_etapa_anterior', false);
    }

    // ------------------------------------------------------------ consultas en lote

    private function contarConsultas(string $url): int
    {
        $peticion = $this->como(Rol::Consulta);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $peticion->getJson($url)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    public function test_el_estado_de_la_revision_no_hace_una_consulta_por_etapa(): void
    {
        $expediente = $this->expediente();
        $url = "/GestPro/expedientes/{$expediente->id}";
        $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
        $this->travelTo(self::HOY . ' 10:05:00');
        $this->marcar($expediente, 'CDP');
        $conUna = $this->contarConsultas($url);

        $this->aprobar($expediente, 'pliego_definitivo', ['confirmar' => true])->assertOk();
        $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk();
        $this->travelTo(self::HOY . ' 10:10:00');
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->marcar($expediente, 'Acta de cierre');
        $this->marcar($expediente, 'Estudios previos');

        $this->assertSame($conUna, $this->contarConsultas($url));
        $this->assertSame(['aprobada_con_cambios', 'aprobada_con_cambios', 'lista'], $this->estados($expediente));
    }

    public function test_la_lista_de_procesos_no_hace_una_consulta_por_proceso_con_revisiones(): void
    {
        $plantilla = $this->plantilla();
        $url = "/GestPro/proyectos/{$this->proyecto->id}/procesos";
        $revisado = function () use ($plantilla) {
            $expediente = $this->expediente($plantilla);
            $this->aprobar($expediente, 'planeacion', ['confirmar' => true])->assertOk();
            $this->devolver($expediente, 'cierre', ['observacion' => 'No.'])->assertOk();
            $this->travel(1)->minutes();
            $this->marcar($expediente, 'CDP');
            $this->marcar($expediente, 'Acta de cierre');
        };
        $revisado();
        $conUno = $this->contarConsultas($url);

        foreach (range(1, 3) as $i) {
            $revisado();
        }

        $this->assertSame($conUno, $this->contarConsultas($url));
    }

    public function test_la_viabilizacion_no_cambia(): void
    {
        $r = $this->leer($this->viabilizacion());
        $this->assertArrayNotHasKey('avance_por_etapa', $r->json());
        $this->assertSame(0, ExpedienteEtapa::count());
    }
}
