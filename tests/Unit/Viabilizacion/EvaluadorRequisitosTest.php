<?php

namespace Tests\Unit\Viabilizacion;

use App\Services\Viabilizacion\ConfiguracionExpediente;
use App\Services\Viabilizacion\EvaluadorRequisitos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EvaluadorRequisitosTest extends TestCase
{
    private EvaluadorRequisitos $evaluador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluador = new EvaluadorRequisitos();
    }

    private function cfg(int $fase = 3, array $componentes = [], array $tipologias = [], array $sectores = [], array $tipos = []): ConfiguracionExpediente
    {
        return new ConfiguracionExpediente($fase, $sectores, $tipos, $componentes, $tipologias);
    }

    // ---------------------------------------------------------------- DSL

    public static function casosDsl(): array
    {
        // [condición, fase, componentes, tipologías, esperado]
        return [
            'vacía aplica siempre' => ['', 1, [], [], true],
            'null aplica siempre' => [null, 1, [], [], true],
            'siempre' => ['siempre', 2, [], [], true],
            'fase=3 en fase 3' => ['fase=3', 3, [], [], true],
            'fase=3 en fase 2' => ['fase=3', 2, [], [], false],
            'fase=2 en fase 2' => ['fase=2', 2, [], [], true],
            'fase=2 en fase 3' => ['fase=2', 3, [], [], false],
            'fase=1|2 en fase 1' => ['fase=1|2', 1, [], [], true],
            'fase=1|2 en fase 3' => ['fase=1|2', 3, [], [], false],
            'fase!=1 en fase 2' => ['fase!=1', 2, [], [], true],
            'fase!=1 en fase 1' => ['fase!=1', 1, [], [], false],
            'comp presente' => ['comp=suelo', 1, ['suelo'], [], true],
            'comp ausente' => ['comp=suelo', 1, ['predios'], [], false],
            'esp presente' => ['esp=paz', 1, [], ['paz'], true],
            'esp ausente' => ['esp=paz', 1, [], [], false],
            'conjunción se cumple' => ['fase=3 & comp=infraestructura', 3, ['infraestructura'], [], true],
            'conjunción falla por fase' => ['fase=3 & comp=infraestructura', 2, ['infraestructura'], [], false],
            'disyunción primera rama' => ['fase=3 & (comp=suelo|comp=infraestructura)', 3, ['suelo'], [], true],
            'disyunción segunda rama' => ['fase=3 & (comp=suelo|comp=infraestructura)', 3, ['infraestructura'], [], true],
            'disyunción ninguna rama' => ['fase=3 & (comp=suelo|comp=infraestructura)', 3, ['predios'], [], false],
            'espacios tolerados' => ['  comp=predios   &   fase!=1 ', 2, ['predios'], [], true],
            // Semántica heredada del prototipo: átomo desconocido = true. El validador
            // impide guardarlos; aquí se documenta el comportamiento del evaluador.
            'átomo desconocido se trata como verdadero (heredado)' => ['fase=9', 1, [], [], true],
            // Heredado: dentro del paréntesis solo se reconoce comp=.
            'esp dentro de paréntesis no se reconoce (heredado)' => ['(esp=paz|esp=car)', 1, [], ['paz'], false],
        ];
    }

    #[DataProvider('casosDsl')]
    public function test_evalua_el_dsl_con_la_semantica_del_prototipo(?string $dsl, int $fase, array $comp, array $esp, bool $esperado): void
    {
        $this->assertSame($esperado, $this->evaluador->cumpleCondicion($dsl, $this->cfg($fase, $comp, $esp)));
    }

    // ---------------------------------------------------------- validador

    public function test_validador_acepta_las_condiciones_del_catalogo(): void
    {
        $validas = ['siempre', 'fase=3', 'fase=2', 'fase=1|2', 'fase!=1', 'comp=predios & fase!=1',
            'fase=3 & (comp=suelo|comp=infraestructura)', 'esp=desastre'];

        foreach ($validas as $dsl) {
            $this->assertSame([], $this->evaluador->validarCondicion($dsl, ['predios', 'suelo', 'infraestructura'], ['desastre']), $dsl);
        }
    }

    public function test_validador_rechaza_atomos_desconocidos(): void
    {
        $errores = $this->evaluador->validarCondicion('fase=9 & comp=suelo', ['suelo'], []);
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('fase=9', $errores[0]);
    }

    public function test_validador_rechaza_claves_inexistentes_en_los_catalogos(): void
    {
        $this->assertNotEmpty($this->evaluador->validarCondicion('comp=sueloo', ['suelo'], []));
        $this->assertNotEmpty($this->evaluador->validarCondicion('esp=pazz', [], ['paz']));
        $this->assertNotEmpty($this->evaluador->validarCondicion('(comp=suelo|comp=nada)', ['suelo'], []));
    }

    public function test_validador_rechaza_esp_dentro_de_parentesis(): void
    {
        $this->assertNotEmpty($this->evaluador->validarCondicion('(comp=suelo|esp=paz)', ['suelo'], ['paz']));
    }

    public function test_validador_rechaza_condicion_vacia(): void
    {
        $this->assertNotEmpty($this->evaluador->validarCondicion('', [], []));
        $this->assertNotEmpty($this->evaluador->validarCondicion('fase=3 &', [], []));
    }

    // ---------------------------------------------- reglas por sección

    private function req(string $clave, string $seccion, ?string $condicion = null, ?string $sector = null, ?string $tipo = null): array
    {
        return compact('clave', 'seccion', 'condicion', 'sector', 'tipo');
    }

    public function test_seccion_d_solo_aparece_con_desastre_y_reemplaza_la_a(): void
    {
        $reqs = [
            $this->req('a1', 'A', 'siempre'),
            $this->req('b1', 'B', 'comp=predios'),
            $this->req('d1', 'D', 'esp=desastre'),
        ];

        $this->assertSame(['a1', 'b1'], $this->evaluador->aplicables($reqs, $this->cfg(3, ['predios'])));
        $this->assertSame(['b1', 'd1'], $this->evaluador->aplicables($reqs, $this->cfg(3, ['predios'], ['desastre'])));
    }

    public function test_seccion_c_sin_tipos_marcados_trae_el_sector_completo(): void
    {
        $reqs = [
            $this->req('j1', 'C', null, 'justicia', 'casas_justicia'),
            $this->req('j2', 'C', null, 'justicia', 'construccion_carceles'),
            $this->req('s1', 'C', null, 'salud', 'infra_salud'),
        ];

        $this->assertSame(['j1', 'j2'], $this->evaluador->aplicables($reqs, $this->cfg(3, [], [], ['justicia'])));
    }

    public function test_seccion_c_con_tipos_marcados_trae_esos_y_los_de_todo_el_sector(): void
    {
        $reqs = [
            $this->req('c0', 'C', null, 'ctei', null), // aplica a todo el sector
            $this->req('c1', 'C', null, 'ctei', 'formacion'),
            $this->req('c2', 'C', null, 'ctei', 'centros'),
        ];

        $cfg = $this->cfg(3, [], [], ['ctei'], ['ctei::formacion']);
        $this->assertSame(['c0', 'c1'], $this->evaluador->aplicables($reqs, $cfg));
    }

    public function test_tipos_de_un_sector_no_acotan_a_otro_sector(): void
    {
        $reqs = [
            $this->req('e1', 'C', null, 'educacion', 'alimentacion_pae'),
            $this->req('v1', 'C', null, 'vivienda', 'agua_saneamiento'),
            $this->req('v2', 'C', null, 'vivienda', 'vis_urbana'),
        ];

        $cfg = $this->cfg(3, [], [], ['educacion', 'vivienda'], ['vivienda::agua_saneamiento']);
        $this->assertSame(['e1', 'v1'], $this->evaluador->aplicables($reqs, $cfg));
    }

    public function test_seccion_c_respeta_la_fase_ademas_del_sector_y_el_tipo(): void
    {
        $reqs = [
            $this->req('g1', 'C', null, 'minas', 'gas'), // sin condición: toda fase
            $this->req('g2', 'C', 'fase=3', 'minas', 'gas'),
            $this->req('g3', 'C', 'fase=2', 'minas', 'gas'),
            $this->req('g4', 'C', 'fase=3', 'minas', 'otro_tipo'),
            $this->req('s1', 'C', 'fase=3', 'salud', 'infra_salud'), // sector no marcado
        ];
        $seleccion = fn (int $fase) => $this->cfg($fase, [], [], ['minas'], ['minas::gas']);

        $this->assertSame(['g1'], $this->evaluador->aplicables($reqs, $seleccion(1)));
        $this->assertSame(['g1', 'g3'], $this->evaluador->aplicables($reqs, $seleccion(2)));
        $this->assertSame(['g1', 'g2'], $this->evaluador->aplicables($reqs, $seleccion(3)));
    }

    // ------------------------------------------------ paridad con el HTML

    public static function casosParidad(): array
    {
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../Fixtures/paridad_evaluador_sgr.json'), true);

        return collect($fixture['casos'])->mapWithKeys(fn ($c) => [$c['nombre'] => [$c['configuracion'], $c['esperado']]])->all();
    }

    #[DataProvider('casosParidad')]
    public function test_da_el_mismo_resultado_que_el_prototipo_original(array $config, array $esperado): void
    {
        $catalogo = json_decode(file_get_contents(__DIR__ . '/../../../database/seeders/data/requisitos_sgr_v1.json'), true);

        $cfg = new ConfiguracionExpediente(
            $config['fase_sgr'], $config['sectores'], $config['tipos'], $config['componentes'], $config['tipologias']
        );

        // El prototipo no filtra la sección C por fase; GestPro sí, por decisión del
        // usuario. La paridad se comprueba sobre lo demás, sin esa diferencia.
        $requisitos = array_map(
            fn (array $r) => $r['seccion'] === 'C' ? [...$r, 'condicion' => null] : $r,
            $catalogo['requisitos']
        );

        $this->assertSame($esperado, $this->evaluador->aplicables($requisitos, $cfg));
    }
}
