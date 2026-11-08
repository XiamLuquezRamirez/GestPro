<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "No aplica", "por definir" y aprobación de una fila según sus orígenes. */
class NoAplicaOrigenesApiTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);
    }

    /** @param array<string, array{0: ?string, 1?: string, 2?: bool}> $requisitos texto => [codigo_comun, caracter, requiere_aprobacion] */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $r) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $r[0], 'caracter' => $r[1] ?? RequisitoCatalogo::OBLIGATORIO,
                'requiere_aprobacion_no_aplica' => $r[2] ?? false,
            ]);
        }

        return $plantilla;
    }

    /** Expediente confirmado con las plantillas dadas; la primera es la principal. */
    private function expediente(PlantillaChecklist ...$plantillas): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', 'recursos_propios')->value('id')]);
        $seleccion = ['plantilla_ids' => array_map(fn ($p) => $p->id, $plantillas), 'principal_id' => $plantillas[0]->id];
        $expediente = $this->servicio->crear($proyecto, $seleccion, null);

        return $this->servicio->confirmarConfiguracion($expediente, $seleccion);
    }

    private function fila(ProyectoExpediente $expediente, string $texto): ProyectoRequisito
    {
        return $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->sole();
    }

    private function propio(ProyectoExpediente $expediente): ProyectoRequisito
    {
        return $expediente->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000,
            'titulo_propio' => 'Acta de socialización',
        ]);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(ProyectoRequisito $fila, string $resto): string
    {
        return "/GestPro/expedientes/{$fila->expediente_id}/requisitos/{$fila->id}{$resto}";
    }

    private function marcar(ProyectoRequisito $fila)
    {
        return $this->como()->postJson($this->url($fila, '/no-aplica'), ['justificacion' => 'No hay predios.']);
    }

    private function decidirAplica(ProyectoRequisito $fila, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson($this->url($fila, '/aplica'));
    }

    private function historial(ProyectoRequisito $fila): array
    {
        return DB::table('checklist_historial')->where('entidad_tipo', 'requisito')->where('entidad_id', $fila->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true)['estado'] ?? null, json_decode((string) $h->despues, true)['estado'] ?? null])
            ->all();
    }

    // ------------------------------------------------------------ aprobación

    public function test_la_fila_exige_aprobacion_si_algun_origen_la_exige(): void
    {
        // El ancla (de la principal) no la exige; el otro origen sí.
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => ['planos']]);
        $vias = $this->plantilla('Vías', ['Planos de la obra' => ['planos', 'obligatorio', true]]);
        $fila = $this->fila($this->expediente($agua, $vias), 'Planos firmados');

        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$fila->expediente_id}")->assertOk()
            ->assertJsonPath('requisitos.0.requiere_aprobacion_no_aplica', true);

        $this->marcar($fila)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica_pendiente')
            ->assertJsonPath('requisito.requiere_aprobacion_no_aplica', true);
    }

    public function test_sin_ningun_origen_que_la_exija_el_no_aplica_vale_de_inmediato(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => ['planos']]);
        $vias = $this->plantilla('Vías', ['Planos de la obra' => ['planos']]);
        $fila = $this->fila($this->expediente($agua, $vias), 'Planos firmados');

        $this->marcar($fila)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.requiere_aprobacion_no_aplica', false)
            ->assertJsonPath('progreso.total', 0);
    }

    public function test_si_el_origen_que_la_exigia_sale_del_expediente_ya_no_se_exige(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => ['planos']]);
        $vias = $this->plantilla('Vías', ['Planos de la obra' => ['planos', 'obligatorio', true]]);
        $expediente = $this->expediente($agua, $vias);
        $this->servicio->actualizarConfiguracion($expediente, ['plantilla_ids' => [$agua->id]]);

        $this->marcar($this->fila($expediente, 'Planos firmados'))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.origenes', [['plantilla_id' => $agua->id, 'nombre' => 'Obras de agua']]);
    }

    public function test_una_fila_sin_origenes_muestra_la_aprobacion_de_su_ancla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null, 'obligatorio', true]]);
        $vias = $this->plantilla('Vías', ['Licencia' => [null]]);
        $expediente = $this->expediente($vias, $agua);
        $this->servicio->actualizarConfiguracion($expediente, ['plantilla_ids' => [$vias->id]]);
        $retirada = $expediente->requisitos()->where('aplica', false)->sole();

        $this->assertTrue($retirada->requiereAprobacionNoAplica());
        $this->assertTrue($this->servicio->presentarFila($retirada)['requiere_aprobacion_no_aplica']);
    }

    public function test_en_el_sgr_cada_fila_sigue_pidiendo_aprobacion(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        $expediente = $this->servicio->crear($proyecto, ['fase_sgr' => 3], null);

        $filas = $this->servicio->presentar($expediente)['requisitos'];

        $this->assertCount(8, $filas);
        $this->assertSame([true], collect($filas)->pluck('requiere_aprobacion_no_aplica')->unique()->values()->all());
    }

    // ------------------------------------------------------------ por definir

    public function test_aplica_decide_una_fila_por_definir_y_queda_en_el_historial(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $fila->estado_aplicabilidad);

        $this->decidirAplica($fila)->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('requisito.no_aplica', null)
            ->assertJsonPath('progreso.total', 1);

        $this->assertSame([['aplicabilidad_decidida', 'por_definir', 'aplica']], $this->historial($fila));
    }

    public function test_aplica_solo_vale_en_una_fila_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null], 'Licencia' => [null]]);
        $expediente = $this->expediente($agua);
        $marcada = $this->fila($expediente, 'Licencia');
        $this->marcar($marcada)->assertOk();

        foreach ([$this->fila($expediente, 'Planos firmados'), $marcada, $this->propio($expediente)] as $fila) {
            $this->decidirAplica($fila)->assertStatus(422)->assertJsonPath('mensaje', 'Este requisito no está por definir.');
        }
        $this->assertSame(ProyectoRequisito::NO_APLICA, $marcada->fresh()->estado_aplicabilidad);
    }

    public function test_aplica_exige_permiso_un_borrador_configurado_y_una_fila_del_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $expediente = $this->expediente($agua);
        $fila = $this->fila($expediente, 'Permiso de vertimientos');
        $otra = $this->fila($this->expediente($agua), 'Permiso de vertimientos');

        $this->decidirAplica($fila, Rol::Consulta)->assertForbidden();
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$otra->id}/aplica")->assertNotFound();

        $expediente->update(['estado' => ProyectoExpediente::RADICADO]);
        $this->decidirAplica($fila)->assertStatus(422)
            ->assertJsonPath('mensaje', 'El expediente está radicado: su checklist está congelado y no admite cambios.');

        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $fila->fresh()->estado_aplicabilidad);
    }

    // ------------------------------------------------- volver al estado anterior

    public function test_retirar_un_no_aplica_devuelve_la_fila_a_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->marcar($fila)->assertOk()->assertJsonPath('requisito.estado', 'no_aplica');

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'por_definir')
            ->assertJsonPath('requisito.no_aplica.justificacion', null);

        $this->assertSame(
            [['no_aplica_marcado', 'por_definir', 'no_aplica'], ['no_aplica_retirado', 'no_aplica', 'por_definir']],
            $this->historial($fila)
        );
    }

    public function test_rechazar_un_no_aplica_devuelve_la_fila_a_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado', true]]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->marcar($fila)->assertOk()->assertJsonPath('requisito.estado', 'no_aplica_pendiente');

        $this->como()->postJson($this->url($fila, '/no-aplica/rechazar'), ['motivo' => 'Sí hay vertimientos'])->assertOk()
            ->assertJsonPath('requisito.estado', 'por_definir');

        $guardada = $fila->fresh();
        $this->assertNull($guardada->justificacion_no_aplica);
        $this->assertNull($guardada->usuario_no_aplica_id);
    }

    public function test_un_no_aplica_aprobado_y_retirado_vuelve_a_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado', true]]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $this->marcar($fila)->assertOk();
        $this->como(Rol::Administrador)->postJson($this->url($fila, '/no-aplica/aprobar'))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica');

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'por_definir');
        $this->assertNull($fila->fresh()->usuario_aprobo_id);
    }

    public function test_cuenta_el_ultimo_no_aplica_marcado(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');

        // Por definir → no aplica → por definir → aplica → no aplica → aplica.
        $this->marcar($fila)->assertOk();
        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()->assertJsonPath('requisito.estado', 'por_definir');
        $this->decidirAplica($fila)->assertOk();
        $this->marcar($fila)->assertOk();

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()->assertJsonPath('requisito.estado', 'pendiente');
    }

    public function test_sin_registro_del_no_aplica_la_fila_vuelve_a_aplica(): void
    {
        // Datos anteriores al historial: la fila quedó marcada sin evento no_aplica_marcado.
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado']]);
        $fila = $this->fila($this->expediente($agua), 'Permiso de vertimientos');
        $fila->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA, 'justificacion_no_aplica' => 'No hay vertimientos.']);

        $this->como()->deleteJson($this->url($fila, '/no-aplica'))->assertOk()->assertJsonPath('requisito.estado', 'pendiente');
    }

    // ---------------------------------------------------------------- propios

    public function test_un_requisito_propio_no_exige_aprobacion_y_vuelve_a_aplica(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null, 'obligatorio', true]]);
        $propio = $this->propio($this->expediente($agua));

        $this->assertFalse($propio->requiereAprobacionNoAplica());
        $this->marcar($propio)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.requiere_aprobacion_no_aplica', false)
            ->assertJsonPath('requisito.requisito', null)
            ->assertJsonPath('requisito.propio.titulo', 'Acta de socialización')
            ->assertJsonPath('progreso.total', 1);

        $this->como()->deleteJson($this->url($propio, '/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('progreso.total', 2);
    }
}
