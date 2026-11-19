<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\Contrato;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vista detallada del proceso (fase 6): GET /procesos/{id}/detalle trae los datos del proceso,
 * con la forma de la lista de procesos del proyecto (la que usa el formulario de editar), su
 * proyecto, si admite contratos y sus contratos; también sin expediente.
 */
class DetalleProcesoApiTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $proyecto;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-19 10:00:00');
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

    private function modalidad(): int
    {
        return Modalidad::where('clave', 'licitacion_publica')->value('id');
    }

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
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
            'texto' => 'Estudios previos', 'etapa_id' => EtapaContratacion::where('clave', 'planeacion')->value('id'),
        ])->assertCreated();
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

    private function contrato(ProcesoLicitacion $proceso, string $numero, array $cambios = []): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'proceso_licitacion' => $proceso->id,
            'n_contrato' => $numero,
            'objeto' => 'Construcción del acueducto',
            'contratista' => 'Consorcio Agua',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => Contrato::EN_EJECUCION,
            ...$cambios,
        ]);
    }

    private function detalle(ProcesoLicitacion $proceso): array
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertOk()->json();
    }

    public function test_trae_los_datos_del_proceso_su_proyecto_y_sus_contratos(): void
    {
        $fuente = FuenteFinanciacion::create(['clave' => 'f_local', 'nombre' => 'Fondo local', 'orden' => 99, 'activo' => true]);
        $responsable = Responsable::create(['nombre' => 'Ana Pérez', 'activo' => true]);
        $proceso = $this->proceso([
            'monto' => 2500000, 'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
            'fecha_apertura' => '2026-11-01', 'fecha_cierre' => '2026-11-30',
            'url_proceso' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.1',
            'observaciones' => 'Con visita de obra.', 'estado' => ProcesoLicitacion::ADJUDICADO,
        ]);
        $primero = $this->contrato($proceso, 'C-001', ['valor' => 1200000]);
        $segundo = $this->contrato($proceso, 'C-002', ['estado' => Contrato::ANULADO, 'valor' => null, 'contratista' => null]);
        $this->contrato($this->proceso(), 'C-999');

        $detalle = $this->detalle($proceso);

        $this->assertSame(['proyecto', 'proceso', 'admite_contratos', 'contratos'], array_keys($detalle));
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal', 'fuente_ids' => []], $detalle['proyecto']);
        // El proceso, con la misma forma de la lista de procesos del proyecto (la del formulario).
        $lista = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos.0');
        $this->assertSame($lista, $detalle['proceso']);
        $this->assertSame('2500000.00', $detalle['proceso']['monto']);
        $this->assertSame(['id' => $fuente->id, 'nombre' => 'Fondo local'], $detalle['proceso']['fuente']);
        $this->assertSame(['id' => $responsable->id, 'nombre' => 'Ana Pérez'], $detalle['proceso']['responsable']);
        $this->assertSame('2026-11-01', $detalle['proceso']['fecha_apertura']);
        $this->assertSame('2026-11-30', $detalle['proceso']['fecha_cierre']);
        $this->assertSame('secop_ii', $detalle['proceso']['plataforma']);
        $this->assertStringStartsWith('https://community.secop.gov.co/', $detalle['proceso']['url_proceso']);
        $this->assertSame('Con visita de obra.', $detalle['proceso']['observaciones']);
        $this->assertSame(2, $detalle['proceso']['contratos']);
        $this->assertNotNull($detalle['proceso']['actualizado']);
        $this->assertNull($detalle['proceso']['expediente']);
        $this->assertTrue($detalle['admite_contratos']);

        $this->assertSame([
            [
                'id' => $primero->id, 'n_contrato' => 'C-001', 'objeto' => 'Construcción del acueducto', 'contratista' => 'Consorcio Agua',
                'estado' => 'en_ejecucion', 'valor_inicial' => '1000000.00', 'valor' => '1200000.00',
            ],
            [
                'id' => $segundo->id, 'n_contrato' => 'C-002', 'objeto' => 'Construcción del acueducto', 'contratista' => null,
                'estado' => 'anulado', 'valor_inicial' => '1000000.00', 'valor' => null,
            ],
        ], $detalle['contratos']);
    }

    public function test_el_proyecto_trae_sus_fuentes_como_la_lista_de_proyectos(): void
    {
        $segunda = FuenteFinanciacion::create(['clave' => 'f_b', 'nombre' => 'Fondo B', 'orden' => 98, 'activo' => true]);
        $primera = FuenteFinanciacion::create(['clave' => 'f_a', 'nombre' => 'Fondo A', 'orden' => 99, 'activo' => true]);
        $otro = Proyecto::create(['nombre' => 'Otro', 'sector' => $this->proyecto->sector]);
        $this->proyecto->fuentes()->attach([$primera->id, $segunda->id]);
        $ajena = FuenteFinanciacion::create(['clave' => 'f_c', 'nombre' => 'Fondo C', 'orden' => 97, 'activo' => true]);
        $otro->fuentes()->attach([$segunda->id, $ajena->id]);

        $detalle = $this->detalle($this->proceso());

        // Los ids de las fuentes del proyecto (enteros, ascendentes; las de otro proyecto no): la
        // forma de `fuente_ids` en la lista de proyectos, con la que el formulario agrupa "Fuentes del proyecto".
        $ids = [min($primera->id, $segunda->id), max($primera->id, $segunda->id)];
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal', 'fuente_ids' => $ids], $detalle['proyecto']);
    }

    public function test_con_expediente_trae_su_resumen_y_sin_contratos_una_lista_vacia(): void
    {
        $proceso = $this->proceso();
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$this->plantilla()->id]])
            ->assertCreated()->json('id');

        $detalle = $this->detalle($proceso);

        $this->assertSame($id, $detalle['proceso']['expediente']['id']);
        $this->assertSame(['id', 'estado', 'progreso', 'aviso_plantilla'], array_keys($detalle['proceso']['expediente']));
        $this->assertSame(['completadas' => 0, 'total' => 1], $detalle['proceso']['avisos']['hitos']);
        $this->assertFalse($detalle['admite_contratos']);
        $this->assertSame([], $detalle['contratos']);
    }

    public function test_admite_contratos_solo_adjudicado_o_celebrado(): void
    {
        foreach (ProcesoLicitacion::ESTADOS as $estado) {
            $this->assertSame(
                in_array($estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true),
                $this->detalle($this->proceso(['estado' => $estado]))['admite_contratos'],
                $estado
            );
        }
    }

    public function test_el_actualizado_sirve_para_editar_y_cambiar_el_estado(): void
    {
        $proceso = $this->proceso();
        $actualizado = $this->detalle($proceso)['proceso']['actualizado'];

        $editado = $this->como()->putJson("/GestPro/procesos/{$proceso->id}", [
            'objeto' => 'Construcción del acueducto veredal', 'modalidad_id' => $this->modalidad(), 'plataforma' => 'secop_ii',
            'actualizado' => $actualizado,
        ])->assertOk()->json();
        $this->como()->postJson("/GestPro/procesos/{$proceso->id}/estado", [
            'estado' => ProcesoLicitacion::PUBLICADO, 'actualizado' => $editado['actualizado'],
        ])->assertOk();

        $this->assertSame('publicado', $this->detalle($proceso)['proceso']['estado']);
    }

    public function test_cualquier_usuario_autenticado_lo_lee_y_un_proceso_que_no_existe_es_404(): void
    {
        $proceso = $this->proceso();
        $this->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertOk();
        }
        $this->como(Rol::Consulta)->getJson('/GestPro/procesos/999999/detalle')->assertNotFound();
    }

    public function test_las_consultas_no_crecen_con_los_contratos(): void
    {
        $proceso = $this->proceso(['estado' => ProcesoLicitacion::ADJUDICADO]);
        $this->contrato($proceso, 'C-001');
        $consultas = function () use ($proceso): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson("/GestPro/procesos/{$proceso->id}/detalle")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();
        foreach (range(2, 5) as $i) {
            $this->contrato($proceso, "C-00{$i}");
        }

        $this->assertSame($conUno, $consultas());
        $this->assertCount(5, $this->detalle($proceso)['contratos']);
    }
}
