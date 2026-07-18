<?php

namespace Tests\Feature\Schema;

use App\Enums\Rol;
use App\Models\ActividadAvance;
use App\Models\ActividadContrato;
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvancesTest extends TestCase
{
    use RefreshDatabase;

    private function crearContrato(): Contrato
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);
        return Contrato::create(['proyecto' => $proyecto->id, 'n_contrato' => 'C-001']);
    }

    public function test_avance_financiero_casts_decimals_and_percentage(): void
    {
        $contrato = $this->crearContrato();

        $avance = AvanceFinanciero::create([
            'contrato_id' => $contrato->id,
            'descripcion' => 'Acta 1',
            'fecha_acta' => '2026-02-01',
            'valor_facturado' => 1000000,
            'porcentaje_ejecutado' => 38.83,
        ]);

        $fresco = $avance->fresh();
        $this->assertSame('1000000.00', $fresco->valor_facturado);
        $this->assertSame('38.83', $fresco->porcentaje_ejecutado);
        $this->assertTrue($fresco->contrato->is($contrato));
    }

    public function test_avance_fisico_belongs_to_contrato(): void
    {
        $contrato = $this->crearContrato();

        $avance = AvanceFisico::create([
            'contrato_id' => $contrato->id,
            'descripcion_avance_fisico' => 'Excavación completa',
            'fecha_avance_fisico' => '2026-02-15',
            'valor_avance_fisico' => 60,
        ]);

        $this->assertTrue($avance->fresh()->contrato->is($contrato));
        $this->assertSame(60, $avance->fresh()->valor_avance_fisico);
    }

    public function test_actividad_contrato_belongs_to_contrato(): void
    {
        $contrato = $this->crearContrato();

        $actividad = ActividadContrato::create([
            'contrato_id' => $contrato->id,
            'nombre' => 'Cimentación',
            'peso' => 30,
        ]);

        $this->assertTrue($actividad->fresh()->contrato->is($contrato));
        $this->assertSame(30, $actividad->fresh()->peso);
    }

    public function test_actividad_avance_belongs_to_actividad_and_tracks_history(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Estructura', 'peso' => 40]);

        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-01-01', 'porcentaje_ejecucion' => 20]);
        $segundo = ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-02-01', 'porcentaje_ejecucion' => 50]);

        $this->assertTrue($segundo->fresh()->actividad->is($actividad));
        $this->assertCount(2, $actividad->fresh()->avances);
    }

    public function test_deleting_actividad_cascades_to_its_avances(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Acabados', 'peso' => 30]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-01-01', 'porcentaje_ejecucion' => 10]);

        $actividad->delete();

        $this->assertDatabaseCount('actividad_avances', 0);
    }

    public function test_proyectos_entrega_historico_de_avances_por_actividad(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Excavación', 'peso' => 50]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-01-15', 'porcentaje_ejecucion' => 20]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-02-15', 'porcentaje_ejecucion' => 60]);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/GestPro/proyectos');
        $resp->assertOk();

        $proyectoJson = collect($resp->json())->firstWhere('id', $contrato->proyecto);
        $this->assertNotNull($proyectoJson);
        $contratoJson = collect($proyectoJson['contratos'])->firstWhere('id', $contrato->id);
        $actividadJson = collect($contratoJson['actividades'])->firstWhere('id', $actividad->id);

        $this->assertCount(2, $actividadJson['avances']);
        $this->assertSame('2026-01-15', substr($actividadJson['avances'][0]['fecha'], 0, 10));
        $this->assertSame(20, $actividadJson['avances'][0]['porcentaje_ejecucion']);
        $this->assertSame(60, $actividadJson['avances'][1]['porcentaje_ejecucion']);
    }

    public function test_actividad_sin_avances_entrega_arreglo_vacio(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Acabados', 'peso' => 30]);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/GestPro/proyectos');
        $resp->assertOk();

        $proyectoJson = collect($resp->json())->firstWhere('id', $contrato->proyecto);
        $contratoJson = collect($proyectoJson['contratos'])->firstWhere('id', $contrato->id);
        $actividadJson = collect($contratoJson['actividades'])->firstWhere('id', $actividad->id);

        $this->assertIsArray($actividadJson['avances']);
        $this->assertCount(0, $actividadJson['avances']);
    }

    public function test_listar_contratos_entrega_historico_de_avances(): void
    {
        $contrato = $this->crearContrato();
        $actividad = ActividadContrato::create(['contrato_id' => $contrato->id, 'nombre' => 'Estructura', 'peso' => 40]);
        ActividadAvance::create(['actividad_id' => $actividad->id, 'fecha' => '2026-03-01', 'porcentaje_ejecucion' => 45]);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/GestPro/listarContratos?proyecto=' . $contrato->proyecto);
        $resp->assertOk();

        $contratoJson = collect($resp->json())->firstWhere('id', $contrato->id);
        $actividadJson = collect($contratoJson['actividades'])->firstWhere('id', $actividad->id);
        $this->assertCount(1, $actividadJson['avances']);
        $this->assertSame(45, $actividadJson['avances'][0]['porcentaje_ejecucion']);
    }
}
