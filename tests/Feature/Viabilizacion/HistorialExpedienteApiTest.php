<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\ChecklistHistorial;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Historial de todo el expediente: sus eventos y los de sus filas, por páginas. */
class HistorialExpedienteApiTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;
    private ProyectoExpediente $expediente;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        Storage::fake('local');

        $this->servicio = app(ExpedienteService::class);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora Uno']);
        $this->expediente = $this->expedienteSgr('Acueducto');
    }

    private function expedienteSgr(string $nombre): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => $nombre]);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = $this->servicio->crear($proyecto, ['fase_sgr' => 3], $this->gestor);
        $this->servicio->confirmarConfiguracion($expediente, ['fase_sgr' => 3], $this->gestor);

        return $expediente;
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(?ProyectoExpediente $expediente = null): string
    {
        return '/GestPro/expedientes/' . ($expediente ?? $this->expediente)->id;
    }

    private function historial(array $consulta = [], ?ProyectoExpediente $expediente = null): array
    {
        $url = $this->url($expediente) . '/historial' . ($consulta ? '?' . http_build_query($consulta) : '');

        return $this->como(Rol::Consulta)->getJson($url)->assertOk()->json();
    }

    /** @return list<ProyectoRequisito> filas que aplican, en su orden */
    private function filas(int $cuantas): array
    {
        return $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->take($cuantas)->get()->all();
    }

    private function agregarPropio(string $titulo): ProyectoRequisito
    {
        $id = $this->como($this->gestor)->postJson($this->url() . '/requisitos-propios', ['titulo' => $titulo])
            ->assertCreated()->json('requisito.id');

        return ProyectoRequisito::findOrFail($id);
    }

    /** Entradas de relleno en el historial de una fila. */
    private function registrar(ProyectoRequisito $fila, int $veces): void
    {
        $historial = app(HistorialChecklist::class);
        for ($i = 1; $i <= $veces; $i++) {
            $historial->registrarFila($fila, 'observacion_cambiada', ['observacion' => null], ['observacion' => "Nota {$i}"], $this->gestor);
        }
    }

    public function test_reune_los_eventos_del_expediente_y_de_sus_filas_del_mas_reciente_al_mas_antiguo(): void
    {
        [$primera, $segunda] = $this->filas(2);
        $this->como($this->gestor)->patchJson("{$this->url()}/requisitos/{$primera->id}", ['cumplido' => true])->assertOk();
        $this->como($this->gestor)->patchJson("{$this->url()}/requisitos/{$segunda->id}", ['observacion' => 'Falta firma'])->assertOk();
        DB::table('proyectos_requisitos')->where('expediente_id', $this->expediente->id)->update(['cumplido' => true]);
        $this->como($this->gestor)->postJson("{$this->url()}/radicar")->assertOk();

        $respuesta = $this->historial();
        $entradas = $respuesta['entradas'];

        $this->assertSame(
            ['expediente_radicado', 'observacion_cambiada', 'cumplido_marcado', 'configuracion_confirmada', 'expediente_creado'],
            array_column($entradas, 'evento')
        );
        $this->assertSame(['expediente', 'requisito', 'requisito', 'expediente', 'expediente'], array_column($entradas, 'entidad'));
        $this->assertSame(array_values(array_reverse(array_column($entradas, 'id'))), DB::table('checklist_historial')->orderBy('id')->pluck('id')->all());
        $this->assertSame([$segunda->id, $primera->id], [$entradas[1]['requisito']['id'], $entradas[2]['requisito']['id']]);
        $this->assertSame(['id', 'evento', 'antes', 'despues', 'usuario', 'sistema', 'fecha', 'soporte', 'entidad', 'requisito'], array_keys($entradas[1]));
        $this->assertNull($entradas[0]['requisito']);
        $this->assertSame('Gestora Uno', $entradas[1]['usuario']);
        $this->assertFalse($entradas[1]['sistema']);
        $this->assertSame(['observacion' => 'Falta firma'], $entradas[1]['despues']);
        $this->assertNotNull($entradas[1]['fecha']);
        $this->assertSame(['pagina' => 1, 'por_pagina' => 50, 'total' => 5], array_diff_key($respuesta, ['entradas' => true]));
    }

    public function test_pagina_de_a_cincuenta(): void
    {
        $this->registrar($this->filas(1)[0], 58); // con las 2 del expediente, 60
        $todas = DB::table('checklist_historial')->orderByDesc('id')->pluck('id')->all();

        $primera = $this->historial();
        $segunda = $this->historial(['pagina' => 2]);
        $fuera = $this->historial(['pagina' => 3]);

        $this->assertCount(50, $primera['entradas']);
        $this->assertSame([2, 50, 60], [$segunda['pagina'], $segunda['por_pagina'], $segunda['total']]);
        $this->assertSame($todas, [...array_column($primera['entradas'], 'id'), ...array_column($segunda['entradas'], 'id')]);
        $this->assertSame(['entradas' => [], 'pagina' => 3, 'por_pagina' => 50, 'total' => 60], $fuera);
        $this->assertSame([], $this->historial(['pagina' => 999999999])['entradas']);
    }

    public function test_distingue_lo_que_registro_el_sistema_de_lo_que_registro_un_usuario_eliminado(): void
    {
        // Sin usuario, como lo crea expedientes:crear-faltantes.
        $proyecto = Proyecto::create(['nombre' => 'Vía terciaria']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = $this->servicio->crear($proyecto, ['fase_sgr' => 3], null);
        $retirado = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestor Retirado']);
        $fila = $expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
        app(HistorialChecklist::class)->registrarFila($fila, 'observacion_cambiada', ['observacion' => null], ['observacion' => 'Revisado'], $retirado);
        $retirado->delete();

        $entradas = collect($this->historial([], $expediente)['entradas'])->keyBy('evento');

        $this->assertNull($entradas['expediente_creado']['usuario']);
        $this->assertTrue($entradas['expediente_creado']['sistema']);
        $this->assertNull($entradas['observacion_cambiada']['usuario']);
        $this->assertFalse($entradas['observacion_cambiada']['sistema']);
        // El historial de la fila se presenta igual.
        $deFila = $this->como(Rol::Consulta)->getJson("{$this->url($expediente)}/requisitos/{$fila->id}/historial")->assertOk()->json();
        $this->assertSame([null, false], [$deFila[0]['usuario'], $deFila[0]['sistema']]);
    }

    public function test_una_fila_de_plantilla_trae_su_codigo_y_su_texto(): void
    {
        $fila = $this->filas(1)[0];
        $this->registrar($fila, 1);
        $mostrada = collect($this->como(Rol::Consulta)->getJson($this->url())->json('requisitos'))->firstWhere('id', $fila->id);

        $this->assertSame([
            'id' => $fila->id,
            'codigo' => $mostrada['requisito']['codigo'],
            'texto' => $mostrada['requisito']['texto'],
            'titulo' => null,
        ], $this->historial()['entradas'][0]['requisito']);
        $this->assertNotNull($mostrada['requisito']['codigo']);
    }

    public function test_una_fila_compartida_muestra_el_requisito_de_la_plantilla_principal(): void
    {
        $agua = PlantillaChecklist::create([
            'nombre' => 'Obras de agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $agua->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create([
            'catalogo_version_id' => $version->id, 'clave' => 'r1', 'texto' => 'Presupuesto detallado de agua', 'orden' => 1,
            'codigo_comun' => 'a1', 'caracter' => RequisitoCatalogo::OBLIGATORIO, 'codigo' => 'AG-1',
        ]);
        $sgr = PlantillaChecklist::sgr();
        $this->servicio->actualizarConfiguracion($this->expediente, [
            'fase_sgr' => 3, 'plantilla_ids' => [$sgr->id, $agua->id], 'principal_id' => $agua->id,
        ], $this->gestor);
        $a1 = RequisitoCatalogo::where('catalogo_version_id', $sgr->versionVigente()->id)->where('clave', 'a1')->sole();
        $compartida = $this->expediente->requisitos()->where('requisito_catalogo_id', $a1->id)->sole();
        $this->registrar($compartida, 1);

        $entrada = collect($this->historial()['entradas'])->firstWhere('requisito.id', $compartida->id);

        $this->assertSame(['id' => $compartida->id, 'codigo' => 'AG-1', 'texto' => 'Presupuesto detallado de agua', 'titulo' => null], $entrada['requisito']);
    }

    public function test_un_propio_se_resume_por_su_titulo_y_uno_eliminado_llega_sin_requisito(): void
    {
        $vigente = $this->agregarPropio('Acta de socialización');
        $eliminado = $this->agregarPropio('Permiso de vertimientos');
        $this->como($this->gestor)->deleteJson("{$this->url()}/requisitos-propios/{$eliminado->id}")->assertOk();

        $entradas = collect($this->historial()['entradas']);

        $this->assertSame(
            ['requisito_propio_eliminado', 'requisito_propio_agregado', 'requisito_propio_agregado'],
            $entradas->take(3)->pluck('evento')->all()
        );
        [$baja, $altaEliminado, $altaVigente] = $entradas->take(3)->all();
        $this->assertSame(
            ['id' => $vigente->id, 'codigo' => null, 'texto' => null, 'titulo' => 'Acta de socialización'],
            $altaVigente['requisito']
        );
        $this->assertSame('requisito', $baja['entidad']);
        $this->assertNull($baja['requisito']);
        $this->assertSame('Permiso de vertimientos', $baja['antes']['titulo']);
        $this->assertNull($altaEliminado['requisito']);
        $this->assertSame('Permiso de vertimientos', $altaEliminado['despues']['titulo']);
    }

    public function test_no_expone_la_ruta_de_los_soportes(): void
    {
        $fila = $this->filas(1)[0];
        foreach (['v1.pdf', 'v2.pdf'] as $nombre) {
            $this->como($this->gestor)->post("{$this->url()}/requisitos/{$fila->id}/soporte", [
                'archivo' => UploadedFile::fake()->create($nombre, 20, 'application/pdf'),
            ])->assertOk();
        }
        $rutas = DB::table('checklist_historial')->where('evento', 'soporte_reemplazado')->pluck('antes')
            ->map(fn ($antes) => json_decode($antes, true)['soporte_archivo']);
        $this->assertCount(1, $rutas);

        $respuesta = $this->como(Rol::Consulta)->getJson("{$this->url()}/historial")->assertOk();

        $this->assertStringNotContainsString('soporte_archivo', $respuesta->getContent());
        $this->assertStringNotContainsString(basename($rutas[0]), $respuesta->getContent());
        $this->assertStringNotContainsString(basename($fila->fresh()->soporte_archivo), $respuesta->getContent());
        $reemplazo = collect($respuesta->json('entradas'))->firstWhere('evento', 'soporte_reemplazado');
        $this->assertSame(['nombre' => 'v1.pdf'], $reemplazo['soporte']);
        $this->como(Rol::Consulta)->get("{$this->url()}/historial/{$reemplazo['id']}/soporte")->assertOk()->assertDownload('v1.pdf');
    }

    public function test_lo_consulta_cualquier_usuario_autenticado(): void
    {
        $this->getJson("{$this->url()}/historial")->assertUnauthorized();
        $this->como(Rol::Consulta)->getJson("{$this->url()}/historial")->assertOk()->assertJsonPath('total', 2);
        $this->como(Rol::Consulta)->getJson('/GestPro/expedientes/999999/historial')->assertNotFound();
    }

    public function test_una_pagina_invalida_se_rechaza_en_espanol(): void
    {
        $casos = [
            'pagina=0' => 'Revisa la página: debe ser 1 o mayor.',
            'pagina=-1' => 'Revisa la página: debe ser 1 o mayor.',
            'pagina=dos' => 'Revisa la página: debe ser un número entero.',
            'pagina=1.5' => 'Revisa la página: debe ser un número entero.',
            'pagina=' => 'Revisa la página: debe ser un número entero.',
            'pagina[]=1' => 'Revisa la página: debe ser un número entero.',
            'pagina=99999999999999999999' => 'Revisa la página: debe ser un número entero.',
        ];
        foreach ($casos as $consulta => $mensaje) {
            $this->como(Rol::Consulta)->getJson("{$this->url()}/historial?{$consulta}")
                ->assertStatus(422)
                ->assertJsonValidationErrors(['pagina' => $mensaje]);
        }
    }

    public function test_no_mezcla_el_historial_de_otro_expediente(): void
    {
        $otro = $this->expedienteSgr('Vía terciaria');
        $filaOtro = $otro->requisitos()->where('aplica', true)->orderBy('orden')->first();
        $this->registrar($filaOtro, 3);

        $propias = DB::table('checklist_historial')->where('expediente_id', $this->expediente->id)->orderByDesc('id')->pluck('id')->all();
        $this->assertSame($propias, array_column($this->historial()['entradas'], 'id'));
        $this->assertSame(2, $this->historial()['total']);
        $this->assertSame(5, $this->historial([], $otro)['total']);
    }

    public function test_las_consultas_no_crecen_con_las_entradas_ni_con_las_filas(): void
    {
        $medir = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson("{$this->url()}/historial")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        [$primera, $segunda, $tercera, $cuarta] = $this->filas(4);
        $propio = $this->agregarPropio('Acta de socialización');
        $this->registrar($primera, 1);
        $conPocas = $medir();

        foreach ([$segunda, $tercera, $cuarta, $propio] as $fila) {
            $this->registrar($fila, 10);
        }
        $eliminado = $this->agregarPropio('Permiso de vertimientos');
        $this->como($this->gestor)->deleteJson("{$this->url()}/requisitos-propios/{$eliminado->id}")->assertOk();

        $this->assertSame($conPocas, $medir());
    }
}
