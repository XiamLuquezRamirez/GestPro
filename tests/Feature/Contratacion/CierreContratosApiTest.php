<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Contratacion\ContratosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 7A: terminar y liquidar un contrato (POST /contratos/{id}/terminar y /liquidar), la regla de
 * editar que no deja pasar a terminado o liquidado por el formulario, el aviso de datos de cierre
 * faltantes en los listados y la migración 24. Hoy es el 19 de noviembre de 2026 (en Bogotá) salvo
 * que la prueba diga otra cosa.
 */
class CierreContratosApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
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
            'valor' => 1200000,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => '2026-10-31',
            'fecha_fin' => '2026-11-30',
            'avance_fisico' => 80,
            'avance_financiero' => 60,
            'estado' => Contrato::EN_EJECUCION,
            ...$mas,
        ]);
    }

    private function version(Contrato $contrato): ?string
    {
        return $contrato->fresh()->updated_at?->format('Y-m-d H:i:s');
    }

    private function terminar(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/terminar", [
            'fecha_terminacion_real' => '2026-11-15',
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    private function liquidar(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/liquidar", [
            'fecha_liquidacion' => '2026-11-18',
            'valor_liquidado' => 1150000.5,
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')
            ->where('entidad_id', $contrato->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    /** Formulario de edición completo del contrato (como lo manda la pantalla). */
    private function editar(Contrato $contrato, array $mas = [])
    {
        $c = $contrato->fresh();

        return $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'id' => $c->id,
            'actualizado' => $this->version($c),
            'n_contrato' => $c->n_contrato,
            'objeto' => $c->objeto,
            'valor_inicial' => $c->valor_inicial,
            'estado' => $c->estado,
            'proyecto' => $c->proyecto,
            ...$mas,
        ], 'anexos' => null]);
    }

    // ------------------------------------------------------------------ permisos

    public function test_sin_token_responde_401_y_consulta_403(): void
    {
        $c = $this->contrato();

        $this->postJson("/GestPro/contratos/{$c->id}/terminar", ['fecha_terminacion_real' => '2026-11-15', 'actualizado' => null])->assertUnauthorized();
        $this->postJson("/GestPro/contratos/{$c->id}/liquidar", ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1, 'actualizado' => null])->assertUnauthorized();
        $this->terminar($c, [], Rol::Consulta)->assertForbidden();
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15'], Rol::Consulta)->assertForbidden();

        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame([], $this->eventos($c));
    }

    public function test_un_contrato_que_no_existe_responde_404(): void
    {
        $this->como()->postJson('/GestPro/contratos/9999/terminar', ['fecha_terminacion_real' => '2026-11-15', 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
        $this->como()->postJson('/GestPro/contratos/9999/liquidar', ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1, 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
    }

    // ------------------------------------------------------------------ terminar

    public static function estadosQueTerminan(): array
    {
        return [
            'celebrado' => [Contrato::CELEBRADO],
            'en ejecución' => [Contrato::EN_EJECUCION],
            'suspendido' => [Contrato::SUSPENDIDO],
            'terminado (corrige la fecha)' => [Contrato::TERMINADO],
        ];
    }

    #[DataProvider('estadosQueTerminan')]
    public function test_termina_desde_cada_estado_permitido(string $estado): void
    {
        $c = $this->contrato(['estado' => $estado, 'fecha_terminacion_real' => $estado === Contrato::TERMINADO ? '2026-11-01' : null]);

        $r = $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15'])->assertOk();

        $this->assertSame(['success', 'contrato', 'avisos'], array_keys($r->json()));
        $r->assertJsonPath('contrato.id', $c->id)
            ->assertJsonPath('contrato.estado', Contrato::TERMINADO)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-15')
            ->assertJsonPath('contrato.fecha_liquidacion', null)
            ->assertJsonPath('contrato.valor_liquidado', null)
            ->assertJsonPath('contrato.aviso_cierre', null)
            ->assertJsonPath('avisos', []);
        $fila = $c->fresh();
        $this->assertSame(Contrato::TERMINADO, $fila->estado);
        $this->assertSame('2026-11-15', $fila->fecha_terminacion_real->format('Y-m-d'));
    }

    public function test_no_termina_un_contrato_liquidado_ni_uno_anulado(): void
    {
        $liquidado = $this->contrato(['estado' => Contrato::LIQUIDADO]);
        $anulado = $this->contrato(['n_contrato' => 'C-002', 'estado' => Contrato::ANULADO]);

        $this->terminar($liquidado)->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato está liquidado: corrige la terminación desde «Liquidar».');
        $this->terminar($anulado)->assertUnprocessable()->assertJsonPath('mensaje', 'El contrato está anulado.');

        $this->assertNull($liquidado->fresh()->fecha_terminacion_real);
        $this->assertSame(Contrato::ANULADO, $anulado->fresh()->estado);
        $this->assertSame([], $this->eventos($liquidado));
    }

    public function test_la_fecha_de_terminacion_es_obligatoria_y_con_formato(): void
    {
        $c = $this->contrato();

        $this->terminar($c, ['fecha_terminacion_real' => null])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'Escribe la fecha de terminación real.');
        $this->terminar($c, ['fecha_terminacion_real' => '15/11/2026'])->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_terminacion_real');
        $this->terminar($c, ['observacion' => str_repeat('a', 2001)])->assertUnprocessable()
            ->assertJsonValidationErrors('observacion');
        $this->como()->postJson("/GestPro/contratos/{$c->id}/terminar", ['fecha_terminacion_real' => '2026-11-15'])
            ->assertUnprocessable()->assertJsonValidationErrors('actualizado');
    }

    public function test_la_fecha_de_terminacion_no_es_posterior_a_hoy_en_bogota(): void
    {
        // 03:00 del 20 en UTC son las 22:00 del 19 en Bogotá: hoy es el 19.
        $this->travelTo('2026-11-20 03:00:00');
        $c = $this->contrato();

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser posterior a hoy.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-19'])->assertOk();
    }

    public function test_la_fecha_de_terminacion_no_es_anterior_a_la_de_inicio(): void
    {
        $c = $this->contrato(['fecha_inicio' => '2026-02-01']);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-01-31'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser anterior a la de inicio.');
        $this->terminar($c, ['fecha_terminacion_real' => '2026-02-01'])->assertOk();

        // Sin fecha de inicio, no hay límite inferior.
        $sinInicio = $this->contrato(['n_contrato' => 'C-002', 'fecha_inicio' => null]);
        $this->terminar($sinInicio, ['fecha_terminacion_real' => '2020-01-01'])->assertOk();
    }

    public function test_terminar_con_una_version_vieja_responde_409_y_adelanta_la_version(): void
    {
        $c = $this->contrato();
        $vieja = $this->version($c);

        $this->terminar($c, ['actualizado' => '2020-01-01 00:00:00'])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        // En el mismo segundo: la versión igual cambia.
        $r = $this->terminar($c)->assertOk();
        $this->assertNotSame($vieja, $r->json('contrato.updated_at'));
        $this->assertSame($r->json('contrato.updated_at'), $this->version($c));
        $this->terminar($c, ['actualizado' => $vieja])->assertStatus(409);
    }

    public function test_terminar_deja_historial_con_antes_y_despues(): void
    {
        $c = $this->contrato();

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15', 'observacion' => '  Acta de terminación  '])->assertOk();
        $this->terminar($c->fresh(), ['fecha_terminacion_real' => '2026-11-16'])->assertOk();

        $this->assertSame([
            ['contrato_terminado',
                ['estado' => 'en_ejecucion', 'fecha_terminacion_real' => null],
                ['estado' => 'terminado', 'fecha_terminacion_real' => '2026-11-15', 'observacion' => 'Acta de terminación']],
            ['contrato_terminado',
                ['estado' => 'terminado', 'fecha_terminacion_real' => '2026-11-15'],
                ['estado' => 'terminado', 'fecha_terminacion_real' => '2026-11-16', 'observacion' => null]],
        ], $this->eventos($c));
    }

    // ------------------------------------------------------------------ liquidar

    public function test_liquida_un_contrato_terminado_con_la_fecha_que_ya_tiene(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        $r = $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => '1150000.5'])->assertOk();

        $this->assertSame(['success', 'contrato', 'avisos'], array_keys($r->json()));
        $r->assertJsonPath('contrato.estado', Contrato::LIQUIDADO)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-10')
            ->assertJsonPath('contrato.fecha_liquidacion', '2026-11-18')
            ->assertJsonPath('contrato.valor_liquidado', '1150000.50')
            ->assertJsonPath('contrato.aviso_cierre', null)
            ->assertJsonPath('avisos', []);
        $fila = $c->fresh();
        $this->assertSame(Contrato::LIQUIDADO, $fila->estado);
        $this->assertSame('1150000.50', $fila->valor_liquidado);
    }

    public function test_liquidar_sin_fecha_de_terminacion_la_pide(): void
    {
        $c = $this->contrato();

        $this->liquidar($c)->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'Escribe la fecha de terminación real.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-12'])->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::LIQUIDADO)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-12');
    }

    public function test_liquidar_un_liquidado_completa_y_corrige_sus_datos(): void
    {
        // Como el contrato 5 de gest_pro: liquidado sin datos de cierre.
        $c = $this->contrato(['estado' => Contrato::LIQUIDADO]);

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => 1000000])->assertOk()
            ->assertJsonPath('contrato.aviso_cierre', null);
        $this->liquidar($c->fresh(), ['fecha_liquidacion' => '2026-11-06', 'valor_liquidado' => 999999.99])->assertOk()
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-01')
            ->assertJsonPath('contrato.fecha_liquidacion', '2026-11-06')
            ->assertJsonPath('contrato.valor_liquidado', '999999.99');

        $eventos = $this->eventos($c);
        $this->assertSame(['contrato_liquidado', 'contrato_liquidado'], array_column($eventos, 0));
        $this->assertSame(
            ['estado' => 'liquidado', 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => '1000000.00'],
            $eventos[1][1]
        );
    }

    public function test_no_liquida_un_contrato_anulado(): void
    {
        $c = $this->contrato(['estado' => Contrato::ANULADO]);

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-01'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato está anulado.');
        $this->assertNull($c->fresh()->fecha_liquidacion);
    }

    public function test_las_fechas_de_liquidar(): void
    {
        $c = $this->contrato();

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-14'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_liquidacion.0', 'La fecha de liquidación no puede ser anterior a la de terminación.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_liquidacion.0', 'La fecha de liquidación no puede ser posterior a hoy.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-20', 'fecha_liquidacion' => '2026-11-19'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser posterior a hoy.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-01-15'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser anterior a la de inicio.');
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => null])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_liquidacion.0', 'Escribe la fecha de liquidación.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        // El mismo día de la terminación vale.
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-15'])->assertOk();
    }

    public function test_el_valor_liquidado_es_obligatorio_no_negativo_y_con_dos_decimales(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        $this->liquidar($c, ['valor_liquidado' => -1])->assertUnprocessable()
            ->assertJsonPath('errors.valor_liquidado.0', 'El valor liquidado no puede ser negativo.');
        $this->liquidar($c, ['valor_liquidado' => null])->assertUnprocessable()
            ->assertJsonPath('errors.valor_liquidado.0', 'Escribe el valor liquidado.');
        $this->liquidar($c, ['valor_liquidado' => '10.123'])->assertUnprocessable()
            ->assertJsonValidationErrors('valor_liquidado');
        $this->liquidar($c, ['valor_liquidado' => 'mil'])->assertUnprocessable()
            ->assertJsonValidationErrors('valor_liquidado');
        $this->liquidar($c, ['valor_liquidado' => '12345678901234'])->assertUnprocessable()
            ->assertJsonValidationErrors('valor_liquidado');
        $this->assertSame(Contrato::TERMINADO, $c->fresh()->estado);

        $this->liquidar($c, ['valor_liquidado' => 0])->assertOk()->assertJsonPath('contrato.valor_liquidado', '0.00');
    }

    public function test_avisa_si_el_valor_liquidado_supera_el_vigente(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10', 'valor' => 1200000]);

        $this->liquidar($c, ['valor_liquidado' => '1200000.01'])->assertOk()
            ->assertJsonPath('avisos', ['El valor liquidado supera el valor vigente del contrato.'])
            ->assertJsonPath('contrato.estado', Contrato::LIQUIDADO);
        $this->liquidar($c->fresh(), ['valor_liquidado' => 1200000])->assertOk()->assertJsonPath('avisos', []);
    }

    public function test_liquidar_con_una_version_vieja_responde_409(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        $this->liquidar($c, ['actualizado' => '2020-01-01 00:00:00'])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::TERMINADO, $c->fresh()->estado);
    }

    public function test_liquidar_deja_historial_con_antes_y_despues(): void
    {
        $c = $this->contrato();

        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-12', 'observacion' => 'Acta de liquidación bilateral'])->assertOk();

        $this->assertSame([
            ['contrato_liquidado',
                ['estado' => 'en_ejecucion', 'fecha_terminacion_real' => null, 'fecha_liquidacion' => null, 'valor_liquidado' => null],
                ['estado' => 'liquidado', 'fecha_terminacion_real' => '2026-11-12', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => '1150000.50', 'observacion' => 'Acta de liquidación bilateral']],
        ], $this->eventos($c));
    }

    public function test_terminar_y_liquidar_no_cambian_valores_fechas_fin_ni_avances(): void
    {
        $c = $this->contrato();
        $campos = ['valor', 'valor_inicial', 'fecha_fin', 'fecha_fin_inicial', 'avance_fisico', 'avance_financiero'];
        $antes = (array) DB::table('contratos')->where('id', $c->id)->first($campos);

        $this->terminar($c)->assertOk();
        $this->assertEquals($antes, (array) DB::table('contratos')->where('id', $c->id)->first($campos));
        $this->liquidar($c->fresh(), ['valor_liquidado' => 5])->assertOk();
        $this->assertEquals($antes, (array) DB::table('contratos')->where('id', $c->id)->first($campos));
    }

    // ------------------------------------------------------------------ editar y crear

    public function test_editar_no_pasa_a_terminado_ni_a_liquidado(): void
    {
        $c = $this->contrato();
        $terminado = $this->contrato(['n_contrato' => 'C-002', 'estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);

        foreach ([Contrato::TERMINADO, Contrato::LIQUIDADO] as $estado) {
            $this->editar($c, ['estado' => $estado])->assertUnprocessable()
                ->assertJsonPath('errors.estado.0', 'Para terminar o liquidar el contrato usa «Terminar» o «Liquidar».');
        }
        $this->editar($terminado, ['estado' => Contrato::LIQUIDADO])->assertUnprocessable()
            ->assertJsonValidationErrors('estado');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame(Contrato::TERMINADO, $terminado->fresh()->estado);

        // Quedarse en el mismo estado sí: se editan otros campos.
        $this->editar($terminado, ['objeto' => 'Objeto corregido'])->assertOk()->assertJsonPath('contrato.estado', Contrato::TERMINADO);
    }

    public function test_editar_saca_de_liquidado_y_conserva_los_datos_de_cierre(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10']);
        $this->liquidar($c)->assertOk();

        // El formulario no escribe los datos de cierre aunque los mande.
        $this->editar($c, ['estado' => Contrato::EN_EJECUCION, 'fecha_liquidacion' => null, 'valor_liquidado' => 1, 'fecha_terminacion_real' => null])
            ->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION)
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-10')
            ->assertJsonPath('contrato.fecha_liquidacion', '2026-11-18')
            ->assertJsonPath('contrato.valor_liquidado', '1150000.50')
            ->assertJsonPath('contrato.aviso_cierre', null);

        $fila = $c->fresh();
        $this->assertSame('2026-11-18', $fila->fecha_liquidacion->format('Y-m-d'));
        $this->assertSame('1150000.50', $fila->valor_liquidado);
    }

    public function test_crear_un_contrato_liquidado_queda_con_el_aviso(): void
    {
        $r = $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'n_contrato' => 'C-SECOP',
            'objeto' => 'Contrato que ya estaba liquidado en SECOP II',
            'valor_inicial' => 500000,
            'estado' => Contrato::LIQUIDADO,
            'proyecto' => $this->proyecto->id,
            'fecha_liquidacion' => '2026-01-01',
        ], 'anexos' => []])->assertOk();

        $r->assertJsonPath('contrato.estado', Contrato::LIQUIDADO)
            ->assertJsonPath('contrato.fecha_liquidacion', null)
            ->assertJsonPath('contrato.aviso_cierre', ['faltan' => ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado']]);

        $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'n_contrato' => 'C-SECOP-2', 'objeto' => 'Terminado en SECOP II', 'valor_inicial' => 1,
            'estado' => Contrato::TERMINADO, 'proyecto' => $this->proyecto->id,
        ], 'anexos' => []])->assertOk()
            ->assertJsonPath('contrato.aviso_cierre', ['faltan' => ['fecha_terminacion_real']]);
    }

    // ------------------------------------------------------------------ listados

    public function test_el_aviso_de_cierre_en_la_busqueda_el_detalle_y_el_listado_por_proyecto(): void
    {
        $sinDatos = $this->contrato(['n_contrato' => 'A', 'estado' => Contrato::LIQUIDADO]);
        $faltaValor = $this->contrato(['n_contrato' => 'B', 'estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05']);
        $terminadoSinFecha = $this->contrato(['n_contrato' => 'C', 'estado' => Contrato::TERMINADO]);
        $completo = $this->contrato(['n_contrato' => 'D', 'estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => 990000]);
        $enEjecucion = $this->contrato(['n_contrato' => 'E']);

        $esperado = [
            $sinDatos->id => ['faltan' => ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado']],
            $faltaValor->id => ['faltan' => ['valor_liquidado']],
            $terminadoSinFecha->id => ['faltan' => ['fecha_terminacion_real']],
            $completo->id => null,
            $enEjecucion->id => null,
        ];

        DB::enableQueryLog();
        $busqueda = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $consultasCinco = count(DB::getQueryLog());
        $listado = collect($this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json())->keyBy('id');

        foreach ($esperado as $id => $aviso) {
            $this->assertSame($aviso, $busqueda[$id]['aviso_cierre'], "búsqueda #{$id}");
            $this->assertSame($aviso, $listado[$id]['aviso_cierre'], "listado #{$id}");
            $this->assertSame($aviso, $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$id}/detalle")->assertOk()->json('aviso_cierre'), "detalle #{$id}");
        }
        $this->assertEquals(990000, $busqueda[$completo->id]['valor_liquidado']);
        $this->assertSame('2026-11-01', substr($busqueda[$completo->id]['fecha_terminacion_real'], 0, 10));
        $this->assertSame('2026-11-05', substr($listado[$completo->id]['fecha_liquidacion'], 0, 10));

        // Sin consultas por fila: con más contratos, la búsqueda hace las mismas consultas.
        $this->contrato(['n_contrato' => 'F', 'estado' => Contrato::LIQUIDADO]);
        $this->contrato(['n_contrato' => 'G', 'estado' => Contrato::TERMINADO]);
        DB::flushQueryLog();
        $this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk();
        $this->assertSame($consultasCinco, count(DB::getQueryLog()));
    }

    public function test_get_proyectos_trae_los_datos_y_el_aviso_de_cierre(): void
    {
        $sinDatos = $this->contrato(['n_contrato' => 'A', 'estado' => Contrato::LIQUIDADO]);
        $completo = $this->contrato(['n_contrato' => 'B', 'estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-05', 'valor_liquidado' => 990000]);

        $contratos = collect(collect($this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json())
            ->firstWhere('id', $this->proyecto->id)['contratos'])->keyBy('id');

        $this->assertSame(['faltan' => ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado']], $contratos[$sinDatos->id]['aviso_cierre']);
        $this->assertNull($contratos[$sinDatos->id]['fecha_liquidacion']);
        $this->assertNull($contratos[$completo->id]['aviso_cierre']);
        $this->assertSame('2026-11-01', substr($contratos[$completo->id]['fecha_terminacion_real'], 0, 10));
        $this->assertSame('2026-11-05', substr($contratos[$completo->id]['fecha_liquidacion'], 0, 10));
        $this->assertEquals(990000, $contratos[$completo->id]['valor_liquidado']);
    }

    // ------------------------------------------------------------------ revisión: fechas cruzadas

    public function test_terminar_no_acepta_una_fecha_posterior_a_la_liquidacion_que_conserva(): void
    {
        // Liquidado y sacado de liquidado por el formulario: conserva la fecha de liquidación.
        $c = $this->contrato(['estado' => Contrato::EN_EJECUCION, 'fecha_terminacion_real' => '2026-11-01', 'fecha_liquidacion' => '2026-11-10', 'valor_liquidado' => 5]);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-11'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_terminacion_real.0', 'La fecha de terminación no puede ser posterior a la de liquidación.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame([], $this->eventos($c));

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-10'])->assertOk()
            ->assertJsonPath('contrato.fecha_terminacion_real', '2026-11-10');
    }

    public function test_editar_no_acepta_una_fecha_de_inicio_posterior_a_la_terminacion_real(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-10', 'fecha_fin_inicial' => '2026-12-31']);

        $this->editar($c, ['fecha_inicio' => '2026-11-11'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_inicio.0', 'La fecha de inicio no puede ser posterior a la de terminación real.');
        $this->assertSame('2026-02-01', $c->fresh()->fecha_inicio->format('Y-m-d'));

        $this->editar($c, ['fecha_inicio' => '2026-11-10'])->assertOk();
        // Sin terminación real no hay límite.
        $otro = $this->contrato(['n_contrato' => 'C-002', 'fecha_fin_inicial' => '2026-12-31']);
        $this->editar($otro, ['fecha_inicio' => '2026-11-30'])->assertOk();
    }

    // ------------------------------------------------------------------ revisión: sin cambios

    public function test_terminar_sin_cambios_ni_observacion_no_guarda_ni_adelanta_la_version(): void
    {
        $c = $this->contrato(['estado' => Contrato::TERMINADO, 'fecha_terminacion_real' => '2026-11-15']);
        $version = $this->version($c);

        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15'])->assertOk()
            ->assertJsonPath('contrato.updated_at', $version)
            ->assertJsonPath('contrato.estado', Contrato::TERMINADO)
            ->assertJsonPath('avisos', []);
        $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15', 'observacion' => '   '])->assertOk();
        $this->assertSame($version, $this->version($c));
        $this->assertSame([], $this->eventos($c));

        // Con observación sí queda registro y cambia la versión.
        $r = $this->terminar($c, ['fecha_terminacion_real' => '2026-11-15', 'observacion' => 'Acta firmada'])->assertOk();
        $this->assertNotSame($version, $r->json('contrato.updated_at'));
        $this->assertSame(['contrato_terminado'], array_column($this->eventos($c), 0));
    }

    public function test_liquidar_sin_cambios_ni_observacion_no_guarda_pero_responde_los_avisos(): void
    {
        $c = $this->contrato(['estado' => Contrato::LIQUIDADO, 'valor' => 1000, 'fecha_terminacion_real' => '2026-11-10', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500]);
        $version = $this->version($c);

        $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => '1500.00'])->assertOk()
            ->assertJsonPath('contrato.updated_at', $version)
            ->assertJsonPath('contrato.valor_liquidado', '1500.00')
            ->assertJsonPath('avisos', ['El valor liquidado supera el valor vigente del contrato.']);
        $this->liquidar($c, ['fecha_terminacion_real' => '2026-11-10', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500])->assertOk();
        $this->assertSame($version, $this->version($c));
        $this->assertSame([], $this->eventos($c));

        // Una versión vieja sigue respondiendo 409 aunque no haya cambios.
        $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500, 'actualizado' => '2020-01-01 00:00:00'])->assertStatus(409);

        $this->liquidar($c, ['fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500, 'observacion' => 'Revisada'])->assertOk();
        $this->assertNotSame($version, $this->version($c));
        $this->assertSame(['contrato_liquidado'], array_column($this->eventos($c), 0));
    }

    // ------------------------------------------------------------------ revisión: eliminar

    public function test_eliminar_guarda_los_datos_de_cierre_en_el_historial(): void
    {
        $c = $this->contrato(['estado' => Contrato::LIQUIDADO, 'fecha_terminacion_real' => '2026-11-10', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1500.5]);

        $this->como()->postJson('/GestPro/eliminarContrato', ['id' => $c->id])->assertOk();

        [$evento, $antes] = collect($this->eventos($c))->last();
        $this->assertSame('contrato_eliminado', $evento);
        $this->assertSame('2026-11-10', $antes['fecha_terminacion_real']);
        $this->assertSame('2026-11-18', $antes['fecha_liquidacion']);
        $this->assertSame('1500.50', $antes['valor_liquidado']);
    }

    // ------------------------------------------------------------------ migración

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100023_add_liquidacion_a_contratos.php');
    }

    public function test_la_migracion_agrega_las_columnas_nulas_y_su_rollback_se_detiene_con_datos(): void
    {
        $columnas = collect(Schema::getColumns('contratos'))->keyBy('name');
        foreach (['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'] as $columna) {
            $this->assertTrue($columnas->has($columna), $columna);
            $this->assertTrue($columnas[$columna]['nullable'], $columna);
        }

        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $c = $this->contrato();

        foreach ([['fecha_terminacion_real' => '2026-11-01'], ['fecha_liquidacion' => '2026-11-05'], ['valor_liquidado' => 0]] as $dato) {
            DB::table('contratos')->where('id', $c->id)->update(['fecha_terminacion_real' => null, 'fecha_liquidacion' => null, 'valor_liquidado' => null, ...$dato]);
            try {
                $migracion->down();
                $this->fail('El rollback debía detenerse: ' . key($dato));
            } catch (RuntimeException $e) {
                $this->assertSame('No se puede revertir: hay contratos con fecha de terminación, fecha de liquidación o valor liquidado registrados, que se perderían. Restaure un respaldo.', $e->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('contratos', 'valor_liquidado'));
        }

        DB::table('contratos')->update(['fecha_terminacion_real' => null, 'fecha_liquidacion' => null, 'valor_liquidado' => null]);
        $migracion->down();
        $migracion->down();
        foreach (['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'] as $columna) {
            $this->assertFalse(Schema::hasColumn('contratos', $columna), $columna);
        }
        $this->assertSame('C-001-2026', DB::table('contratos')->value('n_contrato'));
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('contratos', 'fecha_liquidacion'));
    }
}
