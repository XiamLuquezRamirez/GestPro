<?php

namespace Tests\Feature\Schema;

use App\Enums\Rol;
use App\Models\Contrato;
use App\Models\ModificacionContrato;
use App\Models\Municipio;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModificacionesContratoTest extends TestCase
{
    use RefreshDatabase;

    private function crearContrato(array $overrides = []): Contrato
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);
        return Contrato::create(array_merge([
            'proyecto' => $proyecto->id,
            'n_contrato' => 'C-001',
            'valor' => 100000000,
            'fecha_fin' => '2026-12-31',
        ], $overrides));
    }

    public function test_tabla_modificaciones_contrato_tiene_columnas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('modificaciones_contrato'));
        $this->assertTrue(Schema::hasColumns('modificaciones_contrato', [
            'id', 'contrato_id', 'numero_otrosi', 'tipo',
            'valor_adicion', 'dias_prorroga', 'fecha_modificacion', 'justificacion',
        ]));
    }

    public function test_contratos_tiene_columnas_iniciales(): void
    {
        $this->assertTrue(Schema::hasColumns('contratos', ['valor_inicial', 'fecha_fin_inicial']));
    }

    public function test_backfill_copia_valor_y_fecha_fin_a_iniciales(): void
    {
        // Simular un contrato "preexistente" a la migración: iniciales en null.
        // El helper crea el contrato con valor 100M y fecha_fin 2026-12-31.
        $contrato = $this->crearContrato();
        \Illuminate\Support\Facades\DB::table('contratos')->where('id', $contrato->id)->update([
            'valor_inicial' => null,
            'fecha_fin_inicial' => null,
        ]);

        // Ejecutar la misma rutina de backfill idempotente que corre la migración.
        \Illuminate\Support\Facades\DB::statement('UPDATE contratos SET valor_inicial = valor WHERE valor_inicial IS NULL');
        \Illuminate\Support\Facades\DB::statement('UPDATE contratos SET fecha_fin_inicial = fecha_fin WHERE fecha_fin_inicial IS NULL');

        // Leer a través del modelo Eloquent para aplicar el cast decimal:2
        // (consistente con AvancesTest; el query builder crudo no castea).
        $fresco = $contrato->fresh();
        $this->assertSame('100000000.00', $fresco->valor_inicial);
        $this->assertSame('2026-12-31', $fresco->fecha_fin_inicial->toDateString());
    }

    public function test_modificacion_belongs_to_contrato_y_castea_decimales(): void
    {
        $contrato = $this->crearContrato();

        $mod = ModificacionContrato::create([
            'contrato_id' => $contrato->id,
            'numero_otrosi' => 'Otrosí No. 1',
            'tipo' => 'adicion_prorroga',
            'valor_adicion' => 30000000,
            'dias_prorroga' => 60,
            'fecha_modificacion' => '2026-03-01',
            'justificacion' => 'Mayor cantidad de obra',
        ]);

        $fresco = $mod->fresh();
        $this->assertTrue($fresco->contrato->is($contrato));
        $this->assertSame('30000000.00', $fresco->valor_adicion);
        $this->assertSame(60, $fresco->dias_prorroga);
        $this->assertCount(1, $contrato->fresh()->modificaciones);
    }

    public function test_guardar_contrato_nuevo_fija_valores_iniciales(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $municipio = Municipio::create(['codigo' => '05001', 'nombre' => 'MEDELLÍN', 'activo' => true, 'departamento' => '05']);
        $proyecto = Proyecto::create(['municipio' => $municipio->codigo, 'nombre' => 'Proyecto de prueba']);

        $admin = User::factory()->create(['rol' => Rol::Administrador]);
        $token = auth('api')->login($admin);

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/GestPro/guardarContrato', [
                'formContrato' => [
                    'n_contrato' => 'C-100',
                    'objeto' => 'Construcción',
                    'valor' => 200000000,
                    'fecha_fin' => '2027-06-30',
                    'estado' => 'En ejecución',
                    'proyecto' => $proyecto->id,
                ],
                'anexos' => [],
            ]);

        $resp->assertOk();
        $contrato = \Illuminate\Support\Facades\DB::table('contratos')->where('n_contrato', 'C-100')->first();
        $this->assertEquals('200000000.00', $contrato->valor_inicial);
        $this->assertStringStartsWith('2027-06-30', (string) $contrato->fecha_fin_inicial);
    }
}
