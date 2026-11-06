<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProyectoFuentesTest extends TestCase
{
    use RefreshDatabase;

    private int $sgr;
    private int $propios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sgr = FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id');
        $this->propios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function guardar(array $cambios)
    {
        return $this->como(Rol::Gestor)->postJson('/GestPro/guardarProyecto', [
            'accion' => 'Agregar',
            'municipio' => null,
            'nombre' => 'Acueducto',
            'descripcion' => 'Prueba',
            'fechaInicio' => '2026-01-01',
            'estado' => null,
            'fase' => null,
            'presupuesto' => null,
            'entidadPresenta' => null,
            'entidadFinancia' => null,
            ...$cambios,
        ]);
    }

    public function test_guarda_varias_fuentes_y_une_sus_nombres_en_el_orden_del_catalogo(): void
    {
        $this->guardar(['fuenteIds' => [$this->propios, $this->sgr]])->assertOk();

        $proyecto = Proyecto::where('nombre', 'Acueducto')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->sgr, $this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
        $this->assertSame('Sistema General de Regalías (SGR), Recursos propios', $proyecto->fuente_financiacion);
        $this->assertFalse($proyecto->financiacion_por_definir);
    }

    public function test_puede_quedar_por_definir(): void
    {
        $this->guardar(['fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();

        $proyecto = Proyecto::where('nombre', 'Acueducto')->firstOrFail();
        $this->assertSame(0, $proyecto->fuentes()->count());
        $this->assertTrue($proyecto->financiacion_por_definir);
        $this->assertSame('Por definir', $proyecto->fuente_financiacion);
    }

    public function test_ningun_proyecto_queda_sin_fuente_ni_por_definir(): void
    {
        $this->guardar(['fuenteIds' => []])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Seleccione al menos una fuente de financiación o marque que está por definir.');
        $this->guardar([])->assertStatus(422);

        $this->assertDatabaseCount('proyectos', 0);
    }

    public function test_no_admite_fuentes_y_por_definir_a_la_vez(): void
    {
        $this->guardar(['fuenteIds' => [$this->sgr], 'financiacionPorDefinir' => true])->assertStatus(422);
    }

    public function test_rechaza_fuentes_que_no_existen_o_estan_inactivas(): void
    {
        $this->guardar(['fuenteIds' => [999999]])->assertStatus(422);

        FuenteFinanciacion::whereKey($this->sgr)->update(['activo' => false]);
        $this->guardar(['fuenteIds' => [$this->sgr]])->assertStatus(422);
    }

    public function test_al_editar_conserva_una_fuente_que_ya_tenia_aunque_este_inactiva(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente']);
        $proyecto->fuentes()->sync([$this->sgr]);
        FuenteFinanciacion::whereKey($this->sgr)->update(['activo' => false]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->sgr, $this->propios]])->assertOk();

        $this->assertEqualsCanonicalizing([$this->sgr, $this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_al_editar_reemplaza_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'financiacion_por_definir' => true, 'fuente_financiacion' => 'Por definir']);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->propios]])->assertOk();

        $proyecto->refresh();
        $this->assertFalse($proyecto->financiacion_por_definir);
        $this->assertSame('Recursos propios', $proyecto->fuente_financiacion);
        $this->assertSame([$this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_al_editar_sin_enviar_fuentes_las_conserva(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'fuente_financiacion' => 'Recursos propios']);
        $proyecto->fuentes()->sync([$this->propios]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id])->assertOk();

        $proyecto->refresh();
        $this->assertSame('Recursos propios', $proyecto->fuente_financiacion);
        $this->assertSame([$this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_el_listado_de_proyectos_incluye_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente', 'financiacion_por_definir' => false]);
        $proyecto->fuentes()->sync([$this->propios]);
        Proyecto::create(['nombre' => 'Sin fuentes', 'financiacion_por_definir' => true]);

        $respuesta = $this->como(Rol::Consulta)->getJson('/GestPro/proyectos')->assertOk()->json();

        $porNombre = collect($respuesta)->keyBy('nombre');
        $this->assertSame([$this->propios], $porNombre['Puente']['fuente_ids']);
        $this->assertSame([], $porNombre['Sin fuentes']['fuente_ids']);
        $this->assertEquals(1, $porNombre['Sin fuentes']['financiacion_por_definir']);
    }

    public function test_el_informe_lista_los_proyectos_por_definir_con_su_texto_original(): void
    {
        Proyecto::create([
            'nombre' => 'Sin reconocer',
            'financiacion_por_definir' => true,
            'fuente_financiacion_original' => 'Convenio con la gobernación',
        ]);

        // Con Artisan::output(): expectsOutputToContain no admite dos textos en la misma línea.
        $this->assertSame(0, Artisan::call('proyectos:informe-fuentes'));
        $salida = Artisan::output();

        $this->assertStringContainsString('Sin reconocer', $salida);
        $this->assertStringContainsString('Convenio con la gobernación', $salida);
        $this->assertStringContainsString('Proyectos por definir: 1', $salida);
    }

    public function test_el_informe_muestra_cada_texto_original_con_el_resultado_de_la_conversion(): void
    {
        $proyecto = Proyecto::create([
            'nombre' => 'Convertido',
            'fuente_financiacion' => 'Recursos propios',
            'fuente_financiacion_original' => 'Sin regalías, recursos propios',
        ]);
        $proyecto->fuentes()->sync([$this->propios]);

        $this->assertSame(0, Artisan::call('proyectos:informe-fuentes'));
        $salida = Artisan::output();

        $this->assertMatchesRegularExpression('/Sin regalías, recursos propios\s*\|\s*Recursos propios\s*\|\s*1/u', $salida);
    }

    public function test_el_informe_separa_resultados_distintos_del_mismo_texto(): void
    {
        $a = Proyecto::create(['nombre' => 'A', 'fuente_financiacion_original' => 'Mixto']);
        $a->fuentes()->sync([$this->propios]);
        $b = Proyecto::create(['nombre' => 'B', 'fuente_financiacion_original' => 'Mixto']);
        $b->fuentes()->sync([$this->sgr]);

        $this->assertSame(0, Artisan::call('proyectos:informe-fuentes'));
        $salida = Artisan::output();

        $this->assertMatchesRegularExpression('/Mixto\s*\|\s*Recursos propios\s*\|\s*1/u', $salida);
        $this->assertMatchesRegularExpression('/Mixto\s*\|\s*Sistema General de Regalías \(SGR\)\s*\|\s*1/u', $salida);
    }

    public function test_el_informe_muestra_vacio_para_textos_en_blanco(): void
    {
        Proyecto::create(['nombre' => 'En blanco', 'financiacion_por_definir' => true, 'fuente_financiacion_original' => '   ']);

        $this->assertSame(0, Artisan::call('proyectos:informe-fuentes'));

        $this->assertMatchesRegularExpression('/En blanco\s*\|\s*\(vacío\)/u', Artisan::output());
    }

    public function test_rechaza_fuentes_con_formato_invalido(): void
    {
        foreach ([5, 'abc', [[4]], [true], ['4abc'], [1.5]] as $invalido) {
            $this->guardar(['fuenteIds' => $invalido])->assertStatus(422);
        }
        $this->assertDatabaseCount('proyectos', 0);

        $this->guardar(['fuenteIds' => [(string) $this->sgr]])->assertOk();
        $this->assertDatabaseCount('proyectos', 1);
    }

    public function test_al_editar_quita_las_fuentes_que_ya_no_se_envian(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente']);
        $proyecto->fuentes()->sync([$this->sgr, $this->propios]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->propios]])->assertOk();

        $this->assertSame([$this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }

    public function test_pasar_a_por_definir_quita_las_fuentes(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente']);
        $proyecto->fuentes()->sync([$this->sgr]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [], 'financiacionPorDefinir' => true])->assertOk();

        $proyecto->refresh();
        $this->assertSame(0, $proyecto->fuentes()->count());
        $this->assertTrue($proyecto->financiacion_por_definir);
        $this->assertSame('Por definir', $proyecto->fuente_financiacion);
    }

    public function test_al_editar_rechaza_una_fuente_inactiva_nueva(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Puente']);
        $proyecto->fuentes()->sync([$this->propios]);
        FuenteFinanciacion::whereKey($this->sgr)->update(['activo' => false]);

        $this->guardar(['accion' => 'Editar', 'id' => $proyecto->id, 'fuenteIds' => [$this->propios, $this->sgr]])->assertStatus(422);

        $this->assertSame([$this->propios], $proyecto->fuentes()->pluck('fuentes_financiacion.id')->all());
    }
}
