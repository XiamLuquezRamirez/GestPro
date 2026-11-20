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
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Avance ponderado de un expediente de contratación (R10): el porcentaje pesa cada requisito que
 * cuenta por el peso del requisito que muestra la fila (un propio pesa 1); `total` y `cumplidos`
 * siguen siendo conteos. La viabilización no cambia (criterio 10).
 */
class AvancePonderadoApiTest extends TestCase
{
    use RefreshDatabase;

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

    private function modalidad(): int
    {
        return Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id;
    }

    private function etapa(string $clave = 'planeacion'): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function etapaJson(string $clave): array
    {
        $etapa = $this->etapa($clave);

        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden];
    }

    /** Requisito de plantilla: de planeación salvo que se diga otra etapa. */
    private function req(string $texto, int $peso = 1, array $cambios = []): array
    {
        return ['texto' => $texto, 'etapa_id' => $this->etapa()->id, 'peso' => $peso, ...$cambios];
    }

    /** Plantilla de contratación publicada por la API, con los requisitos dados. */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
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
        foreach ($requisitos as $requisito) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", $requisito)->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    /** La del criterio 9: cinco requisitos, uno de peso 3 y cuatro de peso 1. */
    private function plantillaCriterio9(): PlantillaChecklist
    {
        return $this->plantilla('Licitación', [
            $this->req('Estudios previos', 3),
            $this->req('CDP'),
            $this->req('Plan de adquisiciones'),
            $this->req('Pliego de condiciones', 1, ['etapa_id' => $this->etapa('pliego_definitivo')->id]),
            $this->req('Acta de cierre', 1, ['etapa_id' => $this->etapa('cierre')->id]),
        ]);
    }

    private function proceso(string $codigo = 'LP-001'): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad(),
            'plataforma' => 'secop_ii',
            'codigo_proceso' => $codigo,
        ]);
    }

    /** @param list<PlantillaChecklist> $plantillas la primera es la principal */
    private function expediente(array $plantillas, ?ProcesoLicitacion $proceso = null): ProyectoExpediente
    {
        $proceso ??= $this->proceso();
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => array_map(fn ($p) => $p->id, $plantillas)])
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

    private function cumplir(ProyectoExpediente $expediente, string $texto)
    {
        return $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, $texto)->id}", ['cumplido' => true])->assertOk();
    }

    private function progreso(int $total, int $cumplidos, int $porcentaje, int $pesoTotal, int $pesoCumplido): array
    {
        return ['total' => $total, 'cumplidos' => $cumplidos, 'porcentaje' => $porcentaje, 'peso_total' => $pesoTotal, 'peso_cumplido' => $pesoCumplido];
    }

    /** Las claves de la revisión (fase 5) de una etapa sin revisar: su estado visible y el aviso de la etapa anterior. */
    private function sinRevisar(string $estado, bool $avisoAnterior = false): array
    {
        return [
            'revision' => ['estado' => $estado, 'usuario' => null, 'fecha' => null, 'observacion' => null, 'pendientes_al_aprobar' => null, 'devuelta_at' => null],
            'aviso_etapa_anterior' => $avisoAnterior,
        ];
    }

    // ------------------------------------------------------------ criterio 9

    public function test_criterio_9_cumplir_el_requisito_de_peso_3_de_cinco_da_43_por_ciento(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);
        $this->leer($expediente)->assertJsonPath('progreso', $this->progreso(5, 0, 0, 7, 0));

        $esperado = $this->progreso(5, 1, 43, 7, 3);
        $this->cumplir($expediente, 'Estudios previos')
            ->assertJsonPath('progreso', $esperado)
            ->assertJsonPath('requisito.peso', 3);

        $r = $this->leer($expediente)->assertJsonPath('progreso', $esperado);
        $this->assertSame(
            ['Estudios previos' => 3, 'CDP' => 1, 'Plan de adquisiciones' => 1, 'Pliego de condiciones' => 1, 'Acta de cierre' => 1],
            collect($r->json('requisitos'))->pluck('peso', 'requisito.texto')->all()
        );
        $this->assertSame($esperado, app(ExpedienteService::class)->progresoDe($expediente));
    }

    public function test_con_todos_los_pesos_en_1_el_porcentaje_es_el_del_conteo(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [
            $this->req('Estudios previos'), $this->req('CDP'), $this->req('Plan de adquisiciones'),
        ])]);

        $this->cumplir($expediente, 'Estudios previos')->assertJsonPath('progreso', $this->progreso(3, 1, 33, 3, 1));
    }

    public function test_un_opcional_y_un_no_aplica_aprobado_no_suman_peso(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [
            $this->req('Estudios previos', 3),
            $this->req('Análisis del sector', 5, ['caracter' => RequisitoCatalogo::OPCIONAL]),
            $this->req('CDP', 4),
            $this->req('Plan de adquisiciones', 2),
        ])]);
        $this->cumplir($expediente, 'Análisis del sector');
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$this->fila($expediente, 'CDP')->id}/no-aplica", [
            'justificacion' => 'Ya se expidió con el proyecto.',
        ])->assertOk()->assertJsonPath('progreso', $this->progreso(2, 0, 0, 5, 0));

        $this->cumplir($expediente, 'Plan de adquisiciones')->assertJsonPath('progreso', $this->progreso(2, 1, 40, 5, 2));
    }

    public function test_un_propio_pesa_1(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [$this->req('Estudios previos', 3)])]);

        $r = $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos-propios", [
            'titulo' => 'Póliza', 'etapa_propia_id' => $this->etapa('adjudicacion')->id,
        ])->assertCreated()
            ->assertJsonPath('requisito.peso', 1)
            ->assertJsonPath('progreso', $this->progreso(2, 0, 0, 4, 0));

        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$r->json('requisito.id')}", ['cumplido' => true])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 25, 4, 1))
            ->assertJsonPath('avance_por_etapa.1', ['etapa' => $this->etapaJson('adjudicacion'), ...$this->progreso(1, 1, 100, 1, 1), 'fecha_programada' => null, 'fecha_real' => now(config('contratacion.zona'))->toDateString(), 'fecha_real_sugerida' => true, 'completa' => true, 'alerta' => 'completa', ...$this->sinRevisar('lista', true)]);
    }

    public function test_el_avance_por_etapa_tambien_se_pondera(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);

        $avance = [
            ['etapa' => $this->etapaJson('planeacion'), ...$this->progreso(3, 1, 60, 5, 3)],
            ['etapa' => $this->etapaJson('pliego_definitivo'), ...$this->progreso(1, 0, 0, 1, 0)],
            ['etapa' => $this->etapaJson('cierre'), ...$this->progreso(1, 0, 0, 1, 0)],
        ];
        // La API agrega a cada etapa sus fechas, su semáforo y su revisión.
        $conFechas = array_map(fn (array $a) => [...$a, 'fecha_programada' => null, 'fecha_real' => null, 'fecha_real_sugerida' => false, 'completa' => false, 'alerta' => 'sin_fecha', ...$this->sinRevisar('pendiente')], $avance);
        $this->assertSame($conFechas, $this->cumplir($expediente, 'Estudios previos')->json('avance_por_etapa'));
        $this->assertSame($conFechas, $this->leer($expediente)->json('avance_por_etapa'));
        $this->assertSame($avance, app(ExpedienteService::class)->avancePorEtapaDe($expediente));
    }

    public function test_el_cierre_con_pendientes_sigue_contando_requisitos(): void
    {
        $expediente = $this->expediente([$this->plantilla('Licitación', [$this->req('Estudios previos', 3), $this->req('CDP')])]);
        $this->cumplir($expediente, 'CDP');

        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar")->assertStatus(422)
            ->assertJsonPath('pendientes.total', 1);
    }

    public function test_la_fila_pesa_lo_del_requisito_que_muestra_el_de_la_plantilla_principal(): void
    {
        $una = $this->plantilla('Una', [$this->req('Estudios previos', 2, ['codigo_comun' => 'EP'])]);
        $otra = $this->plantilla('Otra', [$this->req('Estudios previos (otra)', 5, ['codigo_comun' => 'EP'])]);

        $conUna = $this->expediente([$una, $otra]);
        $this->leer($conUna)->assertJsonPath('requisitos.0.peso', 2)->assertJsonPath('progreso.peso_total', 2);

        $conOtra = $this->expediente([$otra, $una], $this->proceso('LP-002'));
        $this->leer($conOtra)->assertJsonPath('requisitos.0.peso', 5)->assertJsonPath('progreso.peso_total', 5);
    }

    // ------------------------------------------------------------ versiones y procesos

    public function test_la_vista_previa_de_una_version_nueva_trae_el_progreso_ponderado(): void
    {
        $plantilla = $this->plantilla('Licitación', [$this->req('Estudios previos', 3), $this->req('CDP')]);
        $expediente = $this->expediente([$plantilla]);
        $this->cumplir($expediente, 'Estudios previos');

        $borrador = app(CatalogoService::class)->crearBorrador('Más peso al CDP', $plantilla);
        $cdp = $borrador->requisitos()->where('texto', 'CDP')->sole();
        $admin = $this->como(Rol::Administrador);
        $admin->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$cdp->id}", ['texto' => 'CDP', 'peso' => 5])->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $url = "/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva";
        $this->como()->getJson($url)->assertOk()
            ->assertJsonPath('progreso_antes', $this->progreso(2, 1, 75, 4, 3))
            ->assertJsonPath('progreso_despues', $this->progreso(2, 1, 38, 8, 3));

        $this->como()->postJson($url, ['version_id' => $borrador->id])->assertOk()
            ->assertJsonPath('progreso', $this->progreso(2, 1, 38, 8, 3));
    }

    public function test_la_vista_previa_muestra_como_cambio_un_cambio_solo_de_peso_o_de_etapa(): void
    {
        $plantilla = $this->plantilla('Licitación', [$this->req('Estudios previos'), $this->req('CDP')]);
        $expediente = $this->expediente([$plantilla]);

        $borrador = app(CatalogoService::class)->crearBorrador('Peso y etapa', $plantilla);
        $cdp = $borrador->requisitos()->where('texto', 'CDP')->sole();
        $cierre = $this->etapa('cierre');
        $admin = $this->como(Rol::Administrador);
        $admin->putJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos/{$cdp->id}", ['texto' => 'CDP', 'peso' => 5, 'etapa_id' => $cierre->id])->assertOk();
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        $respuesta = $this->como()->getJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva")->assertOk();
        $respuesta->assertJsonCount(1, 'cambian')->assertJsonPath('siguen', 1)
            ->assertJsonPath('cambian.0.antes.peso', 1)->assertJsonPath('cambian.0.despues.peso', 5)
            ->assertJsonPath('cambian.0.antes.etapa.id', $this->etapa()->id)->assertJsonPath('cambian.0.despues.etapa.id', $cierre->id);
    }

    public function test_el_progreso_de_un_expediente_no_carga_las_etapas(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);
        $tabla = (new EtapaContratacion())->getTable();

        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });
        $progreso = app(ExpedienteService::class)->progresoDe($expediente);

        $this->assertSame(5, $progreso['total']);
        $this->assertEmpty(array_filter($consultas, fn (string $sql) => str_contains($sql, $tabla)));
    }

    public function test_los_procesos_y_la_ficha_traen_el_avance_ponderado(): void
    {
        $expediente = $this->expediente([$this->plantillaCriterio9()]);
        $this->cumplir($expediente, 'Estudios previos');
        $esperado = $this->progreso(5, 1, 43, 7, 3);

        $procesos = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos');
        $this->assertSame($esperado, $procesos[0]['expediente']['progreso']);

        $ficha = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('procesos');
        $this->assertSame($esperado, $ficha[0]['expediente']['progreso']);
    }

    public function test_el_resumen_de_procesos_ponderado_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantillaCriterio9();
        $this->expediente([$plantilla]);
        $consultas = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };
        $conUno = $consultas();

        foreach (range(2, 4) as $i) {
            $otro = $this->expediente([$plantilla], $this->proceso("LP-00{$i}"));
            $this->cumplir($otro, 'Estudios previos');
        }

        $this->assertSame($conUno, $consultas());
    }

    // ------------------------------------------------------------ criterio 10

    public function test_criterio_10_en_viabilizacion_un_peso_distinto_de_1_no_cambia_nada(): void
    {
        $this->proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $servicio = app(ExpedienteService::class);
        $expediente = $servicio->confirmarConfiguracion($servicio->crear($this->proyecto->fresh(), ['fase_sgr' => 3], $this->gestor), ['fase_sgr' => 3], $this->gestor);
        $fila = $expediente->requisitos()->where('aplica', true)->orderBy('orden')->firstOrFail();
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();

        $leer = fn () => $this->leer($expediente)->json();
        $resumen = fn () => collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())->firstWhere('expediente_id', $expediente->id);
        $antes = [$leer(), $resumen()];

        // Escrito directo en la base: la administración no deja ponerlo en viabilización.
        RequisitoCatalogo::whereKey($fila->requisito_catalogo_id)->update(['peso' => 5]);
        RequisitoCatalogo::whereIn('id', $fila->origenes()->pluck('requisitos_catalogo.id'))->update(['peso' => 5]);

        $despues = [$leer(), $resumen()];
        $this->assertSame($antes, $despues);
        $this->assertSame(['total', 'cumplidos', 'porcentaje'], array_keys($despues[0]['progreso']));
        $this->assertArrayNotHasKey('peso', $despues[0]['requisitos'][0]);
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['observacion' => 'Revisado'])->assertOk()
            ->assertJsonPath('progreso', $antes[0]['progreso'])
            ->assertJsonMissingPath('requisito.peso');
    }
}
