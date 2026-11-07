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

class SeguimientoRequisitoApiTest extends TestCase
{
    use RefreshDatabase;

    private ProyectoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);

        // Fase III sin sectores: 8 requisitos generales.
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    /** @return list<ProyectoRequisito> */
    private function filas(): array
    {
        return $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->get()->all();
    }

    private function url(ProyectoRequisito $fila, string $resto = ''): string
    {
        return "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$fila->id}{$resto}";
    }

    private function progreso(): array
    {
        return $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")->assertOk()->json('progreso');
    }

    private function cumplirTodas(): void
    {
        DB::table('proyectos_requisitos')->where('expediente_id', $this->expediente->id)->update(['cumplido' => true]);
    }

    // ---------------------------------------------------------------- avance

    public function test_un_expediente_sgr_conserva_sus_cifras(): void
    {
        $this->assertSame(['total' => 8, 'cumplidos' => 0, 'porcentaje' => 0], $this->progreso());
        $this->assertSame(8, $this->expediente->requisitos()->where('caracter', 'obligatorio')->count());
    }

    public function test_no_aplica_y_opcional_no_cuentan_y_lo_pendiente_si(): void
    {
        [$a, $b, $c, $d] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $b->update(['caracter' => 'opcional', 'cumplido' => true]);
        $c->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE, 'cumplido' => true]);
        $d->update(['estado_aplicabilidad' => ProyectoRequisito::POR_DEFINIR]);

        // De 8 quedan 6: c y d cuentan como pendientes aunque c tenga cumplido = true.
        $this->assertSame(['total' => 6, 'cumplidos' => 0, 'porcentaje' => 0], $this->progreso());
    }

    public function test_cada_fila_presenta_su_estado_y_si_cuenta(): void
    {
        [$a, $b, $c, $d] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $b->update(['caracter' => 'opcional', 'cumplido' => true]);
        $c->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE, 'cumplido' => true]);
        $d->update(['estado_aplicabilidad' => ProyectoRequisito::POR_DEFINIR]);

        $detalle = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")->assertOk();
        $filas = collect($detalle->json('requisitos'))->keyBy('id');

        $this->assertSame(['no_aplica', false], [$filas[$a->id]['estado'], $filas[$a->id]['cuenta']]);
        $this->assertSame(['cumplido', false], [$filas[$b->id]['estado'], $filas[$b->id]['cuenta']]);
        $this->assertSame(['no_aplica_pendiente', true], [$filas[$c->id]['estado'], $filas[$c->id]['cuenta']]);
        $this->assertSame(['por_definir', true], [$filas[$d->id]['estado'], $filas[$d->id]['cuenta']]);
        $this->assertNotNull($filas[$a->id]['no_aplica']);
        $this->assertSame($detalle->json('progreso.total'), $filas->where('cuenta', true)->count());
    }

    public function test_el_tablero_y_el_detalle_usan_la_misma_definicion(): void
    {
        [$a, $b] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $b->update(['cumplido' => true]);

        $resumen = collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())
            ->firstWhere('expediente_id', $this->expediente->id);

        $this->assertSame($this->progreso(), $resumen['progreso']);
        $this->assertSame(['total' => 7, 'cumplidos' => 1, 'porcentaje' => 14], $resumen['progreso']);
    }

    public function test_la_fila_nace_con_el_caracter_del_catalogo(): void
    {
        DB::table('requisitos_catalogo')->update(['caracter' => 'opcional']);
        $servicio = app(ExpedienteService::class);

        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->assertGreaterThan(0, $otro->requisitos()->count());
        $this->assertSame(0, $otro->requisitos()->where('caracter', '!=', 'opcional')->count());
    }

    // ------------------------------------------------------------ radicación

    public function test_no_se_radica_con_un_no_aplica_por_aprobar(): void
    {
        $this->cumplirTodas();
        $this->filas()[0]->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede radicar: falta 1 de 8 requisitos por cumplir.');
    }

    public function test_se_radica_con_lo_que_no_aplica_y_sin_cumplir_lo_opcional(): void
    {
        $this->cumplirTodas();
        [$a, $b] = $this->filas();
        $a->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA, 'cumplido' => false]);
        $b->update(['caracter' => 'opcional', 'cumplido' => false]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")
            ->assertOk()->assertJsonPath('estado', 'radicado');
    }

    public function test_no_se_radica_si_nada_cuenta(): void
    {
        DB::table('proyectos_requisitos')->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);

        $this->como(Rol::Gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")
            ->assertStatus(422)->assertJsonPath('mensaje', 'No se puede radicar: el expediente no tiene requisitos aplicables.');
    }

    // ---------------------------------------------------------------- filas

    public function test_no_se_cumple_un_requisito_que_no_aplica(): void
    {
        $fila = $this->filas()[0];
        $fila->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);

        $this->como(Rol::Gestor)->patchJson($this->url($fila), ['cumplido' => true])->assertStatus(422);

        $this->assertFalse($fila->fresh()->cumplido);
    }

    public function test_no_se_cumple_un_requisito_por_definir_ni_por_aprobar(): void
    {
        $fila = $this->filas()[0];
        foreach ([ProyectoRequisito::POR_DEFINIR, ProyectoRequisito::NO_APLICA_PENDIENTE] as $estado) {
            $fila->update(['estado_aplicabilidad' => $estado]);

            $this->como(Rol::Gestor)->patchJson($this->url($fila), ['cumplido' => true])->assertStatus(422);

            $this->assertFalse($fila->fresh()->cumplido, $estado);
        }
    }

    public function test_la_fila_presenta_su_estado(): void
    {
        $fila = $this->filas()[0];

        $respuesta = $this->como(Rol::Gestor)->patchJson($this->url($fila), ['cumplido' => true])->assertOk();

        $respuesta->assertJsonPath('requisito.estado', 'cumplido')
            ->assertJsonPath('requisito.caracter', 'obligatorio')
            ->assertJsonPath('requisito.cuenta', true)
            ->assertJsonPath('requisito.no_aplica', null)
            ->assertJsonPath('requisito.responsable', null)
            ->assertJsonPath('requisito.requisito.requiere_aprobacion_no_aplica', true)
            ->assertJsonPath('progreso.cumplidos', 1);
    }
}
