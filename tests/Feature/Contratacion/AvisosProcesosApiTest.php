<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\Contrato;
use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Avisos de los procesos de contratación (R14, criterio 11) en la lista de procesos del proyecto,
 * sus respuestas y la ficha (`avisos` del proceso), y en los contratos del módulo Contratos
 * (`aviso_proceso`). Hoy es el 19 de noviembre de 2026 salvo que la prueba diga otra cosa.
 */
class AvisosProcesosApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private const SIN_AVISOS = [
        'etapas_vencidas' => 0,
        'etapas_proximas' => 0,
        'cierre_proximo' => false,
        'cierre_vencido' => false,
        'dias_para_cierre' => null,
        'sin_actualizacion' => false,
        'dias_sin_actualizacion' => 0,
        'hitos' => ['completadas' => 0, 'total' => 0],
        'etapas_por_revisar' => 0,
        'etapas_devueltas' => 0,
    ];

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

    private function modalidad(): int
    {
        return Modalidad::where('clave', 'licitacion_publica')->value('id');
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
            'modalidad_ids' => [$this->modalidad()],
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
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
            ...$cambios,
        ]);
    }

    private function expediente(PlantillaChecklist $plantilla, ProcesoLicitacion $proceso): ProyectoExpediente
    {
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos): void
    {
        $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos)->assertOk();
    }

    /** Planeación vencida (ayer) y pliego definitivo próximo (en 3 días). */
    private function conVencidaYProxima(ProyectoExpediente $expediente): void
    {
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $this->fechas($expediente, 'pliego_definitivo', ['fecha_programada' => '2026-11-22']);
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
    }

    private function procesos(): array
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');
    }

    private function contrato(ProcesoLicitacion $proceso, string $numero = 'C-001'): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'proceso_licitacion' => $proceso->id,
            'n_contrato' => $numero,
            'objeto' => 'Construcción del acueducto',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => 'en_ejecucion',
        ]);
    }

    // ------------------------------------------------------------ procesos

    public function test_criterio_11_la_lista_de_procesos_trae_los_avisos_de_cada_uno(): void
    {
        $plantilla = $this->plantilla();
        $conExpediente = $this->proceso(['fecha_cierre' => '2026-11-21']);
        $expediente = $this->expediente($plantilla, $conExpediente);
        $this->conVencidaYProxima($expediente);
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Acta de cierre')->id}", ['cumplido' => true])->assertOk();
        $sinExpediente = $this->proceso(['fecha_cierre' => '2026-11-10']);

        $procesos = $this->procesos();

        $this->assertSame([$conExpediente->id, $sinExpediente->id], array_column($procesos, 'id'));
        $this->assertSame([
            'etapas_vencidas' => 1,
            'etapas_proximas' => 1,
            'cierre_proximo' => true,
            'cierre_vencido' => false,
            'dias_para_cierre' => 2,
            'sin_actualizacion' => false,
            'dias_sin_actualizacion' => 0,
            // Hitos = etapas aprobadas (fase 5, decisión 1): el cierre está completo pero sin aprobar.
            'hitos' => ['completadas' => 0, 'total' => 3],
            'etapas_por_revisar' => 1,
            'etapas_devueltas' => 0,
        ], $procesos[0]['avisos']);
        // El bloque del expediente no cambia.
        $this->assertSame(['id', 'estado', 'progreso', 'aviso_plantilla'], array_keys($procesos[0]['expediente']));
        $this->assertNull($procesos[1]['expediente']);
        $this->assertSame([...self::SIN_AVISOS, 'cierre_vencido' => true, 'dias_para_cierre' => -9], $procesos[1]['avisos']);

        // La ficha del proyecto trae los mismos.
        $ficha = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('procesos');
        $this->assertSame(array_column($procesos, 'avisos'), array_column($ficha, 'avisos'));
    }

    public function test_sin_actualizacion_a_los_15_dias_salvo_en_un_estado_final_o_con_el_expediente_cerrado(): void
    {
        $this->travelTo('2026-11-01 10:00:00');
        $plantilla = $this->plantilla();
        $sinExpediente = $this->proceso();
        $abierto = $this->proceso();
        $this->expediente($plantilla, $abierto);
        $cerrado = $this->proceso();
        $this->como()->postJson("/GestPro/expedientes/{$this->expediente($plantilla, $cerrado)->id}/cerrar", ['confirmar' => true])->assertOk();
        $celebrado = $this->proceso(['estado' => ProcesoLicitacion::CELEBRADO]);

        $this->travelTo('2026-11-15 10:00:00');
        $this->assertSame([false, false, false, false], array_column(array_column($this->procesos(), 'avisos'), 'sin_actualizacion'));

        $this->travelTo('2026-11-16 10:00:00');
        $avisos = array_column($this->procesos(), 'avisos', 'id');
        $this->assertSame(
            [$sinExpediente->id => true, $abierto->id => true, $cerrado->id => false, $celebrado->id => false],
            array_map(fn (array $a) => $a['sin_actualizacion'], $avisos)
        );
        $this->assertSame(15, $avisos[$celebrado->id]['dias_sin_actualizacion']);

        // Un evento en el historial del expediente es actividad; editar el proceso, también.
        $expediente = $abierto->expediente;
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}", ['observacion' => 'Pedido'])->assertOk();
        $this->como()->putJson("/GestPro/procesos/{$sinExpediente->id}", [
            'objeto' => 'Construcción del acueducto veredal', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'actualizado' => $sinExpediente->fresh()->updated_at->toIso8601String(),
        ])->assertOk()->assertJsonPath('avisos.sin_actualizacion', false)->assertJsonPath('avisos.dias_sin_actualizacion', 0);
        $avisos = array_column($this->procesos(), 'avisos', 'id');
        $this->assertFalse($avisos[$abierto->id]['sin_actualizacion']);
        $this->assertSame(0, $avisos[$abierto->id]['dias_sin_actualizacion']);
    }

    public function test_un_expediente_cerrado_no_cuenta_vencidas(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente($this->plantilla(), $proceso);
        $this->conVencidaYProxima($expediente);
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        $avisos = $this->procesos()[0]['avisos'];
        $this->assertSame(0, $avisos['etapas_vencidas']);
        $this->assertSame(0, $avisos['etapas_proximas']);
        $this->assertSame(['completadas' => 0, 'total' => 3], $avisos['hitos']);
    }

    public function test_crear_editar_y_cambiar_el_estado_responden_los_avisos(): void
    {
        $creado = $this->como()->postJson("/GestPro/proyectos/{$this->proyecto->id}/procesos", [
            'objeto' => 'Construcción del acueducto', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'fecha_cierre' => '2026-11-20',
        ])->assertCreated();
        $this->assertSame([...self::SIN_AVISOS, 'cierre_proximo' => true, 'dias_para_cierre' => 1], $creado->json('avisos'));
        $proceso = ProcesoLicitacion::findOrFail($creado->json('id'));

        $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => 'Construcción del acueducto', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'fecha_cierre' => '2026-11-18', 'actualizado' => $creado->json('actualizado'),
        ])->assertOk()->assertJsonPath('avisos.cierre_vencido', true)->assertJsonPath('avisos.cierre_proximo', false);

        // Ya en evaluación, el cierre pasó: no avisa.
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/estado", [
            'estado' => ProcesoLicitacion::EVALUACION, 'actualizado' => $proceso->fresh()->updated_at->toIso8601String(),
        ])->assertOk()->assertJsonPath('avisos', [...self::SIN_AVISOS, 'dias_para_cierre' => -1]);
    }

    public function test_la_lista_de_procesos_con_avisos_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantilla();
        $this->conVencidaYProxima($this->expediente($plantilla, $this->proceso(['fecha_cierre' => '2026-11-21'])));
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        foreach (range(1, 3) as $i) {
            $expediente = $this->expediente($plantilla, $this->proceso(['fecha_cierre' => '2026-11-0' . $i]));
            $this->conVencidaYProxima($expediente);
            $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'Acta de cierre')->id}", ['cumplido' => true])->assertOk();
        }
        $this->proceso();

        $this->assertSame($conUno, $consultas());
        $this->assertSame([1, 1, 1, 1, 0], array_column(array_column($this->procesos(), 'avisos'), 'etapas_vencidas'));
    }

    // ------------------------------------------------------------ contratos

    public function test_los_contratos_traen_el_aviso_de_su_proceso(): void
    {
        $plantilla = $this->plantilla();
        $vencido = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($plantilla, $vencido));
        $alDia = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $conVencidas = $this->contrato($vencido, 'C-001');
        $sinAviso = $this->contrato($alDia, 'C-002');
        $sinProceso = Contrato::create([
            'proyecto' => $this->proyecto->id, 'n_contrato' => 'C-003', 'objeto' => 'Suministro', 'valor_inicial' => 1, 'valor' => 1, 'estado' => 'en_ejecucion',
        ]);
        $esperado = ['proceso_id' => $vencido->id, 'etapas_vencidas' => 1, 'cierre_vencido' => false, 'sin_actualizacion' => false, 'dias_sin_actualizacion' => 0];

        $filas = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $this->assertSame($esperado, $filas[$conVencidas->id]['aviso_proceso']);
        $this->assertNull($filas[$sinAviso->id]['aviso_proceso']);
        $this->assertNull($filas[$sinProceso->id]['aviso_proceso']);

        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$conVencidas->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', $esperado);
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$sinProceso->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', null);
        $listado = collect($this->como(Rol::Consulta)->getJson("/GestPro/listarContratos?proyecto={$this->proyecto->id}")->assertOk()->json())->keyBy('id');
        $this->assertSame($esperado, $listado[$conVencidas->id]['aviso_proceso']);
        $this->assertNull($listado[$sinAviso->id]['aviso_proceso']);

        $ficha = collect($this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('contratos'))->keyBy('id');
        $this->assertSame($esperado, $ficha[$conVencidas->id]['aviso_proceso']);

        // Sin actualización, o con el cierre vencido, también avisa.
        $alDia->update(['fecha_cierre' => '2026-11-01', 'estado' => ProcesoLicitacion::PUBLICADO]);
        $this->travelTo('2026-12-10 10:00:00');
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$sinAviso->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', [
            'proceso_id' => $alDia->id, 'etapas_vencidas' => 0, 'cierre_vencido' => true, 'sin_actualizacion' => true, 'dias_sin_actualizacion' => 21,
        ]);
    }

    public function test_un_proceso_en_estado_final_no_avisa_etapas_vencidas_ni_en_sus_contratos(): void
    {
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($this->plantilla(), $proceso));
        $contrato = $this->contrato($proceso);
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$contrato->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso.etapas_vencidas', 1);

        $proceso->update(['estado' => ProcesoLicitacion::CELEBRADO]);
        $avisos = $this->procesos()[0]['avisos'];
        $this->assertSame([0, 0], [$avisos['etapas_vencidas'], $avisos['etapas_proximas']]);
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$contrato->id}/detalle")->assertOk()->assertJsonPath('aviso_proceso', null);
        $filas = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $this->assertNull($filas[$contrato->id]['aviso_proceso']);
    }

    public function test_la_ficha_del_proyecto_calcula_los_avisos_de_los_procesos_una_sola_vez(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($plantilla, $proceso));
        $this->contrato($proceso);
        $this->expediente($plantilla, $this->proceso());

        $peticion = $this->como(Rol::Consulta);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $ficha = $peticion->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk();
        DB::disableQueryLog();

        // resumenDeProcesos lee los expedientes de los procesos con una consulta: una sola vez.
        $lecturas = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'from "proyectos_expedientes"')
            && str_contains($q['query'], '"proceso_id" in'));
        $this->assertCount(1, $lecturas);
        $this->assertSame(1, $ficha->json('contratos.0.aviso_proceso.etapas_vencidas'));
        $this->assertSame([1, 0], array_column(array_column($ficha->json('procesos'), 'avisos'), 'etapas_vencidas'));
    }

    public function test_la_busqueda_de_contratos_con_avisos_no_hace_una_consulta_por_contrato(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->conVencidaYProxima($this->expediente($plantilla, $proceso));
        $this->contrato($proceso, 'C-001');
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson('/GestPro/contratos/buscar')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        foreach (range(2, 4) as $i) {
            $otro = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
            $this->conVencidaYProxima($this->expediente($plantilla, $otro));
            $this->contrato($otro, "C-00{$i}");
        }

        $this->assertSame($conUno, $consultas());
    }
}
