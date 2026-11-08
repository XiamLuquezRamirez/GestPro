<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Municipio;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpedienteApiTest extends TestCase
{
    use RefreshDatabase;

    /** Filas de la sección C que el catálogo excluye en cada fase. */
    private const C_FUERA_DE_FASE = [
        1 => ['mi2a', 'mi2c', 'mi2d', 'mi2e', 'mi2f', 'mi2g', 'mi2h', 'mi2i', 'vi4a', 'vi4b', 'vi7b', 'tr10b'],
        2 => ['mi2a', 'mi2c', 'mi2d', 'mi2e', 'mi2f', 'mi2g', 'mi2h', 'mi2i', 'vi4b'],
        3 => ['vi4a', 'vi7b', 'tr10b'],
    ];

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $this->proyecto = $this->proyectoSgr(['municipio' => $municipio->codigo, 'nombre' => 'Acueducto veredal']);
        Storage::fake('local');
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    private function como(Rol $rol): static
    {
        $usuario = User::factory()->create(['rol' => $rol]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function id(string $tabla, string $clave, ?string $sector = null): int
    {
        $q = DB::table($tabla)->where('clave', $clave);
        if ($sector) {
            $q->where('sector_id', $this->id('requisitos_sectores', $sector));
        }

        return (int) $q->value('id');
    }

    /** Traduce una configuración en claves (formato del fixture) a ids para la API. */
    private function configuracion(int $fase, array $sectores = [], array $tipos = [], array $componentes = [], array $tipologias = []): array
    {
        return [
            'fase_sgr' => $fase,
            'sector_ids' => array_map(fn ($s) => $this->id('requisitos_sectores', $s), $sectores),
            'tipo_ids' => array_map(function ($t) {
                [$sector, $clave] = explode('::', $t);

                return $this->id('requisitos_tipos', $clave, $sector);
            }, $tipos),
            'componente_ids' => array_map(fn ($c) => $this->id('requisitos_componentes', $c), $componentes),
            'tipologia_ids' => array_map(fn ($t) => $this->id('requisitos_tipologias', $t), $tipologias),
        ];
    }

    /** Crea y confirma la configuración: lo habitual en estos tests es ir a chequear. */
    private function crearEn(Proyecto $proyecto, array $config, Rol $rol = Rol::Gestor)
    {
        $respuesta = $this->como($rol)->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", $config);
        if ($respuesta->getStatusCode() === 201) {
            $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$respuesta->json('id')}/configuracion/confirmar", $config)->assertOk();
        }

        return $respuesta;
    }

    private function crear(array $config, Rol $rol = Rol::Gestor)
    {
        return $this->crearEn($this->proyecto, $config, $rol);
    }

    /** En un proyecto propio: cada proyecto admite un solo expediente en borrador. */
    private function crearAislado(array $config)
    {
        return $this->crearEn($this->otroProyecto('Proyecto aislado'), $config);
    }

    /** Deja el expediente radicado, para poder crear otro en el mismo proyecto. */
    private function radicar($respuesta): void
    {
        $this->cumplirTodo($respuesta);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$respuesta->json('id')}/radicar")->assertOk();
    }

    private function claves($respuesta): array
    {
        return collect($respuesta->json('requisitos'))->pluck('requisito.clave')->all();
    }

    private function filaDe($respuesta, string $clave): int
    {
        return collect($respuesta->json('requisitos'))->firstWhere('requisito.clave', $clave)['id'];
    }

    private function seccionC($respuesta): array
    {
        return collect($respuesta->json('requisitos'))->where('requisito.seccion', 'C')->pluck('requisito.clave')->values()->all();
    }

    /** Marca como cumplidos los requisitos indicados en la respuesta, menos los excluidos. */
    private function cumplirTodo($respuesta, array $salvo = []): void
    {
        $g = $this->como(Rol::Gestor);
        foreach ($respuesta->json('requisitos') as $fila) {
            if (! in_array($fila['requisito']['clave'], $salvo, true)) {
                $g->patchJson("/GestPro/expedientes/{$respuesta->json('id')}/requisitos/{$fila['id']}", ['cumplido' => true])->assertOk();
            }
        }
    }

    // ------------------------------------------------------------- creación

    public function test_crea_el_expediente_con_los_requisitos_que_aplican(): void
    {
        $r = $this->crear($this->configuracion(3));

        $r->assertCreated()
            ->assertJsonPath('estado', 'borrador')
            ->assertJsonPath('fase_sgr', 3)
            ->assertJsonPath('catalogo_version.numero', 1)
            ->assertJsonPath('progreso.total', 8)
            ->assertJsonPath('progreso.cumplidos', 0);
        $this->assertSame(['a1', 'a2', 'a3', 'a4', 'a8', 'a9', 'a10', 'a11'], $this->claves($r));
    }

    public function test_de_punta_a_punta_coincide_con_el_prototipo_para_cada_caso_del_fixture(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/paridad_evaluador_sgr.json')), true);

        foreach ($fixture['casos'] as $caso) {
            $c = $caso['configuracion'];
            $r = $this->crearAislado($this->configuracion($c['fase_sgr'], $c['sectores'], $c['tipos'], $c['componentes'], $c['tipologias']));
            $r->assertCreated();

            // El prototipo no filtra la sección C por fase; GestPro sí. Se descuenta
            // esa única diferencia, cubierta por los tests de filtro por fase.
            $esperado = array_values(array_diff($caso['esperado'], self::C_FUERA_DE_FASE[$c['fase_sgr']]));

            // Mismo conjunto y mismo orden: el prototipo pinta por sección A, B, D, C
            // y el catálogo ya viene en ese orden, igual que la API.
            $this->assertSame($esperado, $this->claves($r), $caso['nombre']);
        }
    }

    // ----------------------------------------------- sección C según la fase

    public function test_gas_en_fase_1_y_2_solo_pide_el_certificado_creg(): void
    {
        $gas = fn (int $fase) => $this->seccionC($this->crearAislado($this->configuracion($fase, ['minas'], ['minas::gas'])));
        $todos = ['mi2a', 'mi2b', 'mi2c', 'mi2d', 'mi2e', 'mi2f', 'mi2g', 'mi2h', 'mi2i'];

        $this->assertSame(['mi2b'], $gas(1));
        $this->assertSame(['mi2b'], $gas(2));
        $this->assertSame($todos, $gas(3));
    }

    public function test_operaciones_urbanas_cambia_de_requisito_segun_la_fase(): void
    {
        $tipo = fn (int $fase) => $this->seccionC($this->crearAislado($this->configuracion($fase, ['vivienda'], ['vivienda::operaciones_urbanas'])));

        $this->assertSame([], $tipo(1));
        $this->assertSame(['vi4a'], $tipo(2));
        $this->assertSame(['vi4b'], $tipo(3));
    }

    public function test_requisitos_de_prefactibilidad_solo_aplican_en_fase_2(): void
    {
        $claves = fn (int $fase, array $sectores, array $tipos) => $this->seccionC($this->crearAislado($this->configuracion($fase, $sectores, $tipos)));

        $this->assertContains('vi7b', $claves(2, ['vivienda'], ['vivienda::renovacion_mejoramiento']));
        $this->assertNotContains('vi7b', $claves(3, ['vivienda'], ['vivienda::renovacion_mejoramiento']));
        $this->assertContains('tr10b', $claves(2, ['transporte'], ['transporte::corredores_rurales']));
        $this->assertNotContains('tr10b', $claves(3, ['transporte'], ['transporte::corredores_rurales']));
    }

    public function test_requisitos_sin_fase_en_el_catalogo_aplican_en_todas(): void
    {
        foreach ([1, 2, 3] as $fase) {
            $this->assertContains('j1', $this->seccionC($this->crearAislado($this->configuracion($fase, ['justicia'], ['justicia::casas_justicia']))));
        }
    }

    public function test_cambiar_de_fase_retiene_lo_cumplido_en_la_seccion_c(): void
    {
        $r = $this->crear($this->configuracion(3, ['minas'], ['minas::gas']));
        $expediente = $r->json('id');
        $mi2a = $this->filaDe($r, 'mi2a');
        $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$mi2a}", ['cumplido' => true])->assertOk();

        $fase2 = $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(2, ['minas'], ['minas::gas']));
        $fase2->assertOk()->assertJsonPath('retenidos', 1);
        $this->assertSame(['mi2b'], $this->seccionC($fase2));

        $fase3 = $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(3, ['minas'], ['minas::gas']));
        $this->assertTrue(collect($fase3->json('requisitos'))->firstWhere('requisito.clave', 'mi2a')['cumplido']);
    }

    public function test_un_tipo_de_un_sector_no_seleccionado_es_rechazado(): void
    {
        $config = $this->configuracion(3, ['salud']);
        $config['tipo_ids'] = [$this->id('requisitos_tipos', 'casas_justicia', 'justicia')];

        $this->crear($config)->assertStatus(422)
            ->assertJsonPath('mensaje', 'La configuración del expediente no es válida.')
            ->assertJsonFragment(['errores' => ['El tipo "Casas de Justicia" pertenece a un sector que no está seleccionado.']]);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_un_id_inexistente_es_rechazado(): void
    {
        $this->crear(['fase_sgr' => 3, 'componente_ids' => [99999]])->assertStatus(422);
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    public function test_la_fase_es_obligatoria_y_solo_admite_1_2_o_3(): void
    {
        $this->crear(['fase_sgr' => 4])->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
        $this->crear([])->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
    }

    public function test_un_proyecto_admite_varios_expedientes_y_sugiere_su_sector(): void
    {
        $vivienda = $this->id('requisitos_sectores', 'vivienda');
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true, 'requisitos_sector_id' => $vivienda]);
        $this->proyecto->update(['sector' => $sector]);

        $this->radicar($this->crear($this->configuracion(3))->assertCreated());
        $this->crear($this->configuracion(2))->assertCreated();

        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/expedientes")
            ->assertOk()
            ->assertJsonCount(2, 'expedientes')
            ->assertJsonPath('sector_sugerido_id', $vivienda)
            ->assertJsonPath('expedientes.0.fase_sgr', 2); // más reciente primero
    }

    // ------------------------------------------------------ resumen consolidado

    private function otroProyecto(string $nombre): Proyecto
    {
        return $this->proyectoSgr(['municipio' => $this->proyecto->municipio, 'nombre' => $nombre]);
    }

    public function test_resumen_trae_el_expediente_mas_reciente_de_cada_proyecto(): void
    {
        $this->radicar($this->crear($this->configuracion(2))->assertCreated());
        $reciente = $this->crear($this->configuracion(3));
        $this->como(Rol::Gestor)->patchJson(
            "/GestPro/expedientes/{$reciente->json('id')}/requisitos/{$this->filaDe($reciente, 'a1')}",
            ['cumplido' => true]
        )->assertOk();

        $this->como(Rol::Consulta)->getJson('/GestPro/expedientes')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.proyecto_id', $this->proyecto->id)
            ->assertJsonPath('0.cantidad_expedientes', 2)
            ->assertJsonPath('0.expediente_id', $reciente->json('id'))
            ->assertJsonPath('0.estado', 'borrador')
            ->assertJsonPath('0.fase_sgr', 3)
            ->assertJsonPath('0.configurado', true)
            ->assertJsonPath('0.fecha_radicado', null)
            ->assertJsonPath('0.progreso', ['total' => 8, 'cumplidos' => 1, 'porcentaje' => 13]);
    }

    public function test_resumen_no_incluye_proyectos_sin_expediente(): void
    {
        $this->otroProyecto('Sin expediente');

        $this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->assertExactJson([]);
    }

    public function test_resumen_no_cuenta_lo_que_dejo_de_aplicar(): void
    {
        $r = $this->crear($this->configuracion(2, [], [], ['predios']));
        $expediente = $r->json('id');
        $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$this->filaDe($r, 'b1')}", ['cumplido' => true])->assertOk();
        $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(2))->assertOk();

        $progreso = $this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->json('0.progreso');

        $this->assertSame(0, $progreso['cumplidos']);
        $this->assertSame($this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente}")->json('progreso.total'), $progreso['total']);
    }

    public function test_resumen_refleja_la_radicacion(): void
    {
        $r = $this->crear($this->configuracion(3));
        $this->cumplirTodo($r);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$r->json('id')}/radicar")->assertOk();

        $fila = $this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->json('0');

        $this->assertSame('radicado', $fila['estado']);
        $this->assertNotNull($fila['fecha_radicado']);
        $this->assertSame(100, $fila['progreso']['porcentaje']);
    }

    public function test_resumen_no_hace_mas_consultas_con_mas_proyectos(): void
    {
        $this->crear($this->configuracion(3))->assertCreated();
        $medir = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson('/GestPro/expedientes')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $medir();

        foreach (['Dos', 'Tres', 'Cuatro'] as $nombre) {
            $proyecto = $this->otroProyecto($nombre);
            $this->como(Rol::Gestor)->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", $this->configuracion(3))->assertCreated();
        }

        $this->assertSame($conUno, $medir());
    }

    // ---------------------------------------------------- recálculo borrador

    public function test_reconfigurar_recalcula_y_conserva_el_cumplimiento_de_lo_que_sale(): void
    {
        $r = $this->crear($this->configuracion(2, [], [], ['predios']));
        $expediente = $r->json('id');
        $b1 = $this->filaDe($r, 'b1');
        $this->assertContains('b1', $this->claves($r));

        $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$b1}", [
            'cumplido' => true, 'observacion' => 'Justificación aprobada por jurídica',
        ])->assertOk();

        // Se quita el componente: b1 deja de aplicar, pero su trabajo se retiene.
        $sin = $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(2));
        $sin->assertOk()->assertJsonPath('retenidos', 1);
        $this->assertNotContains('b1', $this->claves($sin));

        // Vuelve el componente: b1 reaparece tal como estaba.
        $con = $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(2, [], [], ['predios']));
        $fila = collect($con->json('requisitos'))->firstWhere('requisito.clave', 'b1');
        $this->assertSame($b1, $fila['id']);
        $this->assertTrue($fila['cumplido']);
        $this->assertSame('Justificación aprobada por jurídica', $fila['observacion']);
        $con->assertJsonPath('retenidos', 0);
    }

    public function test_un_requisito_que_no_aplica_no_se_puede_marcar(): void
    {
        $r = $this->crear($this->configuracion(2, [], [], ['predios']));
        $expediente = $r->json('id');
        $b1 = $this->filaDe($r, 'b1');
        $this->como(Rol::Gestor)->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(2))->assertOk();

        $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$b1}", ['cumplido' => true])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------ cumplimiento

    public function test_marcar_registra_fecha_y_usuario_y_desmarcar_los_limpia(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');
        $a1 = $this->filaDe($r, 'a1');

        $marcado = $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$a1}", ['cumplido' => true]);
        $marcado->assertOk()
            ->assertJsonPath('requisito.cumplido', true)
            ->assertJsonPath('progreso.cumplidos', 1)
            ->assertJsonPath('progreso.porcentaje', 13); // 1 de 8
        $this->assertNotNull($marcado->json('requisito.fecha_cumplido'));
        $this->assertNotNull($marcado->json('requisito.usuario_cumplido'));

        $this->como(Rol::Gestor)->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$a1}", ['cumplido' => false])
            ->assertOk()
            ->assertJsonPath('requisito.cumplido', false)
            ->assertJsonPath('requisito.fecha_cumplido', null)
            ->assertJsonPath('requisito.usuario_cumplido', null);
    }

    public function test_un_requisito_de_otro_expediente_no_es_accesible(): void
    {
        $uno = $this->crear($this->configuracion(3));
        $otro = $this->crearAislado($this->configuracion(3));

        $this->como(Rol::Gestor)
            ->patchJson("/GestPro/expedientes/{$otro->json('id')}/requisitos/{$this->filaDe($uno, 'a1')}", ['cumplido' => true])
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- soportes

    public function test_soporte_se_guarda_en_disco_privado_se_descarga_y_se_reemplaza(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');
        $a1 = $this->filaDe($r, 'a1');
        $url = "/GestPro/expedientes/{$expediente}/requisitos/{$a1}/soporte";

        $this->como(Rol::Gestor)->post($url, ['archivo' => UploadedFile::fake()->create('presupuesto.pdf', 120, 'application/pdf')])
            ->assertOk()->assertJsonPath('requisito.soporte.nombre', 'presupuesto.pdf');
        $primera = DB::table('proyectos_requisitos')->where('id', $a1)->value('soporte_archivo');
        $this->assertStringStartsWith("expedientes/{$expediente}/", $primera);
        Storage::disk('local')->assertExists($primera);

        $this->como(Rol::Consulta)->get($url)->assertOk()->assertDownload('presupuesto.pdf');

        // Reemplazar conserva el archivo anterior: queda en el historial.
        $this->como(Rol::Gestor)->post($url, ['archivo' => UploadedFile::fake()->create('presupuesto_v2.pdf', 80, 'application/pdf')])->assertOk();
        Storage::disk('local')->assertExists($primera);

        $actual = DB::table('proyectos_requisitos')->where('id', $a1)->value('soporte_archivo');
        $this->como(Rol::Gestor)->deleteJson($url)->assertOk()->assertJsonPath('requisito.soporte', null);
        Storage::disk('local')->assertExists($actual);
        $this->como(Rol::Gestor)->get($url)->assertNotFound();
    }

    public function test_soporte_rechaza_tipos_de_archivo_no_permitidos(): void
    {
        $r = $this->crear($this->configuracion(3));

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$r->json('id')}/requisitos/{$this->filaDe($r, 'a1')}/soporte", [
            'archivo' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    /**
     * Archivo real en disco. UploadedFile::fake() deduce el tipo por el nombre, a
     * diferencia de una subida real, que lo deduce del contenido: con un falso estos
     * casos no reproducen lo que pasa en producción.
     */
    private function archivoReal(string $nombre, string $contenido): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'soporte');
        file_put_contents($ruta, $contenido);

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    public function test_soporte_rechaza_extension_peligrosa_aunque_el_contenido_parezca_pdf(): void
    {
        // mimes solo mira el contenido: sin validar también la extensión, este archivo
        // pasaría y se guardaría y descargaría como .exe.
        $r = $this->crear($this->configuracion(3));
        $url = "/GestPro/expedientes/{$r->json('id')}/requisitos/{$this->filaDe($r, 'a1')}/soporte";

        foreach (['virus.exe', 'factura.pdf.exe', 'pagina.php'] as $nombre) {
            $this->como(Rol::Gestor)->postJson($url, [
                'archivo' => $this->archivoReal($nombre, "%PDF-1.4\n%prueba\n"),
            ])->assertStatus(422)->assertJsonValidationErrors('archivo');
        }

        $this->assertNull(DB::table('proyectos_requisitos')->where('id', $this->filaDe($r, 'a1'))->value('soporte_archivo'));
    }

    public function test_soporte_rechaza_contenido_ajeno_con_extension_permitida(): void
    {
        $r = $this->crear($this->configuracion(3));

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$r->json('id')}/requisitos/{$this->filaDe($r, 'a1')}/soporte", [
            'archivo' => $this->archivoReal('factura.pdf', '<html><script>alert(1)</script></html>'),
        ])->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    // ------------------------------------------------------------- radicación

    public function test_radicado_el_checklist_queda_congelado(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');
        $a1 = $this->filaDe($r, 'a1');
        $this->cumplirTodo($r);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$expediente}/radicar")
            ->assertOk()->assertJsonPath('estado', 'radicado');
        $this->assertNotNull(DB::table('proyectos_expedientes')->where('id', $expediente)->value('fecha_radicado'));

        $g = $this->como(Rol::Gestor);
        $g->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$a1}", ['cumplido' => false])->assertStatus(422);
        $g->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(1))->assertStatus(422);
        $g->postJson("/GestPro/expedientes/{$expediente}/requisitos/{$a1}/soporte", [
            'archivo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
        $g->postJson("/GestPro/expedientes/{$expediente}/radicar")->assertStatus(422);
        $g->deleteJson("/GestPro/expedientes/{$expediente}")->assertStatus(422);

        $this->assertDatabaseHas('proyectos_expedientes', ['id' => $expediente, 'fase_sgr' => 3]);
        $this->assertDatabaseHas('proyectos_requisitos', ['id' => $a1, 'cumplido' => true]);
    }

    public function test_no_se_puede_radicar_con_requisitos_pendientes(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$expediente}/radicar")
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'No se puede radicar: faltan 8 de 8 requisitos por cumplir.');

        // Un solo pendiente también lo impide.
        $this->cumplirTodo($r, ['a11']);
        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$expediente}/radicar")
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'No se puede radicar: falta 1 de 8 requisitos por cumplir.');

        $this->assertDatabaseHas('proyectos_expedientes', ['id' => $expediente, 'estado' => 'borrador', 'fecha_radicado' => null]);
    }

    public function test_los_requisitos_que_dejaron_de_aplicar_no_cuentan_como_pendientes(): void
    {
        $r = $this->crear($this->configuracion(2, [], [], ['predios']));
        $expediente = $r->json('id');
        $predios = array_values(array_filter($this->claves($r), fn ($c) => str_starts_with($c, 'b')));
        $this->assertNotEmpty($predios);
        $this->cumplirTodo($r, $predios);

        $g = $this->como(Rol::Gestor);
        $g->postJson("/GestPro/expedientes/{$expediente}/radicar")->assertStatus(422);

        // Sin el componente, sus requisitos ya no aplican y el expediente queda completo.
        $g->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(2))->assertOk();
        $g->postJson("/GestPro/expedientes/{$expediente}/radicar")->assertOk()->assertJsonPath('estado', 'radicado');
    }

    public function test_no_se_puede_radicar_un_expediente_sin_requisitos(): void
    {
        $r = $this->crear($this->configuracion(3));
        DB::table('proyectos_requisitos')->where('expediente_id', $r->json('id'))->update(['aplica' => false]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$r->json('id')}/radicar")
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'No se puede radicar: el expediente no tiene requisitos aplicables.');
    }

    public function test_eliminar_un_borrador_borra_su_checklist_y_sus_soportes(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');
        $a1 = $this->filaDe($r, 'a1');
        $this->como(Rol::Gestor)->post("/GestPro/expedientes/{$expediente}/requisitos/{$a1}/soporte", [
            'archivo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertOk();
        $ruta = DB::table('proyectos_requisitos')->where('id', $a1)->value('soporte_archivo');

        $this->como(Rol::Gestor)->deleteJson("/GestPro/expedientes/{$expediente}")->assertOk();

        $this->assertDatabaseCount('proyectos_expedientes', 0);
        $this->assertDatabaseCount('proyectos_requisitos', 0);
        Storage::disk('local')->assertMissing($ruta);
    }

    // ---------------------------------------------------------------- permisos

    public function test_consulta_puede_leer_pero_no_escribir(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');
        $c = $this->como(Rol::Consulta);

        $c->getJson("/GestPro/expedientes/{$expediente}")->assertOk()->assertJsonPath('progreso.total', 8);
        $c->postJson("/GestPro/proyectos/{$this->proyecto->id}/expedientes", $this->configuracion(3))->assertForbidden();
        $c->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$this->filaDe($r, 'a1')}", ['cumplido' => true])->assertForbidden();
        $c->postJson("/GestPro/expedientes/{$expediente}/radicar")->assertForbidden();
    }

    public function test_sin_token_no_hay_acceso(): void
    {
        // Se crea sin iniciar sesión: auth('api')->login() deja el token en el
        // singleton de JWT y contaminaría las peticiones siguientes del test.
        $expediente = app(\App\Services\Viabilizacion\ExpedienteService::class)
            ->crear($this->proyecto, $this->configuracion(3), null);

        $this->getJson("/GestPro/expedientes/{$expediente->id}")->assertUnauthorized();
        $this->getJson("/GestPro/proyectos/{$this->proyecto->id}/expedientes")->assertUnauthorized();
        $this->postJson("/GestPro/expedientes/{$expediente->id}/radicar")->assertUnauthorized();
        $this->getJson('/GestPro/catalogo-requisitos/vigente')->assertUnauthorized();
        $this->getJson('/GestPro/expedientes')->assertUnauthorized();
    }
}
