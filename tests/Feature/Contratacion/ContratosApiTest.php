<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\ActividadContrato;
use App\Models\AnexoContrato;
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\Modalidad;
use App\Models\ModificacionContrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Contratos: guardar (crear y editar), eliminar, recalcular y lectura en los listados. */
class ContratosApiTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDAR = '/GestPro/guardarContrato';
    private const ELIMINAR = '/GestPro/eliminarContrato';

    private Proyecto $proyecto;
    private string $publico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        // Los anexos viven en public/anexos_contratos: en las pruebas, en una carpeta temporal.
        $this->publico = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gestpro-publico-' . uniqid();
        File::ensureDirectoryExists($this->publico . DIRECTORY_SEPARATOR . 'anexos_contratos');
        $this->app->usePublicPath($this->publico);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publico);
        parent::tearDown();
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
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

    private function guardar(array $formContrato, array $anexos = [], Rol|User $quien = Rol::Gestor)
    {
        return $this->como($quien)->postJson(self::GUARDAR, ['formContrato' => $formContrato, 'anexos' => $anexos]);
    }

    private function crear(array $mas = []): Contrato
    {
        return Contrato::findOrFail($this->guardar($this->datos($mas))->assertOk()->json('contrato.id'));
    }

    /** El `actualizado` que tendría ahora el formulario del contrato: su updated_at. */
    private function version(Contrato $contrato): ?string
    {
        return $contrato->fresh()->updated_at?->format('Y-m-d H:i:s');
    }

    private function editar(Contrato $contrato, array $mas = [], ?array $anexos = [])
    {
        $form = [...$this->datos(), 'id' => $contrato->id, 'actualizado' => $this->version($contrato), ...$mas];

        return $this->como()->postJson(self::GUARDAR, array_filter(['formContrato' => $form, 'anexos' => $anexos], fn ($v) => $v !== null));
    }

    private function proceso(string $estado = 'adjudicado', array $mas = []): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => $this->proyecto->id,
            'objeto' => 'Obra del acueducto',
            'modalidad' => Modalidad::where('clave', 'licitacion_publica')->firstOrFail()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-001-2026',
            'url_proceso' => 'https://secop.gov.co/p/1',
            'estado' => $estado,
            ...$mas,
        ]);
    }

    /** Un contrato antiguo: escrito con DB::table, sin marcas de tiempo. */
    private function antiguo(array $mas = []): Contrato
    {
        $id = DB::table('contratos')->insertGetId([
            'proyecto' => $this->proyecto->id,
            'n_contrato' => 'C-VIEJO',
            'objeto' => 'Contrato antiguo',
            'valor' => 100000000,
            'valor_inicial' => 100000000,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'fecha_fin_inicial' => '2026-12-31',
            'estado' => 'en_ejecucion',
            ...$mas,
        ]);

        return Contrato::findOrFail($id);
    }

    /** @return list<array{0: string, 1: ?array, 2: ?array}> */
    private function eventos(Contrato|int $contrato): array
    {
        return DB::table('checklist_historial')
            ->where('entidad_tipo', 'contrato')
            ->where('entidad_id', $contrato instanceof Contrato ? $contrato->id : $contrato)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    private function archivo(string $nombre): string
    {
        $ruta = 'anexos_contratos/' . $nombre;
        file_put_contents($this->publico . DIRECTORY_SEPARATOR . $ruta, 'pdf');

        return $ruta;
    }

    // ------------------------------------------------------------------ crear

    public function test_un_gestor_crea_un_contrato_con_todos_sus_campos(): void
    {
        $gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Ana Gestora']);
        $obra = TipoProceso::where('nombre', 'Obra')->firstOrFail();
        Carbon::setTestNow('2026-11-18 10:00:00');

        $r = $this->guardar($this->datos([
            'n_contrato' => '  C-001-2026 ',
            'contratante' => 'Gobernación del Cesar',
            'contratante_nit' => '892399999-1',
            'contratista' => 'Consorcio Agua',
            'contratista_identificacion' => '901234567-8',
            'tipo_contrato_id' => $obra->id,
            'valor_inicial' => '1500000.5',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => '2026-12-31',
            'fecha_suscripcion' => '2026-01-20',
            'numero_rp' => 'RP-77',
            'fecha_rp' => '2026-01-25',
            'fecha_acta_inicio' => '2026-02-01',
            'supervisor' => 'Ing. Pérez',
            'interventoria' => 'Interventoría Andina',
            'estado' => 'celebrado',
            'anticipo' => true,
            'porcentaje_anticipo' => 30,
            'avance_fisico' => 10,
            'avance_financiero' => 5,
            // Lo que la pantalla manda de más y no se escribe desde aquí.
            'valor' => 999,
            'fecha_fin' => '2030-01-01',
            'secop_consultado_at' => '2020-01-01 00:00:00',
            'actividades' => [],
        ]), [], $gestor)->assertOk();

        $id = $r->json('contrato.id');
        $r->assertExactJson([
            'success' => true,
            'contrato' => [
                'id' => $id,
                'proyecto' => $this->proyecto->id,
                'n_contrato' => 'C-001-2026',
                'objeto' => 'Construcción del acueducto veredal',
                'contratante' => 'Gobernación del Cesar',
                'contratante_nit' => '892399999-1',
                'contratista' => 'Consorcio Agua',
                'contratista_identificacion' => '901234567-8',
                'tipo_contrato_id' => $obra->id,
                'tipo_contrato' => ['id' => $obra->id, 'nombre' => 'Obra', 'activo' => true],
                'valor_inicial' => '1500000.50',
                'valor' => '1500000.50',
                'fecha_inicio' => '2026-02-01',
                'fecha_fin_inicial' => '2026-12-31',
                'fecha_fin' => '2026-12-31',
                'fecha_suscripcion' => '2026-01-20',
                'numero_rp' => 'RP-77',
                'fecha_rp' => '2026-01-25',
                'fecha_acta_inicio' => '2026-02-01',
                'supervisor' => 'Ing. Pérez',
                'interventoria' => 'Interventoría Andina',
                'estado' => 'celebrado',
                'estado_original' => null,
                'anticipo' => true,
                'porcentaje_anticipo' => 30,
                'avance_fisico' => 10,
                'avance_financiero' => 5,
                'proceso_id' => null,
                'proceso' => null,
                'secop_id_contrato' => null,
                'secop_proceso' => null,
                'url_secop' => null,
                'secop_consultado_at' => null,
                'anexos' => [],
                'usuario_creo' => 'Ana Gestora',
                'created_at' => '2026-11-18 10:00:00',
                'updated_at' => '2026-11-18 10:00:00',
            ],
            'avisos' => [],
            'proponer_celebrado' => false,
        ]);

        $contrato = Contrato::findOrFail($id);
        $this->assertSame($gestor->id, $contrato->usuario_creo_id);
        $this->assertNull($contrato->secop_id_contrato);
        Carbon::setTestNow();
    }

    public function test_al_crear_el_valor_y_la_fecha_fin_vigentes_son_los_iniciales(): void
    {
        $contrato = $this->crear(['valor_inicial' => 200000000, 'fecha_fin_inicial' => '2027-06-30']);

        $this->assertSame('200000000.00', $contrato->valor_inicial);
        $this->assertSame('200000000.00', $contrato->valor);
        $this->assertSame('2027-06-30', $contrato->fecha_fin_inicial->format('Y-m-d'));
        $this->assertSame('2027-06-30', $contrato->fecha_fin->format('Y-m-d'));
        $this->assertFalse($contrato->anticipo);
    }

    public function test_se_crea_un_contrato_sin_proyecto(): void
    {
        $r = $this->guardar($this->datos(['proyecto' => null]))->assertOk();

        $this->assertNull($r->json('contrato.proyecto'));
        $this->assertNull(Contrato::findOrFail($r->json('contrato.id'))->proyecto);
    }

    public function test_un_proyecto_que_no_existe_se_rechaza(): void
    {
        $this->guardar($this->datos(['proyecto' => 9999]))->assertUnprocessable()
            ->assertJsonPath('errors.proyecto.0', 'Elige un proyecto de la lista.');
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_los_anexos_se_guardan_con_el_contrato(): void
    {
        $r = $this->guardar($this->datos(), [
            ['descripcion' => 'Minuta', 'nombreArchivo' => 'minuta.pdf', 'rutaArchivo' => 'anexos_contratos/1_a.pdf', 'fecha' => '2026-01-20'],
        ])->assertOk();

        $r->assertJsonPath('contrato.anexos.0.descripcion', 'Minuta')
            ->assertJsonPath('contrato.anexos.0.nombre_archivo', 'minuta.pdf')
            ->assertJsonPath('contrato.anexos.0.ruta_archivo', 'anexos_contratos/1_a.pdf')
            ->assertJsonPath('contrato.anexos.0.fecha', '2026-01-20');
        $this->assertDatabaseCount('anexos_contratos', 1);
    }

    // ------------------------------------------------------------------ validación

    public static function pedidosInvalidos(): array
    {
        return [
            'sin número' => [['n_contrato' => null], 'n_contrato', 'Escribe el número del contrato.'],
            'número largo' => [['n_contrato' => str_repeat('x', 101)], 'n_contrato', 'El número del contrato admite hasta 100 caracteres.'],
            'sin objeto' => [['objeto' => '   '], 'objeto', 'Escribe el objeto del contrato.'],
            'objeto largo' => [['objeto' => str_repeat('x', 5001)], 'objeto', 'El objeto admite hasta 5000 caracteres.'],
            'contratante largo' => [['contratante' => str_repeat('x', 5001)], 'contratante', 'En el contratante caben hasta 5000 caracteres.'],
            'nit largo' => [['contratante_nit' => str_repeat('1', 31)], 'contratante_nit', 'En el NIT del contratante caben hasta 30 caracteres.'],
            'identificación larga' => [['contratista_identificacion' => str_repeat('1', 31)], 'contratista_identificacion', 'En la identificación del contratista caben hasta 30 caracteres.'],
            'supervisor largo' => [['supervisor' => str_repeat('x', 256)], 'supervisor', 'En el supervisor caben hasta 255 caracteres.'],
            'rp largo' => [['numero_rp' => str_repeat('1', 41)], 'numero_rp', 'En el número del RP caben hasta 40 caracteres.'],
            'sin valor' => [['valor_inicial' => null], 'valor_inicial', 'Escribe el valor inicial del contrato.'],
            'valor no numérico' => [['valor_inicial' => 'mil'], 'valor_inicial', 'Escribe el valor inicial como un número.'],
            'valor negativo' => [['valor_inicial' => -1], 'valor_inicial', 'El valor inicial no puede ser negativo.'],
            'valor con tres decimales' => [['valor_inicial' => '10.555'], 'valor_inicial', 'El valor inicial admite hasta 2 decimales.'],
            'valor enorme' => [['valor_inicial' => '10000000000000'], 'valor_inicial', 'El valor inicial admite hasta 13 cifras enteras y 2 decimales.'],
            'fecha mal escrita' => [['fecha_inicio' => '01/02/2026'], 'fecha_inicio', 'Escribe la fecha de inicio como año-mes-día.'],
            'fecha rp mal escrita' => [['fecha_rp' => 'ayer'], 'fecha_rp', 'Escribe la fecha del RP como año-mes-día.'],
            'sin estado' => [['estado' => null], 'estado', 'Elige el estado del contrato.'],
            'estado de texto libre' => [['estado' => 'En ejecución'], 'estado', 'Elige un estado de la lista.'],
            'anticipo no booleano' => [['anticipo' => 'quizá'], 'anticipo', 'Revisa el anticipo: debe ser sí o no.'],
            'porcentaje de anticipo alto' => [['porcentaje_anticipo' => 51], 'porcentaje_anticipo', 'El porcentaje de anticipo va de 0 a 50.'],
            'porcentaje de anticipo decimal' => [['porcentaje_anticipo' => 10.5], 'porcentaje_anticipo', 'Revisa el porcentaje de anticipo: debe ser un número entero.'],
            'avance físico alto' => [['avance_fisico' => 101], 'avance_fisico', 'El avance físico va de 0 a 100.'],
            'avance financiero negativo' => [['avance_financiero' => -1], 'avance_financiero', 'El avance financiero va de 0 a 100.'],
            'tipo no entero' => [['tipo_contrato_id' => 'obra'], 'tipo_contrato_id', 'Revisa el tipo de contrato: debe ser un número entero.'],
            'tipo booleano' => [['tipo_contrato_id' => true], 'tipo_contrato_id', 'Revisa el tipo de contrato: debe ser un número entero.'],
            'proyecto no entero' => [['proyecto' => 'uno'], 'proyecto', 'Revisa el proyecto: debe ser un número entero.'],
            'proceso no entero' => [['proceso_id' => 'uno'], 'proceso_id', 'Revisa el proceso: debe ser un número entero.'],
        ];
    }

    #[DataProvider('pedidosInvalidos')]
    public function test_un_pedido_invalido_se_rechaza_por_campo(array $cambios, string $campo, string $mensaje): void
    {
        $this->guardar($this->datos($cambios))->assertUnprocessable()
            ->assertJsonPath("errors.{$campo}.0", $mensaje);
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_los_avances_que_calcula_la_pantalla_se_redondean_a_enteros(): void
    {
        $r = $this->guardar($this->datos(['avance_financiero' => 33.335, 'avance_fisico' => '66.5']))->assertOk();

        $r->assertJsonPath('contrato.avance_financiero', 33)->assertJsonPath('contrato.avance_fisico', 67);
    }

    public function test_los_seis_estados_son_validos(): void
    {
        foreach (Contrato::ESTADOS as $i => $estado) {
            $this->guardar($this->datos(['n_contrato' => "C-{$i}", 'estado' => $estado]))->assertOk()
                ->assertJsonPath('contrato.estado', $estado);
        }
        $this->assertDatabaseCount('contratos', 6);
    }

    public function test_la_fecha_fin_inicial_no_puede_ser_anterior_al_inicio(): void
    {
        $this->guardar($this->datos(['fecha_inicio' => '2026-05-01', 'fecha_fin_inicial' => '2026-04-30']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fecha_fin_inicial.0', 'La fecha de fin no puede ser anterior a la de inicio.');

        $this->guardar($this->datos(['fecha_inicio' => '2026-05-01', 'fecha_fin_inicial' => '2026-05-01']))->assertOk();
    }

    public function test_al_editar_las_fechas_se_comparan_con_lo_que_quedaria_guardado(): void
    {
        $contrato = $this->crear(['fecha_inicio' => '2026-05-01', 'fecha_fin_inicial' => '2026-12-31']);
        $form = ['id' => $contrato->id, 'actualizado' => $this->version($contrato), 'n_contrato' => 'C-1', 'objeto' => 'X', 'valor_inicial' => 1, 'estado' => 'en_ejecucion', 'fecha_fin_inicial' => '2026-04-01'];

        $this->como()->postJson(self::GUARDAR, ['formContrato' => $form])->assertUnprocessable()
            ->assertJsonPath('errors.fecha_fin_inicial.0', 'La fecha de fin no puede ser anterior a la de inicio.');
    }

    public function test_el_tipo_de_contrato_debe_existir_y_estar_activo_salvo_el_que_ya_tiene(): void
    {
        $obra = TipoProceso::where('nombre', 'Obra')->firstOrFail();
        $suministro = TipoProceso::where('nombre', 'Suministro')->firstOrFail();

        $this->guardar($this->datos(['tipo_contrato_id' => 9999]))->assertUnprocessable()
            ->assertJsonPath('errors.tipo_contrato_id.0', 'Elige un tipo de contrato de la lista.');

        $contrato = $this->crear(['tipo_contrato_id' => $obra->id]);
        $obra->update(['activo' => false]);
        $suministro->update(['activo' => false]);

        $this->guardar($this->datos(['tipo_contrato_id' => $obra->id]))->assertUnprocessable()
            ->assertJsonPath('errors.tipo_contrato_id.0', 'El tipo de contrato elegido está inactivo: elige otro.');
        $this->editar($contrato, ['tipo_contrato_id' => $obra->id, 'objeto' => 'Otro objeto'])->assertOk();
        $this->editar($contrato->fresh(), ['tipo_contrato_id' => $suministro->id])->assertUnprocessable()
            ->assertJsonPath('errors.tipo_contrato_id.0', 'El tipo de contrato elegido está inactivo: elige otro.');
    }

    public function test_los_anexos_se_validan_y_solo_se_aceptan_rutas_de_la_carpeta_de_anexos(): void
    {
        $this->guardar($this->datos(), [['descripcion' => '', 'nombreArchivo' => 'a.pdf', 'rutaArchivo' => 'anexos_contratos/a.pdf', 'fecha' => '2026-01-01']])
            ->assertUnprocessable()->assertJsonPath('errors', ['anexos.0.descripcion' => ['Escribe la descripción del anexo.']]);

        foreach (['../.env', 'anexos_contratos/../../.env', 'otra/a.pdf', 'anexos_contratos/sub/a.pdf', 'anexos_contratos\\..\\a.pdf'] as $ruta) {
            $this->guardar($this->datos(), [['descripcion' => 'X', 'nombreArchivo' => 'a.pdf', 'rutaArchivo' => $ruta, 'fecha' => '2026-01-01']])
                ->assertUnprocessable()
                ->assertJsonPath('errors', ['anexos.0.rutaArchivo' => ['El anexo no es válido: vuelve a subirlo.']]);
        }
        $this->assertDatabaseCount('contratos', 0);
    }

    // ------------------------------------------------------------------ proceso

    public function test_un_contrato_se_vincula_a_un_proceso_adjudicado_o_celebrado_del_mismo_proyecto(): void
    {
        foreach (ProcesoLicitacion::ADMITEN_CONTRATOS as $estado) {
            $proceso = $this->proceso($estado);
            $r = $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertOk();

            $r->assertJsonPath('contrato.proceso_id', $proceso->id)
                ->assertJsonPath('contrato.proceso', [
                    'id' => $proceso->id,
                    'codigo_proceso' => 'LP-001-2026',
                    'objeto' => 'Obra del acueducto',
                    'estado' => $estado,
                    'modalidad' => ['id' => $proceso->modalidad, 'nombre' => 'Licitación pública'],
                    'plataforma' => 'secop_ii',
                    'url_proceso' => 'https://secop.gov.co/p/1',
                ]);
            $this->assertSame($proceso->id, Contrato::findOrFail($r->json('contrato.id'))->proceso_licitacion);
        }
    }

    public static function estadosQueNoAdmiten(): array
    {
        return array_map(fn (string $e) => [$e], array_values(array_diff(ProcesoLicitacion::ESTADOS, ProcesoLicitacion::ADMITEN_CONTRATOS)));
    }

    #[DataProvider('estadosQueNoAdmiten')]
    public function test_no_se_vincula_a_un_proceso_que_no_esta_adjudicado_ni_celebrado(string $estado): void
    {
        $proceso = $this->proceso($estado);

        $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'Solo se puede vincular un contrato a un proceso adjudicado o celebrado.');
        $this->assertDatabaseCount('contratos', 0);
    }

    public function test_el_proceso_que_ya_tiene_se_conserva_aunque_cambie_de_estado_y_se_puede_desvincular(): void
    {
        $proceso = $this->proceso();
        $contrato = $this->crear(['proceso_id' => $proceso->id]);
        $proceso->update(['estado' => 'cancelado']);

        $this->editar($contrato, ['proceso_id' => $proceso->id, 'objeto' => 'Nuevo objeto'])->assertOk()
            ->assertJsonPath('contrato.proceso_id', $proceso->id);
        // Si no viene en el pedido, tampoco cambia.
        $this->editar($contrato->fresh(), ['objeto' => 'Otro'])->assertOk()->assertJsonPath('contrato.proceso_id', $proceso->id);

        $this->editar($contrato->fresh(), ['proceso_id' => null])->assertOk()->assertJsonPath('contrato.proceso', null);
        $this->assertNull($contrato->fresh()->proceso_licitacion);

        // Una vez desvinculado, volver a vincularlo es un vínculo nuevo: ya no se admite.
        $this->editar($contrato->fresh(), ['proceso_id' => $proceso->id])->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'Solo se puede vincular un contrato a un proceso adjudicado o celebrado.');
    }

    public function test_el_proceso_debe_ser_del_mismo_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Puente']);
        $proceso = $this->proceso('adjudicado', ['proyecto' => $otro->id]);

        $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'El proceso elegido es de otro proyecto.');

        // Tampoco al cambiar el proyecto de un contrato que conserva su proceso.
        $propio = $this->proceso();
        $contrato = $this->crear(['proceso_id' => $propio->id]);
        $this->editar($contrato, ['proyecto' => $otro->id])->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'El proceso elegido es de otro proyecto.');
    }

    public function test_un_proceso_que_no_existe_se_rechaza(): void
    {
        $this->guardar($this->datos(['proceso_id' => 9999]))->assertUnprocessable()
            ->assertJsonPath('errors.proceso_id.0', 'Elige un proceso de la lista.');
    }

    public function test_un_contrato_sin_proyecto_toma_el_del_proceso(): void
    {
        $proceso = $this->proceso();

        $r = $this->guardar($this->datos(['proyecto' => null, 'proceso_id' => $proceso->id]))->assertOk();
        $this->assertSame($this->proyecto->id, $r->json('contrato.proyecto'));

        $sinProyecto = $this->crear(['proyecto' => null, 'n_contrato' => 'C-2']);
        $this->editar($sinProyecto, ['proyecto' => null, 'proceso_id' => $proceso->id])->assertOk()
            ->assertJsonPath('contrato.proyecto', $this->proyecto->id);
    }

    public function test_al_editar_se_asigna_o_cambia_el_proyecto(): void
    {
        $otro = Proyecto::create(['nombre' => 'Puente']);
        $contrato = $this->crear(['proyecto' => null]);

        $this->editar($contrato, ['proyecto' => $this->proyecto->id])->assertOk()->assertJsonPath('contrato.proyecto', $this->proyecto->id);
        $this->editar($contrato->fresh(), ['proyecto' => $otro->id])->assertOk()->assertJsonPath('contrato.proyecto', $otro->id);
        $this->editar($contrato->fresh(), ['proyecto' => 9999])->assertUnprocessable()
            ->assertJsonPath('errors.proyecto.0', 'Elige un proyecto de la lista.');
    }

    // ------------------------------------------------------------------ avisos y celebrado

    public function test_el_primer_contrato_de_un_proceso_adjudicado_propone_pasarlo_a_celebrado(): void
    {
        $proceso = $this->proceso();

        $this->guardar($this->datos(['proceso_id' => $proceso->id]))->assertOk()->assertJsonPath('proponer_celebrado', true);
        $this->guardar($this->datos(['proceso_id' => $proceso->id, 'n_contrato' => 'C-2']))->assertOk()->assertJsonPath('proponer_celebrado', false);

        $celebrado = $this->proceso('celebrado');
        $this->guardar($this->datos(['proceso_id' => $celebrado->id]))->assertOk()->assertJsonPath('proponer_celebrado', false);
        $this->guardar($this->datos())->assertOk()->assertJsonPath('proponer_celebrado', false);
        // El estado del proceso no cambia solo: lo cambia la persona si confirma.
        $this->assertSame('adjudicado', $proceso->fresh()->estado);
    }

    public function test_editar_no_propone_celebrado(): void
    {
        $proceso = $this->proceso();
        $contrato = $this->crear();

        $this->editar($contrato, ['proceso_id' => $proceso->id])->assertOk()->assertJsonPath('proponer_celebrado', false);
    }

    public function test_avisa_si_los_contratos_del_proceso_superan_su_monto_sin_contar_los_anulados(): void
    {
        $proceso = $this->proceso('adjudicado', ['monto' => 1000000]);

        $this->guardar($this->datos(['proceso_id' => $proceso->id, 'valor_inicial' => 600000]))->assertOk()->assertJsonPath('avisos', []);
        $anulado = $this->crear(['proceso_id' => $proceso->id, 'valor_inicial' => 900000, 'estado' => 'anulado', 'n_contrato' => 'C-2']);
        $this->assertNotNull($anulado);

        $r = $this->guardar($this->datos(['proceso_id' => $proceso->id, 'valor_inicial' => 400000.01, 'n_contrato' => 'C-3']))->assertOk();
        $r->assertJsonPath('avisos', ['Los contratos del proceso suman $1.000.000,01, más que su valor de $1.000.000,00.']);

        // Sin monto no hay aviso.
        $sinMonto = $this->proceso('adjudicado');
        $this->guardar($this->datos(['proceso_id' => $sinMonto->id, 'valor_inicial' => 5]))->assertOk()->assertJsonPath('avisos', []);
    }

    public function test_el_aviso_tambien_sale_al_editar(): void
    {
        $proceso = $this->proceso('adjudicado', ['monto' => 100]);
        $contrato = $this->crear(['proceso_id' => $proceso->id, 'valor_inicial' => 100]);

        $this->editar($contrato, ['proceso_id' => $proceso->id, 'valor_inicial' => 150])->assertOk()
            ->assertJsonPath('avisos', ['Los contratos del proceso suman $150,00, más que su valor de $100,00.']);
    }

    // ------------------------------------------------------------------ editar

    public function test_al_editar_se_guardan_los_iniciales_y_se_recalcula_con_las_modificaciones(): void
    {
        $contrato = $this->antiguo();
        ModificacionContrato::create(['contrato_id' => $contrato->id, 'tipo' => 'adicion_prorroga', 'valor_adicion' => 30000000, 'dias_prorroga' => 60, 'fecha_modificacion' => '2026-03-01']);
        DB::table('contratos')->where('id', $contrato->id)->update(['valor' => 130000000, 'fecha_fin' => '2027-03-01']);

        // Sin cambiar los iniciales, el valor vigente (con la adición) no se pisa.
        $this->editar($contrato, ['valor_inicial' => 100000000, 'fecha_fin_inicial' => '2026-12-31', 'objeto' => 'Otro objeto'])->assertOk()
            ->assertJsonPath('contrato.valor', '130000000.00')
            ->assertJsonPath('contrato.fecha_fin', '2027-03-01');

        // Con otros iniciales, se recalcula: inicial + adiciones, fin inicial + prórrogas.
        $this->editar($contrato->fresh(), ['valor_inicial' => 120000000, 'fecha_fin_inicial' => '2026-11-30'])->assertOk()
            ->assertJsonPath('contrato.valor_inicial', '120000000.00')
            ->assertJsonPath('contrato.valor', '150000000.00')
            ->assertJsonPath('contrato.fecha_fin_inicial', '2026-11-30')
            ->assertJsonPath('contrato.fecha_fin', '2027-01-29');
    }

    public function test_editar_cambia_solo_los_campos_que_vienen_y_registra_solo_lo_que_cambio(): void
    {
        $contrato = $this->crear(['supervisor' => 'Ing. Pérez', 'contratista' => 'Consorcio Agua']);
        $gestor = User::factory()->create(['rol' => Rol::Gestor]);

        $form = [
            'id' => $contrato->id, 'actualizado' => $this->version($contrato),
            'n_contrato' => 'C-001-2026', 'objeto' => 'Construcción del acueducto veredal',
            'valor_inicial' => '1000000.00', 'estado' => 'suspendido', 'contratista' => 'Consorcio Agua 2',
        ];
        $this->como($gestor)->postJson(self::GUARDAR, ['formContrato' => $form, 'anexos' => []])->assertOk()
            ->assertJsonPath('contrato.supervisor', 'Ing. Pérez');

        $eventos = $this->eventos($contrato);
        $this->assertCount(2, $eventos);
        $this->assertSame(['contrato_editado', ['contratista' => 'Consorcio Agua', 'estado' => 'en_ejecucion'], ['contratista' => 'Consorcio Agua 2', 'estado' => 'suspendido']], $eventos[1]);
        $this->assertSame($gestor->id, (int) DB::table('checklist_historial')->orderByDesc('id')->value('usuario_id'));
    }

    public function test_editar_sin_cambios_no_registra_ni_cambia_la_version(): void
    {
        $contrato = $this->crear();
        $version = $this->version($contrato);
        $this->travel(5)->seconds();

        $this->editar($contrato)->assertOk()->assertJsonPath('contrato.updated_at', $version);
        $this->assertCount(1, $this->eventos($contrato));
    }

    public function test_al_editar_los_anexos_se_reemplazan_y_sin_anexos_en_el_pedido_se_conservan(): void
    {
        $contrato = $this->crear();
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Viejo', 'nombre_archivo' => 'v.pdf', 'ruta_archivo' => 'anexos_contratos/v.pdf', 'fecha' => '2026-01-01']);

        $this->editar($contrato, [], null)->assertOk()->assertJsonCount(1, 'contrato.anexos');

        $this->editar($contrato->fresh(), [], [
            ['descripcion' => 'Nuevo', 'nombreArchivo' => 'n.pdf', 'rutaArchivo' => 'anexos_contratos/n.pdf', 'fecha' => '2026-02-01'],
        ])->assertOk()->assertJsonPath('contrato.anexos.0.descripcion', 'Nuevo')->assertJsonCount(1, 'contrato.anexos');

        $this->editar($contrato->fresh(), [], [])->assertOk()->assertJsonCount(0, 'contrato.anexos');
    }

    public function test_editar_un_contrato_antiguo_sin_version_funciona_y_le_pone_fecha(): void
    {
        $contrato = $this->antiguo();
        $this->assertNull($contrato->updated_at);

        $r = $this->editar($contrato, ['objeto' => 'Renovado'])->assertOk();
        $this->assertNotNull($r->json('contrato.updated_at'));
        $this->assertNull($r->json('contrato.created_at'));
    }

    public function test_editar_con_una_version_vieja_responde_409(): void
    {
        $contrato = $this->crear();
        $vieja = $this->version($contrato);
        $this->editar($contrato, ['objeto' => 'Primero'])->assertOk();

        $this->editar($contrato->fresh(), ['objeto' => 'Segundo', 'actualizado' => $vieja])->assertStatus(409)
            ->assertJsonPath('mensaje', 'Otra persona cambió este contrato mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.');
        $this->assertSame('Primero', $contrato->fresh()->objeto);

        // Un formulario de un contrato antiguo (sin versión) tampoco pisa uno que ya la tiene.
        $this->editar($contrato->fresh(), ['objeto' => 'Tercero', 'actualizado' => null])->assertStatus(409);
    }

    public function test_cambiar_solo_los_anexos_cambia_la_version_y_queda_en_el_historial(): void
    {
        $contrato = $this->crear();
        $vieja = $this->version($contrato);
        $anexo = ['descripcion' => 'Minuta', 'nombreArchivo' => 'm.pdf', 'rutaArchivo' => 'anexos_contratos/m.pdf', 'fecha' => '2026-02-01'];

        // B cambia solo los anexos.
        $this->editar($contrato, [], [$anexo])->assertOk();
        $this->assertNotSame($vieja, $this->version($contrato));
        [$evento, $antes, $despues] = collect($this->eventos($contrato))->last();
        $this->assertSame('contrato_editado', $evento);
        $this->assertSame(['anexos' => []], $antes);
        $this->assertSame(['anexos' => [
            ['descripcion' => 'Minuta', 'nombre_archivo' => 'm.pdf', 'ruta_archivo' => 'anexos_contratos/m.pdf', 'fecha' => '2026-02-01'],
        ]], $despues);

        // A guarda con la versión que tenía: no borra en silencio el anexo de B.
        $this->editar($contrato->fresh(), ['objeto' => 'De A', 'actualizado' => $vieja], [])->assertStatus(409);
        $this->assertSame(1, $contrato->anexos()->count());

        // Los mismos anexos no cambian la versión ni registran nada.
        $version = $this->version($contrato);
        $eventos = count($this->eventos($contrato));
        $this->travel(5)->seconds();
        $this->editar($contrato->fresh(), [], [$anexo])->assertOk()->assertJsonPath('contrato.updated_at', $version);
        $this->assertCount($eventos, $this->eventos($contrato));
    }

    public function test_anexos_null_conserva_los_anexos(): void
    {
        $contrato = $this->crear();
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Viejo', 'nombre_archivo' => 'v.pdf', 'ruta_archivo' => 'anexos_contratos/v.pdf', 'fecha' => '2026-01-01']);
        $form = [...$this->datos(), 'id' => $contrato->id, 'actualizado' => $this->version($contrato)];

        $this->como()->postJson(self::GUARDAR, ['formContrato' => $form, 'anexos' => null])->assertOk()
            ->assertJsonCount(1, 'contrato.anexos');
    }

    public function test_un_contrato_con_proyecto_no_se_queda_sin_proyecto_al_editar(): void
    {
        $contrato = $this->crear();

        $this->editar($contrato, ['proyecto' => null])->assertUnprocessable()
            ->assertJsonPath('errors.proyecto.0', 'El contrato debe seguir en un proyecto.');
        $this->assertSame($this->proyecto->id, (int) $contrato->fresh()->proyecto);
    }

    public function test_al_editar_la_version_debe_venir(): void
    {
        $contrato = $this->crear();
        $form = [...$this->datos(), 'id' => $contrato->id];

        $this->como()->postJson(self::GUARDAR, ['formContrato' => $form])->assertUnprocessable()
            ->assertJsonPath('errors.actualizado.0', 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.');
    }

    public function test_editar_un_contrato_que_no_existe_responde_404(): void
    {
        $this->como()->postJson(self::GUARDAR, ['formContrato' => [...$this->datos(), 'id' => 9999, 'actualizado' => null]])
            ->assertNotFound()->assertJsonPath('mensaje', 'El contrato no existe.');
    }

    public function test_crear_y_editar_quedan_en_el_historial(): void
    {
        $proceso = $this->proceso();
        $contrato = $this->crear(['proceso_id' => $proceso->id, 'fecha_inicio' => '2026-02-01', 'supervisor' => ' Ing. Pérez ']);

        [$evento, $antes, $despues] = $this->eventos($contrato)[0];
        $this->assertSame('contrato_creado', $evento);
        $this->assertNull($antes);
        $this->assertSame([
            'proyecto' => $this->proyecto->id,
            'proceso_id' => $proceso->id,
            'tipo_contrato_id' => null,
            'n_contrato' => 'C-001-2026',
            'objeto' => 'Construcción del acueducto veredal',
            'contratante' => null,
            'contratante_nit' => null,
            'contratista' => null,
            'contratista_identificacion' => null,
            'supervisor' => 'Ing. Pérez',
            'interventoria' => null,
            'numero_rp' => null,
            'valor_inicial' => '1000000.00',
            'fecha_inicio' => '2026-02-01',
            'fecha_fin_inicial' => null,
            'fecha_suscripcion' => null,
            'fecha_rp' => null,
            'fecha_acta_inicio' => null,
            'estado' => 'en_ejecucion',
            'anticipo' => false,
            'porcentaje_anticipo' => null,
            'avance_fisico' => null,
            'avance_financiero' => null,
            'secop_id_contrato' => null,
            'secop_proceso' => null,
            'url_secop' => null,
            'secop_consultado_at' => null,
        ], $despues);
    }

    // ------------------------------------------------------------------ eliminar

    public function test_eliminar_un_contrato_sin_actas_borra_sus_anexos_y_archivos(): void
    {
        $contrato = $this->crear();
        $ruta = $this->archivo('1_minuta.pdf');
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'minuta.pdf', 'ruta_archivo' => $ruta, 'fecha' => '2026-01-01']);
        // Un anexo cuyo archivo ya no está no impide eliminar.
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Perdido', 'nombre_archivo' => 'p.pdf', 'ruta_archivo' => 'anexos_contratos/perdido.pdf', 'fecha' => '2026-01-01']);
        $otro = $this->crear(['n_contrato' => 'C-2']);
        $ajeno = $this->archivo('2_otro.pdf');
        AnexoContrato::create(['contrato_id' => $otro->id, 'descripcion' => 'Otro', 'nombre_archivo' => 'o.pdf', 'ruta_archivo' => $ajeno, 'fecha' => '2026-01-01']);

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertOk()
            ->assertJson(['success' => true, 'mensaje' => 'Contrato eliminado.']);

        $this->assertNull(Contrato::find($contrato->id));
        $this->assertSame(1, AnexoContrato::count());
        $this->assertFileDoesNotExist($this->publico . '/' . $ruta);
        $this->assertFileExists($this->publico . '/' . $ajeno);

        [$evento, $antes, $despues] = collect($this->eventos($contrato))->last();
        $this->assertSame('contrato_eliminado', $evento);
        $this->assertSame('C-001-2026', $antes['n_contrato']);
        $this->assertSame($this->proyecto->id, $antes['proyecto']);
        $this->assertSame(['Minuta', 'Perdido'], array_column($antes['anexos'], 'descripcion'));
        $this->assertNull($despues);
    }

    public function test_eliminar_no_toca_archivos_fuera_de_la_carpeta_de_anexos(): void
    {
        $contrato = $this->crear();
        file_put_contents($this->publico . '/secreto.txt', 'x');
        // Una ruta guardada antes de validar los anexos.
        DB::table('anexos_contratos')->insert(['contrato_id' => $contrato->id, 'descripcion' => 'Raro', 'nombre_archivo' => 's', 'ruta_archivo' => 'anexos_contratos/../secreto.txt', 'fecha' => '2026-01-01']);

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertOk();

        $this->assertFileExists($this->publico . '/secreto.txt');
    }

    public function test_eliminar_no_borra_un_archivo_que_usa_otro_anexo(): void
    {
        $contrato = $this->crear();
        $otro = $this->crear(['n_contrato' => 'C-2']);
        $ruta = $this->archivo('compartido.pdf');
        foreach ([$contrato, $otro] as $c) {
            AnexoContrato::create(['contrato_id' => $c->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'm.pdf', 'ruta_archivo' => $ruta, 'fecha' => '2026-01-01']);
        }

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertOk();
        $this->assertFileExists($this->publico . '/' . $ruta);

        $this->como()->postJson(self::ELIMINAR, ['id' => $otro->id])->assertOk();
        $this->assertFileDoesNotExist($this->publico . '/' . $ruta);
    }

    public static function dependencias(): array
    {
        return [
            'acta financiera' => [fn (Contrato $c) => AvanceFinanciero::create(['contrato_id' => $c->id, 'descripcion' => 'Acta 1', 'fecha_acta' => '2026-02-01', 'valor_facturado' => 10])],
            'acta física' => [fn (Contrato $c) => AvanceFisico::create(['contrato_id' => $c->id, 'descripcion_avance_fisico' => 'Acta 1', 'fecha_avance_fisico' => '2026-02-01'])],
            'actividad' => [fn (Contrato $c) => ActividadContrato::create(['contrato_id' => $c->id, 'nombre' => 'Excavación', 'peso' => 50])],
            'modificación' => [fn (Contrato $c) => ModificacionContrato::create(['contrato_id' => $c->id, 'tipo' => 'prorroga', 'dias_prorroga' => 10, 'fecha_modificacion' => '2026-03-01'])],
        ];
    }

    #[DataProvider('dependencias')]
    public function test_no_se_elimina_un_contrato_con_actas_actividades_o_modificaciones(\Closure $crearDependencia): void
    {
        $contrato = $this->crear();
        $ruta = $this->archivo('1_minuta.pdf');
        AnexoContrato::create(['contrato_id' => $contrato->id, 'descripcion' => 'Minuta', 'nombre_archivo' => 'minuta.pdf', 'ruta_archivo' => $ruta, 'fecha' => '2026-01-01']);
        $crearDependencia($contrato);

        $this->como()->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertUnprocessable()
            ->assertJsonPath('mensaje', 'Este contrato tiene actas, actividades o modificaciones: anúlalo en vez de eliminarlo.');

        $this->assertNotNull(Contrato::find($contrato->id));
        $this->assertSame(1, AnexoContrato::count());
        $this->assertFileExists($this->publico . '/' . $ruta);
    }

    public function test_eliminar_un_contrato_que_no_existe_o_sin_id(): void
    {
        $this->como()->postJson(self::ELIMINAR, ['id' => 9999])->assertNotFound()->assertJsonPath('mensaje', 'El contrato no existe.');
        $this->como()->postJson(self::ELIMINAR, [])->assertUnprocessable()->assertJsonPath('errors.id.0', 'Falta el contrato que quieres eliminar.');
    }

    // ------------------------------------------------------------------ recalcular

    public function test_las_modificaciones_siguen_recalculando_desde_el_servicio_sin_cambiar_la_version(): void
    {
        $contrato = $this->crear(['valor_inicial' => 100000000, 'fecha_fin_inicial' => '2026-12-31']);
        $version = $this->version($contrato);
        $this->travel(5)->seconds();

        $this->como()->postJson('/GestPro/guardarModificacionContrato', [
            'contrato_id' => $contrato->id, 'tipo' => 'adicion_prorroga', 'valor_adicion' => 60000000,
            'dias_prorroga' => 31, 'fecha_modificacion' => '2026-03-01',
        ])->assertOk()->assertJsonPath('resumen', [
            // JSON no distingue 60 de 60.0: llegan como enteros.
            'valor_inicial' => 100000000,
            'valor_vigente' => 160000000,
            'fecha_fin_inicial' => '2026-12-31',
            'fecha_fin_vigente' => '2027-01-31',
            'porcentaje_adicionado' => 60,
            'supera_limite' => true,
        ]);

        $fresco = $contrato->fresh();
        $this->assertSame('160000000.00', $fresco->valor);
        // Registrar una modificación no deja viejo el formulario del contrato.
        $this->assertSame($version, $this->version($fresco));
        $this->editar($fresco, ['valor_inicial' => 100000000, 'actualizado' => $version])->assertOk()
            ->assertJsonPath('contrato.valor', '160000000.00');
    }

    // ------------------------------------------------------------------ lectura

    public function test_los_listados_traen_las_columnas_nuevas_y_el_proceso_resumido(): void
    {
        $proceso = $this->proceso();
        $obra = TipoProceso::where('nombre', 'Obra')->firstOrFail();
        $con = $this->crear(['proceso_id' => $proceso->id, 'tipo_contrato_id' => $obra->id, 'supervisor' => 'Ing. Pérez', 'fecha_fin_inicial' => '2026-12-31', 'numero_rp' => 'RP-1']);
        $sin = $this->antiguo();
        // Un contrato sin proyecto no sale en los listados por proyecto ni los rompe.
        $this->crear(['proyecto' => null, 'n_contrato' => 'C-SUELTO']);

        $proyectos = $this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json();
        $listado = $this->como(Rol::Consulta)->getJson('/GestPro/listarContratos?proyecto=' . $this->proyecto->id)->assertOk()->json();
        $deProyectos = collect($proyectos)->firstWhere('id', $this->proyecto->id)['contratos'];

        foreach (['proyectos' => $deProyectos, 'listarContratos' => $listado] as $ruta => $contratos) {
            $this->assertCount(2, $contratos, $ruta);
            $uno = collect($contratos)->firstWhere('id', $con->id);
            foreach ([
                'tipo_contrato_id' => $obra->id, 'supervisor' => 'Ing. Pérez', 'numero_rp' => 'RP-1',
                'proceso_id' => $proceso->id, 'proyecto' => $this->proyecto->id, 'estado' => 'en_ejecucion',
                'usuario_creo_id' => $con->usuario_creo_id, 'updated_at' => $this->version($con),
            ] as $campo => $valor) {
                $this->assertEquals($valor, $uno[$campo], "{$ruta}: {$campo}");
            }
            $this->assertSame('2026-12-31', substr((string) $uno['fecha_fin_inicial'], 0, 10), $ruta);
            $this->assertEquals(1000000, $uno['valor_inicial'], $ruta);
            $this->assertSame(['id', 'codigo_proceso', 'objeto', 'estado', 'modalidad', 'plataforma', 'url_proceso'], array_keys($uno['proceso']), $ruta);
            $this->assertSame('LP-001-2026', $uno['proceso']['codigo_proceso'], $ruta);
            foreach ([
                'contratista_identificacion', 'fecha_suscripcion', 'fecha_rp', 'fecha_acta_inicio', 'estado_original',
                'created_at', 'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
                // Lo que ya devolvían.
                'n_contrato', 'objeto', 'valor', 'contratante', 'contratista', 'fecha_inicio', 'fecha_fin',
                'interventoria', 'avance_financiero', 'avance_fisico', 'anticipo', 'porcentaje_anticipo',
                'anexos', 'avancesFinancieros', 'actividades',
            ] as $campo) {
                $this->assertArrayHasKey($campo, $uno, "{$ruta}: {$campo}");
            }

            $viejo = collect($contratos)->firstWhere('id', $sin->id);
            $this->assertNull($viejo['proceso'], $ruta);
            $this->assertNull($viejo['proceso_id'], $ruta);
            $this->assertNull($viejo['updated_at'], $ruta);
        }
        $this->assertArrayHasKey('modificaciones', $listado[0]);
    }

    // ------------------------------------------------------------------ permisos

    public function test_un_lector_no_guarda_ni_elimina_contratos(): void
    {
        $contrato = $this->crear();

        $this->guardar($this->datos(), [], Rol::Consulta)->assertForbidden();
        $this->como(Rol::Consulta)->postJson(self::ELIMINAR, ['id' => $contrato->id])->assertForbidden();
        $this->assertDatabaseCount('contratos', 1);
    }

    public function test_sin_sesion_no_se_guarda(): void
    {
        $this->postJson(self::GUARDAR, ['formContrato' => $this->datos()])->assertUnauthorized();
    }
}
