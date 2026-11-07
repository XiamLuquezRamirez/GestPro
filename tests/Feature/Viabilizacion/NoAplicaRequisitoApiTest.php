<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoAplicaRequisitoApiTest extends TestCase
{
    use RefreshDatabase;

    private ProyectoExpediente $expediente;
    private ProyectoRequisito $fila;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    private function usuario(Rol $rol): User
    {
        return User::factory()->create(['rol' => $rol]);
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : $this->usuario($quien);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(string $resto = ''): string
    {
        return "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$this->fila->id}{$resto}";
    }

    private function conAprobacion(): void
    {
        DB::table('requisitos_catalogo')->where('id', $this->fila->requisito_catalogo_id)->update(['requiere_aprobacion_no_aplica' => true]);
    }

    private function marcarComo(User $usuario)
    {
        return $this->como($usuario)->postJson($this->url('/no-aplica'), ['justificacion' => '  El proyecto no tiene predios.  ']);
    }

    private function eventos(): array
    {
        return DB::table('checklist_historial')->where('entidad_tipo', 'requisito')->where('entidad_id', $this->fila->id)->orderBy('id')->pluck('evento')->all();
    }

    // ------------------------------------------------------------ sin aprobación

    public function test_un_no_aplica_sin_aprobacion_deja_de_contar_de_inmediato(): void
    {
        $gestor = $this->usuario(Rol::Gestor);

        $this->marcarComo($gestor)->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.cuenta', false)
            ->assertJsonPath('requisito.no_aplica.justificacion', 'El proyecto no tiene predios.')
            ->assertJsonPath('requisito.no_aplica.usuario_id', $gestor->id)
            ->assertJsonPath('progreso.total', 7);

        $this->assertSame(['no_aplica_marcado'], $this->eventos());
    }

    public function test_un_no_aplica_exige_justificacion(): void
    {
        $this->como(Rol::Gestor)->postJson($this->url('/no-aplica'), ['justificacion' => '   '])
            ->assertStatus(422)->assertJsonPath('message', 'Explique por qué el requisito no aplica.');

        $this->assertSame('aplica', $this->fila->fresh()->estado_aplicabilidad);
    }

    public function test_un_requisito_cumplido_no_se_marca_como_no_aplica(): void
    {
        $this->fila->update(['cumplido' => true]);

        $this->marcarComo($this->usuario(Rol::Gestor))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito está cumplido: desmárquelo antes de indicar que no aplica.');
    }

    public function test_retirar_un_no_aplica_vuelve_a_contar(): void
    {
        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk();

        $this->como(Rol::Gestor)->deleteJson($this->url('/no-aplica'))->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('requisito.no_aplica', null)
            ->assertJsonPath('progreso.total', 8);

        $this->assertSame(['no_aplica_marcado', 'no_aplica_retirado'], $this->eventos());
    }

    // ------------------------------------------------------------ con aprobación

    public function test_con_aprobacion_queda_pendiente_y_sigue_contando(): void
    {
        $this->conAprobacion();

        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica_pendiente')
            ->assertJsonPath('requisito.cuenta', true)
            ->assertJsonPath('progreso.total', 8);
    }

    public function test_un_gestor_no_aprueba_lo_que_el_mismo_marco_y_otro_si(): void
    {
        $this->conAprobacion();
        $quienMarca = $this->usuario(Rol::Gestor);
        $revisor = $this->usuario(Rol::Gestor);
        $this->marcarComo($quienMarca)->assertOk();

        $this->como($quienMarca)->postJson($this->url('/no-aplica/aprobar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Otra persona debe revisar este "no aplica": usted lo marcó.');

        $this->como($revisor)->postJson($this->url('/no-aplica/aprobar'))->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('requisito.no_aplica.aprobado_por', $revisor->name)
            ->assertJsonPath('progreso.total', 7);
    }

    public function test_un_administrador_aprueba_lo_que_marco_y_queda_registrado(): void
    {
        $this->conAprobacion();
        $admin = $this->usuario(Rol::Administrador);
        $this->marcarComo($admin)->assertOk();

        $this->como($admin)->postJson($this->url('/no-aplica/aprobar'))->assertOk()->assertJsonPath('requisito.estado', 'no_aplica');

        $registro = DB::table('checklist_historial')->where('evento', 'no_aplica_aprobado')->first();
        $this->assertTrue(json_decode($registro->despues, true)['autoaprobado']);
    }

    public function test_rechazar_vuelve_a_aplica_con_el_motivo_en_el_historial(): void
    {
        $this->conAprobacion();
        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk();

        $this->como(Rol::Gestor)->postJson($this->url('/no-aplica/rechazar'), ['motivo' => 'Sí hay predios'])->assertOk()
            ->assertJsonPath('requisito.estado', 'pendiente');

        $registro = DB::table('checklist_historial')->where('evento', 'no_aplica_rechazado')->first();
        $this->assertSame('Sí hay predios', json_decode($registro->despues, true)['motivo']);
    }

    public function test_no_se_aprueba_lo_que_no_esta_por_aprobar(): void
    {
        $this->marcarComo($this->usuario(Rol::Gestor))->assertOk(); // sin aprobación: ya es no_aplica

        $this->como(Rol::Administrador)->postJson($this->url('/no-aplica/aprobar'))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Este requisito no tiene un "no aplica" por aprobar.');
    }

    // ---------------------------------------------------------------- límites

    public function test_consulta_no_puede_marcar_no_aplica(): void
    {
        $this->como(Rol::Consulta)->postJson($this->url('/no-aplica'), ['justificacion' => 'x'])->assertForbidden();
    }

    public function test_un_expediente_radicado_no_admite_no_aplica(): void
    {
        $this->expediente->update(['estado' => ProyectoExpediente::RADICADO]);

        $this->marcarComo($this->usuario(Rol::Gestor))->assertStatus(422);
    }

    // ----------------------------------------------------------- responsable

    public function test_asignar_y_quitar_el_responsable_queda_registrado(): void
    {
        $ana = DB::table('responsable')->insertGetId(['nombre' => 'Ana Pérez', 'cargo' => 'Ingeniera', 'activo' => true]);

        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $ana])->assertOk()
            ->assertJsonPath('requisito.responsable', ['id' => $ana, 'nombre' => 'Ana Pérez', 'cargo' => 'Ingeniera']);
        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => null])->assertOk()
            ->assertJsonPath('requisito.responsable', null);

        $registros = DB::table('checklist_historial')->where('evento', 'responsable_cambiado')->orderBy('id')->get();
        $this->assertSame(['responsable' => 'Ana Pérez'], json_decode($registros[0]->despues, true));
        $this->assertSame(['responsable' => 'Ana Pérez'], json_decode($registros[1]->antes, true));
    }

    public function test_no_se_asigna_un_responsable_inactivo_o_inexistente(): void
    {
        $inactivo = DB::table('responsable')->insertGetId(['nombre' => 'Luis', 'activo' => false]);

        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $inactivo])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El responsable "Luis" está inactivo.');
        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => 999])->assertStatus(422)
            ->assertJsonPath('mensaje', 'El responsable no existe.');
    }

    public function test_un_responsable_desactivado_se_conserva_en_la_fila_que_ya_lo_tiene(): void
    {
        $luis = DB::table('responsable')->insertGetId(['nombre' => 'Luis', 'activo' => true]);
        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $luis])->assertOk();
        DB::table('responsable')->where('id', $luis)->update(['activo' => false]);

        $this->como(Rol::Gestor)->patchJson($this->url(), ['responsable_id' => $luis, 'observacion' => 'Sigue'])->assertOk();
    }
}
