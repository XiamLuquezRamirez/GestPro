<?php

namespace Tests\Feature\Schema;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MunicipiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_municipios_table_with_fk_to_departamentos(): void
    {
        $this->assertTrue(Schema::hasTable('municipios'));
        $this->assertTrue(Schema::hasColumns('municipios', ['id', 'codigo', 'nombre', 'activo', 'departamento', 'imagen']));
    }

    public function test_seeds_the_real_catalog_of_1123_municipios(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);
        $this->seed(\Database\Seeders\MunicipiosSeeder::class);

        $this->assertSame(1123, Municipio::count());

        $medellin = Municipio::where('nombre', 'MEDELLÍN')->first();
        $this->assertNotNull($medellin);
        $this->assertSame('05', $medellin->departamentoRel->codigo);
    }

    public function test_rejects_a_municipio_with_an_unknown_departamento(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Municipio::create([
            'codigo' => '99999',
            'nombre' => 'MUNICIPIO INEXISTENTE',
            'activo' => true,
            'departamento' => 'ZZ',
        ]);
    }
}
