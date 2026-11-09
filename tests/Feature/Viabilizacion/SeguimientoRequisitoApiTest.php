<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
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
        $this->expediente = $servicio->crear($this->proyectoSgr(['nombre' => 'Acueducto']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    /** Proyecto con la fuente SGR: solo a esos se les preselecciona la plantilla SGR. */
    private function proyectoSgr(array $datos): Proyecto
    {
        $proyecto = Proyecto::create($datos);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
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

        $otro = $servicio->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

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

    public function test_una_fila_retirada_con_responsable_cuenta_como_retenida(): void
    {
        $responsable = DB::table('responsable')->insertGetId(['nombre' => 'Ana', 'activo' => true]);
        DB::table('proyectos_requisitos')->where('id', $this->filas()[0]->id)
            ->update(['aplica' => false, 'responsable_id' => $responsable]);

        $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")
            ->assertOk()->assertJsonPath('retenidos', 1);
    }

    // ------------------------------------------------- responsables en el tablero

    private function responsable(string $nombre): int
    {
        return DB::table('responsable')->insertGetId(['nombre' => $nombre, 'activo' => true]);
    }

    private function resumenDe(ProyectoExpediente $expediente): array
    {
        return collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())
            ->firstWhere('expediente_id', $expediente->id);
    }

    public function test_el_tablero_cuenta_lo_que_falta_a_cada_responsable(): void
    {
        $beto = $this->responsable('Beto');
        $ana = $this->responsable('Ana');
        $carla = $this->responsable('Carla');
        [$a, $b, $c, $d, $e, $f, $g, $h] = $this->filas();
        $a->update(['responsable_id' => $ana]);
        $b->update(['responsable_id' => $carla, 'cumplido' => true]);
        $c->update(['responsable_id' => $ana, 'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        $d->update(['responsable_id' => $ana, 'aplica' => false]);
        $e->update(['responsable_id' => $ana, 'caracter' => 'opcional']);
        $f->update(['responsable_id' => $ana, 'estado_aplicabilidad' => ProyectoRequisito::NO_APLICA_PENDIENTE, 'cumplido' => true]);
        $g->update(['responsable_id' => $ana, 'estado_aplicabilidad' => ProyectoRequisito::POR_DEFINIR]);
        $h->update(['responsable_id' => $beto]);

        $resumen = $this->resumenDe($this->expediente);

        // Ana: la pendiente, el "no aplica" por aprobar y el "por definir". Carla solo tiene cumplidas.
        $this->assertSame([
            ['id' => $ana, 'nombre' => 'Ana', 'pendientes' => 3],
            ['id' => $beto, 'nombre' => 'Beto', 'pendientes' => 1],
        ], $resumen['responsables']);
        // Con todas las que cuentan asignadas, lo de los responsables es lo que falta en el avance.
        $this->assertSame(
            $resumen['progreso']['total'] - $resumen['progreso']['cumplidos'],
            array_sum(array_column($resumen['responsables'], 'pendientes'))
        );
    }

    public function test_sin_responsables_la_lista_del_tablero_esta_vacia(): void
    {
        $this->assertSame([], $this->resumenDe($this->expediente)['responsables']);
    }

    public function test_cada_expediente_del_tablero_trae_solo_sus_responsables(): void
    {
        $ana = $this->responsable('Ana');
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear($this->proyectoSgr(['nombre' => 'Vía']), ['fase_sgr' => 3], null);
        $servicio->confirmarConfiguracion($otro, ['fase_sgr' => 3]);
        $this->filas()[0]->update(['responsable_id' => $ana]);

        $this->assertSame([['id' => $ana, 'nombre' => 'Ana', 'pendientes' => 1]], $this->resumenDe($this->expediente)['responsables']);
        $this->assertSame([], $this->resumenDe($otro)['responsables']);
    }

    public function test_los_responsables_no_suman_consultas_al_tablero(): void
    {
        $servicio = app(ExpedienteService::class);
        $asignar = function (ProyectoExpediente $expediente): void {
            $responsable = $this->responsable("Responsable {$expediente->id}");
            $expediente->requisitos()->where('aplica', true)->limit(2)->get()
                ->each(fn (ProyectoRequisito $fila) => $fila->update(['responsable_id' => $responsable]));
        };
        $medir = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson('/GestPro/expedientes')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $asignar($this->expediente);
        $conUno = $medir();

        foreach (['Dos', 'Tres', 'Cuatro'] as $nombre) {
            $expediente = $servicio->crear($this->proyectoSgr(['nombre' => $nombre]), ['fase_sgr' => 3], null);
            $servicio->confirmarConfiguracion($expediente, ['fase_sgr' => 3]);
            $asignar($expediente);
        }

        $this->assertSame($conUno, $medir());
        $this->assertCount(4, collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->json())
            ->filter(fn (array $r) => count($r['responsables']) === 1 && $r['responsables'][0]['pendientes'] === 2));
    }
}
