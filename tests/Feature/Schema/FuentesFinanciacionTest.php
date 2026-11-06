<?php

namespace Tests\Feature\Schema;

use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuentesFinanciacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_catalogo_inicia_con_las_diez_fuentes_de_la_propuesta(): void
    {
        $this->assertSame([
            'Sistema General de Regalías (SGR)',
            'Presupuesto General de la Nación (PGN)',
            'Sistema General de Participaciones (SGP)',
            'Recursos propios',
            'Cofinanciación',
            'Crédito público',
            'Cooperación internacional',
            'Obras por Impuestos',
            'Asociaciones Público-Privadas (APP)',
            'Otras fuentes',
        ], FuenteFinanciacion::orderBy('orden')->pluck('nombre')->all());

        $this->assertTrue(FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('activo'));
        $this->assertSame('Recursos propios', FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('nombre'));
    }

    public function test_un_proyecto_puede_tener_varias_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $ids = FuenteFinanciacion::whereIn('clave', [FuenteFinanciacion::SGR, FuenteFinanciacion::RECURSOS_PROPIOS])->pluck('id');

        $proyecto->fuentes()->sync($ids);

        $this->assertEqualsCanonicalizing($ids->all(), $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_no_se_puede_borrar_una_fuente_con_proyectos(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $fuente = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->first();
        $proyecto->fuentes()->sync([$fuente->id]);

        $this->expectException(QueryException::class);
        $fuente->delete();
    }

    public function test_borrar_un_proyecto_borra_su_relacion_con_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        $proyecto->delete();

        $this->assertDatabaseCount('proyecto_fuentes', 0);
    }
}
