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
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Checklist de un expediente de contratación: etapas de cada fila, avance por etapa, requisitos
 * propios con etapa, cierre con aviso de pendientes, expediente cerrado congelado y el bloque del
 * expediente en la API del proceso. La API de viabilización no cambia.
 */
class ExpedienteContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private const CERRADO = 'El expediente está cerrado.';

    private Proyecto $proyecto;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function modalidad(string $clave = 'licitacion_publica'): int
    {
        return Modalidad::where('clave', $clave)->firstOrFail()->id;
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function etapaJson(string $clave): array
    {
        $etapa = $this->etapa($clave);

        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden];
    }

    /** Las claves de la revisión (fase 5) de una etapa sin revisar: su estado visible y el aviso de la etapa anterior. */
    private function sinRevisar(string $estado, bool $avisoAnterior = false): array
    {
        return [
            'revision' => ['estado' => $estado, 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            'aviso_etapa_anterior' => $avisoAnterior,
        ];
    }

    /**
     * Plantilla de contratación publicada, por la API: licitación pública, todas las fuentes y
     * todos los sectores. Por defecto, tres requisitos: dos de planeación (uno opcional) y uno
     * del pliego definitivo, creados en desorden para comprobar el orden del catálogo.
     */
    private function plantilla(string $nombre = 'Licitación', array $requisitos = []): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => $nombre,
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        $requisitos = $requisitos ?: [
            ['texto' => 'Pliego de condiciones', 'etapa_id' => $this->etapa('pliego_definitivo')->id],
            ['texto' => 'Estudios previos', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'Análisis del sector', 'etapa_id' => $this->etapa('planeacion')->id, 'caracter' => RequisitoCatalogo::OPCIONAL],
        ];
        foreach ($requisitos as $requisito) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", $requisito)->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    private function proceso(array $cambios = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001',
            ...$cambios,
        ]);
    }

    /** Expediente de contratación de un proceso nuevo, creado por la API. */
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

    private function cumplir(ProyectoExpediente $expediente, ProyectoRequisito $fila)
    {
        return $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();
    }

    /** Expediente de viabilización del proyecto, con la plantilla SGR y la configuración confirmada. */
    private function viabilizacion(): ProyectoExpediente
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor);

        return $servicio->confirmarConfiguracion($expediente, ['fase_sgr' => 3], $this->gestor);
    }

    // ------------------------------------------------------------ respuesta

    public function test_la_respuesta_trae_el_proceso_las_etapas_y_el_avance_por_etapa(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso();
        $expediente = $this->expediente($plantilla, $proceso);
        $r = $this->leer($expediente);

        $this->assertSame([
            'id', 'proyecto', 'plantillas', 'tiene_sgr', 'configurado', 'estado', 'usuario_creo', 'creado', 'actualizado',
            'tipo', 'proceso', 'fecha_cierre', 'usuario_cierre',
            'progreso', 'avance_por_etapa', 'avisos', 'requisitos', 'avisos_duplicados', 'avisos_duplicados_total', 'retenidos',
        ], array_keys($r->json()));
        $r->assertJsonPath('tipo', ProyectoExpediente::TIPO_CONTRATACION)
            ->assertJsonPath('proceso', [
                'id' => $proceso->id,
                'codigo_proceso' => 'LP-001',
                'objeto' => 'Construcción del acueducto',
                'estado' => ProcesoLicitacion::PREPARACION,
                'modalidad' => ['id' => $this->modalidad(), 'nombre' => Modalidad::find($this->modalidad())->nombre],
            ])
            ->assertJsonPath('plantillas.0.plantilla_id', $plantilla->id)
            ->assertJsonPath('plantillas.0.compatible', true)
            ->assertJsonPath('fecha_cierre', null)
            ->assertJsonPath('usuario_cierre', null)
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0]);

        $etapas = collect($r->json('requisitos'))->pluck('etapa', 'requisito.texto')->all();
        $this->assertSame([
            'Pliego de condiciones' => $this->etapaJson('pliego_definitivo'),
            'Estudios previos' => $this->etapaJson('planeacion'),
            'Análisis del sector' => $this->etapaJson('planeacion'),
        ], $etapas);
        // En el orden del catálogo de etapas, no en el de los requisitos.
        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
        ], $r->json('avance_por_etapa'));
    }

    public function test_la_plantilla_deja_de_ser_compatible_al_cambiar_la_modalidad_del_proceso(): void
    {
        $expediente = $this->expediente();
        $expediente->proceso->update(['modalidad' => $this->modalidad('minima_cuantia')]);

        $this->leer($expediente)->assertJsonPath('plantillas.0.compatible', false);
    }

    public function test_el_avance_por_etapa_cuenta_como_el_progreso(): void
    {
        $expediente = $this->expediente($this->plantilla('Licitación', [
            ['texto' => 'Estudios previos', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'CDP', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'Plan de adquisiciones', 'etapa_id' => $this->etapa('planeacion')->id],
            ['texto' => 'Pliego de condiciones', 'etapa_id' => $this->etapa('pliego_definitivo')->id],
            ['texto' => 'Acta de cierre', 'etapa_id' => $this->etapa('cierre')->id],
        ]));
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}/no-aplica", [
            'justificacion' => 'Ya se expidió con el proyecto.',
        ])->assertOk();
        // Un requisito que quedó sin etapa (datos anteriores) va al final.
        RequisitoCatalogo::where('texto', 'Acta de cierre')->update(['etapa_id' => null]);

        $r = $this->leer($expediente);

        $this->assertSame([
            ['etapa' => $this->etapaJson('planeacion'), 'total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
            ['etapa' => $this->etapaJson('pliego_definitivo'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')],
            ['etapa' => null, 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', 'revision' => null, 'aviso_etapa_anterior' => false],
        ], $r->json('avance_por_etapa'));
        $r->assertJsonPath('progreso', ['total' => 4, 'cumplidos' => 1, 'porcentaje' => 25, 'peso_total' => 4, 'peso_cumplido' => 1]);
        $this->assertSame(
            $r->json('progreso.total'),
            array_sum(array_column($r->json('avance_por_etapa'), 'total'))
        );
    }

    public function test_marcar_responde_tambien_el_avance_por_etapa(): void
    {
        $expediente = $this->expediente();

        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'))
            ->assertJsonPath('requisito.etapa', $this->etapaJson('planeacion'))
            ->assertJsonPath('progreso', ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1])
            ->assertJsonPath('avance_por_etapa.0', ['etapa' => $this->etapaJson('planeacion'), 'total' => 1, 'cumplidos' => 1, 'porcentaje' => 100, 'peso_total' => 1, 'peso_cumplido' => 1, 'fecha_programada' => null, 'fecha_real' => now(config('contratacion.zona'))->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista')]);
    }

    public function test_la_api_de_viabilizacion_no_trae_las_claves_de_contratacion(): void
    {
        $viabilizacion = $this->viabilizacion();
        $r = $this->leer($viabilizacion);

        $this->assertSame([
            'id', 'proyecto', 'plantillas', 'tiene_sgr', 'fase_sgr', 'configurado', 'estado', 'fecha_radicado',
            'usuario_radico', 'usuario_creo', 'creado', 'actualizado',
            'configuracion', 'progreso', 'requisitos', 'avisos_duplicados', 'avisos_duplicados_total', 'retenidos',
        ], array_keys($r->json()));
        $this->assertNotEmpty($r->json('requisitos'));
        $this->assertSame([
            'id', 'estado', 'caracter', 'cuenta', 'no_aplica', 'responsable', 'cumplido', 'fecha_cumplido',
            'usuario_cumplido', 'observacion', 'soporte', 'requiere_aprobacion_no_aplica', 'requisito', 'plantilla',
            'origenes', 'propio', 'categoria',
        ], array_keys($r->json('requisitos.0')));
        $this->assertTrue($r->json('plantillas.0.compatible'));

        $fila = $viabilizacion->requisitos()->firstOrFail();
        $this->como()->patchJson("/GestPro/expedientes/{$viabilizacion->id}/requisitos/{$fila->id}", ['observacion' => 'Revisado'])
            ->assertOk()->assertJsonMissingPath('avance_por_etapa')->assertJsonMissingPath('requisito.etapa');
    }

    // ---------------------------------------------------- requisitos propios

    public function test_un_propio_de_contratacion_exige_una_etapa_activa(): void
    {
        $expediente = $this->expediente();
        $url = "/GestPro/expedientes/{$expediente->id}/requisitos-propios";
        $inactiva = $this->etapa('adendas');
        $inactiva->update(['activo' => false]);

        $this->como()->postJson($url, ['titulo' => 'Póliza'])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'Elige la etapa de contratación del requisito.');
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => 99999])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'La etapa de contratación con id 99999 no existe.');
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $inactiva->id])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', "La etapa \"{$inactiva->nombre}\" está inactiva.");
        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => '3'])->assertStatus(422)
            ->assertJsonValidationErrors('etapa_propia_id');
        $this->assertSame(0, $expediente->requisitos()->whereNull('requisito_catalogo_id')->count());

        $this->como()->postJson($url, ['titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id])
            ->assertCreated()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('adjudicacion'))
            ->assertJsonPath('avance_por_etapa.2', ['etapa' => $this->etapaJson('adjudicacion'), 'total' => 1, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 1, 'peso_cumplido' => 0, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')]);

        $propio = $expediente->requisitos()->whereNull('requisito_catalogo_id')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, $propio->etapa_propia_id);
        $historial = DB::table('checklist_historial')->where('evento', 'requisito_propio_agregado')->sole();
        $this->assertSame($this->etapa('adjudicacion')->id, json_decode($historial->despues, true)['etapa_propia_id']);
    }

    public function test_editar_un_propio_conserva_o_cambia_su_etapa(): void
    {
        $expediente = $this->expediente();
        $adendas = $this->etapa('adendas');
        $id = $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos-propios", [
            'titulo' => 'Póliza', 'etapa_propia_id' => $adendas->id,
        ])->assertCreated()->json('requisito.id');
        $url = "/GestPro/expedientes/{$expediente->id}/requisitos-propios/{$id}";
        $adendas->update(['activo' => false]);

        // Sin el campo, no cambia; la que ya tiene se acepta aunque esté inactiva.
        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad'])->assertOk()
            ->assertJsonPath('requisito.etapa.id', $adendas->id);
        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad', 'etapa_propia_id' => $adendas->id])->assertOk();
        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad', 'etapa_propia_id' => null])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'Elige la etapa de contratación del requisito.');

        $this->como()->putJson($url, ['titulo' => 'Póliza de seriedad', 'etapa_propia_id' => $this->etapa('cierre')->id])->assertOk()
            ->assertJsonPath('requisito.etapa', $this->etapaJson('cierre'));
        $historial = DB::table('checklist_historial')->where('evento', 'requisito_propio_editado')->orderByDesc('id')->first();
        $this->assertSame(['etapa_propia_id' => $adendas->id], json_decode($historial->antes, true));
        $this->assertSame(['etapa_propia_id' => $this->etapa('cierre')->id], json_decode($historial->despues, true));
    }

    public function test_un_propio_de_viabilizacion_no_lleva_etapa(): void
    {
        $viabilizacion = $this->viabilizacion();
        $url = "/GestPro/expedientes/{$viabilizacion->id}/requisitos-propios";

        $this->como()->postJson($url, ['titulo' => 'Acta', 'etapa_propia_id' => $this->etapa('planeacion')->id])->assertStatus(422)
            ->assertJsonPath('errors.etapa_propia_id.0', 'Los requisitos propios de un expediente de viabilización no llevan etapa de contratación.');
        $this->assertSame(0, $viabilizacion->requisitos()->whereNull('requisito_catalogo_id')->count());

        $r = $this->como()->postJson($url, ['titulo' => 'Acta'])->assertCreated()->assertJsonMissingPath('requisito.etapa');
        $this->assertNull(ProyectoRequisito::find($r->json('requisito.id'))->etapa_propia_id);
        $historial = DB::table('checklist_historial')->where('evento', 'requisito_propio_agregado')->sole();
        $this->assertArrayNotHasKey('etapa_propia_id', json_decode($historial->despues, true));
    }

    // ---------------------------------------------------------------- cierre

    public function test_cerrar_con_pendientes_sin_confirmar_avisa_y_no_cierra(): void
    {
        $expediente = $this->expediente();
        $historial = DB::table('checklist_historial')->count();

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")
            ->assertStatus(422)
            ->assertExactJson([
                // Fase 5, decisión 3: también avisa las etapas sin aprobar.
                'mensaje' => 'Faltan 2 requisitos por cumplir y 2 etapas sin aprobar. Confirma para cerrar el expediente de todos modos.',
                'pendientes' => [
                    'total' => 2,
                    'por_etapa' => [
                        ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                        ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                    ],
                    'etapas_sin_aprobar' => [
                        ['etapa' => $this->etapaJson('planeacion'), 'estado' => 'pendiente'],
                        ['etapa' => $this->etapaJson('pliego_definitivo'), 'estado' => 'pendiente'],
                    ],
                ],
            ]);
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => false])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Falta 1 requisito por cumplir y 2 etapas sin aprobar. Confirma para cerrar el expediente de todos modos.')
            ->assertJsonPath('pendientes.por_etapa', [['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1]]);

        $this->assertSame(ProyectoExpediente::BORRADOR, $expediente->fresh()->estado);
        $this->assertSame($historial + 1, DB::table('checklist_historial')->count()); // solo la marca
    }

    public function test_cerrar_confirmando_cierra_con_pendientes_y_lo_registra(): void
    {
        $expediente = $this->expediente();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(10, 0));

        $r = $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk()
            ->assertJsonPath('estado', ProyectoExpediente::CERRADO)
            ->assertJsonPath('usuario_cierre', 'Gestora');
        $this->assertSame(now()->toIso8601String(), $r->json('fecha_cierre'));

        $cerrado = $expediente->fresh();
        $this->assertSame(ProyectoExpediente::CERRADO, $cerrado->estado);
        $this->assertSame($this->gestor->id, (int) $cerrado->usuario_radicado_id);
        $entrada = DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->sole();
        $this->assertSame($expediente->id, (int) $entrada->expediente_id);
        $this->assertSame([
            'requisitos' => 2,
            'cumplidos' => 0,
            'pendientes' => [
                'total' => 2,
                'por_etapa' => [
                    ['etapa' => $this->etapaJson('planeacion'), 'pendientes' => 1],
                    ['etapa' => $this->etapaJson('pliego_definitivo'), 'pendientes' => 1],
                ],
                // Fase 5, decisión 3: también las etapas sin aprobar.
                'etapas_sin_aprobar' => [
                    ['etapa' => $this->etapaJson('planeacion'), 'estado' => 'pendiente'],
                    ['etapa' => $this->etapaJson('pliego_definitivo'), 'estado' => 'pendiente'],
                ],
            ],
        ], json_decode($entrada->despues, true));
    }

    public function test_sin_pendientes_cierra_sin_confirmar(): void
    {
        $expediente = $this->expediente();
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));
        $this->cumplir($expediente, $this->fila($expediente, 'Pliego de condiciones'));
        // Fase 5, decisión 3: sin pendientes es también con las etapas aprobadas.
        foreach (['planeacion', 'pliego_definitivo'] as $etapa) {
            $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/aprobar")->assertOk();
        }

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertOk()
            ->assertJsonPath('estado', ProyectoExpediente::CERRADO)
            ->assertJsonPath('progreso.porcentaje', 100);
        $this->assertSame(
            ['requisitos' => 2, 'cumplidos' => 2, 'pendientes' => ['total' => 0, 'por_etapa' => [], 'etapas_sin_aprobar' => []]],
            json_decode(DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->value('despues'), true)
        );
    }

    public function test_cerrar_exige_permiso_y_un_expediente_de_contratacion_abierto(): void
    {
        $expediente = $this->expediente();
        $viabilizacion = $this->viabilizacion();

        $this->como(Rol::Consulta)->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertForbidden();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => 'si'])->assertStatus(422)
            ->assertJsonValidationErrors('confirmar');
        $this->como()->postJson("/GestPro/expedientes/{$viabilizacion->id}/cerrar", ['confirmar' => true])->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente es de viabilización: se radica, no se cierra.']);
        $this->assertSame(ProyectoExpediente::BORRADOR, $viabilizacion->fresh()->estado);

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertStatus(422)
            ->assertExactJson(['mensaje' => self::CERRADO]);
        $this->assertSame(1, DB::table('checklist_historial')->where('evento', 'expediente_cerrado')->count());
    }

    public function test_un_expediente_cerrado_queda_congelado(): void
    {
        Storage::fake(ExpedienteService::DISCO);
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);
        $fila = $this->fila($expediente, 'Estudios previos');
        $base = "/GestPro/expedientes/{$expediente->id}";
        $this->como()->postJson("{$base}/requisitos/{$fila->id}/soporte", [
            'archivo' => UploadedFile::fake()->create('cdp.pdf', 10, 'application/pdf'),
        ])->assertOk();
        $propio = $this->como()->postJson("{$base}/requisitos-propios", [
            'titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('cierre')->id,
        ])->assertCreated()->json('requisito.id');
        $this->como()->postJson("{$base}/cerrar", ['confirmar' => true])->assertOk();
        $antes = [
            DB::table('proyectos_expedientes')->where('id', $expediente->id)->first(),
            DB::table('proyectos_requisitos')->where('expediente_id', $expediente->id)->orderBy('id')->get(),
            DB::table('checklist_historial')->count(),
        ];

        $pedidos = [
            ['patchJson', "{$base}/requisitos/{$fila->id}", ['cumplido' => true]],
            ['postJson', "{$base}/requisitos/{$fila->id}/soporte", ['archivo' => UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf')]],
            ['deleteJson', "{$base}/requisitos/{$fila->id}/soporte", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/no-aplica", ['justificacion' => 'No aplica']],
            ['deleteJson', "{$base}/requisitos/{$fila->id}/no-aplica", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/aplica", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/no-aplica/aprobar", []],
            ['postJson', "{$base}/requisitos/{$fila->id}/no-aplica/rechazar", []],
            ['postJson', "{$base}/requisitos-propios", ['titulo' => 'Otro', 'etapa_propia_id' => $this->etapa('cierre')->id]],
            ['putJson', "{$base}/requisitos-propios/{$propio}", ['titulo' => 'Póliza editada']],
            ['deleteJson', "{$base}/requisitos-propios/{$propio}", []],
            ['getJson', "{$base}/plantillas/{$plantilla->id}/version-nueva", []],
            ['postJson', "{$base}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $plantilla->versionVigente()->id]],
            ['deleteJson', $base, []],
        ];
        foreach ($pedidos as [$metodo, $url, $datos]) {
            $this->como()->{$metodo}($url, $datos)->assertStatus(422)->assertExactJson(['mensaje' => self::CERRADO]);
        }

        $this->assertEquals($antes, [
            DB::table('proyectos_expedientes')->where('id', $expediente->id)->first(),
            DB::table('proyectos_requisitos')->where('expediente_id', $expediente->id)->orderBy('id')->get(),
            DB::table('checklist_historial')->count(),
        ]);
        // Se sigue leyendo, con su cierre.
        $this->leer($expediente)->assertJsonPath('estado', ProyectoExpediente::CERRADO)->assertJsonPath('usuario_cierre', 'Gestora');
    }

    public function test_un_radicado_de_viabilizacion_conserva_su_mensaje(): void
    {
        $viabilizacion = $this->viabilizacion();
        DB::table('proyectos_expedientes')->where('id', $viabilizacion->id)->update(['estado' => ProyectoExpediente::RADICADO]);

        $this->como()->deleteJson("/GestPro/expedientes/{$viabilizacion->id}")->assertStatus(422)
            ->assertExactJson(['mensaje' => 'El expediente está radicado: su checklist está congelado y no admite cambios.']);
    }

    public function test_un_expediente_de_contratacion_en_borrador_se_elimina_y_libera_el_proceso(): void
    {
        $expediente = $this->expediente();
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));

        $this->como()->deleteJson("/GestPro/expedientes/{$expediente->id}")->assertOk();

        $this->assertDatabaseMissing('proyectos_expedientes', ['id' => $expediente->id]);
        $this->como()->deleteJson("/GestPro/procesos/{$expediente->proceso_id}")->assertOk();
    }

    // ------------------------------------------------------ API del proceso

    public function test_los_procesos_traen_su_expediente_con_avance(): void
    {
        $plantilla = $this->plantilla();
        $sin = $this->proceso(['codigo_proceso' => 'LP-000']);
        $expediente = $this->expediente($plantilla);
        $this->cumplir($expediente, $this->fila($expediente, 'Estudios previos'));

        $procesos = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');

        $this->assertSame([$sin->id, $expediente->proceso_id], array_column($procesos, 'id'));
        $this->assertNull($procesos[0]['expediente']);
        $this->assertSame([
            'id' => $expediente->id,
            'estado' => ProyectoExpediente::BORRADOR,
            'progreso' => ['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50, 'peso_total' => 2, 'peso_cumplido' => 1],
            'aviso_plantilla' => null,
        ], $procesos[1]['expediente']);
    }

    public function test_el_proceso_avisa_cuando_su_plantilla_ya_no_corresponde(): void
    {
        $expediente = $this->expediente();
        $proceso = $expediente->proceso;

        $r = $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => $proceso->objeto,
            'modalidad_id' => $this->modalidad('minima_cuantia'),
            'plataforma' => $proceso->plataforma,
            'actualizado' => $proceso->updated_at?->toIso8601String(),
        ])->assertOk();

        $r->assertJsonPath('expediente.id', $expediente->id)
            ->assertJsonPath('expediente.aviso_plantilla', 'La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.');
        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")
            ->assertJsonPath('procesos.0.expediente.aviso_plantilla', 'La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.');
    }

    public function test_con_varias_plantillas_avisa_solo_si_ninguna_corresponde(): void
    {
        $una = $this->plantilla('Una');
        $otra = $this->plantilla('Otra');
        $proceso = $this->proceso();
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$una->id, $otra->id]])->assertCreated();
        $aviso = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")
            ->json('procesos.0.expediente.aviso_plantilla');

        $otra->update(['activo' => false]);
        $this->assertNull($aviso());

        $proceso->update(['modalidad' => $this->modalidad('minima_cuantia')]);
        $this->assertSame('Ninguna plantilla del expediente corresponde ya a la modalidad, la fuente o el sector del proceso.', $aviso());
    }

    private function avisoDelProceso(): ?string
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")
            ->assertOk()->json('procesos.0.expediente.aviso_plantilla');
    }

    public function test_si_la_plantilla_esta_inactiva_el_aviso_lo_dice(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);

        $plantilla->update(['activo' => false]);

        // Sigue cubriendo la modalidad, la fuente y el sector: no se dice que no corresponde.
        $this->assertSame('La plantilla del expediente está inactiva.', $this->avisoDelProceso());
        $this->leer($expediente)
            ->assertJsonPath('plantillas.0.compatible', false)
            ->assertJsonPath('plantillas.0.corresponde', true)
            ->assertJsonPath('plantillas.0.activa', false)
            ->assertJsonPath('plantillas.0.publicada', true);
    }

    public function test_si_la_plantilla_ya_no_tiene_version_publicada_el_aviso_lo_dice(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);

        DB::table('catalogo_versiones')->where('plantilla_id', $plantilla->id)->update(['vigente' => false]);

        $this->assertSame('La plantilla del expediente ya no tiene una versión publicada.', $this->avisoDelProceso());
        $this->leer($expediente)
            ->assertJsonPath('plantillas.0.compatible', false)
            ->assertJsonPath('plantillas.0.corresponde', true)
            ->assertJsonPath('plantillas.0.activa', true)
            ->assertJsonPath('plantillas.0.publicada', false);
    }

    public function test_inactiva_y_fuera_de_la_modalidad_avisa_que_no_corresponde(): void
    {
        $plantilla = $this->plantilla();
        $expediente = $this->expediente($plantilla);

        $plantilla->update(['activo' => false]);
        $expediente->proceso->update(['modalidad' => $this->modalidad('minima_cuantia')]);

        $this->assertSame('La plantilla del expediente ya no corresponde a la modalidad, la fuente o el sector del proceso.', $this->avisoDelProceso());
        $this->leer($expediente)
            ->assertJsonPath('plantillas.0.corresponde', false)
            ->assertJsonPath('plantillas.0.activa', false);
    }

    public function test_con_varias_plantillas_el_aviso_da_la_razon_de_todas(): void
    {
        $una = $this->plantilla('Una');
        $otra = $this->plantilla('Otra');
        $proceso = $this->proceso();
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$una->id, $otra->id]])->assertCreated();

        $una->update(['activo' => false]);
        $otra->update(['activo' => false]);
        $this->assertSame('Las plantillas del expediente están inactivas.', $this->avisoDelProceso());

        $vigente = $otra->versionVigente()->id;
        DB::table('catalogo_versiones')->where('id', $vigente)->update(['vigente' => false]);
        $otra->update(['activo' => true]);
        $this->assertSame(
            'Ninguna plantilla del expediente sigue vigente: unas están inactivas y otras ya no tienen una versión publicada.',
            $this->avisoDelProceso()
        );

        $otra->update(['activo' => false]);
        DB::table('catalogo_versiones')->where('id', $vigente)->update(['vigente' => true]);
        DB::table('plantilla_modalidades')->where('plantilla_id', $otra->id)->update(['modalidad_id' => $this->modalidad('minima_cuantia')]);
        $this->assertSame(
            'Ninguna plantilla del expediente sigue vigente: unas ya no corresponden a la modalidad, la fuente o el sector del proceso y otras están inactivas.',
            $this->avisoDelProceso()
        );
    }

    public function test_un_proceso_nuevo_responde_el_expediente_que_nacio_con_el(): void
    {
        $this->plantilla();

        $r = $this->como()->postJson("/GestPro/proyectos/{$this->proyecto->id}/procesos", [
            'objeto' => 'Interventoría', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
        ])->assertCreated();

        $r->assertJsonPath('expediente.estado', ProyectoExpediente::BORRADOR)
            ->assertJsonPath('expediente.progreso', ['total' => 2, 'cumplidos' => 0, 'porcentaje' => 0, 'peso_total' => 2, 'peso_cumplido' => 0])
            ->assertJsonPath('expediente.aviso_plantilla', null);
    }

    public function test_el_listado_de_procesos_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantilla();
        $this->expediente($plantilla);
        $consultas = fn () => $this->contarConsultas(fn () => $this->como(Rol::Consulta)
            ->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk());
        $conUno = $consultas();

        foreach (range(2, 4) as $i) {
            $this->expediente($plantilla, $this->proceso(['codigo_proceso' => "LP-00{$i}"]));
        }
        $this->proceso(['codigo_proceso' => 'LP-sin']);

        $this->assertSame($conUno, $consultas());
    }

    private function contarConsultas(\Closure $accion): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $accion();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }
}
