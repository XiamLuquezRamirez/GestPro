<?php

namespace Tests\Feature\Schema;

use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DepartamentosTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_departamentos_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('departamentos'));
        $this->assertTrue(Schema::hasColumns('departamentos', ['codigo', 'nombre', 'activo']));
    }

    public function test_seeds_the_real_catalog_of_34_departamentos(): void
    {
        $this->seed(\Database\Seeders\DepartamentosSeeder::class);

        $this->assertSame(34, Departamento::count());

        $antioquia = Departamento::find('05');
        $this->assertSame('ANTIOQUIA', $antioquia->nombre);
        $this->assertIsBool($antioquia->activo);
    }
}
