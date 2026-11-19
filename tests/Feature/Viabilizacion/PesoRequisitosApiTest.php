<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Peso de cada requisito en la administración de plantillas: solo lo llevan las de contratación. */
class PesoRequisitosApiTest extends TestCase
{
    use RefreshDatabase;

    private const SIN_PESO = 'Los requisitos de una plantilla de viabilización no llevan peso.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
    }

    private function comoAdmin(): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])));
    }

    private function etapa(string $clave = 'planeacion'): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    /** Plantilla creada por la API (de contratación si no se indica otro uso), con su borrador. */
    private function plantilla(string $uso = PlantillaChecklist::USO_CONTRATACION): PlantillaChecklist
    {
        $id = $this->comoAdmin()->postJson('/GestPro/plantillas', [
            'nombre' => $uso === PlantillaChecklist::USO_CONTRATACION ? 'Licitación pública – SECOP II' : 'Inversión',
            'uso' => $uso,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => $uso === PlantillaChecklist::USO_CONTRATACION ? [Modalidad::where('clave', 'licitacion_publica')->value('id')] : [],
        ])->assertCreated()->json('id');

        return PlantillaChecklist::findOrFail($id);
    }

    private function borrador(PlantillaChecklist $plantilla): CatalogoVersion
    {
        return $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
    }

    private function requisitos(CatalogoVersion $version): string
    {
        return "/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos";
    }

    /** Requisito de contratación: con su etapa, salvo que se indique otra cosa. */
    private function crearRequisito(CatalogoVersion $version, array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->requisitos($version), [
            'texto' => 'Estudios previos', 'etapa_id' => $this->etapa()->id, ...$datos,
        ]);
    }

    // ------------------------------------------------------------- contratación

    public function test_en_contratacion_el_peso_es_opcional_y_por_defecto_vale_1(): void
    {
        $borrador = $this->borrador($this->plantilla());

        $this->crearRequisito($borrador)->assertCreated()->assertJsonPath('peso', 1);
        $this->crearRequisito($borrador, ['texto' => 'CDP', 'peso' => 3])->assertCreated()->assertJsonPath('peso', 3);
        $this->crearRequisito($borrador, ['texto' => 'Pliego', 'peso' => 100])->assertCreated()->assertJsonPath('peso', 100);

        $this->assertSame([1, 3, 100], $borrador->requisitos()->orderBy('id')->pluck('peso')->all());
    }

    public function test_al_editar_sin_peso_en_el_pedido_conserva_el_que_tenia(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $id = $this->crearRequisito($borrador, ['peso' => 3])->assertCreated()->json('id');
        $url = $this->requisitos($borrador) . "/{$id}";

        $this->comoAdmin()->putJson($url, ['texto' => 'Estudios previos y anexos'])->assertOk()->assertJsonPath('peso', 3);
        $this->comoAdmin()->putJson($url, ['texto' => 'Estudios previos', 'peso' => 5])->assertOk()->assertJsonPath('peso', 5);
        $this->comoAdmin()->putJson($url, ['texto' => 'Estudios previos', 'peso' => 1])->assertOk()->assertJsonPath('peso', 1);
        $this->assertSame(1, RequisitoCatalogo::findOrFail($id)->peso);
    }

    public function test_el_peso_debe_ser_un_entero_de_1_a_100(): void
    {
        $borrador = $this->borrador($this->plantilla());

        foreach (['3', true, 1.5, null] as $valor) {
            $this->crearRequisito($borrador, ['peso' => $valor])->assertStatus(422)
                ->assertJsonPath('errors.peso.0', 'Revisa el peso: debe ser un número entero.');
        }
        $this->crearRequisito($borrador, ['peso' => 0])->assertStatus(422)
            ->assertJsonPath('errors.peso.0', 'Revisa el peso: el mínimo es 1.');
        $this->crearRequisito($borrador, ['peso' => 101])->assertStatus(422)
            ->assertJsonPath('errors.peso.0', 'Revisa el peso: el máximo es 100.');

        $this->assertSame(0, $borrador->requisitos()->count());
    }

    public function test_la_lista_de_requisitos_trae_el_peso(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $this->crearRequisito($borrador, ['peso' => 4])->assertCreated();

        $this->assertSame(4, $this->comoAdmin()->getJson($this->requisitos($borrador))->assertOk()->json('0.peso'));
    }

    public function test_un_borrador_nuevo_y_una_copia_conservan_el_peso_de_cada_requisito(): void
    {
        $plantilla = $this->plantilla();
        $primero = $this->borrador($plantilla);
        $this->crearRequisito($primero, ['peso' => 7])->assertCreated();
        $this->crearRequisito($primero, ['texto' => 'CDP'])->assertCreated();
        $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$primero->id}/publicar")->assertOk();

        $segundo = app(CatalogoService::class)->crearBorrador('Ajustes', $plantilla);
        $this->assertSame([7, 1], $segundo->requisitos()->orderBy('id')->pluck('peso')->all());

        $copia = $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Licitación pública – copia'])
            ->assertCreated()->json('id');
        $this->assertSame([7, 1], $this->borrador(PlantillaChecklist::findOrFail($copia))->requisitos()->orderBy('orden')->pluck('peso')->all());
    }

    // ------------------------------------------------------------- viabilización

    public function test_en_viabilizacion_un_peso_distinto_de_1_se_rechaza_tambien_en_su_campo(): void
    {
        $borrador = $this->borrador($this->plantilla(PlantillaChecklist::USO_VIABILIZACION));

        $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Estudios', 'peso' => 3])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [self::SIN_PESO],
            'errors' => ['peso' => [self::SIN_PESO]],
        ]);
        $this->assertSame(0, $borrador->requisitos()->count());

        $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Con peso 1', 'peso' => 1])->assertCreated()->assertJsonPath('peso', 1);
        $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Sin peso'])->assertCreated()->assertJsonPath('peso', 1);
    }

    public function test_en_la_plantilla_sgr_un_peso_distinto_de_1_se_rechaza(): void
    {
        $borrador = app(CatalogoService::class)->crearBorrador('Ajustes');
        $datos = ['clave' => 'a99', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '99', 'texto' => 'Nuevo', 'condicion' => 'siempre'];

        $this->comoAdmin()->postJson($this->requisitos($borrador), [...$datos, 'peso' => 2])->assertStatus(422)
            ->assertJsonPath('errores', [self::SIN_PESO])->assertJsonPath('errors', ['peso' => [self::SIN_PESO]]);

        $this->comoAdmin()->postJson($this->requisitos($borrador), [...$datos, 'peso' => 1])->assertCreated()->assertJsonPath('peso', 1);
    }

    public function test_en_viabilizacion_guardar_deja_el_peso_en_1_aunque_la_base_tuviera_otro(): void
    {
        $borrador = $this->borrador($this->plantilla(PlantillaChecklist::USO_VIABILIZACION));
        $id = $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Estudios'])->assertCreated()->json('id');
        // Escrito directo en la base: la administración no deja ponerlo.
        RequisitoCatalogo::whereKey($id)->update(['peso' => 4]);

        $this->comoAdmin()->putJson($this->requisitos($borrador) . "/{$id}", ['texto' => 'Estudios previos'])->assertOk()->assertJsonPath('peso', 1);
        $this->assertSame(1, RequisitoCatalogo::findOrFail($id)->peso);
    }

    // ------------------------------------------------------------- errores en su campo

    public function test_los_errores_del_peso_vienen_despues_de_los_de_la_etapa_y_en_su_campo(): void
    {
        $viabilizacion = $this->borrador($this->plantilla(PlantillaChecklist::USO_VIABILIZACION));
        $sinEtapa = 'Los requisitos de una plantilla de viabilización no llevan etapa de contratación.';

        // Etapa y peso: en la lista, la etapa primero; cada campo, con los suyos.
        $this->crearRequisito($viabilizacion, ['peso' => 2])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [$sinEtapa, self::SIN_PESO],
            'errors' => ['etapa_id' => [$sinEtapa], 'peso' => [self::SIN_PESO]],
        ]);

        // Peso y otros errores: la lista los trae todos, el peso primero; el campo, solo los suyos.
        $this->comoAdmin()->postJson($this->requisitos($viabilizacion), [
            'texto' => 'Estudios', 'peso' => 2, 'caracter' => RequisitoCatalogo::CONDICIONADO,
        ])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [self::SIN_PESO, 'Un requisito condicionado necesita describir cuándo aplica.'],
            'errors' => ['peso' => [self::SIN_PESO]],
        ]);
    }
}
