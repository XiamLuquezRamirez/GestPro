<?php

namespace Tests\Feature\Schema;

use App\Models\Contrato;
use App\Models\Municipio;
use App\Models\Proyecto;
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
}
