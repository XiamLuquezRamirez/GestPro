<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Procesos de contratación de un proyecto: listar, crear, editar, cambiar estado y eliminar. */
class ProcesosContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private Entidad $entidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $this->entidad = Entidad::create(['nombre' => 'Gobernación del Cesar']);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function modalidad(string $clave): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    private function urlProyecto(?Proyecto $proyecto = null): string
    {
        return '/GestPro/proyectos/' . ($proyecto ?? $this->proyecto)->id . '/procesos';
    }

    private function urlProceso(ProcesoLicitacion|int $proceso, string $sufijo = ''): string
    {
        return '/GestPro/procesos/' . ($proceso instanceof ProcesoLicitacion ? $proceso->id : $proceso) . $sufijo;
    }

    private function datos(array $mas = []): array
    {
        return [
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $this->modalidad('licitacion_publica')->id,
            'plataforma' => 'secop_ii',
            ...$mas,
        ];
    }

    private function crear(array $mas = [], ?Proyecto $proyecto = null)
    {
        return $this->como()->postJson($this->urlProyecto($proyecto), $this->datos($mas));
    }

    private function proceso(array $mas = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::findOrFail($this->crear($mas, $proyecto)->assertCreated()->json('id'));
    }

    /** El `actualizado` que tendría ahora el formulario del proceso: el de presentar(). */
    private function version(ProcesoLicitacion $proceso): ?string
    {
        return $proceso->fresh()->updated_at?->toIso8601String();
    }

    /** Edita como desde un formulario recién abierto, salvo que `$datos` traiga otro `actualizado`. */
    private function editar(ProcesoLicitacion $proceso, array $datos)
    {
        return $this->como()->putJson($this->urlProceso($proceso), ['actualizado' => $this->version($proceso), ...$datos]);
    }

    /** Cambia el estado como desde un formulario recién abierto, salvo que `$datos` traiga otro `actualizado`. */
    private function cambiarEstado(ProcesoLicitacion $proceso, array $datos)
    {
        return $this->como()->postJson($this->urlProceso($proceso, '/estado'), ['actualizado' => $this->version($proceso), ...$datos]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(ProcesoLicitacion|int $proceso): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'proceso')
            ->where('entidad_id', $proceso instanceof ProcesoLicitacion ? $proceso->id : $proceso)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    // ------------------------------------------------------------------ crear

    public function test_un_gestor_crea_un_proceso_en_preparacion(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Ana Gestora']);
        $modalidad = $this->modalidad('licitacion_publica');

        $r = $this->como($gestor)->postJson($this->urlProyecto(), $this->datos([
            'objeto' => '  Construcción del acueducto veredal  ',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => 1500000.5,
        ]))->assertCreated();

        $r->assertExactJson([
            'id' => $r->json('id'),
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad' => ['id' => $modalidad->id, 'nombre' => 'Licitación pública', 'clave' => 'licitacion_publica', 'activo' => true],
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante' => ['id' => $this->entidad->id, 'nombre' => 'Gobernación del Cesar'],
            'monto' => '1500000.50',
            'fuente' => null,
            'responsable' => null,
            'fecha_apertura' => null,
            'fecha_cierre' => null,
            'observaciones' => null,
            'estado' => 'preparacion',
            'contratos' => 0,
            // Sin plantilla de contratación publicada, no nace expediente.
            'expediente' => null,
            'avisos' => [
                'etapas_vencidas' => 0, 'etapas_proximas' => 0, 'cierre_proximo' => false, 'cierre_vencido' => false,
                'dias_para_cierre' => null, 'sin_actualizacion' => false, 'dias_sin_actualizacion' => 0,
                'hitos' => ['completadas' => 0, 'total' => 0],
                'etapas_por_revisar' => 0, 'etapas_devueltas' => 0,
            ],
            'creado' => $r->json('creado'),
            'actualizado' => $r->json('actualizado'),
            'usuario_creo' => 'Ana Gestora',
        ]);
        $this->assertNotNull($r->json('creado'));

        $proceso = ProcesoLicitacion::findOrFail($r->json('id'));
        $this->assertSame($this->proyecto->id, (int) $proceso->proyecto);
        $this->assertSame($gestor->id, $proceso->usuario_creo_id);
        $this->assertSame($modalidad->id, (int) $proceso->modalidad);
        $this->assertSame($this->entidad->id, (int) $proceso->entidad_contratante);

        $this->assertSame([['proceso_creado', null, [
            'proyecto_id' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $modalidad->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => '1500000.50',
            'fuente_id' => null,
            'responsable_id' => null,
            'fecha_apertura' => null,
            'fecha_cierre' => null,
            'observaciones' => null,
            'estado' => 'preparacion',
        ]]], $this->eventos($proceso));
        $this->assertNull(DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->value('expediente_id'));
        $this->assertSame($gestor->id, (int) DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->value('usuario_id'));
    }

    public function test_los_campos_opcionales_pueden_faltar_o_venir_vacios(): void
    {
        $this->crear(['codigo_proceso' => '   ', 'url_proceso' => null])->assertCreated()
            ->assertJsonPath('codigo_proceso', null)
            ->assertJsonPath('url_proceso', null)
            ->assertJsonPath('entidad_contratante', null)
            ->assertJsonPath('monto', null);
    }

    public function test_el_estado_no_se_fija_al_crear_ni_al_editar(): void
    {
        $proceso = $this->proceso(['estado' => 'adjudicado']);
        $this->assertSame('preparacion', $proceso->estado);

        $this->editar($proceso, $this->datos(['estado' => 'celebrado']))
            ->assertOk()->assertJsonPath('estado', 'preparacion');
    }

    public function test_el_monto_admite_un_texto_numerico(): void
    {
        $this->crear(['monto' => '2500000'])->assertCreated()->assertJsonPath('monto', '2500000.00');
        $this->crear(['monto' => 0])->assertCreated()->assertJsonPath('monto', '0.00');
        $this->crear(['monto' => '9999999999999.99'])->assertCreated()->assertJsonPath('monto', '9999999999999.99');
    }

    // ----------------------------------------------------------------- listar

    public function test_lista_los_procesos_del_proyecto_en_orden_con_sus_contratos(): void
    {
        $otroProyecto = Proyecto::create(['nombre' => 'Vía terciaria']);
        $obra = $this->proceso();
        $ajeno = $this->proceso(['objeto' => 'Pavimentación'], $otroProyecto);
        $interventoria = $this->proceso([
            'objeto' => 'Interventoría del acueducto',
            'modalidad_id' => $this->modalidad('concurso_meritos')->id,
            'plataforma' => 'secop_i',
        ]);
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $interventoria->id]);
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $interventoria->id]);

        $r = $this->como(Rol::Consulta)->getJson($this->urlProyecto())->assertOk();

        $r->assertJsonPath('proyecto', ['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal'])
            ->assertJsonCount(2, 'procesos')
            ->assertJsonPath('procesos.0.id', $obra->id)
            ->assertJsonPath('procesos.0.contratos', 0)
            ->assertJsonPath('procesos.1.id', $interventoria->id)
            ->assertJsonPath('procesos.1.objeto', 'Interventoría del acueducto')
            ->assertJsonPath('procesos.1.modalidad.clave', 'concurso_meritos')
            ->assertJsonPath('procesos.1.plataforma', 'secop_i')
            ->assertJsonPath('procesos.1.contratos', 2);
        $this->assertNotContains($ajeno->id, array_column($r->json('procesos'), 'id'));

        // Cada proceso del listado tiene la misma forma que devuelve crear.
        $this->assertSame(
            array_keys($this->crear()->json()),
            array_keys($r->json('procesos.0'))
        );
    }

    public function test_el_listado_no_consulta_por_cada_proceso(): void
    {
        $this->proceso(['entidad_contratante_id' => $this->entidad->id]);
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson($this->urlProyecto())->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        $otra = Entidad::create(['nombre' => 'Alcaldía de Becerril']);
        foreach (['minima_cuantia', 'seleccion_abreviada', 'contratacion_directa'] as $clave) {
            $proceso = $this->proceso(['modalidad_id' => $this->modalidad($clave)->id, 'entidad_contratante_id' => $otra->id]);
            Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id]);
        }

        $this->assertSame($conUno, $consultas());
    }

    public function test_un_proyecto_sin_procesos_lista_vacio(): void
    {
        $this->como(Rol::Consulta)->getJson($this->urlProyecto())->assertOk()->assertJsonPath('procesos', []);
    }

    // ----------------------------------------------------------------- editar

    public function test_edita_y_registra_solo_lo_que_cambio(): void
    {
        $proceso = $this->proceso(['codigo_proceso' => 'LP-001', 'monto' => 1000]);
        $directa = $this->modalidad('contratacion_directa');

        $this->editar($proceso, $this->datos([
            'objeto' => 'Construcción del acueducto veredal, fase 2',
            'modalidad_id' => $directa->id,
            'codigo_proceso' => 'LP-001',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => '1000.00',
        ]))->assertOk()
            ->assertJsonPath('id', $proceso->id)
            ->assertJsonPath('objeto', 'Construcción del acueducto veredal, fase 2')
            ->assertJsonPath('modalidad.id', $directa->id)
            ->assertJsonPath('entidad_contratante.id', $this->entidad->id)
            ->assertJsonPath('monto', '1000.00');

        $this->assertSame(['proceso_editado', [
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $this->modalidad('licitacion_publica')->id,
            'entidad_contratante_id' => null,
        ], [
            'objeto' => 'Construcción del acueducto veredal, fase 2',
            'modalidad_id' => $directa->id,
            'entidad_contratante_id' => $this->entidad->id,
        ]], $this->eventos($proceso)[1]);
    }

    public function test_los_opcionales_que_no_vienen_no_cambian_y_con_null_se_borran(): void
    {
        $proceso = $this->proceso([
            'codigo_proceso' => 'LP-001', 'url_proceso' => 'http://secop.gov.co/1', 'monto' => 5000,
            'entidad_contratante_id' => $this->entidad->id,
        ]);

        $this->editar($proceso, $this->datos(['codigo_proceso' => null, 'monto' => null]))
            ->assertOk()
            ->assertJsonPath('codigo_proceso', null)
            ->assertJsonPath('monto', null)
            ->assertJsonPath('url_proceso', 'http://secop.gov.co/1')
            ->assertJsonPath('entidad_contratante.id', $this->entidad->id);

        $this->assertSame(['proceso_editado', ['codigo_proceso' => 'LP-001', 'monto' => '5000.00'], ['codigo_proceso' => null, 'monto' => null]],
            $this->eventos($proceso)[1]);
    }

    public function test_editar_sin_cambios_no_registra_nada(): void
    {
        $proceso = $this->proceso(['monto' => 1200]);

        $this->editar($proceso, $this->datos(['monto' => '1200']))->assertOk();

        $this->assertCount(1, $this->eventos($proceso));
    }

    // ------------------------------------------------- ediciones simultáneas

    public function test_un_formulario_desactualizado_no_deshace_lo_que_guardo_otra_persona(): void
    {
        $proceso = $this->proceso(['codigo_proceso' => 'LP-001']);
        // Las dos personas abren el formulario a la vez: el mismo `actualizado`, el del listado.
        $abierto = $this->como(Rol::Consulta)->getJson($this->urlProyecto())->json('procesos.0.actualizado');
        $this->assertSame($this->version($proceso), $abierto);

        $this->editar($proceso, $this->datos(['objeto' => 'Lo de la primera', 'actualizado' => $abierto]))->assertOk();
        $this->editar($proceso, $this->datos(['codigo_proceso' => null, 'actualizado' => $abierto]))
            ->assertStatus(409)
            ->assertExactJson(['mensaje' => 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.']);

        $proceso->refresh();
        $this->assertSame('Lo de la primera', $proceso->objeto);
        $this->assertSame('LP-001', $proceso->codigo_proceso);
        $this->assertCount(2, $this->eventos($proceso));

        // Con la versión de ahora pasa.
        $this->editar($proceso, $this->datos(['objeto' => 'Lo de la primera', 'codigo_proceso' => null]))
            ->assertOk()->assertJsonPath('codigo_proceso', null);
    }

    public function test_cada_cambio_mueve_la_version_aunque_caiga_en_el_mismo_segundo(): void
    {
        $this->freezeSecond();
        $proceso = $this->proceso();
        $versiones = [$this->version($proceso)];

        $versiones[] = $this->editar($proceso, $this->datos(['objeto' => 'Uno']))->assertOk()->json('actualizado');
        $versiones[] = $this->cambiarEstado($proceso, ['estado' => 'publicado'])->assertOk()->json('actualizado');
        $versiones[] = $this->editar($proceso, $this->datos(['objeto' => 'Dos']))->assertOk()->json('actualizado');

        $this->assertSame($versiones, array_values(array_unique($versiones)));
        $this->assertSame(end($versiones), $this->version($proceso));
    }

    public function test_editar_sin_cambios_no_mueve_la_version(): void
    {
        $proceso = $this->proceso();
        $version = $this->version($proceso);

        $this->editar($proceso, $this->datos())->assertOk()->assertJsonPath('actualizado', $version);
    }

    public function test_un_cambio_de_estado_desactualizado_se_rechaza(): void
    {
        $proceso = $this->proceso();
        $abierto = $this->version($proceso);
        $this->editar($proceso, $this->datos(['objeto' => 'Otro objeto']))->assertOk();

        $this->cambiarEstado($proceso, ['estado' => 'publicado', 'actualizado' => $abierto])
            ->assertStatus(409)
            ->assertJsonPath('mensaje', 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.');
        $this->assertSame('preparacion', $proceso->refresh()->estado);
        $this->assertCount(2, $this->eventos($proceso));

        $this->cambiarEstado($proceso, ['estado' => 'publicado'])->assertOk()->assertJsonPath('estado', 'publicado');
    }

    public function test_la_version_es_obligatoria_y_debe_ser_una_fecha(): void
    {
        $proceso = $this->proceso();
        $falta = 'Falta la versión del proceso que estabas editando. Ciérralo y vuelve a abrirlo.';

        $this->como()->putJson($this->urlProceso($proceso), $this->datos(['objeto' => 'Otro']))->assertStatus(422)
            ->assertJsonValidationErrors(['actualizado' => $falta]);
        $this->como()->postJson($this->urlProceso($proceso, '/estado'), ['estado' => 'publicado'])->assertStatus(422)
            ->assertJsonValidationErrors(['actualizado' => $falta]);
        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => 'ayer no']))->assertStatus(422)
            ->assertJsonValidationErrors(['actualizado' => 'La versión del proceso no es válida. Ciérralo y vuelve a abrirlo.']);

        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_la_version_se_compara_en_cualquier_zona_horaria(): void
    {
        $proceso = $this->proceso();
        $enUtc = $proceso->fresh()->updated_at->copy()->utc()->format('Y-m-d\TH:i:s\Z');

        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => $enUtc]))->assertOk();
    }

    public function test_un_proceso_sin_fechas_se_edita_con_version_null(): void
    {
        // Un proceso anterior a las fechas: presentar() da `actualizado` null.
        $proceso = $this->proceso();
        DB::table('procesos_licitacion')->where('id', $proceso->id)->update(['created_at' => null, 'updated_at' => null]);
        $this->assertNull($this->como(Rol::Consulta)->getJson($this->urlProyecto())->json('procesos.0.actualizado'));

        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => now()->toIso8601String()]))->assertStatus(409);
        $this->editar($proceso, $this->datos(['objeto' => 'Otro', 'actualizado' => null]))->assertOk()
            ->assertJsonPath('objeto', 'Otro');
        $this->assertNotNull($this->version($proceso));
    }

    // ------------------------------------------------------ catálogos inactivos

    public function test_al_crear_la_modalidad_y_la_entidad_deben_estar_activas(): void
    {
        $this->modalidad('minima_cuantia')->update(['activo' => false]);
        $this->entidad->update(['activo' => false]);

        $this->crear([
            'modalidad_id' => $this->modalidad('minima_cuantia')->id,
            'entidad_contratante_id' => $this->entidad->id,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'modalidad_id' => 'La modalidad elegida está inactiva: elige otra.',
            'entidad_contratante_id' => 'La entidad elegida está inactiva: elige otra.',
        ]);

        $this->assertSame(0, ProcesoLicitacion::count());
    }

    public function test_al_editar_se_conservan_la_modalidad_y_la_entidad_inactivas_que_ya_tiene(): void
    {
        $proceso = $this->proceso(['entidad_contratante_id' => $this->entidad->id]);
        $this->modalidad('licitacion_publica')->update(['activo' => false]);
        $this->entidad->update(['activo' => false]);

        $this->editar($proceso, $this->datos([
            'objeto' => 'Otro objeto', 'entidad_contratante_id' => $this->entidad->id,
        ]))->assertOk()
            ->assertJsonPath('objeto', 'Otro objeto')
            ->assertJsonPath('modalidad.activo', false)
            ->assertJsonPath('entidad_contratante.id', $this->entidad->id);
    }

    public function test_al_editar_no_se_cambia_a_una_modalidad_o_entidad_inactiva(): void
    {
        $proceso = $this->proceso();
        $this->modalidad('regimen_especial')->update(['activo' => false]);
        $this->entidad->update(['activo' => false]);

        $this->editar($proceso, $this->datos([
            'modalidad_id' => $this->modalidad('regimen_especial')->id,
            'entidad_contratante_id' => $this->entidad->id,
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'modalidad_id' => 'La modalidad elegida está inactiva: elige otra.',
            'entidad_contratante_id' => 'La entidad elegida está inactiva: elige otra.',
        ]);

        $this->assertSame($this->modalidad('licitacion_publica')->id, (int) $proceso->refresh()->modalidad);
        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_modalidad_o_entidad_inexistente(): void
    {
        $this->crear(['modalidad_id' => 9999, 'entidad_contratante_id' => 9999])->assertStatus(422)
            ->assertJsonValidationErrors([
                'modalidad_id' => 'Elige una modalidad de la lista.',
                'entidad_contratante_id' => 'Elige una entidad de la lista.',
            ]);
    }

    // ------------------------------------------------------------ validaciones

    public static function invalidos(): array
    {
        return [
            'sin objeto' => [['objeto' => null], 'objeto', 'Escribe el objeto del proceso.'],
            'objeto en blanco' => [['objeto' => '   '], 'objeto', 'Escribe el objeto del proceso.'],
            'objeto no texto' => [['objeto' => ['a']], 'objeto', 'Revisa el objeto: debe ser un texto.'],
            'objeto largo' => [['objeto' => str_repeat('a', 5001)], 'objeto', 'El objeto admite hasta 5000 caracteres.'],
            'sin modalidad' => [['modalidad_id' => null], 'modalidad_id', 'Elige la modalidad del proceso.'],
            'modalidad texto' => [['modalidad_id' => '1'], 'modalidad_id', 'Revisa la modalidad: debe ser un número entero.'],
            'modalidad booleana' => [['modalidad_id' => true], 'modalidad_id', 'Revisa la modalidad: debe ser un número entero.'],
            'sin plataforma' => [['plataforma' => null], 'plataforma', 'Elige la plataforma del proceso.'],
            'plataforma ajena' => [['plataforma' => 'colombia_compra'], 'plataforma', 'Elige una plataforma de la lista.'],
            'código largo' => [['codigo_proceso' => str_repeat('a', 101)], 'codigo_proceso', 'El número del proceso admite hasta 100 caracteres.'],
            'enlace sin http' => [['url_proceso' => 'secop.gov.co/proceso'], 'url_proceso', 'Escribe un enlace completo que empiece por http:// o https://.'],
            'enlace ftp' => [['url_proceso' => 'ftp://secop.gov.co/proceso'], 'url_proceso', 'Escribe un enlace completo que empiece por http:// o https://.'],
            'enlace largo' => [['url_proceso' => 'https://secop.gov.co/' . str_repeat('a', 480)], 'url_proceso', 'El enlace admite hasta 500 caracteres.'],
            'entidad texto' => [['entidad_contratante_id' => '1'], 'entidad_contratante_id', 'Revisa la entidad contratante: debe ser un número entero.'],
            'monto no numérico' => [['monto' => 'mil'], 'monto', 'Escribe el valor como un número.'],
            'fuente texto' => [['fuente_id' => '1'], 'fuente_id', 'Revisa la fuente de financiación: debe ser un número entero.'],
            'responsable texto' => [['responsable_id' => '1'], 'responsable_id', 'Revisa el responsable: debe ser un número entero.'],
            'apertura sin formato' => [['fecha_apertura' => '15/03/2026'], 'fecha_apertura', 'Escribe la fecha de apertura como año-mes-día.'],
            'apertura imposible' => [['fecha_apertura' => '2026-02-30'], 'fecha_apertura', 'Escribe la fecha de apertura como año-mes-día.'],
            'cierre sin formato' => [['fecha_cierre' => 'mañana'], 'fecha_cierre', 'Escribe la fecha de cierre como año-mes-día.'],
            'observaciones no texto' => [['observaciones' => ['a']], 'observaciones', 'Revisa las observaciones: debe ser un texto.'],
            'observaciones largas' => [['observaciones' => str_repeat('a', 5001)], 'observaciones', 'Las observaciones admiten hasta 5000 caracteres.'],
            'cierre antes de la apertura' => [['fecha_apertura' => '2026-03-10', 'fecha_cierre' => '2026-03-09'], 'fecha_cierre', 'La fecha de cierre no puede ser anterior a la de apertura.'],
            'monto negativo' => [['monto' => -1], 'monto', 'El valor no puede ser negativo.'],
            'monto con tres decimales' => [['monto' => '10.555'], 'monto', 'El valor admite hasta 2 decimales.'],
            'monto enorme' => [['monto' => '10000000000000'], 'monto', 'El valor admite hasta 13 cifras enteras y 2 decimales.'],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_valida_al_crear_con_mensajes_en_espanol(array $datos, string $campo, string $mensaje): void
    {
        $this->crear($datos)->assertStatus(422)->assertJsonValidationErrors([$campo => $mensaje]);

        $this->assertSame(0, ProcesoLicitacion::count());
    }

    #[DataProvider('invalidos')]
    public function test_valida_al_editar_con_los_mismos_mensajes(array $datos, string $campo, string $mensaje): void
    {
        $proceso = $this->proceso();

        $this->editar($proceso, $this->datos($datos))
            ->assertStatus(422)->assertJsonValidationErrors([$campo => $mensaje]);

        $this->assertCount(1, $this->eventos($proceso));
    }

    // ----------------------------------------------------------------- estado

    public function test_cambia_el_estado_y_lo_registra_con_la_observacion(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, [
            'estado' => 'publicado', 'observacion' => '  Publicado en SECOP II el 17 de noviembre  ',
        ])->assertOk()
            ->assertJsonPath('id', $proceso->id)
            ->assertJsonPath('estado', 'publicado')
            ->assertJsonPath('modalidad.clave', 'licitacion_publica');

        $this->cambiarEstado($proceso, ['estado' => 'evaluacion'])->assertOk();

        $eventos = $this->eventos($proceso);
        $this->assertSame(['proceso_estado_cambiado', ['estado' => 'preparacion'], [
            'estado' => 'publicado', 'observacion' => 'Publicado en SECOP II el 17 de noviembre',
        ]], $eventos[1]);
        $this->assertSame(['proceso_estado_cambiado', ['estado' => 'publicado'], ['estado' => 'evaluacion', 'observacion' => null]], $eventos[2]);
        $this->assertSame('evaluacion', $proceso->refresh()->estado);
    }

    public function test_no_hay_transiciones_prohibidas(): void
    {
        $proceso = $this->proceso();

        foreach (['cancelado', 'preparacion', 'celebrado', 'desierto'] as $estado) {
            $this->cambiarEstado($proceso, ['estado' => $estado])
                ->assertOk()->assertJsonPath('estado', $estado);
        }
    }

    public function test_el_mismo_estado_se_rechaza(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, ['estado' => 'preparacion'])
            ->assertStatus(422)->assertJsonPath('mensaje', 'El proceso ya está en ese estado.');

        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_valida_el_estado_y_la_observacion(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, [])->assertStatus(422)
            ->assertJsonValidationErrors(['estado' => 'Elige el estado del proceso.']);
        $this->cambiarEstado($proceso, ['estado' => 'archivado'])->assertStatus(422)
            ->assertJsonValidationErrors(['estado' => 'Elige un estado de la lista.']);
        $this->cambiarEstado($proceso, ['estado' => 'publicado', 'observacion' => str_repeat('a', 2001)])->assertStatus(422)
            ->assertJsonValidationErrors(['observacion' => 'La observación admite hasta 2000 caracteres.']);

        $this->assertSame('preparacion', $proceso->refresh()->estado);
    }

    // --------------------------------------------------------------- eliminar

    public function test_elimina_un_proceso_sin_contratos_y_deja_en_el_historial_que_era(): void
    {
        $proceso = $this->proceso([
            'codigo_proceso' => 'LP-001', 'entidad_contratante_id' => $this->entidad->id, 'monto' => 800,
        ]);
        $this->cambiarEstado($proceso, ['estado' => 'cancelado'])->assertOk();

        $this->como()->deleteJson($this->urlProceso($proceso))->assertOk()
            ->assertJsonPath('mensaje', 'Proceso eliminado.');

        $this->assertNull(ProcesoLicitacion::find($proceso->id));
        $this->assertSame(['proceso_eliminado', [
            'proyecto_id' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto veredal',
            'modalidad_id' => $this->modalidad('licitacion_publica')->id,
            'modalidad' => 'Licitación pública',
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001',
            'url_proceso' => null,
            'entidad_contratante_id' => $this->entidad->id,
            'entidad_contratante' => 'Gobernación del Cesar',
            'monto' => '800.00',
            'fuente_id' => null,
            'fuente' => null,
            'responsable_id' => null,
            'responsable' => null,
            'fecha_apertura' => null,
            'fecha_cierre' => null,
            'observaciones' => null,
            'estado' => 'cancelado',
        ], null], $this->eventos($proceso)[2]);
    }

    public function test_no_elimina_un_proceso_con_contratos(): void
    {
        $proceso = $this->proceso();
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id]);

        $this->como()->deleteJson($this->urlProceso($proceso))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El proceso tiene 1 contrato vinculado: no se puede eliminar.');

        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id]);
        $this->como()->deleteJson($this->urlProceso($proceso))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El proceso tiene 2 contratos vinculados: no se puede eliminar.');

        $this->assertNotNull(ProcesoLicitacion::find($proceso->id));
        $this->assertCount(1, $this->eventos($proceso));
    }

    // -------------------------------------------------------------- historial

    public function test_el_historial_de_los_procesos_no_aparece_en_el_de_ningun_expediente(): void
    {
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = app(ExpedienteService::class)->crear($this->proyecto, ['fase_sgr' => 3], User::factory()->create(['rol' => Rol::Gestor]));
        $historial = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}/historial")->assertOk()->json();
        $antes = $historial();
        $this->assertGreaterThan(0, $antes['total']);

        // Un proceso del mismo proyecto con cada evento, y otro que queda; sus ids pueden coincidir
        // con los del expediente.
        $proceso = $this->proceso();
        $this->editar($proceso, $this->datos(['objeto' => 'Otro objeto']))->assertOk();
        $this->cambiarEstado($proceso, ['estado' => 'cancelado'])->assertOk();
        $this->como()->deleteJson($this->urlProceso($proceso))->assertOk();
        $this->proceso();

        $despues = $historial();
        $this->assertSame(5, DB::table('checklist_historial')->where('entidad_tipo', 'proceso')->whereNull('expediente_id')->count());
        $this->assertSame($antes['total'], $despues['total']);
        $this->assertSame($antes['entradas'], $despues['entradas']);
        $this->assertNotContains('proceso', array_column($despues['entradas'], 'entidad'));
    }

    // ---------------------------------------------------------- permisos y 404

    public function test_consulta_lee_pero_no_escribe(): void
    {
        $proceso = $this->proceso();

        $this->como(Rol::Consulta)->getJson($this->urlProyecto())->assertOk()->assertJsonCount(1, 'procesos');
        $this->como(Rol::Consulta)->postJson($this->urlProyecto(), $this->datos())->assertForbidden();
        $this->como(Rol::Consulta)->putJson($this->urlProceso($proceso), $this->datos(['objeto' => 'Otro']))->assertForbidden();
        $this->como(Rol::Consulta)->postJson($this->urlProceso($proceso, '/estado'), ['estado' => 'publicado'])->assertForbidden();
        $this->como(Rol::Consulta)->deleteJson($this->urlProceso($proceso))->assertForbidden();

        $this->assertSame(1, ProcesoLicitacion::count());
        $this->assertSame('Construcción del acueducto veredal', $proceso->refresh()->objeto);
        $this->assertSame('preparacion', $proceso->estado);
    }

    public function test_un_administrador_tambien_escribe(): void
    {
        $this->como(Rol::Administrador)->postJson($this->urlProyecto(), $this->datos())->assertCreated();
    }

    public function test_sin_sesion_no_hay_acceso(): void
    {
        $this->getJson($this->urlProyecto())->assertUnauthorized();
        $this->postJson($this->urlProyecto(), $this->datos())->assertUnauthorized();
    }

    public function test_proyecto_o_proceso_inexistente_responde_404(): void
    {
        $this->como(Rol::Consulta)->getJson('/GestPro/proyectos/9999/procesos')->assertNotFound();
        $this->como()->postJson('/GestPro/proyectos/9999/procesos', $this->datos())->assertNotFound();
        $this->como()->putJson($this->urlProceso(9999), $this->datos())->assertNotFound();
        $this->como()->postJson($this->urlProceso(9999, '/estado'), ['estado' => 'publicado'])->assertNotFound();
        $this->como()->deleteJson($this->urlProceso(9999))->assertNotFound();

        $this->assertSame(0, DB::table('checklist_historial')->count());
    }

    // ----------------------------------------- fuente, responsable, fechas, observaciones

    private function fuente(string $nombre = 'Fuente de prueba', bool $activo = true): FuenteFinanciacion
    {
        return FuenteFinanciacion::create(['clave' => 'f_' . uniqid(), 'nombre' => $nombre, 'orden' => 99, 'activo' => $activo]);
    }

    private function responsable(string $nombre = 'Marta Ríos', bool $activo = true): Responsable
    {
        return Responsable::create(['nombre' => $nombre, 'activo' => $activo]);
    }

    public function test_crea_con_fuente_responsable_fechas_y_observaciones(): void
    {
        $fuente = $this->fuente();
        $responsable = $this->responsable();

        $r = $this->crear([
            'fuente_id' => $fuente->id,
            'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-03-10',
            'fecha_cierre' => '2026-03-24',
            'observaciones' => '  Pliegos publicados el 9 de marzo.  ',
        ])->assertCreated()
            ->assertJsonPath('fuente', ['id' => $fuente->id, 'nombre' => 'Fuente de prueba'])
            ->assertJsonPath('responsable', ['id' => $responsable->id, 'nombre' => 'Marta Ríos'])
            ->assertJsonPath('fecha_apertura', '2026-03-10')
            ->assertJsonPath('fecha_cierre', '2026-03-24')
            ->assertJsonPath('observaciones', 'Pliegos publicados el 9 de marzo.');

        $proceso = ProcesoLicitacion::findOrFail($r->json('id'));
        $this->assertSame($fuente->id, $proceso->fuente_id);
        $this->assertSame($responsable->id, $proceso->responsable_id);
        $despues = $this->eventos($proceso)[0][2];
        $this->assertSame($fuente->id, $despues['fuente_id']);
        $this->assertSame($responsable->id, $despues['responsable_id']);
        $this->assertSame('2026-03-10', $despues['fecha_apertura']);
        $this->assertSame('2026-03-24', $despues['fecha_cierre']);
        $this->assertSame('Pliegos publicados el 9 de marzo.', $despues['observaciones']);
    }

    public function test_el_listado_trae_fuente_y_responsable_sin_consultas_por_proceso(): void
    {
        $this->proceso(['fuente_id' => $this->fuente()->id, 'responsable_id' => $this->responsable()->id]);
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson($this->urlProyecto())->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();
        $this->proceso(['fuente_id' => $this->fuente('Otra fuente')->id, 'responsable_id' => $this->responsable('Luis Paz')->id]);

        $this->assertSame($conUno, $consultas());
        $this->como(Rol::Consulta)->getJson($this->urlProyecto())
            ->assertJsonPath('procesos.0.fuente.nombre', 'Fuente de prueba')
            ->assertJsonPath('procesos.1.responsable.nombre', 'Luis Paz');
    }

    public function test_edita_los_campos_nuevos_y_registra_solo_lo_que_cambio(): void
    {
        $fuente = $this->fuente();
        $otraFuente = $this->fuente('Otra fuente');
        $responsable = $this->responsable();
        $proceso = $this->proceso(['fuente_id' => $fuente->id, 'fecha_apertura' => '2026-03-10', 'observaciones' => 'Uno']);

        $this->editar($proceso, $this->datos([
            'fuente_id' => $otraFuente->id,
            'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-03-10',
            'fecha_cierre' => '2026-03-20',
            'observaciones' => 'Uno',
        ]))->assertOk()
            ->assertJsonPath('fuente.id', $otraFuente->id)
            ->assertJsonPath('responsable.id', $responsable->id)
            ->assertJsonPath('fecha_cierre', '2026-03-20');

        $this->assertSame(['proceso_editado', [
            'fuente_id' => $fuente->id,
            'responsable_id' => null,
            'fecha_cierre' => null,
        ], [
            'fuente_id' => $otraFuente->id,
            'responsable_id' => $responsable->id,
            'fecha_cierre' => '2026-03-20',
        ]], $this->eventos($proceso)[1]);
    }

    public function test_los_campos_nuevos_que_no_vienen_no_cambian_y_con_null_se_borran(): void
    {
        $proceso = $this->proceso([
            'fuente_id' => $this->fuente()->id, 'responsable_id' => $this->responsable()->id,
            'fecha_apertura' => '2026-03-10', 'fecha_cierre' => '2026-03-20', 'observaciones' => 'Nota',
        ]);

        $this->editar($proceso, $this->datos(['fuente_id' => null, 'fecha_cierre' => null, 'observaciones' => '   ']))
            ->assertOk()
            ->assertJsonPath('fuente', null)
            ->assertJsonPath('fecha_cierre', null)
            ->assertJsonPath('observaciones', null)
            ->assertJsonPath('fecha_apertura', '2026-03-10')
            ->assertJsonPath('responsable.nombre', 'Marta Ríos');

        $this->assertSame(['fuente_id', 'fecha_cierre', 'observaciones'], array_keys($this->eventos($proceso)[1][2]));
    }

    public function test_editar_los_campos_nuevos_sin_cambios_no_registra_nada(): void
    {
        $proceso = $this->proceso(['fecha_apertura' => '2026-03-10', 'fuente_id' => $this->fuente()->id]);

        $this->editar($proceso, $this->datos(['fecha_apertura' => '2026-03-10', 'fuente_id' => $proceso->fuente_id]))->assertOk();

        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_al_crear_la_fuente_y_el_responsable_deben_estar_activos(): void
    {
        $this->crear([
            'fuente_id' => $this->fuente('Inactiva', false)->id,
            'responsable_id' => $this->responsable('Inactivo', false)->id,
        ])->assertStatus(422)->assertJsonValidationErrors([
            'fuente_id' => 'La fuente elegida está inactiva: elige otra.',
            'responsable_id' => 'El responsable elegido está inactivo: elige otro.',
        ]);

        $this->assertSame(0, ProcesoLicitacion::count());
    }

    public function test_fuente_o_responsable_inexistente(): void
    {
        $this->crear(['fuente_id' => 9999, 'responsable_id' => 9999])->assertStatus(422)
            ->assertJsonValidationErrors([
                'fuente_id' => 'Elige una fuente de la lista.',
                'responsable_id' => 'Elige un responsable de la lista.',
            ]);
    }

    public function test_al_editar_se_conservan_la_fuente_y_el_responsable_inactivos_que_ya_tiene(): void
    {
        $fuente = $this->fuente();
        $responsable = $this->responsable();
        $proceso = $this->proceso(['fuente_id' => $fuente->id, 'responsable_id' => $responsable->id]);
        $fuente->update(['activo' => false]);
        $responsable->update(['activo' => false]);

        $this->editar($proceso, $this->datos([
            'objeto' => 'Otro objeto', 'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
        ]))->assertOk()->assertJsonPath('fuente.id', $fuente->id)->assertJsonPath('responsable.id', $responsable->id);
    }

    public function test_al_editar_no_se_cambia_a_una_fuente_o_responsable_inactivo(): void
    {
        $proceso = $this->proceso();

        $this->editar($proceso, $this->datos([
            'fuente_id' => $this->fuente('Inactiva', false)->id,
            'responsable_id' => $this->responsable('Inactivo', false)->id,
        ]))->assertStatus(422)->assertJsonValidationErrors([
            'fuente_id' => 'La fuente elegida está inactiva: elige otra.',
            'responsable_id' => 'El responsable elegido está inactivo: elige otro.',
        ]);

        $this->assertNull($proceso->refresh()->fuente_id);
        $this->assertCount(1, $this->eventos($proceso));
    }

    public function test_el_cierre_puede_ser_el_mismo_dia_de_la_apertura_o_faltar_la_otra_fecha(): void
    {
        $this->crear(['fecha_apertura' => '2026-03-10', 'fecha_cierre' => '2026-03-10'])->assertCreated();
        $this->crear(['fecha_cierre' => '2026-03-01'])->assertCreated();
        $this->crear(['fecha_apertura' => '2026-03-10'])->assertCreated();
    }

    public function test_al_editar_el_cierre_se_compara_con_la_apertura_guardada(): void
    {
        $proceso = $this->proceso(['fecha_apertura' => '2026-03-10']);

        $this->editar($proceso, $this->datos(['fecha_cierre' => '2026-03-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_cierre' => 'La fecha de cierre no puede ser anterior a la de apertura.']);
        $this->editar($proceso, $this->datos(['fecha_cierre' => '2026-03-10']))->assertOk();

        // Adelantar la apertura más allá del cierre guardado también se rechaza.
        $this->editar($proceso, $this->datos(['fecha_apertura' => '2026-04-01']))
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_cierre']);
        $this->assertSame('2026-03-10', $proceso->refresh()->fecha_apertura->toDateString());
    }

    public function test_el_estado_revocado_se_admite(): void
    {
        $proceso = $this->proceso();

        $this->cambiarEstado($proceso, ['estado' => 'revocado', 'observacion' => 'Se revocó el acto'])
            ->assertOk()->assertJsonPath('estado', 'revocado');

        $this->assertSame('revocado', $proceso->refresh()->estado);
    }

    // ------------------------------------------------------ precarga de un contrato

    private function urlPrecarga(ProcesoLicitacion|int $proceso): string
    {
        return $this->urlProceso($proceso, '/precarga-contrato');
    }

    private function pasarA(ProcesoLicitacion $proceso, string $estado): void
    {
        $this->cambiarEstado($proceso, ['estado' => $estado])->assertOk();
    }

    public function test_la_precarga_resume_el_proceso_y_sugiere_el_valor(): void
    {
        $proceso = $this->proceso([
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://secop.gov.co/p/1',
            'entidad_contratante_id' => $this->entidad->id,
            'monto' => 1000000,
        ]);
        $this->pasarA($proceso, 'adjudicado');

        $this->como(Rol::Consulta)->getJson($this->urlPrecarga($proceso))->assertOk()->assertExactJson([
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => 'LP-001-2026',
                'objeto' => 'Construcción del acueducto veredal',
                'estado' => 'adjudicado',
                'modalidad' => ['id' => $this->modalidad('licitacion_publica')->id, 'nombre' => 'Licitación pública'],
                'plataforma' => 'secop_ii',
                'url_proceso' => 'https://secop.gov.co/p/1',
                'monto' => '1000000.00',
            ],
            'admite_contratos' => true,
            'precarga' => [
                'objeto' => 'Construcción del acueducto veredal',
                'contratante' => 'Gobernación del Cesar',
                'valor_inicial' => '1000000.00',
            ],
        ]);
    }

    public function test_el_valor_sugerido_descuenta_los_contratos_no_anulados(): void
    {
        $proceso = $this->proceso(['monto' => 1000]);
        $this->pasarA($proceso, 'adjudicado');
        $nuevo = fn (array $mas) => Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id, ...$mas]);
        $nuevo(['valor_inicial' => 300.25, 'estado' => 'en_ejecucion']);
        $nuevo(['valor_inicial' => 200, 'estado' => 'anulado']);
        $nuevo(['valor_inicial' => null]);
        $nuevo(['valor_inicial' => 100.10, 'estado' => 'liquidado']);
        // De otro proceso: no cuenta.
        Contrato::create(['proyecto' => $this->proyecto->id, 'valor_inicial' => 999]);

        $this->como()->getJson($this->urlPrecarga($proceso))->assertOk()
            ->assertJsonPath('precarga.valor_inicial', '599.65');
    }

    public function test_el_valor_sugerido_nunca_es_negativo_ni_existe_sin_monto(): void
    {
        $proceso = $this->proceso(['monto' => 100]);
        Contrato::create(['proyecto' => $this->proyecto->id, 'proceso_licitacion' => $proceso->id, 'valor_inicial' => 150, 'estado' => 'celebrado']);
        $this->como()->getJson($this->urlPrecarga($proceso))->assertOk()->assertJsonPath('precarga.valor_inicial', '0.00');

        $sinMonto = $this->proceso();
        $this->como()->getJson($this->urlPrecarga($sinMonto))->assertOk()
            ->assertJsonPath('proceso.monto', null)
            ->assertJsonPath('precarga.valor_inicial', null)
            ->assertJsonPath('precarga.contratante', null);
    }

    public static function admiten(): array
    {
        return [
            'preparación' => ['preparacion', false],
            'publicado' => ['publicado', false],
            'evaluación' => ['evaluacion', false],
            'adjudicado' => ['adjudicado', true],
            'celebrado' => ['celebrado', true],
            'desierto' => ['desierto', false],
            'cancelado' => ['cancelado', false],
            'revocado' => ['revocado', false],
        ];
    }

    #[DataProvider('admiten')]
    public function test_solo_adjudicado_o_celebrado_admiten_contratos(string $estado, bool $admite): void
    {
        $proceso = $this->proceso();
        if ($estado !== 'preparacion') {
            $this->pasarA($proceso, $estado);
        }

        $this->como(Rol::Consulta)->getJson($this->urlPrecarga($proceso))->assertOk()
            ->assertJsonPath('admite_contratos', $admite)
            ->assertJsonPath('proceso.estado', $estado);
    }

    public function test_la_precarga_sin_sesion_no_tiene_acceso(): void
    {
        $proceso = ProcesoLicitacion::create(['proyecto' => $this->proyecto->id, 'objeto' => 'Obra']);

        $this->getJson($this->urlPrecarga($proceso))->assertUnauthorized();
    }

    public function test_la_precarga_es_de_lectura(): void
    {
        $proceso = $this->proceso();

        $this->como(Rol::Consulta)->getJson($this->urlPrecarga($proceso))->assertOk();
        $this->como()->getJson($this->urlPrecarga(999999))->assertNotFound();
        $this->assertSame(
            '/GestPro/procesos/' . $proceso->id . '/precarga-contrato',
            parse_url(route('procesos.precarga-contrato', $proceso), PHP_URL_PATH)
        );
    }
}
