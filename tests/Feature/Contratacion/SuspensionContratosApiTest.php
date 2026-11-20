<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Contratacion\ContratosService;
use App\Services\Contratacion\SuspensionContratos;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 7B: suspender y reiniciar un contrato (POST /contratos/{id}/suspender y /reiniciar), las
 * suspensiones en la pestaña Modificaciones (guardar y eliminar), las reglas de editar, el ajuste de
 * terminar y liquidar, el aviso de suspensión en los listados y la migración 25. Hoy es el 19 de
 * noviembre de 2026 (en Bogotá) salvo que la prueba diga otra cosa.
 */
class SuspensionContratosApiTest extends TestCase
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
            'valor' => 1000000,
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => '2026-12-31',
            'fecha_fin' => '2026-12-31',
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

    private function suspender(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/suspender", [
            'fecha_suspension' => '2026-11-01',
            'actualizado' => $this->version($contrato),
            ...$datos,
        ]);
    }

    private function reiniciar(Contrato $contrato, array $datos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson("/GestPro/contratos/{$contrato->id}/reiniciar", [
            'fecha_reinicio' => '2026-11-15',
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

    /** Las suspensiones del contrato, en orden, con sus fechas como año-mes-día. */
    private function suspensiones(Contrato $contrato): array
    {
        $dia = fn ($v) => $v === null ? null : substr((string) $v, 0, 10);

        return DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id)->where('tipo', 'suspension')
            ->orderBy('id')->get()
            ->map(fn ($f) => [
                'fecha_modificacion' => $dia($f->fecha_modificacion),
                'fecha_reinicio' => $dia($f->fecha_reinicio),
                'fecha_reinicio_prevista' => $dia($f->fecha_reinicio_prevista),
                'dias_prorroga' => $f->dias_prorroga === null ? null : (int) $f->dias_prorroga,
                'valor_adicion' => $f->valor_adicion,
                'numero_otrosi' => $f->numero_otrosi,
                'justificacion' => $f->justificacion,
            ])->all();
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

    private function idSuspension(Contrato $contrato, int $orden = 0): int
    {
        return (int) DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id)->where('tipo', 'suspension')
            ->orderBy('id')->skip($orden)->value('id');
    }

    // ------------------------------------------------------------------ permisos

    public function test_sin_token_responde_401_y_consulta_403(): void
    {
        $c = $this->contrato();
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);

        $this->postJson("/GestPro/contratos/{$c->id}/suspender", ['fecha_suspension' => '2026-11-01', 'actualizado' => null])->assertUnauthorized();
        $this->postJson("/GestPro/contratos/{$s->id}/reiniciar", ['fecha_reinicio' => '2026-11-15', 'actualizado' => null])->assertUnauthorized();
        $this->suspender($c, [], Rol::Consulta)->assertForbidden();
        $this->reiniciar($s, ['fecha_suspension' => '2026-11-01'], Rol::Consulta)->assertForbidden();

        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame(Contrato::SUSPENDIDO, $s->fresh()->estado);
        $this->assertSame(0, DB::table('modificaciones_contrato')->count());
        $this->assertSame([], $this->eventos($c));
    }

    public function test_un_contrato_que_no_existe_responde_404(): void
    {
        $this->como()->postJson('/GestPro/contratos/9999/suspender', ['fecha_suspension' => '2026-11-01', 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
        $this->como()->postJson('/GestPro/contratos/9999/reiniciar', ['fecha_reinicio' => '2026-11-15', 'actualizado' => null])
            ->assertNotFound()->assertExactJson(['mensaje' => 'El contrato no existe.']);
    }

    // ------------------------------------------------------------------ suspender

    public function test_suspende_un_contrato_en_ejecucion(): void
    {
        $c = $this->contrato();
        $version = $this->version($c);
        $this->travel(5)->seconds();

        $this->suspender($c, [
            'fecha_suspension' => '2026-11-05',
            'fecha_reinicio_prevista' => '2026-12-01',
            'numero_acta' => '  Acta 3  ',
            'motivo' => 'Ola invernal',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('avisos', [])
            ->assertJsonPath('contrato.estado', Contrato::SUSPENDIDO)
            ->assertJsonPath('contrato.fecha_fin', '2026-12-31')
            ->assertJsonPath('contrato.aviso_suspension', [
                'desde' => '2026-11-05', 'dias' => 14, 'reinicio_previsto' => '2026-12-01', 'vencido' => false,
            ]);

        $this->assertSame([[
            'fecha_modificacion' => '2026-11-05',
            'fecha_reinicio' => null,
            'fecha_reinicio_prevista' => '2026-12-01',
            'dias_prorroga' => null,
            'valor_adicion' => null,
            'numero_otrosi' => 'Acta 3',
            'justificacion' => 'Ola invernal',
        ]], $this->suspensiones($c));
        $fresco = $c->fresh();
        $this->assertNotSame($version, $this->version($fresco));
        $this->assertSame('2026-12-31', $fresco->fecha_fin->format('Y-m-d'));
        $this->assertSame('1000000.00', $fresco->valor);
    }

    public static function estadosQueNoSeSuspenden(): array
    {
        return [
            'celebrado' => [Contrato::CELEBRADO],
            'suspendido' => [Contrato::SUSPENDIDO],
            'terminado' => [Contrato::TERMINADO],
            'liquidado' => [Contrato::LIQUIDADO],
            'anulado' => [Contrato::ANULADO],
        ];
    }

    #[DataProvider('estadosQueNoSeSuspenden')]
    public function test_solo_se_suspende_un_contrato_en_ejecucion(string $estado): void
    {
        $c = $this->contrato(['estado' => $estado]);

        $this->suspender($c)->assertUnprocessable()->assertJsonPath('mensaje', 'Solo se suspende un contrato en ejecución.');
        $this->assertSame($estado, $c->fresh()->estado);
        $this->assertSame([], $this->suspensiones($c));
        $this->assertSame([], $this->eventos($c));
    }

    public function test_no_se_suspende_con_una_suspension_abierta(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato ya tiene una suspensión abierta.');

        // Una abierta en un contrato en ejecución (datos inconsistentes) también lo impide.
        DB::table('contratos')->where('id', $c->id)->update(['estado' => Contrato::EN_EJECUCION]);
        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato ya tiene una suspensión abierta.');
        $this->assertCount(1, $this->suspensiones($c));
    }

    public function test_la_fecha_de_suspension_es_obligatoria_y_con_formato(): void
    {
        $c = $this->contrato();

        $this->suspender($c, ['fecha_suspension' => null])->assertUnprocessable()->assertJsonValidationErrors('fecha_suspension');
        $this->suspender($c, ['fecha_suspension' => '01/11/2026'])->assertUnprocessable()->assertJsonValidationErrors('fecha_suspension');
        $this->suspender($c, ['fecha_reinicio_prevista' => 'mañana'])->assertUnprocessable()->assertJsonValidationErrors('fecha_reinicio_prevista');
        $this->suspender($c, ['numero_acta' => str_repeat('a', 51)])->assertUnprocessable()->assertJsonValidationErrors('numero_acta');
        $this->suspender($c, ['motivo' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->como()->postJson("/GestPro/contratos/{$c->id}/suspender", ['fecha_suspension' => '2026-11-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('actualizado');
        $this->assertSame([], $this->suspensiones($c));
    }

    public function test_la_fecha_de_suspension_no_es_posterior_a_hoy_en_bogota(): void
    {
        // 03:00 del 20 en UTC son las 22:00 del 19 en Bogotá: hoy es el 19.
        $this->travelTo('2026-11-20 03:00:00');
        $c = $this->contrato();

        $this->suspender($c, ['fecha_suspension' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser posterior a hoy.');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        $this->suspender($c, ['fecha_suspension' => '2026-11-19'])->assertOk()
            ->assertJsonPath('contrato.aviso_suspension.dias', 0);
    }

    public function test_la_fecha_de_suspension_no_es_anterior_a_la_de_inicio(): void
    {
        $c = $this->contrato(['fecha_inicio' => '2026-02-01']);

        $this->suspender($c, ['fecha_suspension' => '2026-01-31'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser anterior a la de inicio.');
        $this->suspender($c, ['fecha_suspension' => '2026-02-01'])->assertOk();

        // Sin fecha de inicio, no hay tope.
        $sinInicio = $this->contrato(['n_contrato' => 'B', 'fecha_inicio' => null]);
        $this->suspender($sinInicio, ['fecha_suspension' => '2025-06-01'])->assertOk();
    }

    public function test_la_fecha_de_suspension_no_es_anterior_al_ultimo_reinicio(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-20'])->assertOk();

        $this->suspender($c, ['fecha_suspension' => '2026-10-19'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser anterior al último reinicio.');
        // El mismo día del reinicio sí.
        $this->suspender($c, ['fecha_suspension' => '2026-10-20'])->assertOk();
    }

    public function test_la_fecha_prevista_no_es_anterior_a_la_de_suspension(): void
    {
        $c = $this->contrato();

        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio_prevista' => '2026-11-04'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio_prevista.0', 'La fecha prevista de reinicio no puede ser anterior a la de suspensión.');
        // Puede ser pasada (queda vencida) o el mismo día.
        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio_prevista' => '2026-11-05'])->assertOk()
            ->assertJsonPath('contrato.aviso_suspension.vencido', true);
    }

    public function test_suspender_con_una_version_vieja_responde_409(): void
    {
        $c = $this->contrato();
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $c->fresh()->touch();

        $this->suspender($c, ['actualizado' => $vieja])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);
        $this->assertSame([], $this->suspensiones($c));
    }

    public function test_suspender_deja_historial_con_antes_y_despues(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'numero_acta' => 'Acta 3', 'motivo' => 'Lluvias'])->assertOk();

        $this->assertSame([[
            'contrato_suspendido',
            ['estado' => Contrato::EN_EJECUCION],
            [
                'estado' => Contrato::SUSPENDIDO,
                'fecha_suspension' => '2026-11-05',
                'fecha_reinicio_prevista' => null,
                'numero_acta' => 'Acta 3',
                'motivo' => 'Lluvias',
                'modificacion_id' => $this->idSuspension($c),
            ],
        ]], $this->eventos($c));
    }

    // ------------------------------------------------------------------ reiniciar

    public function test_reinicia_y_corre_la_fecha_fin_como_una_prorroga(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();
        $version = $this->version($c);
        $this->travel(5)->seconds();

        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15', 'observacion' => 'Cesaron las lluvias'])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('avisos', [])
            ->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION)
            ->assertJsonPath('contrato.fecha_fin', '2027-01-14')
            ->assertJsonPath('contrato.aviso_suspension', null)
            ->assertJsonPath('resumen.fecha_fin_inicial', '2026-12-31')
            ->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-14');

        $this->assertSame([[
            'fecha_modificacion' => '2026-11-01',
            'fecha_reinicio' => '2026-11-15',
            'fecha_reinicio_prevista' => null,
            'dias_prorroga' => 14,
            'valor_adicion' => null,
            'numero_otrosi' => null,
            'justificacion' => null,
        ]], $this->suspensiones($c));

        // Igual que una prórroga de los mismos días en un contrato igual.
        $otro = $this->contrato(['n_contrato' => 'Gemelo']);
        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $otro->id, 'tipo' => 'prorroga', 'dias_prorroga' => 14, 'fecha_modificacion' => '2026-11-15',
        ])->assertOk();
        $this->assertSame($otro->fresh()->fecha_fin->format('Y-m-d'), $c->fresh()->fecha_fin->format('Y-m-d'));

        // Solo cambian el estado y la fecha fin vigente.
        $fresco = $c->fresh();
        $this->assertNotSame($version, $this->version($fresco));
        $this->assertSame('1000000.00', $fresco->valor);
        $this->assertSame('1000000.00', $fresco->valor_inicial);
        $this->assertSame('2026-12-31', $fresco->fecha_fin_inicial->format('Y-m-d'));
        $this->assertSame(80, (int) $fresco->avance_fisico);
        $this->assertSame(60, (int) $fresco->avance_financiero);

        [$evento, $antes, $despues] = $this->eventos($c)[1];
        $this->assertSame('contrato_reiniciado', $evento);
        $this->assertSame(['estado' => Contrato::SUSPENDIDO, 'fecha_fin' => '2026-12-31'], $antes);
        $this->assertSame([
            'estado' => Contrato::EN_EJECUCION,
            'fecha_fin' => '2027-01-14',
            'fecha_suspension' => '2026-11-01',
            'fecha_reinicio' => '2026-11-15',
            'dias_suspendido' => 14,
            'observacion' => 'Cesaron las lluvias',
            'modificacion_id' => $this->idSuspension($c),
        ], $despues);
    }

    public function test_reiniciar_el_mismo_dia_suma_cero_dias(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertOk();

        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-10'])->assertOk()
            ->assertJsonPath('contrato.fecha_fin', '2026-12-31');
        $this->assertSame(0, $this->suspensiones($c)[0]['dias_prorroga']);
    }

    public function test_reiniciar_sin_suspension_abierta_pide_la_fecha_y_la_crea_cerrada(): void
    {
        // Un contrato que llegó suspendido (de SECOP II o de antes de la fase 7B).
        $c = $this->contrato(['estado' => Contrato::SUSPENDIDO]);

        $this->reiniciar($c)->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'Escribe la fecha de suspensión.');
        $this->assertSame([], $this->suspensiones($c));

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-11', 'fecha_reinicio' => '2026-11-10'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio.0', 'La fecha de reinicio no puede ser anterior a la de suspensión.');
        $this->reiniciar($c, ['fecha_suspension' => '2026-01-31'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_suspension.0', 'La fecha de suspensión no puede ser anterior a la de inicio.');

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio' => '2026-11-15'])->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION)
            ->assertJsonPath('contrato.fecha_fin', '2027-01-10');
        $this->assertSame([[
            'fecha_modificacion' => '2026-11-05',
            'fecha_reinicio' => '2026-11-15',
            'fecha_reinicio_prevista' => null,
            'dias_prorroga' => 10,
            'valor_adicion' => null,
            'numero_otrosi' => null,
            'justificacion' => null,
        ]], $this->suspensiones($c));
        $this->assertSame('contrato_reiniciado', $this->eventos($c)[0][0]);
    }

    public function test_con_suspension_abierta_la_fecha_de_suspension_del_pedido_no_cuenta(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-10', 'fecha_reinicio' => '2026-11-15'])->assertOk();
        $this->assertSame(['2026-11-01', 14], [$this->suspensiones($c)[0]['fecha_modificacion'], $this->suspensiones($c)[0]['dias_prorroga']]);
    }

    public static function estadosQueNoSeReinician(): array
    {
        return [
            'celebrado' => [Contrato::CELEBRADO],
            'en ejecución' => [Contrato::EN_EJECUCION],
            'terminado' => [Contrato::TERMINADO],
            'liquidado' => [Contrato::LIQUIDADO],
            'anulado' => [Contrato::ANULADO],
        ];
    }

    #[DataProvider('estadosQueNoSeReinician')]
    public function test_solo_se_reinicia_un_contrato_suspendido(string $estado): void
    {
        $c = $this->contrato(['estado' => $estado]);

        $this->reiniciar($c, ['fecha_suspension' => '2026-11-01'])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'El contrato no está suspendido.');
        $this->assertSame($estado, $c->fresh()->estado);
        $this->assertSame([], $this->suspensiones($c));
    }

    public function test_las_fechas_de_reiniciar(): void
    {
        $this->travelTo('2026-11-20 03:00:00');
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-10'])->assertOk();

        $this->reiniciar($c, ['fecha_reinicio' => null])->assertUnprocessable()->assertJsonValidationErrors('fecha_reinicio');
        $this->reiniciar($c, ['fecha_reinicio' => '15/11/2026'])->assertUnprocessable()->assertJsonValidationErrors('fecha_reinicio');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15', 'observacion' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('observacion');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-20'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio.0', 'La fecha de reinicio no puede ser posterior a hoy.');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-09'])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_reinicio.0', 'La fecha de reinicio no puede ser anterior a la de suspensión.');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
        $this->assertNull($this->suspensiones($c)[0]['fecha_reinicio']);

        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-19'])->assertOk();
    }

    public function test_reiniciar_con_una_version_vieja_responde_409(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $c->fresh()->touch();

        $this->reiniciar($c, ['actualizado' => $vieja])->assertStatus(409)
            ->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
    }

    public function test_dos_suspensiones_suman_sus_dias(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-11'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-10');
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-10');
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-06'])->assertOk()
            ->assertJsonPath('contrato.fecha_fin', '2027-01-15')
            ->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-15');

        // Con una prórroga, también suma.
        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 5, 'fecha_modificacion' => '2026-11-10',
        ])->assertOk()->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-20');
    }

    // ------------------------------------------------------------------ pestaña Modificaciones

    public function test_guardar_modificacion_no_registra_suspensiones_y_sigue_aceptando_prorrogas(): void
    {
        $c = $this->contrato();

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'suspension', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-11-01',
        ])->assertUnprocessable()->assertJsonPath('errors.tipo.0', 'Las suspensiones se registran con «Suspender».');
        $this->assertSame(0, DB::table('modificaciones_contrato')->count());

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-11-01',
        ])->assertOk()->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-10');
    }

    public function test_eliminar_la_suspension_abierta_devuelve_el_contrato_a_ejecucion(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-05', 'numero_acta' => 'Acta 3'])->assertOk();
        $id = $this->idSuspension($c);
        $version = $this->version($c);
        $this->travel(5)->seconds();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $id, 'actualizado' => $version])->assertOk()
            ->assertJsonPath('resumen.fecha_fin_vigente', '2026-12-31');

        $fresco = $c->fresh();
        $this->assertSame(Contrato::EN_EJECUCION, $fresco->estado);
        $this->assertSame('2026-12-31', $fresco->fecha_fin->format('Y-m-d'));
        $this->assertNotSame($version, $this->version($fresco));
        $this->assertSame([], $this->suspensiones($c));

        [$evento, $antes, $despues] = $this->eventos($c)[1];
        $this->assertSame('suspension_eliminada', $evento);
        $this->assertSame($id, $antes['modificacion_id']);
        $this->assertSame('2026-11-05', $antes['fecha_suspension']);
        $this->assertNull($antes['fecha_reinicio']);
        $this->assertSame('Acta 3', $antes['numero_acta']);
        $this->assertSame(Contrato::SUSPENDIDO, $antes['estado']);
        $this->assertSame(['estado' => Contrato::EN_EJECUCION, 'fecha_fin' => '2026-12-31'], $despues);
    }

    public function test_eliminar_la_suspension_cerrada_devuelve_la_fecha_fin(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-14');

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c), 'actualizado' => $this->version($c)])->assertOk()
            ->assertJsonPath('resumen.fecha_fin_vigente', '2026-12-31');

        $fresco = $c->fresh();
        $this->assertSame(Contrato::EN_EJECUCION, $fresco->estado);
        $this->assertSame('2026-12-31', $fresco->fecha_fin->format('Y-m-d'));
        [$evento, $antes, $despues] = $this->eventos($c)[2];
        $this->assertSame('suspension_eliminada', $evento);
        $this->assertSame(14, $antes['dias_suspendido']);
        $this->assertSame('2027-01-14', $antes['fecha_fin']);
        $this->assertSame(['estado' => Contrato::EN_EJECUCION, 'fecha_fin' => '2026-12-31'], $despues);
    }

    public function test_solo_se_elimina_la_ultima_suspension(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-11'])->assertOk();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c, 0), 'actualizado' => $this->version($c)])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Solo se puede eliminar la última suspensión.');
        $this->assertCount(2, $this->suspensiones($c));
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);

        // Eliminar una prórroga sigue como antes, aunque haya suspensiones.
        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 5, 'fecha_modificacion' => '2026-11-10',
        ])->assertOk();
        $prorroga = DB::table('modificaciones_contrato')->where('tipo', 'prorroga')->value('id');
        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $prorroga])->assertOk()
            ->assertJsonPath('resumen.fecha_fin_vigente', '2027-01-10');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
    }

    public function test_eliminar_una_suspension_sin_version_responde_422(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c)])->assertUnprocessable()
            ->assertJsonPath('errors.actualizado.0', 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.');
        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c), 'actualizado' => 'ayer'])->assertUnprocessable()
            ->assertJsonPath('errors.actualizado.0', 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.');
        $this->assertCount(1, $this->suspensiones($c));
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
    }

    public function test_eliminar_una_suspension_con_una_version_vieja_responde_409_y_no_deshace_el_reinicio(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();
        // La pestaña Modificaciones se abrió con esta versión; otra persona reinicia entretanto.
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15'])->assertOk()->assertJsonPath('contrato.fecha_fin', '2027-01-14');

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c), 'actualizado' => $vieja])
            ->assertStatus(409)->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);

        $fresco = $c->fresh();
        $this->assertSame(Contrato::EN_EJECUCION, $fresco->estado);
        $this->assertSame('2027-01-14', $fresco->fecha_fin->format('Y-m-d'));
        $this->assertSame([['2026-11-01', '2026-11-15', 14]], array_map(
            fn ($f) => [$f['fecha_modificacion'], $f['fecha_reinicio'], $f['dias_prorroga']],
            $this->suspensiones($c)
        ));
        $this->assertSame(['contrato_suspendido', 'contrato_reiniciado'], array_column($this->eventos($c), 0));
    }

    public function test_la_version_se_exige_antes_que_la_regla_de_la_ultima(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-10-01'])->assertOk();
        $vieja = $this->version($c);
        $this->travel(5)->seconds();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-10-11'])->assertOk();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01'])->assertOk();

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $this->idSuspension($c, 0), 'actualizado' => $vieja])
            ->assertStatus(409)->assertExactJson(['mensaje' => ContratosService::DESACTUALIZADO]);
        $this->assertCount(2, $this->suspensiones($c));
    }

    public function test_eliminar_una_suspension_que_ya_no_existe_responde_404(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();
        $id = $this->idSuspension($c);
        // Otra persona la eliminó entre la lectura del controlador y el bloqueo del contrato.
        DB::table('modificaciones_contrato')->where('id', $id)->delete();

        try {
            app(SuspensionContratos::class)->eliminar($id, $c->id, $this->version($c), null);
            $this->fail('Debió responder 404.');
        } catch (HttpResponseException $e) {
            $this->assertSame(404, $e->getResponse()->getStatusCode());
            $this->assertSame(['mensaje' => 'La suspensión ya no existe.'], $e->getResponse()->getData(true));
        }
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
        $this->assertSame(['contrato_suspendido'], array_column($this->eventos($c), 0));
    }

    /**
     * Registrar o eliminar una adición o prórroga bloquea primero la fila del contrato, para no pisar
     * la fecha fin de un reinicio que corre a la vez. SQLite no escribe FOR UPDATE: se comprueba que
     * el contrato se lee (con el bloqueo, en MySQL) antes de escribir en modificaciones_contrato.
     */
    public function test_guardar_y_eliminar_una_prorroga_bloquean_el_contrato_antes_de_escribir(): void
    {
        $c = $this->contrato();
        $consultas = [];
        DB::listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });
        $primera = function (array $consultas, string $patron): int {
            foreach ($consultas as $i => $sql) {
                if (preg_match($patron, $sql)) {
                    return $i;
                }
            }

            return PHP_INT_MAX;
        };

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-11-01',
        ])->assertOk()->assertExactJson([
            'success' => 'Modificación registrada correctamente',
            'resumen' => [
                'valor_inicial' => 1000000, 'valor_vigente' => 1000000, 'fecha_fin_inicial' => '2026-12-31',
                'fecha_fin_vigente' => '2027-01-10', 'porcentaje_adicionado' => 0, 'supera_limite' => false,
            ],
        ]);
        $this->assertLessThan(
            $primera($consultas, '/^insert into "modificaciones_contrato"/'),
            $primera($consultas, '/^select .* from "contratos" where "contratos"\."id" = \?/')
        );

        $consultas = [];
        $prorroga = DB::table('modificaciones_contrato')->where('tipo', 'prorroga')->value('id');
        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => $prorroga])->assertOk()
            ->assertJsonPath('success', 'Modificación eliminada correctamente')
            ->assertJsonPath('resumen.fecha_fin_vigente', '2026-12-31');
        $this->assertLessThan(
            $primera($consultas, '/^delete from "modificaciones_contrato"/'),
            $primera($consultas, '/^select .* from "contratos" where "contratos"\."id" = \?/')
        );

        $this->como()->postJson('/GestPro/eliminarModificacionContrato', ['id' => 99999])->assertNotFound()
            ->assertExactJson(['error' => 'Modificación no encontrada']);
    }

    public function test_las_modificaciones_del_contrato_traen_las_fechas_de_reinicio(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-01', 'fecha_reinicio_prevista' => '2026-11-20'])->assertOk();
        $this->reiniciar($c, ['fecha_reinicio' => '2026-11-15'])->assertOk();

        $mod = $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()->json('modificaciones.0');
        $this->assertSame('suspension', $mod['tipo']);
        $this->assertSame('2026-11-15', substr($mod['fecha_reinicio'], 0, 10));
        $this->assertSame('2026-11-20', substr($mod['fecha_reinicio_prevista'], 0, 10));
        $this->assertSame(14, (int) $mod['dias_prorroga']);

        $listado = $this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json('0.modificaciones.0');
        $this->assertSame('2026-11-15', substr($listado['fecha_reinicio'], 0, 10));
    }

    // ------------------------------------------------------------------ editar, terminar y liquidar

    public function test_editar_no_pasa_a_suspendido(): void
    {
        $c = $this->contrato();

        $this->editar($c, ['estado' => Contrato::SUSPENDIDO])->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Para suspender el contrato usa «Suspender».');
        $this->assertSame(Contrato::EN_EJECUCION, $c->fresh()->estado);

        // Uno ya suspendido (sin abierta) puede seguir así al editar otros campos.
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);
        $this->editar($s, ['objeto' => 'Otro objeto'])->assertOk();
    }

    public function test_editar_no_sale_de_suspendido_con_una_suspension_abierta(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->editar($c, ['estado' => Contrato::EN_EJECUCION])->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Para reanudar el contrato usa «Reiniciar».');
        $this->editar($c, ['estado' => Contrato::ANULADO])->assertUnprocessable()
            ->assertJsonPath('errors.estado.0', 'Para reanudar el contrato usa «Reiniciar».');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);
        // Seguir suspendido y cambiar otro campo sí.
        $this->editar($c, ['objeto' => 'Otro objeto'])->assertOk();

        // Sin suspensión abierta, se sale de suspendido.
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);
        $this->editar($s, ['estado' => Contrato::EN_EJECUCION])->assertOk()->assertJsonPath('contrato.estado', Contrato::EN_EJECUCION);
    }

    public function test_crear_un_contrato_suspendido_se_permite(): void
    {
        $this->como()->postJson('/GestPro/guardarContrato', ['formContrato' => [
            'n_contrato' => 'N-1', 'objeto' => 'Obra', 'valor_inicial' => 10, 'estado' => Contrato::SUSPENDIDO,
            'proyecto' => $this->proyecto->id,
        ]])->assertOk()
            ->assertJsonPath('contrato.estado', Contrato::SUSPENDIDO)
            ->assertJsonPath('contrato.aviso_suspension', null);
    }

    public function test_terminar_o_liquidar_con_una_suspension_abierta_se_rechaza(): void
    {
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        $this->como()->postJson("/GestPro/contratos/{$c->id}/terminar", [
            'fecha_terminacion_real' => '2026-11-15', 'actualizado' => $this->version($c),
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'Reinicia el contrato antes de terminarlo o liquidarlo.');
        $this->como()->postJson("/GestPro/contratos/{$c->id}/liquidar", [
            'fecha_terminacion_real' => '2026-11-15', 'fecha_liquidacion' => '2026-11-18', 'valor_liquidado' => 1, 'actualizado' => $this->version($c),
        ])->assertUnprocessable()->assertJsonPath('mensaje', 'Reinicia el contrato antes de terminarlo o liquidarlo.');
        $this->assertSame(Contrato::SUSPENDIDO, $c->fresh()->estado);

        // Un suspendido sin suspensión abierta se termina como en la fase 7A.
        $s = $this->contrato(['n_contrato' => 'S', 'estado' => Contrato::SUSPENDIDO]);
        $this->como()->postJson("/GestPro/contratos/{$s->id}/terminar", [
            'fecha_terminacion_real' => '2026-11-15', 'actualizado' => $this->version($s),
        ])->assertOk()->assertJsonPath('contrato.estado', Contrato::TERMINADO);
    }

    // ------------------------------------------------------------------ aviso de suspensión

    public function test_el_aviso_de_suspension_en_la_busqueda_el_detalle_y_los_listados(): void
    {
        $vencido = $this->contrato(['n_contrato' => 'A']);
        $this->suspender($vencido, ['fecha_suspension' => '2026-11-01', 'fecha_reinicio_prevista' => '2026-11-18'])->assertOk();
        $alDia = $this->contrato(['n_contrato' => 'B']);
        $this->suspender($alDia, ['fecha_suspension' => '2026-11-05', 'fecha_reinicio_prevista' => '2026-11-19'])->assertOk();
        $sinPrevista = $this->contrato(['n_contrato' => 'C']);
        $this->suspender($sinPrevista, ['fecha_suspension' => '2026-11-19'])->assertOk();
        $suspendidoSinFila = $this->contrato(['n_contrato' => 'D', 'estado' => Contrato::SUSPENDIDO]);
        $enEjecucion = $this->contrato(['n_contrato' => 'E']);
        $reiniciado = $this->contrato(['n_contrato' => 'F']);
        $this->suspender($reiniciado, ['fecha_suspension' => '2026-11-01'])->assertOk();
        $this->reiniciar($reiniciado, ['fecha_reinicio' => '2026-11-02'])->assertOk();

        $esperado = [
            $vencido->id => ['desde' => '2026-11-01', 'dias' => 18, 'reinicio_previsto' => '2026-11-18', 'vencido' => true],
            $alDia->id => ['desde' => '2026-11-05', 'dias' => 14, 'reinicio_previsto' => '2026-11-19', 'vencido' => false],
            $sinPrevista->id => ['desde' => '2026-11-19', 'dias' => 0, 'reinicio_previsto' => null, 'vencido' => false],
            $suspendidoSinFila->id => null,
            $enEjecucion->id => null,
            $reiniciado->id => null,
        ];

        DB::enableQueryLog();
        $busqueda = collect($this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk()->json())->keyBy('id');
        $consultasBusqueda = count(DB::getQueryLog());
        DB::flushQueryLog();
        $listado = collect($this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json())->keyBy('id');
        DB::flushQueryLog();
        $proyectos = collect(collect($this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json())
            ->firstWhere('id', $this->proyecto->id)['contratos'])->keyBy('id');
        $consultasProyectos = count(DB::getQueryLog());

        foreach ($esperado as $id => $aviso) {
            $this->assertSame($aviso, $busqueda[$id]['aviso_suspension'], "búsqueda #{$id}");
            $this->assertSame($aviso, $listado[$id]['aviso_suspension'], "listado #{$id}");
            $this->assertSame($aviso, $proyectos[$id]['aviso_suspension'], "proyectos #{$id}");
            $this->assertSame($aviso, $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$id}/detalle")->assertOk()->json('aviso_suspension'), "detalle #{$id}");
        }
        // Y en la ficha del proyecto (sus contratos son los de la búsqueda).
        $ficha = collect($this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/ficha")->assertOk()->json('contratos'))->keyBy('id');
        $this->assertSame($esperado[$vencido->id], $ficha[$vencido->id]['aviso_suspension']);

        // Sin consultas por fila: con más contratos suspendidos, las mismas consultas.
        $mas = $this->contrato(['n_contrato' => 'G']);
        $this->suspender($mas, ['fecha_suspension' => '2026-11-10', 'fecha_reinicio_prevista' => '2026-11-12'])->assertOk();
        $this->contrato(['n_contrato' => 'H', 'estado' => Contrato::SUSPENDIDO]);
        DB::flushQueryLog();
        $this->como(Rol::Consulta)->getJson('/GestPro/contratos/buscar')->assertOk();
        $this->assertSame($consultasBusqueda, count(DB::getQueryLog()));
        DB::flushQueryLog();
        $this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk();
        // GET /proyectos consulta los anexos, actas y actividades de cada contrato (ya era así):
        // dos contratos más son seis consultas más, ninguna de suspensiones.
        $this->assertSame($consultasProyectos + 2 * 3, count(DB::getQueryLog()));
    }

    public function test_el_aviso_se_vence_con_los_dias(): void
    {
        $c = $this->contrato();
        $this->suspender($c, ['fecha_suspension' => '2026-11-10', 'fecha_reinicio_prevista' => '2026-11-19'])->assertOk()
            ->assertJsonPath('contrato.aviso_suspension.vencido', false);

        $this->travelTo('2026-11-21 03:00:00'); // el 20 en Bogotá
        $this->como(Rol::Consulta)->getJson("/GestPro/contratos/{$c->id}/detalle")->assertOk()
            ->assertJsonPath('aviso_suspension', ['desde' => '2026-11-10', 'dias' => 10, 'reinicio_previsto' => '2026-11-19', 'vencido' => true]);
    }

    // ------------------------------------------------------------------ migración

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100024_add_suspension_a_modificaciones_contrato.php');
    }

    public function test_la_migracion_agrega_las_columnas_nulas_y_su_rollback_se_detiene_con_suspensiones(): void
    {
        $columnas = collect(Schema::getColumns('modificaciones_contrato'))->keyBy('name');
        foreach (['fecha_reinicio', 'fecha_reinicio_prevista'] as $columna) {
            $this->assertTrue($columnas->has($columna), $columna);
            $this->assertTrue($columnas[$columna]['nullable'], $columna);
        }

        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $c = $this->contrato();
        $this->suspender($c)->assertOk();

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertSame('No se puede revertir: hay suspensiones de contratos registradas, que se perderían. Restaure un respaldo.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('modificaciones_contrato', 'fecha_reinicio'));

        // Con solo adiciones y prórrogas, sí se revierte (y dos veces no falla).
        DB::table('modificaciones_contrato')->delete();
        DB::table('modificaciones_contrato')->insert(['contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 5, 'fecha_modificacion' => '2026-11-01']);
        $migracion->down();
        $migracion->down();
        foreach (['fecha_reinicio', 'fecha_reinicio_prevista'] as $columna) {
            $this->assertFalse(Schema::hasColumn('modificaciones_contrato', $columna), $columna);
        }
        $this->assertSame(5, (int) DB::table('modificaciones_contrato')->value('dias_prorroga'));
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('modificaciones_contrato', 'fecha_reinicio_prevista'));
    }
}
