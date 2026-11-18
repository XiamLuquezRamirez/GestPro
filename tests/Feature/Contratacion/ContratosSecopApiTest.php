<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Http\Controllers\ContratoController;
use App\Models\Contrato;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Contratos: guardar con la referencia de SECOP II. */
class ContratosSecopApiTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDAR = '/GestPro/guardarContrato';

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function guardar(array $formContrato)
    {
        $usuario = User::factory()->create(['rol' => Rol::Gestor]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario))
            ->postJson(self::GUARDAR, ['formContrato' => $formContrato, 'anexos' => []]);
    }

    private function datos(array $mas = []): array
    {
        return [
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => 1000000,
            'estado' => 'en_ejecucion',
            'proyecto' => $this->proyecto->id,
            ...$mas,
        ];
    }

    private function crear(array $mas = []): Contrato
    {
        return Contrato::findOrFail($this->guardar($this->datos($mas))->assertOk()->json('contrato.id'));
    }

    private function editar(Contrato $contrato, array $mas = [])
    {
        return $this->guardar([
            ...$this->datos(),
            'id' => $contrato->id,
            'actualizado' => $contrato->fresh()->updated_at?->format('Y-m-d H:i:s'),
            ...$mas,
        ]);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')->where('entidad_id', $contrato->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode($h->antes, true), json_decode($h->despues, true)])
            ->all();
    }

    public function test_se_crea_con_la_referencia_de_secop_y_el_id_queda_en_mayusculas(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $enlace = 'https://community.secop.gov.co/Public/Tendering/ContractNoticeManagement/Index?id=1';

        $r = $this->guardar($this->datos([
            'secop_id_contrato' => ' co1.pccntr.8912345 ',
            'secop_proceso' => ' CO1.REQ.123 ',
            'url_secop' => $enlace,
            'secop_consultado' => true,
        ]))->assertOk();

        $r->assertJsonPath('contrato.secop_id_contrato', 'CO1.PCCNTR.8912345')
            ->assertJsonPath('contrato.secop_proceso', 'CO1.REQ.123')
            ->assertJsonPath('contrato.url_secop', $enlace)
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-18 10:00:00');
        $this->assertSame('CO1.PCCNTR.8912345', Contrato::findOrFail($r->json('contrato.id'))->secop_id_contrato);
    }

    public function test_sin_consultado_o_con_falso_no_hay_fecha_de_consulta(): void
    {
        $this->assertNull($this->crear(['secop_id_contrato' => 'CO1.PCCNTR.1'])->secop_consultado_at);

        $falso = $this->guardar($this->datos(['n_contrato' => 'C-2', 'secop_id_contrato' => 'CO1.PCCNTR.2', 'secop_consultado' => false]))->assertOk();
        $this->assertNull($falso->json('contrato.secop_consultado_at'));
    }

    public function test_el_pedido_no_puede_fijar_la_fecha_de_consulta(): void
    {
        $r = $this->guardar($this->datos(['secop_consultado_at' => '2020-01-01 00:00:00']))->assertOk();

        $this->assertNull($r->json('contrato.secop_consultado_at'));
    }

    public static function pedidosInvalidos(): array
    {
        $forma = 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.';
        $web = 'El enlace de SECOP II debe empezar por http:// o https://.';

        return [
            'id incompleto' => [['secop_id_contrato' => 'CO1.PCCNTR'], 'secop_id_contrato', $forma],
            'id de un proceso' => [['secop_id_contrato' => 'CO1.REQ.55'], 'secop_id_contrato', $forma],
            'id con letras al final' => [['secop_id_contrato' => 'CO1.PCCNTR.12a'], 'secop_id_contrato', $forma],
            // 41 caracteres: la columna es varchar(40).
            'id largo' => [['secop_id_contrato' => 'CO1.PCCNTR.' . str_repeat('1', 30)], 'secop_id_contrato', 'El ID del contrato de SECOP II admite hasta 40 caracteres.'],
            'proceso largo' => [['secop_proceso' => str_repeat('a', 41)], 'secop_proceso', 'El proceso de SECOP II admite hasta 40 caracteres.'],
            'enlace ftp' => [['url_secop' => 'ftp://secop.gov.co/x'], 'url_secop', $web],
            'enlace javascript' => [['url_secop' => 'javascript:alert(1)'], 'url_secop', $web],
            'enlace largo' => [['url_secop' => 'https://secop.gov.co/' . str_repeat('a', 480)], 'url_secop', 'El enlace de SECOP II admite hasta 500 caracteres.'],
            'consultado no booleano' => [['secop_consultado' => 'tal vez'], 'secop_consultado', 'Revisa la consulta a SECOP II: debe ser sí o no.'],
        ];
    }

    #[DataProvider('pedidosInvalidos')]
    public function test_un_pedido_invalido_se_rechaza_por_campo(array $cambios, string $campo, string $mensaje): void
    {
        $this->guardar($this->datos($cambios))->assertStatus(422)->assertJsonPath("errors.$campo.0", $mensaje);
        $this->assertSame(0, Contrato::count());
    }

    public function test_el_id_de_secop_es_unico_al_crear_sin_distinguir_mayusculas(): void
    {
        $this->crear(['n_contrato' => 'C-ORIGINAL', 'secop_id_contrato' => 'CO1.PCCNTR.77']);

        $this->guardar($this->datos(['n_contrato' => 'C-2', 'secop_id_contrato' => 'co1.pccntr.77']))
            ->assertStatus(422)
            ->assertJsonPath('errors.secop_id_contrato.0', 'Ese contrato de SECOP II ya está registrado: contrato N° C-ORIGINAL.');
        $this->assertSame(1, Contrato::count());
    }

    public function test_al_editar_el_id_es_unico_pero_el_propio_no_choca(): void
    {
        $uno = $this->crear(['n_contrato' => 'C-1', 'secop_id_contrato' => 'CO1.PCCNTR.1']);
        $dos = $this->crear(['n_contrato' => 'C-2', 'secop_id_contrato' => 'CO1.PCCNTR.2']);

        $this->editar($dos, ['n_contrato' => 'C-2', 'secop_id_contrato' => 'CO1.PCCNTR.1'])
            ->assertStatus(422)
            ->assertJsonPath('errors.secop_id_contrato.0', 'Ese contrato de SECOP II ya está registrado: contrato N° C-1.');
        $this->assertSame('CO1.PCCNTR.2', $dos->fresh()->secop_id_contrato);

        // Reenviar el suyo (en minúsculas) no es un choque.
        $this->editar($uno, ['n_contrato' => 'C-1', 'secop_id_contrato' => 'co1.pccntr.1', 'objeto' => 'Otro objeto'])->assertOk();
        $this->assertSame('Otro objeto', $uno->fresh()->objeto);
    }

    public function test_si_dos_guardan_a_la_vez_el_indice_unico_da_el_mismo_422(): void
    {
        // El otro guardado gana la carrera: su fila entra justo después de que este comprobó que el
        // ID estaba libre y antes de su insert.
        $competidor = false;
        DB::listen(function ($consulta) use (&$competidor) {
            if (! $competidor && str_starts_with($consulta->sql, 'select') && str_contains($consulta->sql, '"secop_id_contrato" = ?')) {
                $competidor = true;
                DB::table('contratos')->insert([
                    'n_contrato' => 'C-GANADOR', 'objeto' => 'x', 'valor' => 1, 'valor_inicial' => 1,
                    'estado' => 'celebrado', 'secop_id_contrato' => 'CO1.PCCNTR.9',
                ]);
            }
        });

        $this->guardar($this->datos(['secop_id_contrato' => 'CO1.PCCNTR.9']))
            ->assertStatus(422)
            ->assertJsonPath('errors.secop_id_contrato.0', 'Ese contrato de SECOP II ya está registrado: contrato N° C-GANADOR.');
        $this->assertSame(1, Contrato::count());
    }

    public function test_un_id_de_40_caracteres_se_acepta(): void
    {
        $id = 'CO1.PCCNTR.' . str_repeat('1', 29);

        $this->assertSame($id, $this->crear(['secop_id_contrato' => $id])->secop_id_contrato);
    }

    public function test_el_choque_de_otro_indice_unico_no_se_confunde_con_el_de_secop(): void
    {
        // Otro índice único de la tabla: el insert que lo viola menciona la columna secop_id_contrato
        // en su SQL, pero el choque no es del ID de SECOP II y no debe responderse como tal.
        DB::statement('CREATE UNIQUE INDEX contratos_n_contrato_prueba_unique ON contratos (n_contrato)');
        $this->crear(['n_contrato' => 'C-REPETIDO']);

        $r = $this->guardar($this->datos(['n_contrato' => 'C-REPETIDO', 'secop_id_contrato' => 'CO1.PCCNTR.31']));

        $r->assertStatus(500);
        $this->assertNull($r->json('errors.secop_id_contrato'));
        $this->assertSame(1, Contrato::count());
    }

    public static function mensajesDelMotor(): array
    {
        return [
            'mariadb, el índice del ID' => ["Duplicate entry 'CO1.PCCNTR.9' for key 'contratos_secop_id_contrato_unique'", true],
            'mysql 8, con la tabla' => ["Duplicate entry 'CO1.PCCNTR.9' for key 'contratos.contratos_secop_id_contrato_unique'", true],
            'sqlite, la columna' => ['UNIQUE constraint failed: contratos.secop_id_contrato', true],
            'mariadb, otro índice' => ["Duplicate entry 'C-1' for key 'contratos_n_contrato_unique'", false],
            'sqlite, otra columna' => ['UNIQUE constraint failed: contratos.n_contrato', false],
            'sqlite, índice compuesto' => ['UNIQUE constraint failed: contratos.secop_id_contrato, contratos.n_contrato', false],
            'sin detalle' => [null, false],
        ];
    }

    #[DataProvider('mensajesDelMotor')]
    public function test_el_choque_se_reconoce_por_el_indice_en_el_mensaje_del_motor(?string $detalle, bool $esDelIdSecop): void
    {
        $pdo = new \PDOException('SQLSTATE[23000]: Integrity constraint violation');
        $pdo->errorInfo = $detalle === null ? null : ['23000', 1062, $detalle];
        // El SQL siempre menciona la columna (en el insert y en un JSON del historial).
        $e = new UniqueConstraintViolationException('mysql', 'insert into contratos (secop_id_contrato) values (?)', ['{"secop_id_contrato":1}'], $pdo);

        $metodo = new \ReflectionMethod(ContratoController::class, 'esChoqueDelIdSecop');

        $this->assertSame($esDelIdSecop, $metodo->invoke(null, $e));
    }

    public function test_varios_contratos_pueden_quedar_sin_id_de_secop(): void
    {
        $this->crear(['n_contrato' => 'C-1']);
        $this->crear(['n_contrato' => 'C-2', 'secop_id_contrato' => null]);
        $this->crear(['n_contrato' => 'C-3', 'secop_id_contrato' => '']);

        $this->assertSame(3, Contrato::whereNull('secop_id_contrato')->count());
    }

    public function test_al_editar_un_campo_secop_ausente_no_cambia_y_null_lo_borra(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $contrato = $this->crear([
            'secop_id_contrato' => 'CO1.PCCNTR.5', 'secop_proceso' => 'CO1.REQ.5',
            'url_secop' => 'https://secop.gov.co/5', 'secop_consultado' => true,
        ]);
        Carbon::setTestNow('2026-11-18 11:00:00');

        // Ausentes: no cambian (tampoco la fecha de consulta).
        $this->editar($contrato, ['objeto' => 'Otro objeto'])->assertOk()
            ->assertJsonPath('contrato.secop_id_contrato', 'CO1.PCCNTR.5')
            ->assertJsonPath('contrato.secop_proceso', 'CO1.REQ.5')
            ->assertJsonPath('contrato.url_secop', 'https://secop.gov.co/5')
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-18 10:00:00');

        // Null los borra.
        Carbon::setTestNow('2026-11-18 12:00:00');
        $this->editar($contrato, ['secop_id_contrato' => null, 'secop_proceso' => null, 'url_secop' => null])->assertOk()
            ->assertJsonPath('contrato.secop_id_contrato', null)
            ->assertJsonPath('contrato.secop_proceso', null)
            ->assertJsonPath('contrato.url_secop', null)
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-18 10:00:00');
    }

    public function test_consultar_de_nuevo_actualiza_la_fecha_de_consulta(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $contrato = $this->crear(['secop_id_contrato' => 'CO1.PCCNTR.5', 'secop_consultado' => true]);
        Carbon::setTestNow('2026-11-19 09:30:00');

        $this->editar($contrato, ['secop_consultado' => true])->assertOk()
            ->assertJsonPath('contrato.secop_consultado_at', '2026-11-19 09:30:00');
        $this->assertSame('2026-11-19 09:30:00', $contrato->fresh()->secop_consultado_at->format('Y-m-d H:i:s'));
    }

    public function test_los_campos_secop_entran_al_historial(): void
    {
        Carbon::setTestNow('2026-11-18 10:00:00');
        $contrato = $this->crear([
            'secop_id_contrato' => 'co1.pccntr.5', 'secop_proceso' => 'CO1.REQ.5',
            'url_secop' => 'https://secop.gov.co/5', 'secop_consultado' => true,
        ]);

        [$evento, $antes, $despues] = $this->eventos($contrato)[0];
        $this->assertSame('contrato_creado', $evento);
        $this->assertNull($antes);
        $this->assertSame('CO1.PCCNTR.5', $despues['secop_id_contrato']);
        $this->assertSame('CO1.REQ.5', $despues['secop_proceso']);
        $this->assertSame('https://secop.gov.co/5', $despues['url_secop']);
        $this->assertSame('2026-11-18 10:00:00', $despues['secop_consultado_at']);

        Carbon::setTestNow('2026-11-19 08:00:00');
        $this->editar($contrato, ['secop_id_contrato' => 'CO1.PCCNTR.6', 'url_secop' => 'https://secop.gov.co/6', 'secop_consultado' => true])->assertOk();

        [$evento, $antes, $despues] = $this->eventos($contrato)[1];
        $this->assertSame('contrato_editado', $evento);
        $this->assertSame(
            ['secop_id_contrato' => 'CO1.PCCNTR.5', 'url_secop' => 'https://secop.gov.co/5', 'secop_consultado_at' => '2026-11-18 10:00:00'],
            $antes
        );
        $this->assertSame(
            ['secop_id_contrato' => 'CO1.PCCNTR.6', 'url_secop' => 'https://secop.gov.co/6', 'secop_consultado_at' => '2026-11-19 08:00:00'],
            $despues
        );
    }

    public function test_reenviar_los_mismos_datos_secop_no_registra_cambios(): void
    {
        $contrato = $this->crear(['secop_id_contrato' => 'CO1.PCCNTR.5', 'secop_proceso' => 'CO1.REQ.5']);

        $this->editar($contrato, ['secop_id_contrato' => 'co1.pccntr.5', 'secop_proceso' => 'CO1.REQ.5'])->assertOk();

        $this->assertCount(1, $this->eventos($contrato));
    }
}
