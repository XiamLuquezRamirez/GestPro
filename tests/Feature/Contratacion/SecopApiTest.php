<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SECOP II (datos abiertos): buscar un contrato, los de la empresa por su NIT y uno por su ID. */
class SecopApiTest extends TestCase
{
    use RefreshDatabase;

    private const BUSCAR = '/GestPro/secop/contratos';

    private const EMPRESA = '/GestPro/secop/contratos-empresa';

    private const FUENTE = 'https://www.datos.gov.co/resource/jbjy-vk9h.json';

    private const NO_DISPONIBLE = 'No se pudo consultar SECOP II. Intenta de nuevo en unos minutos.';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.secop.token' => null]);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    /** Una fila real de jbjy-vk9h (MUNICIPIO DE CARTAGO), recortada. */
    private function fila(array $mas = []): array
    {
        return [
            'nombre_entidad' => 'MUNICIPIO DE CARTAGO',
            'nit_entidad' => '891900493',
            'proceso_de_compra' => 'CO1.BDOS.3336966',
            'id_contrato' => 'CO1.PCCNTR.4078659',
            'referencia_del_contrato' => '1-305-2022',
            'estado_contrato' => 'En ejecución',
            'descripcion_del_proceso' => 'Prestación de servicios de apoyo',
            'objeto_del_contrato' => 'Prestación de servicios de apoyo a la gestión',
            'tipo_de_contrato' => 'Prestación de servicios',
            'modalidad_de_contratacion' => 'Contratación directa',
            'fecha_de_firma' => '2022-09-30T14:21:47.161',
            'fecha_de_inicio_del_contrato' => '2022-09-30T15:00:00.000',
            'fecha_de_fin_del_contrato' => '2022-12-30T23:59:00.000',
            'documento_proveedor' => '900919250',
            'proveedor_adjudicado' => 'CORPORACION SOLIDARIA',
            'valor_del_contrato' => '40000000',
            'valor_facturado' => '0',
            'valor_pagado' => '0',
            'dias_adicionados' => '0',
            'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true'],
            ...$mas,
        ];
    }

    private function responder(array $filas = [], int $estado = 200): void
    {
        Http::fake(['www.datos.gov.co/*' => Http::response($filas, $estado)]);
    }

    /** Los parámetros de la consulta tal como viajaron en la URL. */
    private function consulta(Request $pedido): array
    {
        parse_str((string) parse_url($pedido->url(), PHP_URL_QUERY), $parametros);

        return $parametros;
    }

    private function unicoPedido(): Request
    {
        $pedidos = Http::recorded();
        $this->assertCount(1, $pedidos);

        return $pedidos[0][0];
    }

    // ------------------------------------------------------------------ buscar

    public function test_busca_por_el_id_del_contrato_aunque_venga_en_un_enlace(): void
    {
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.1&id=co1.pccntr.4078659']))
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.secop_id_contrato', 'CO1.PCCNTR.4078659');

        $pedido = $this->unicoPedido();
        $this->assertSame('GET', $pedido->method());
        $this->assertStringStartsWith(self::FUENTE . '?', $pedido->url());
        $this->assertSame(['id_contrato' => 'CO1.PCCNTR.4078659', '$limit' => '25'], $this->consulta($pedido));
    }

    public function test_el_resultado_trae_la_forma_acordada(): void
    {
        $tipo = TipoProceso::where('nombre', 'Prestación de servicios')->firstOrFail();
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')
            ->assertOk()
            ->assertExactJson(['resultados' => [[
                'contrato' => [
                    'n_contrato' => '1-305-2022',
                    'objeto' => 'Prestación de servicios de apoyo a la gestión',
                    'contratante' => 'MUNICIPIO DE CARTAGO',
                    'contratante_nit' => '891900493',
                    'contratista' => 'CORPORACION SOLIDARIA',
                    'contratista_identificacion' => '900919250',
                    'valor_inicial' => '40000000.00',
                    'fecha_suscripcion' => '2022-09-30',
                    'fecha_inicio' => '2022-09-30',
                    'fecha_fin_inicial' => '2022-12-30',
                    'tipo_contrato_id' => $tipo->id,
                    'estado' => 'en_ejecucion',
                    'secop_id_contrato' => 'CO1.PCCNTR.4078659',
                    'secop_proceso' => 'CO1.BDOS.3336966',
                    'url_secop' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true',
                ],
                'secop' => [
                    'tipo_contrato_secop' => 'Prestación de servicios',
                    'modalidad_secop' => 'Contratación directa',
                    'estado_secop' => 'En ejecución',
                    'dias_adicionados' => 0,
                    'valor_pagado' => '0.00',
                    'valor_facturado' => '0.00',
                ],
                'registrado' => null,
            ]]]);
    }

    public function test_un_tipo_inactivo_no_se_propone(): void
    {
        TipoProceso::where('nombre', 'Prestación de servicios')->update(['activo' => false]);
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.tipo_contrato_id', null)
            ->assertJsonPath('resultados.0.secop.tipo_contrato_secop', 'Prestación de servicios');
    }

    public function test_la_equivalencia_de_tipo_usa_el_catalogo_activo(): void
    {
        $suministro = TipoProceso::where('nombre', 'Suministro')->firstOrFail();
        $this->responder([$this->fila(['tipo_de_contrato' => 'Suministros']), $this->fila(['tipo_de_contrato' => 'Acuerdo Marco de Precios'])]);
        TipoProceso::where('nombre', 'Orden de compra')->update(['activo' => false]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.BDOS.3336966')
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.tipo_contrato_id', $suministro->id)
            ->assertJsonPath('resultados.1.contrato.tipo_contrato_id', null);
    }

    public function test_marca_los_que_ya_estan_registrados(): void
    {
        $c = Contrato::create([
            'n_contrato' => 'C-305', 'objeto' => 'Apoyo', 'valor_inicial' => 1, 'valor' => 1,
            'estado' => 'en_ejecucion', 'secop_id_contrato' => 'CO1.PCCNTR.4078659',
        ]);
        $this->responder([$this->fila(), $this->fila(['id_contrato' => 'CO1.PCCNTR.999', 'referencia_del_contrato' => '1-306-2022'])]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.BDOS.3336966')
            ->assertOk()
            ->assertJsonPath('resultados.0.registrado', ['id' => $c->id, 'n_contrato' => 'C-305'])
            ->assertJsonPath('resultados.1.registrado', null);
    }

    public function test_busca_por_el_proceso_de_compra(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=' . urlencode(' co1.req.12345 '))
            ->assertOk()
            ->assertExactJson(['resultados' => []]);

        $this->assertSame(['proceso_de_compra' => 'CO1.REQ.12345', '$limit' => '25'], $this->consulta($this->unicoPedido()));
    }

    public function test_el_enlace_del_aviso_no_sirve_para_buscar(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True']))
            ->assertStatus(422)
            ->assertJsonPath('errors.q.0', 'Ese enlace es del aviso del proceso. Pega el enlace o el ID del contrato (CO1.PCCNTR…) o búscalo por su número.');
        Http::assertNothingSent();
    }

    public function test_un_enlace_sin_id_del_contrato_no_se_busca_como_numero(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.1']))
            ->assertStatus(422)
            ->assertJsonPath('errors.q.0', 'Ese enlace no trae el ID del contrato. Pega el ID (CO1.PCCNTR…) o busca el contrato por su número.');
        Http::assertNothingSent();
    }

    public function test_busca_por_el_numero_del_contrato_y_el_nit_de_la_entidad(): void
    {
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => ' 1-305-2022 ', 'nit' => '891.900.493-1']))
            ->assertOk()
            ->assertJsonCount(1, 'resultados');

        $this->assertSame([
            '$where' => "upper(referencia_del_contrato) = '1-305-2022' AND nit_entidad = '891900493'",
            '$order' => 'fecha_de_firma DESC',
            '$limit' => '25',
        ], $this->consulta($this->unicoPedido()));
    }

    public function test_el_numero_va_en_mayusculas_y_sin_nit_si_no_viene(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=lp-001-2026')->assertOk();

        $this->assertSame("upper(referencia_del_contrato) = 'LP-001-2026'", $this->consulta($this->unicoPedido())['$where']);
    }

    public function test_las_comillas_del_usuario_se_escapan_en_soql(): void
    {
        $this->responder([]);
        $texto = "1-305' OR '1'='1";

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => $texto]))->assertOk();

        $this->assertSame("upper(referencia_del_contrato) = '1-305'' OR ''1''=''1'", $this->consulta($this->unicoPedido())['$where']);
    }

    public function test_la_url_lleva_el_texto_codificado(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => "año 5 & \$limit=1#x'"]))->assertOk();

        $pedido = $this->unicoPedido();
        $url = $pedido->url();
        $this->assertStringNotContainsString(' ', $url);
        $this->assertStringNotContainsString("'", $url);
        $this->assertStringNotContainsString('#', $url);
        $this->assertStringNotContainsString('ñ', $url);
        $this->assertSame(1, substr_count($url, '$limit=') + substr_count($url, '%24limit='));
        $this->assertSame([
            '$where' => "upper(referencia_del_contrato) = 'AÑO 5 & \$LIMIT=1#X'''",
            '$order' => 'fecha_de_firma DESC',
            '$limit' => '25',
        ], $this->consulta($pedido));
    }

    public function test_valida_la_busqueda(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR)->assertStatus(422)->assertJsonPath('errors.q.0', 'Escribe el número, el ID o el enlace del contrato.');
        $this->como()->getJson(self::BUSCAR . '?q=%20%20')->assertStatus(422)->assertJsonPath('errors.q.0', 'Escribe el número, el ID o el enlace del contrato.');
        $this->como()->getJson(self::BUSCAR . '?q=' . str_repeat('1', 101))->assertStatus(422)->assertJsonPath('errors.q.0', 'El número del contrato admite hasta 100 caracteres.');
        $this->como()->getJson(self::BUSCAR . '?q=' . str_repeat('a', 501))->assertStatus(422)->assertJsonPath('errors.q.0', 'En la búsqueda caben hasta 500 caracteres.');
        $this->como()->getJson(self::BUSCAR . '?q=1-305&nit=' . urlencode("8919' OR 1=1"))->assertStatus(422)->assertJsonPath('errors.nit.0', 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).');
        Http::assertNothingSent();
    }

    public function test_un_id_largo_del_aviso_cabe_en_la_busqueda(): void
    {
        $this->responder([$this->fila()]);
        $enlace = 'https://community.secop.gov.co/Public/Tendering/ContractNoticePhases/View?PPI=CO1.PPI.20000000&isFromPublicArea=True&isModal=False&contrato=CO1.PCCNTR.4078659';

        $this->como()->getJson(self::BUSCAR . '?' . http_build_query(['q' => $enlace]))->assertOk();
    }

    // ------------------------------------------------------------------ de la empresa

    public function test_lista_los_contratos_de_la_empresa_por_su_nit(): void
    {
        $this->responder([$this->fila()]);

        $this->como()->getJson(self::EMPRESA . '?nit=' . urlencode('900.919.250-3'))
            ->assertOk()
            ->assertJsonPath('resultados.0.contrato.contratista_identificacion', '900919250')
            ->assertJsonPath('resultados.0.registrado', null);

        $this->assertSame([
            'documento_proveedor' => '900919250',
            '$order' => 'fecha_de_firma DESC',
            '$limit' => '50',
        ], $this->consulta($this->unicoPedido()));
    }

    public function test_valida_el_nit_de_la_empresa(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::EMPRESA)->assertStatus(422)->assertJsonPath('errors.nit.0', 'Escribe el NIT de la empresa.');
        $this->como()->getJson(self::EMPRESA . '?nit=900919250-12')->assertStatus(422)->assertJsonPath('errors.nit.0', 'Escribe el NIT solo con números (puede llevar puntos y el dígito de verificación con guion).');
        $this->como()->getJson(self::EMPRESA . '?nit=' . urlencode("900' OR '1'='1"))->assertStatus(422);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ por id

    public function test_trae_un_contrato_por_su_id(): void
    {
        $this->responder([$this->fila(['estado_contrato' => 'Cerrado'])]);

        $this->como()->getJson(self::BUSCAR . '/CO1.PCCNTR.4078659')
            ->assertOk()
            ->assertJsonPath('resultado.contrato.estado', 'terminado')
            ->assertJsonPath('resultado.secop.estado_secop', 'Cerrado')
            ->assertJsonPath('resultado.registrado', null);

        $this->assertSame(['id_contrato' => 'CO1.PCCNTR.4078659', '$limit' => '1'], $this->consulta($this->unicoPedido()));
    }

    public function test_por_id_responde_404_si_secop_no_lo_tiene(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '/CO1.PCCNTR.1')
            ->assertNotFound()
            ->assertExactJson(['mensaje' => 'SECOP II no tiene ese contrato.']);
    }

    public function test_por_id_valida_el_formato(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '/CO1.BDOS.3336966')
            ->assertStatus(422)
            ->assertJsonPath('errors.idContrato.0', 'El ID del contrato de SECOP II tiene la forma CO1.PCCNTR.123456.');
        $this->como()->getJson(self::BUSCAR . '/' . rawurlencode("CO1.PCCNTR.1' OR '1'='1"))->assertStatus(422);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------------ errores de SECOP

    public function test_si_secop_falla_responde_502(): void
    {
        $this->responder(['message' => 'Internal error'], 500);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
        $this->como()->getJson(self::EMPRESA . '?nit=900919250')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
        $this->como()->getJson(self::BUSCAR . '/CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
    }

    public function test_si_no_hay_conexion_responde_502(): void
    {
        Http::fake(['www.datos.gov.co/*' => Http::failedConnection()]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
    }

    public function test_si_secop_responde_algo_que_no_es_una_lista_responde_502(): void
    {
        Http::fake(['www.datos.gov.co/*' => Http::response('<html>mantenimiento</html>', 200)]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.4078659')->assertStatus(502)->assertExactJson(['mensaje' => self::NO_DISPONIBLE]);
    }

    // ------------------------------------------------------------------ configuración y permisos

    public function test_usa_la_url_y_el_token_configurados(): void
    {
        config(['services.secop.url' => 'https://www.datos.gov.co/resource/otra.json', 'services.secop.token' => 'abc123']);
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertOk();

        $pedido = $this->unicoPedido();
        $this->assertStringStartsWith('https://www.datos.gov.co/resource/otra.json?', $pedido->url());
        $this->assertSame(['abc123'], $pedido->header('X-App-Token'));
    }

    public function test_sin_token_no_manda_la_cabecera(): void
    {
        $this->responder([]);

        $this->como()->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertOk();

        $this->assertFalse($this->unicoPedido()->hasHeader('X-App-Token'));
    }

    public function test_solo_quien_puede_editar_consulta_secop(): void
    {
        $this->responder([]);

        $this->como(Rol::Consulta)->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertForbidden();
        $this->como(Rol::Consulta)->getJson(self::EMPRESA . '?nit=900919250')->assertForbidden();
        $this->como(Rol::Consulta)->getJson(self::BUSCAR . '/CO1.PCCNTR.1')->assertForbidden();
        $this->como(Rol::Administrador)->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->getJson(self::BUSCAR . '?q=CO1.PCCNTR.1')->assertUnauthorized();
    }
}
