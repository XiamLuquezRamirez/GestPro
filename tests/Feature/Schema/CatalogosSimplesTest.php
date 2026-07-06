<?php

namespace Tests\Feature\Schema;

use App\Models\Entidad;
use App\Models\Modalidad;
use App\Models\Prioridad;
use App\Models\Responsable;
use App\Models\Sector;
use App\Models\TipoEvento;
use App\Models\TipoProceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogosSimplesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_simple_catalog_tables_exist_with_activo_boolean(): void
    {
        $tablas = [
            'sectores' => Sector::class,
            'entidades' => Entidad::class,
            'tipos_procesos' => TipoProceso::class,
            'modalidades' => Modalidad::class,
            'tipo_eventos' => TipoEvento::class,
            'prioridades' => Prioridad::class,
            'responsable' => Responsable::class,
        ];

        foreach ($tablas as $tabla => $modeloClase) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
            $this->assertTrue(Schema::hasColumns($tabla, ['id', 'nombre', 'activo']), "Faltan columnas en {$tabla}");

            $registro = $modeloClase::create(['nombre' => 'Ejemplo de prueba', 'activo' => true]);
            $this->assertTrue($registro->fresh()->activo);
        }
    }

    public function test_tipo_evento_and_responsable_have_their_extra_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('tipo_eventos', 'icono'));
        $this->assertTrue(Schema::hasColumns('responsable', ['email', 'cargo']));
    }
}
