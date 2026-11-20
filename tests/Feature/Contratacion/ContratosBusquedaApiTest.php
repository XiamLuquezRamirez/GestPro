<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\ActividadAvance;
use App\Models\ActividadContrato;
use App\Models\AnexoContrato;
use App\Models\AvanceFinanciero;
use App\Models\Contrato;
use App\Models\Modalidad;
use App\Models\ModificacionContrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Contratos: búsqueda de todos los contratos (con o sin proyecto) y detalle de uno. */
class ContratosBusquedaApiTest extends TestCase
{
    use RefreshDatabase;

    private const BUSCAR = '/GestPro/contratos/buscar';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    private function como(Rol|User $quien = Rol::Consulta): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function contrato(array $mas = []): Contrato
    {
        return Contrato::create([
            'proyecto' => $this->proyecto->id,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'valor' => 1000000,
            'estado' => 'en_ejecucion',
            ...$mas,
        ]);
    }

    private function proceso(?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Obra del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'estado' => 'adjudicado',
        ]);
    }

    private function ids(array $filtros = []): array
    {
        return collect($this->como()->getJson(self::BUSCAR . '?' . http_build_query($filtros))->assertOk()->json())->pluck('id')->all();
    }

    // ------------------------------------------------------------------ buscar

    public function test_la_fila_trae_los_campos_acordados(): void
    {
        $tipo = TipoProceso::create(['nombre' => 'Obra', 'activo' => true]);
        $proceso = $this->proceso();
        $c = $this->contrato([
            'contratista' => 'Constructora S.A.S.', 'contratante' => 'Alcaldía', 'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31', 'avance_fisico' => 40, 'avance_financiero' => 30,
            'tipo_contrato_id' => $tipo->id, 'proceso_licitacion' => $proceso->id, 'secop_id_contrato' => 'CO1.PCCNTR.1',
        ]);

        $filas = $this->como()->getJson(self::BUSCAR)->assertOk()->json();

        $this->assertCount(1, $filas);
        $fila = $filas[0];
        $this->assertSame(
            ['id', 'n_contrato', 'objeto', 'contratista', 'contratante', 'estado', 'valor', 'valor_inicial', 'fecha_inicio', 'fecha_fin',
                // Fase 7A: datos de cierre y su aviso.
                'fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado',
                'avance_fisico', 'avance_financiero', 'tipo_contrato', 'proyecto', 'proceso', 'aviso_proceso', 'aviso_cierre',
                // Fase 7B: aviso de suspensión.
                'aviso_suspension', 'secop_id_contrato', 'updated_at'],
            array_keys($fila),
        );
        $this->assertSame($c->id, $fila['id']);
        $this->assertSame('C-001-2026', $fila['n_contrato']);
        $this->assertSame('Constructora S.A.S.', $fila['contratista']);
        $this->assertSame('en_ejecucion', $fila['estado']);
        $this->assertEquals(1000000, $fila['valor']);
        $this->assertEquals(1000000, $fila['valor_inicial']);
        $this->assertSame('2026-01-01', substr($fila['fecha_inicio'], 0, 10));
        $this->assertSame(40, $fila['avance_fisico']);
        $this->assertSame(30, $fila['avance_financiero']);
        $this->assertSame(['id' => $tipo->id, 'nombre' => 'Obra'], $fila['tipo_contrato']);
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal'], $fila['proyecto']);
        $this->assertSame(['id', 'codigo_proceso', 'objeto', 'estado', 'modalidad', 'plataforma', 'url_proceso'], array_keys($fila['proceso']));
        $this->assertSame($proceso->id, $fila['proceso']['id']);
        $this->assertSame('CO1.PCCNTR.1', $fila['secop_id_contrato']);
        $this->assertNotNull($fila['updated_at']);
    }

    public function test_un_contrato_sin_proyecto_ni_proceso_ni_tipo_trae_nulos(): void
    {
        $this->contrato(['proyecto' => null]);

        $fila = $this->como()->getJson(self::BUSCAR)->assertOk()->json(0);

        $this->assertNull($fila['proyecto']);
        $this->assertNull($fila['proceso']);
        $this->assertNull($fila['tipo_contrato']);
        $this->assertNull($fila['avance_fisico']);
    }

    public function test_devuelve_todos_ordenados_por_id_descendente(): void
    {
        $a = $this->contrato(['n_contrato' => 'A']);
        $b = $this->contrato(['n_contrato' => 'B', 'proyecto' => null]);
        $c = $this->contrato(['n_contrato' => 'C']);

        $this->assertSame([$c->id, $b->id, $a->id], $this->ids());
    }

    public function test_sin_contratos_devuelve_una_lista_vacia(): void
    {
        $this->como()->getJson(self::BUSCAR)->assertOk()->assertExactJson([]);
    }

    public function test_filtra_por_texto_en_todos_los_campos_sin_distinguir_mayusculas(): void
    {
        $numero = $this->contrato(['n_contrato' => 'CTO-777']);
        $objeto = $this->contrato(['n_contrato' => 'X1', 'objeto' => 'Pavimentación de la Vía Norte']);
        $contratista = $this->contrato(['n_contrato' => 'X2', 'contratista' => 'Ingeniería Andina']);
        $contratante = $this->contrato(['n_contrato' => 'X3', 'contratante' => 'Gobernación del Cauca']);
        $nit = $this->contrato(['n_contrato' => 'X4', 'contratante_nit' => '800.123.456-7']);
        $identificacion = $this->contrato(['n_contrato' => 'X5', 'contratista_identificacion' => '900555111']);
        $this->contrato(['n_contrato' => 'X6']);

        $this->assertSame([$numero->id], $this->ids(['q' => 'cto-777']));
        $this->assertSame([$objeto->id], $this->ids(['q' => 'vía norte']));
        $this->assertSame([$contratista->id], $this->ids(['q' => 'ANDINA']));
        $this->assertSame([$contratante->id], $this->ids(['q' => 'cauca']));
        $this->assertSame([$nit->id], $this->ids(['q' => '123.456']));
        $this->assertSame([$identificacion->id], $this->ids(['q' => '555111']));
    }

    public function test_el_texto_no_trata_porcentaje_ni_guion_bajo_como_comodines(): void
    {
        $this->contrato(['n_contrato' => 'ABC']);
        $literal = $this->contrato(['n_contrato' => 'A%C_1']);

        $this->assertSame([$literal->id], $this->ids(['q' => '%C_']));
        $this->assertSame([], $this->ids(['q' => 'A_C']));
    }

    public function test_filtra_por_estado(): void
    {
        $this->contrato(['n_contrato' => 'A', 'estado' => 'en_ejecucion']);
        $liquidado = $this->contrato(['n_contrato' => 'B', 'estado' => 'liquidado']);

        $this->assertSame([$liquidado->id], $this->ids(['estado' => 'liquidado']));
    }

    public function test_filtra_por_proyecto_y_sin_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Otro']);
        $this->contrato(['n_contrato' => 'A']);
        $deOtro = $this->contrato(['n_contrato' => 'B', 'proyecto' => $otro->id]);
        $suelto = $this->contrato(['n_contrato' => 'C', 'proyecto' => null]);

        $this->assertSame([$deOtro->id], $this->ids(['proyecto' => $otro->id]));
        $this->assertSame([$suelto->id], $this->ids(['proyecto' => 'sin']));
    }

    public function test_filtra_por_proceso(): void
    {
        $proceso = $this->proceso();
        $this->contrato(['n_contrato' => 'A']);
        $vinculado = $this->contrato(['n_contrato' => 'B', 'proceso_licitacion' => $proceso->id]);

        $this->assertSame([$vinculado->id], $this->ids(['proceso' => $proceso->id]));
    }

    public function test_combina_filtros(): void
    {
        $esta = $this->contrato(['n_contrato' => 'A', 'objeto' => 'Puente', 'estado' => 'terminado']);
        $this->contrato(['n_contrato' => 'B', 'objeto' => 'Puente', 'estado' => 'en_ejecucion']);
        $this->contrato(['n_contrato' => 'C', 'objeto' => 'Vía', 'estado' => 'terminado']);

        $this->assertSame([$esta->id], $this->ids(['q' => 'puente', 'estado' => 'terminado', 'proyecto' => $this->proyecto->id]));
    }

    public function test_los_filtros_vacios_se_ignoran(): void
    {
        $a = $this->contrato();

        $this->assertSame([$a->id], $this->ids(['q' => '', 'estado' => '', 'proyecto' => '', 'proceso' => '']));
    }

    public function test_filtros_invalidos_responden_422_con_mensaje_por_campo(): void
    {
        $respuesta = $this->como()->getJson(self::BUSCAR . '?' . http_build_query([
            'estado' => 'cualquiera', 'proyecto' => 'abc', 'proceso' => '-1', 'q' => str_repeat('a', 101),
        ]))->assertStatus(422);

        $errores = $respuesta->json('errors');
        $this->assertEqualsCanonicalizing(['estado', 'proyecto', 'proceso', 'q'], array_keys($errores));
        $this->assertSame('Elige un estado de la lista.', $errores['estado'][0]);
        $this->assertSame('Elige un proyecto de la lista o "sin".', $errores['proyecto'][0]);
        $this->assertSame('Revisa el proceso: debe ser un número entero.', $errores['proceso'][0]);
        $this->assertSame('En la búsqueda caben hasta 100 caracteres.', $errores['q'][0]);
    }

    public function test_cualquier_usuario_autenticado_lee_y_sin_sesion_no(): void
    {
        $this->contrato();

        $this->getJson(self::BUSCAR)->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson(self::BUSCAR)->assertOk()->assertJsonCount(1);
        }
    }

    // ------------------------------------------------------------------ detalle

    private function conTodo(): Contrato
    {
        $tipo = TipoProceso::create(['nombre' => 'Obra', 'activo' => true]);
        $c = $this->contrato(['proceso_licitacion' => $this->proceso()->id, 'tipo_contrato_id' => $tipo->id]);
        AnexoContrato::create(['contrato_id' => $c->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'm.pdf', 'ruta_archivo' => 'anexos_contratos/m.pdf', 'fecha' => '2026-01-02']);
        AvanceFinanciero::create(['contrato_id' => $c->id, 'descripcion' => 'Acta 1', 'fecha_acta' => '2026-02-01', 'valor_facturado' => 10]);
        ModificacionContrato::create(['contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-03-01']);
        $actividad = ActividadContrato::create(['contrato_id' => $c->id, 'nombre' => 'Excavación', 'peso' => 50]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-02-01', 'porcentaje_ejecucion' => 20]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-03-01', 'porcentaje_ejecucion' => 60]);

        return $c;
    }

    public function test_el_detalle_tiene_la_misma_forma_que_un_elemento_de_listar_contratos(): void
    {
        $c = $this->conTodo();

        $listado = $this->como()->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json(0);
        $detalle = $this->como()->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()->json();

        $this->assertSame('Acueducto veredal', $detalle['proyecto_nombre']);
        unset($detalle['proyecto_nombre']);
        $this->assertEquals($listado, $detalle);
        $this->assertSame(array_keys($listado), array_keys($detalle));
        $this->assertCount(1, $detalle['anexos']);
        $this->assertCount(1, $detalle['avancesFinancieros']);
        $this->assertCount(1, $detalle['modificaciones']);
        $this->assertEquals(60, $detalle['actividades'][0]['ultimo_avance']);
        $this->assertCount(2, $detalle['actividades'][0]['avances']);
        $this->assertSame($c->proceso_licitacion, $detalle['proceso_id']);
        $this->assertSame('LP-001-2026', $detalle['proceso']['codigo_proceso']);
    }

    public function test_el_detalle_de_un_contrato_sin_proyecto(): void
    {
        $c = $this->contrato(['proyecto' => null]);

        $detalle = $this->como()->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()->json();

        $this->assertNull($detalle['proyecto']);
        $this->assertNull($detalle['proyecto_nombre']);
        $this->assertNull($detalle['proceso']);
        $this->assertSame([], $detalle['anexos']);
        $this->assertSame([], $detalle['actividades']);
    }

    public function test_el_detalle_inexistente_responde_404_con_mensaje(): void
    {
        $this->como()->getJson('/GestPro/contratos/9999/detalle')->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
    }

    public function test_el_detalle_con_un_id_fuera_de_rango_responde_404_con_mensaje(): void
    {
        foreach (['99999999999999999999', '9223372036854775808'] as $id) {
            $this->como()->getJson("/GestPro/contratos/{$id}/detalle")->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
        }
    }

    public function test_el_detalle_exige_sesion_y_lo_lee_cualquier_rol(): void
    {
        $c = $this->contrato();

        $this->getJson("/GestPro/contratos/{$c->id}/detalle")->assertUnauthorized();
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk();
    }

    // ------------------------------------------------------------------ rutas

    public function test_las_rutas_del_spa_no_son_rutas_get_de_la_api(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('GET', $ruta->methods(), true))
            ->map(fn ($ruta) => $ruta->uri())
            ->all();

        $this->assertNotContains('GestPro/contratos', $uris);
        $this->assertNotContains('GestPro/contratos/{contrato}', $uris);
        $this->assertContains('GestPro/contratos/buscar', $uris);
        $this->assertContains('GestPro/contratos/{contrato}/detalle', $uris);
    }

    public function test_las_rutas_existentes_de_contratos_no_cambian(): void
    {
        $this->contrato();

        $this->como()->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->assertJsonCount(1);
        $this->como(Rol::Consulta)->postJson('/GestPro/guardarContrato', ['formContrato' => []])->assertForbidden();
        $this->como(Rol::Consulta)->postJson('/GestPro/eliminarContrato', ['id' => 1])->assertForbidden();
    }
}
