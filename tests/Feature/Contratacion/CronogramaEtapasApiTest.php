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
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cronograma de un expediente de contratación (R14, criterio 11): fecha programada y real de cada
 * etapa, la fecha real sugerida al completarse una etapa, el semáforo por etapa y los avisos del
 * expediente. Hoy es el 19 de noviembre de 2026 salvo que la prueba diga otra cosa.
 */
class CronogramaEtapasApiTest extends TestCase
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

    private function proceso(array $cambios = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->value('id'),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001',
            ...$cambios,
        ]);
    }

    private function expediente(?PlantillaChecklist $plantilla = null, ?ProcesoLicitacion $proceso = null): ProyectoExpediente
    {
        $plantilla ??= $this->plantilla();
        $proceso ??= $this->proceso();
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

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos)
    {
        return $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos);
    }

    /** El elemento de avance_por_etapa de la etapa dada. */
    private function deEtapa(array $avance, string $clave): array
    {
        return collect($avance)->firstWhere('etapa.id', $this->etapa($clave)->id);
    }

    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);

        return $servicio->confirmarConfiguracion($servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor), ['fase_sgr' => 3], $this->gestor);
    }

    // ------------------------------------------------------------ guardar fechas

    public function test_guarda_la_fecha_programada_y_la_real_con_su_historial(): void
    {
        $expediente = $this->expediente();
        $planeacion = $this->etapa('planeacion');

        $r = $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-25'])->assertOk();
        $r->assertJsonPath('etapa', [
            'etapa' => ['id' => $planeacion->id, 'nombre' => $planeacion->nombre, 'orden' => $planeacion->orden],
            'total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0,
            'fecha_programada' => '2026-11-25', 'fecha_real' => null, 'fecha_real_sugerida' => false,
            'completa' => false, 'alerta' => 'al_dia',
        ]);
        $this->assertSame(['etapa', 'avisos'], array_keys($r->json()));

        // Lo que no llega no cambia.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-18'])->assertOk()
            ->assertJsonPath('etapa.fecha_programada', '2026-11-25')
            ->assertJsonPath('etapa.fecha_real', '2026-11-18');
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => null])->assertOk()
            ->assertJsonPath('etapa.fecha_programada', null)
            ->assertJsonPath('etapa.fecha_real', '2026-11-18')
            ->assertJsonPath('etapa.alerta', 'sin_fecha');

        $fila = ExpedienteEtapa::where('expediente_id', $expediente->id)->sole();
        $this->assertSame($planeacion->id, $fila->etapa_id);
        $this->assertSame($this->gestor->id, $fila->usuario_actualizo_id);
        $this->assertNull($fila->fecha_programada);
        $this->assertSame('2026-11-18', $fila->fecha_real->format('Y-m-d'));

        $historial = DB::table('checklist_historial')->where('evento', 'etapa_fechas')->orderBy('id')->get()
            ->map(fn ($h) => [$h->entidad_tipo, (int) $h->expediente_id, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
        $base = ['etapa_id' => $planeacion->id, 'etapa' => $planeacion->nombre];
        $this->assertSame([
            ['expediente', $expediente->id, [...$base, 'fecha_programada' => null], [...$base, 'fecha_programada' => '2026-11-25']],
            ['expediente', $expediente->id, [...$base, 'fecha_real' => null], [...$base, 'fecha_real' => '2026-11-18']],
            ['expediente', $expediente->id, [...$base, 'fecha_programada' => '2026-11-25'], [...$base, 'fecha_programada' => null]],
        ], $historial);

        // Sin cambios, no hay registro nuevo.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-18'])->assertOk();
        $this->assertSame(3, DB::table('checklist_historial')->where('evento', 'etapa_fechas')->count());

        $this->assertSame('2026-11-18', $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion')['fecha_real']);
    }

    public function test_valida_las_fechas_y_rechaza_una_fecha_real_futura(): void
    {
        $expediente = $this->expediente();

        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '25/11/2026'])->assertStatus(422)
            ->assertJsonValidationErrors('fecha_programada');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => 20261118])->assertStatus(422)
            ->assertJsonValidationErrors('fecha_real');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-20'])->assertStatus(422)
            ->assertJsonPath('mensaje', 'La fecha real no puede ser posterior a hoy.')
            ->assertJsonPath('errors.fecha_real.0', 'La fecha real no puede ser posterior a hoy.');
        $this->fechas($expediente, 'planeacion', ['fecha_real' => self::HOY])->assertOk();
        // Una fecha programada en el pasado sí se admite.
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-01-01'])->assertOk();

        $this->assertSame(self::HOY, ExpedienteEtapa::sole()->fecha_real->format('Y-m-d'));
    }

    public function test_rechaza_viabilizacion_cerrado_etapa_sin_requisitos_y_lectores(): void
    {
        $expediente = $this->expediente();
        $viabilizacion = $this->viabilizacion();
        $datos = ['fecha_programada' => '2026-11-25'];

        $this->como(Rol::Consulta)->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa('planeacion')->id}", $datos)->assertForbidden();
        $this->fechas($viabilizacion, 'planeacion', $datos)->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente no es de contratación.']);
        $this->fechas($expediente, 'adendas', $datos)->assertStatus(422)
            ->assertExactJson(['mensaje' => 'La etapa no tiene requisitos en este expediente.']);
        $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/99999", $datos)->assertNotFound();

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();
        $this->fechas($expediente, 'planeacion', $datos)->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente está cerrado.']);

        $this->assertSame(0, ExpedienteEtapa::count());
        $this->assertSame(0, DB::table('checklist_historial')->where('evento', 'etapa_fechas')->count());
    }

    // ------------------------------------------------------------ semáforo (R14, criterio 11)

    public function test_criterio_11_vencida_proxima_al_dia_completa_y_sin_fecha(): void
    {
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'vencida');
        // Faltan 3 días: próxima; 4, al día.
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-22'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'proxima');
        $this->fechas($expediente, 'cierre', ['fecha_programada' => '2026-11-23'])->assertOk()
            ->assertJsonPath('etapa.alerta', 'al_dia');

        $r = $this->leer($expediente);
        $avance = $r->json('avance_por_etapa');
        $this->assertSame(['vencida', 'proxima', 'al_dia'], array_column($avance, 'alerta'));
        $this->assertSame([
            'etapa', 'total', 'cumplidos', 'porcentaje', 'peso_total', 'peso_cumplido',
            'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'completa', 'alerta',
        ], array_keys($avance[0]));
        $r->assertJsonPath('avisos.etapas_vencidas', 1)
            ->assertJsonPath('avisos.etapas_proximas', 1)
            ->assertJsonPath('avisos.hitos', ['completadas' => 0, 'total' => 3]);

        // Hoy mismo también es próxima; una completa con la fecha pasada ya no avisa.
        $this->fechas($expediente, 'cierre', ['fecha_programada' => self::HOY])->assertOk()->assertJsonPath('etapa.alerta', 'proxima');
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $r = $this->leer($expediente);
        $this->assertSame(['completa', 'proxima', 'proxima'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $r->assertJsonPath('avisos.etapas_vencidas', 0)
            ->assertJsonPath('avisos.etapas_proximas', 2)
            ->assertJsonPath('avisos.hitos', ['completadas' => 1, 'total' => 3]);

        // Al pasar los días, la próxima se vence.
        $this->travelTo('2026-11-23 08:00:00');
        $this->assertSame(['completa', 'vencida', 'vencida'], array_column($this->leer($expediente)->json('avance_por_etapa'), 'alerta'));
    }

    public function test_un_expediente_cerrado_no_avisa_vencimientos(): void
    {
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-01'])->assertOk();
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-20'])->assertOk();
        $this->marcar($expediente, 'Acta de cierre');
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        // Las etapas sin completar quedan sin completar, con o sin fecha; la completa, completa.
        $this->travelTo('2026-12-31 08:00:00');
        $r = $this->leer($expediente);
        $this->assertSame(['sin_completar', 'sin_completar', 'completa'], array_column($r->json('avance_por_etapa'), 'alerta'));
        $r->assertJsonPath('avisos.etapas_vencidas', 0)
            ->assertJsonPath('avisos.etapas_proximas', 0)
            ->assertJsonPath('avisos.sin_actualizacion', false);
    }

    // ------------------------------------------------------------ fecha real sugerida

    public function test_al_completarse_una_etapa_se_sugiere_hoy_y_al_dejar_de_estarlo_se_borra(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos')->assertJsonPath('avance_por_etapa.0.fecha_real', null);

        $r = $this->marcar($expediente, 'CDP');
        $this->assertSame([
            'fecha_programada' => null, 'fecha_real' => self::HOY, 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa',
        ], array_slice($r->json('avance_por_etapa.0'), 6));
        $this->assertSame(0, $r->json('avisos.etapas_vencidas'));
        $fila = ExpedienteEtapa::sole();
        $this->assertTrue($fila->fecha_real_sugerida);
        $this->assertNull($fila->usuario_actualizo_id);
        $this->assertSame(self::HOY, $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion')['fecha_real']);

        // Al día siguiente, otra acción no cambia la sugerida.
        $this->travelTo('2026-11-20 09:00:00');
        $this->marcar($expediente, 'Acta de cierre')->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY);

        $this->marcar($expediente, 'CDP', false)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', null)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', false)
            ->assertJsonPath('avance_por_etapa.0.completa', false);
        $this->assertNull(ExpedienteEtapa::where('etapa_id', $this->etapa('planeacion')->id)->sole()->fecha_real);
        // La de cierre se completó y tiene la suya, de hoy.
        $this->assertSame('2026-11-20', ExpedienteEtapa::where('etapa_id', $this->etapa('cierre')->id)->sole()->fecha_real->format('Y-m-d'));
        // La sugerencia no se registra como un cambio de fechas: la acción ya tiene el suyo.
        $this->assertSame(0, DB::table('checklist_historial')->where('evento', 'etapa_fechas')->count());
    }

    public function test_una_fecha_real_puesta_por_una_persona_se_conserva(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');

        // Corregida: deja de ser sugerida y sobrevive a que la etapa deje de estar completa.
        $this->fechas($expediente, 'planeacion', ['fecha_real' => '2026-11-17'])->assertOk()
            ->assertJsonPath('etapa.fecha_real', '2026-11-17')
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->assertSame([
            'etapa_id' => $this->etapa('planeacion')->id, 'etapa' => $this->etapa('planeacion')->nombre,
            'fecha_real' => self::HOY, 'fecha_real_sugerida' => true,
        ], json_decode((string) DB::table('checklist_historial')->where('evento', 'etapa_fechas')->sole()->antes, true));
        $this->marcar($expediente, 'CDP', false)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', '2026-11-17')
            ->assertJsonPath('avance_por_etapa.0.completa', false);

        // Confirmar la sugerida tal cual también la vuelve de una persona.
        $this->marcar($expediente, 'Pliego de condiciones');
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_real' => self::HOY])->assertOk()
            ->assertJsonPath('etapa.fecha_real_sugerida', false);
        $this->marcar($expediente, 'Pliego de condiciones', false)->assertJsonPath('avance_por_etapa.1.fecha_real', self::HOY);
    }

    public function test_la_sugerencia_sigue_al_no_aplica_y_a_los_propios(): void
    {
        $expediente = $this->expediente();
        $base = "/GestPro/expedientes/{$expediente->id}";
        $this->marcar($expediente, 'Estudios previos');

        $this->como()->postJson("{$base}/requisitos/{$this->fila($expediente, 'CDP')->id}/no-aplica", ['justificacion' => 'Ya se expidió.'])
            ->assertOk()->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)->assertJsonPath('avance_por_etapa.0.completa', true);

        $propio = $this->como()->postJson("{$base}/requisitos-propios", ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('planeacion')->id])
            ->assertCreated()->assertJsonPath('avance_por_etapa.0.fecha_real', null)->json('requisito.id');

        $this->como()->deleteJson("{$base}/requisitos-propios/{$propio}")->assertOk()
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
    }

    public function test_la_sugerencia_sigue_a_una_version_nueva(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $this->marcar($expediente, 'Estudios previos');

        $borrador = app(CatalogoService::class)->crearBorrador('Sin CDP', $plantilla);
        $cdp = $borrador->requisitos()->where('texto', 'CDP')->sole();
        $admin = $this->como(Rol::Administrador);
        $admin->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$cdp->id}")->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $borrador->id])
            ->assertOk()
            ->assertJsonPath('avance_por_etapa.0.completa', true)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
    }

    // ------------------------------------------------------------ avisos del expediente

    public function test_los_avisos_del_expediente_con_cierre_y_sin_actualizacion(): void
    {
        $this->travelTo('2026-11-01 10:00:00');
        $proceso = $this->proceso(['fecha_cierre' => '2026-11-21']);
        $expediente = $this->expediente(null, $proceso);

        // 14 días sin actividad: todavía no; 15: sí.
        $this->travelTo('2026-11-15 23:00:00');
        $this->leer($expediente)->assertJsonPath('avisos', [
            'etapas_vencidas' => 0,
            'etapas_proximas' => 0,
            'cierre_proximo' => false,
            'cierre_vencido' => false,
            'dias_para_cierre' => 6,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 14,
            'hitos' => ['completadas' => 0, 'total' => 3],
        ]);
        $this->travelTo('2026-11-16 05:30:00');
        $this->leer($expediente)->assertJsonPath('avisos.sin_actualizacion', true)->assertJsonPath('avisos.dias_sin_actualizacion', 15);

        // Una acción en el checklist es actividad.
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->marcar($expediente, 'Estudios previos')
            ->assertJsonPath('avisos.sin_actualizacion', false)
            ->assertJsonPath('avisos.dias_sin_actualizacion', 0)
            ->assertJsonPath('avisos.cierre_proximo', true)
            ->assertJsonPath('avisos.dias_para_cierre', 2);

        $this->travelTo('2026-11-22 10:00:00');
        $this->leer($expediente)->assertJsonPath('avisos.cierre_proximo', false)->assertJsonPath('avisos.cierre_vencido', true)
            ->assertJsonPath('avisos.dias_para_cierre', -1);
        // En evaluación, el cierre ya pasó: no avisa.
        $proceso->update(['estado' => ProcesoLicitacion::EVALUACION]);
        $this->leer($expediente)->assertJsonPath('avisos.cierre_vencido', false);
    }

    // ------------------------------------------------------------ zona horaria (M1)

    public function test_hoy_es_el_de_la_zona_de_contratacion_y_no_el_utc(): void
    {
        $this->assertSame('America/Bogota', config('contratacion.zona'));
        $expediente = $this->expediente();
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => self::HOY])->assertOk();

        // Las 20:00 del 19 en Bogotá son la 01:00 del 20 en UTC: la etapa de hoy no está vencida.
        $this->travelTo('2026-11-20 01:00:00');
        $this->assertSame('proxima', $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion')['alerta']);
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
        // El 20 todavía no llega en Bogotá.
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_real' => '2026-11-20'])->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_real' => 'La fecha real no puede ser posterior a hoy.']);
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => self::HOY])->assertOk()->assertJsonPath('etapa.alerta', 'proxima');

        // Con la zona UTC, el mismo instante ya es el 20.
        config(['contratacion.zona' => 'UTC']);
        $this->assertSame('vencida', $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'pliego_definitivo')['alerta']);
    }

    public function test_los_dias_sin_actualizacion_cuentan_en_la_zona_de_contratacion(): void
    {
        // Las 21:00 del 1 de noviembre en Bogotá (02:00 del 2 en UTC): la actividad es del 1.
        $this->travelTo('2026-11-02 02:00:00');
        $expediente = $this->expediente();

        $this->travelTo('2026-11-16 12:00:00');
        $this->leer($expediente)
            ->assertJsonPath('avisos.dias_sin_actualizacion', 15)
            ->assertJsonPath('avisos.sin_actualizacion', true);
    }

    // ------------------------------------------------------------ sugerencia solo al completarse (M2)

    public function test_una_fecha_real_vaciada_por_una_persona_no_se_vuelve_a_sugerir_mientras_siga_completa(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP')->assertJsonPath('avance_por_etapa.0.fecha_real', self::HOY);

        $this->fechas($expediente, 'planeacion', ['fecha_real' => null])->assertOk()
            ->assertJsonPath('etapa.fecha_real', null)
            ->assertJsonPath('etapa.fecha_real_sugerida', false)
            ->assertJsonPath('etapa.completa', true);

        // Otras acciones no la vuelven a sugerir, ni cambiar solo la programada.
        $this->marcar($expediente, 'Acta de cierre')->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-30'])->assertOk()->assertJsonPath('etapa.fecha_real', null);
        $this->marcar($expediente, 'Estudios previos')->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $planeacion = $this->deEtapa($this->leer($expediente)->json('avance_por_etapa'), 'planeacion');
        $this->assertSame([null, false], [$planeacion['fecha_real'], $planeacion['fecha_real_sugerida']]);

        // Deja de estar completa y se vuelve a completar: se sugiere otra vez.
        $this->marcar($expediente, 'CDP', false)->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $this->travelTo('2026-11-21 15:00:00');
        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.fecha_real', '2026-11-21')
            ->assertJsonPath('avance_por_etapa.0.fecha_real_sugerida', true);
    }

    public function test_una_etapa_con_fila_previa_recibe_la_sugerida_al_completarse(): void
    {
        $expediente = $this->expediente();
        // La fila nace con la fecha programada, y una persona vacía la real mientras no está completa.
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-25', 'fecha_real' => null])->assertOk();

        $this->marcar($expediente, 'Pliego de condiciones')
            ->assertJsonPath('avance_por_etapa.1.fecha_real', self::HOY)
            ->assertJsonPath('avance_por_etapa.1.fecha_real_sugerida', true)
            ->assertJsonPath('avance_por_etapa.1.fecha_programada', '2026-11-25');
    }

    // ------------------------------------------------------------ estados finales (B1)

    public function test_un_proceso_en_estado_final_no_cuenta_vencidas_ni_proximas(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente(null, $proceso);
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18'])->assertOk();
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-20'])->assertOk()
            ->assertJsonPath('avisos.etapas_vencidas', 1)->assertJsonPath('avisos.etapas_proximas', 1);

        foreach ([ProcesoLicitacion::CELEBRADO, ProcesoLicitacion::DESIERTO, ProcesoLicitacion::CANCELADO, ProcesoLicitacion::REVOCADO] as $estado) {
            $proceso->update(['estado' => $estado]);
            $this->leer($expediente)
                ->assertJsonPath('avisos.etapas_vencidas', 0)
                ->assertJsonPath('avisos.etapas_proximas', 0);
        }
    }

    // ------------------------------------------------------------ caminos sin avanceDe (B4)

    public function test_sincronizar_tambien_ajusta_la_fecha_sugerida(): void
    {
        $expediente = $this->expediente();
        // Cumplidos sin pasar por una acción del checklist: solo sincronizar los ve.
        $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('etapa_id', $this->etapa('planeacion')->id))
            ->update(['cumplido' => true]);

        app(ExpedienteService::class)->sincronizar($expediente->fresh());

        $fila = ExpedienteEtapa::where('expediente_id', $expediente->id)->sole();
        $this->assertSame(
            [$this->etapa('planeacion')->id, self::HOY, true],
            [$fila->etapa_id, $fila->fecha_real->format('Y-m-d'), $fila->fecha_real_sugerida]
        );
    }

    public function test_la_vista_previa_de_una_version_nueva_no_sugiere_y_aplicarla_si(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $this->marcar($expediente, 'Estudios previos');
        $borrador = app(CatalogoService::class)->crearBorrador('Sin CDP', $plantilla);
        $admin = $this->como(Rol::Administrador);
        $admin->deleteJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$borrador->requisitos()->where('texto', 'CDP')->sole()->id}")->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();
        $ruta = "/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva";

        $this->como()->getJson($ruta)->assertOk();
        $this->assertSame(0, ExpedienteEtapa::count());

        $this->como()->postJson($ruta, ['version_id' => $borrador->id])->assertOk();
        $this->assertTrue(ExpedienteEtapa::sole()->sugerida());
    }

    // ------------------------------------------------------------ fallos al ajustar la sugerida (B5)

    /** Hace fallar, una vez, la primera consulta que cumpla `$cumple` mientras `activo` sea true. */
    private function fallarUnaVez(\Closure $cumple): \stdClass
    {
        $estado = new \stdClass();
        $estado->activo = false;
        $estado->fallo = false;
        DB::connection()->beforeExecuting(function (string $sql) use ($estado, $cumple) {
            if ($estado->activo && ! $estado->fallo && $cumple($sql)) {
                $estado->fallo = true;
                throw new \RuntimeException('SQLSTATE[40001]: Deadlock found when trying to get lock');
            }
        });

        return $estado;
    }

    public function test_un_interbloqueo_al_bloquear_el_expediente_no_hace_fallar_la_accion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $cdp = $this->fila($expediente, 'CDP');
        // La acción ya quedó guardada; falla el bloqueo del expediente, la primera consulta del
        // ajuste de la sugerida que acompaña su respuesta.
        $cdp->update(['cumplido' => true]);
        $estado = $this->fallarUnaVez(fn (string $sql) => str_starts_with($sql, 'select') && str_contains($sql, '"proyectos_expedientes"'));
        $fresco = $expediente->fresh();
        $estado->activo = true;

        $avance = app(ExpedienteService::class)->avanceDe($fresco);

        $this->assertTrue($estado->fallo);
        $this->assertSame([true, null], [$avance['avance_por_etapa'][0]['completa'], $avance['avance_por_etapa'][0]['fecha_real']]);
        $this->assertSame(0, ExpedienteEtapa::count());
    }

    public function test_un_fallo_al_guardar_la_sugerida_no_hace_fallar_la_accion(): void
    {
        $expediente = $this->expediente();
        $this->marcar($expediente, 'Estudios previos');
        $estado = $this->fallarUnaVez(fn (string $sql) => str_starts_with($sql, 'insert into "expediente_etapas"'));
        $estado->activo = true;

        $this->marcar($expediente, 'CDP')
            ->assertJsonPath('avance_por_etapa.0.completa', true)
            ->assertJsonPath('avance_por_etapa.0.fecha_real', null);
        $this->assertTrue($estado->fallo);
        $this->assertTrue($this->fila($expediente, 'CDP')->cumplido);
        $this->assertSame(0, ExpedienteEtapa::count());
    }

    public function test_la_viabilizacion_no_cambia(): void
    {
        $viabilizacion = $this->viabilizacion();
        $fila = $viabilizacion->requisitos()->where('aplica', true)->orderBy('orden')->firstOrFail();

        $r = $this->leer($viabilizacion);
        $this->assertArrayNotHasKey('avisos', $r->json());
        $this->assertArrayNotHasKey('avance_por_etapa', $r->json());
        $parcial = $this->como()->patchJson("/GestPro/expedientes/{$viabilizacion->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();
        $this->assertSame(['requisito', 'progreso'], array_keys($parcial->json()));
        $this->assertSame(0, ExpedienteEtapa::count());
    }
}
