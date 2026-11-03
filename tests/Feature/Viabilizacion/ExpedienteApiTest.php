<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Municipio;
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

    private Proyecto $proyecto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $this->proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Acueducto veredal']);
        Storage::fake('local');
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

    private function crear(array $config, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson("/GestPro/proyectos/{$this->proyecto->id}/expedientes", $config);
    }

    private function claves($respuesta): array
    {
        return collect($respuesta->json('requisitos'))->pluck('requisito.clave')->all();
    }

    private function filaDe($respuesta, string $clave): int
    {
        return collect($respuesta->json('requisitos'))->firstWhere('requisito.clave', $clave)['id'];
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
            $r = $this->crear($this->configuracion($c['fase_sgr'], $c['sectores'], $c['tipos'], $c['componentes'], $c['tipologias']));
            $r->assertCreated();

            // Mismo conjunto y mismo orden: el prototipo pinta por sección A, B, D, C
            // y el catálogo ya viene en ese orden, igual que la API.
            $this->assertSame($caso['esperado'], $this->claves($r), $caso['nombre']);
        }
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

        $this->crear($this->configuracion(3))->assertCreated();
        $this->crear($this->configuracion(2))->assertCreated();

        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/expedientes")
            ->assertOk()
            ->assertJsonCount(2, 'expedientes')
            ->assertJsonPath('sector_sugerido_id', $vivienda)
            ->assertJsonPath('expedientes.0.fase_sgr', 2); // más reciente primero
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
        $otro = $this->crear($this->configuracion(3));

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

        // Reemplazar borra el archivo anterior.
        $this->como(Rol::Gestor)->post($url, ['archivo' => UploadedFile::fake()->create('presupuesto_v2.pdf', 80, 'application/pdf')])->assertOk();
        Storage::disk('local')->assertMissing($primera);

        $actual = DB::table('proyectos_requisitos')->where('id', $a1)->value('soporte_archivo');
        $this->como(Rol::Gestor)->deleteJson($url)->assertOk()->assertJsonPath('requisito.soporte', null);
        Storage::disk('local')->assertMissing($actual);
        $this->como(Rol::Gestor)->get($url)->assertNotFound();
    }

    public function test_soporte_rechaza_tipos_de_archivo_no_permitidos(): void
    {
        $r = $this->crear($this->configuracion(3));

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$r->json('id')}/requisitos/{$this->filaDe($r, 'a1')}/soporte", [
            'archivo' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    // ------------------------------------------------------------- radicación

    public function test_radicado_el_checklist_queda_congelado(): void
    {
        $r = $this->crear($this->configuracion(3));
        $expediente = $r->json('id');
        $a1 = $this->filaDe($r, 'a1');

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$expediente}/radicar")
            ->assertOk()->assertJsonPath('estado', 'radicado');
        $this->assertNotNull(DB::table('proyectos_expedientes')->where('id', $expediente)->value('fecha_radicado'));

        $g = $this->como(Rol::Gestor);
        $g->patchJson("/GestPro/expedientes/{$expediente}/requisitos/{$a1}", ['cumplido' => true])->assertStatus(422);
        $g->putJson("/GestPro/expedientes/{$expediente}/configuracion", $this->configuracion(1))->assertStatus(422);
        $g->postJson("/GestPro/expedientes/{$expediente}/requisitos/{$a1}/soporte", [
            'archivo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);
        $g->postJson("/GestPro/expedientes/{$expediente}/radicar")->assertStatus(422);
        $g->deleteJson("/GestPro/expedientes/{$expediente}")->assertStatus(422);

        $this->assertDatabaseHas('proyectos_expedientes', ['id' => $expediente, 'fase_sgr' => 3]);
        $this->assertDatabaseHas('proyectos_requisitos', ['id' => $a1, 'cumplido' => false]);
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
    }
}
