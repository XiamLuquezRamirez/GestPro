<?php

namespace Tests\Unit\Contratacion;

use App\Services\Contratacion\FilaSecop;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Mapeo de una fila de SECOP II (datos abiertos, jbjy-vk9h) a los campos del contrato. */
class FilaSecopTest extends TestCase
{
    private const TIPOS = ['Obra' => 1, 'Interventoría' => 2, 'Suministro' => 4, 'Prestación de servicios' => 5, 'Orden de compra' => 6];

    /** Una fila real (MUNICIPIO DE CARTAGO, CO1.PCCNTR.4078659), recortada. */
    private function fila(array $mas = []): array
    {
        return [
            'nombre_entidad' => 'MUNICIPIO DE CARTAGO',
            'nit_entidad' => '891900493',
            'proceso_de_compra' => 'CO1.BDOS.3336966',
            'id_contrato' => 'CO1.PCCNTR.4078659',
            'referencia_del_contrato' => '1-305-2022',
            'estado_contrato' => 'En ejecución',
            'descripcion_del_proceso' => 'Descripción del proceso',
            'objeto_del_contrato' => 'Prestación de servicios de apoyo a la gestión',
            'tipo_de_contrato' => 'Prestación de servicios',
            'modalidad_de_contratacion' => 'Contratación directa',
            'fecha_de_firma' => '2022-09-30T14:21:47.161',
            'fecha_de_inicio_del_contrato' => '2022-09-30T15:00:00.000',
            'fecha_de_fin_del_contrato' => '2022-12-30T23:59:00.000',
            'documento_proveedor' => '900919250',
            'proveedor_adjudicado' => 'CORPORACION SOLIDARIA',
            'valor_del_contrato' => '40000000',
            'valor_facturado' => '13333333',
            'valor_pagado' => '0',
            'dias_adicionados' => '0',
            'urlproceso' => ['url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID=CO1.NTC.3342434&isFromPublicArea=True&isModal=true&asPopupView=true'],
            ...$mas,
        ];
    }

    public function test_mapea_una_fila_real_a_los_campos_del_contrato(): void
    {
        $this->assertSame([
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
                'tipo_contrato_id' => 5,
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
                'valor_facturado' => '13333333.00',
            ],
        ], FilaSecop::mapear($this->fila(), self::TIPOS));
    }

    public static function estados(): array
    {
        return [
            ['En ejecución', 'en_ejecucion'], ['EN EJECUCION', 'en_ejecucion'], ['Activo', 'en_ejecucion'],
            ['Modificado', 'en_ejecucion'], ['Prorrogado', 'en_ejecucion'], ['Cedido', 'en_ejecucion'],
            ['Celebrado', 'celebrado'], ['Firmado', 'celebrado'], ['Suspendido', 'suspendido'],
            ['Terminado', 'terminado'], ['Cerrado', 'terminado'], ['  liquidado ', 'liquidado'],
            ['Cancelado', 'anulado'], ['Anulado', 'anulado'], ['Aprobado', 'celebrado'], ['terminado', 'terminado'],
            ['cedido', 'en_ejecucion'], ['En aprobación', null], ['enviado Proveedor', null], ['Borrador', null], ['', null],
        ];
    }

    #[DataProvider('estados')]
    public function test_mapea_el_estado_sin_mayusculas_ni_tildes(string $secop, ?string $estado): void
    {
        $mapeo = FilaSecop::mapear($this->fila(['estado_contrato' => $secop]), self::TIPOS);

        $this->assertSame($estado, $mapeo['contrato']['estado']);
    }

    public function test_el_tipo_coincide_sin_mayusculas_ni_tildes_o_queda_vacio(): void
    {
        $this->assertSame(2, FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'INTERVENTORIA']), self::TIPOS)['contrato']['tipo_contrato_id']);

        $otro = FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'Compraventa']), self::TIPOS);
        $this->assertNull($otro['contrato']['tipo_contrato_id']);
        $this->assertSame('Compraventa', $otro['secop']['tipo_contrato_secop']);
    }

    public static function equivalencias(): array
    {
        return [
            ['Suministros', 4], ['SUMINISTROS', 4], ['Acuerdo Marco de Precios', 6], ['acuerdo marco de precios', 6],
            ['Suministro', 4], ['Comodato', null], ['No Definido', null],
        ];
    }

    #[DataProvider('equivalencias')]
    public function test_algunos_tipos_de_secop_tienen_equivalente_en_el_catalogo(string $secop, ?int $id): void
    {
        $this->assertSame($id, FilaSecop::mapear($this->fila(['tipo_de_contrato' => $secop]), self::TIPOS)['contrato']['tipo_contrato_id']);
    }

    public function test_la_equivalencia_sin_tipo_activo_de_destino_queda_vacia(): void
    {
        $sinDestino = ['Obra' => 1, 'Prestación de servicios' => 5];

        $this->assertNull(FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'Suministros']), $sinDestino)['contrato']['tipo_contrato_id']);
        $this->assertNull(FilaSecop::mapear($this->fila(['tipo_de_contrato' => 'Acuerdo Marco de Precios']), $sinDestino)['contrato']['tipo_contrato_id']);
    }

    public function test_sin_objeto_del_contrato_usa_la_descripcion_del_proceso(): void
    {
        $sinObjeto = $this->fila(['objeto_del_contrato' => '  ']);

        $this->assertSame('Descripción del proceso', FilaSecop::mapear($sinObjeto, self::TIPOS)['contrato']['objeto']);
    }

    public function test_campos_ausentes_o_invalidos_quedan_vacios(): void
    {
        $mapeo = FilaSecop::mapear([
            'id_contrato' => 'CO1.PCCNTR.1',
            'valor_del_contrato' => 'no es un número',
            'fecha_de_firma' => '30/09/2022',
            'dias_adicionados' => 'x',
            'urlproceso' => ['url' => 'javascript:alert(1)'],
        ], self::TIPOS);

        $this->assertNull($mapeo['contrato']['n_contrato']);
        $this->assertNull($mapeo['contrato']['objeto']);
        $this->assertNull($mapeo['contrato']['valor_inicial']);
        $this->assertNull($mapeo['contrato']['fecha_suscripcion']);
        $this->assertNull($mapeo['contrato']['fecha_inicio']);
        $this->assertNull($mapeo['contrato']['tipo_contrato_id']);
        $this->assertNull($mapeo['contrato']['estado']);
        $this->assertNull($mapeo['contrato']['url_secop']);
        $this->assertNull($mapeo['secop']['dias_adicionados']);
        $this->assertNull($mapeo['secop']['valor_pagado']);
        $this->assertSame('CO1.PCCNTR.1', $mapeo['contrato']['secop_id_contrato']);
    }

    public function test_el_valor_lleva_dos_decimales_redondeados(): void
    {
        $this->assertSame('1234.57', FilaSecop::mapear($this->fila(['valor_del_contrato' => '1234.567']), self::TIPOS)['contrato']['valor_inicial']);
        $this->assertSame('15.50', FilaSecop::mapear($this->fila(['valor_del_contrato' => '15.5']), self::TIPOS)['contrato']['valor_inicial']);
    }

    public function test_el_enlace_tambien_puede_venir_como_texto(): void
    {
        $mapeo = FilaSecop::mapear($this->fila(['urlproceso' => 'https://community.secop.gov.co/x']), self::TIPOS);

        $this->assertSame('https://community.secop.gov.co/x', $mapeo['contrato']['url_secop']);
    }

    public function test_dias_adicionados_es_un_entero(): void
    {
        $this->assertSame(30, FilaSecop::mapear($this->fila(['dias_adicionados' => '30']), self::TIPOS)['secop']['dias_adicionados']);
    }
}
