<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Plantillas de contratación: uso, modalidades y etapa de cada requisito en la administración. */
class PlantillasContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private int $fuentePropios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->fuentePropios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function comoAdmin(): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])));
    }

    private function modalidad(string $clave): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Licitación pública – SECOP II',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad('licitacion_publica')->id],
            ...$cambios,
        ];
    }

    private function crear(array $cambios = [])
    {
        return $this->comoAdmin()->postJson('/GestPro/plantillas', $this->datos($cambios));
    }

    private function editar(int $id, array $datos)
    {
        return $this->comoAdmin()->putJson("/GestPro/plantillas/{$id}", $datos);
    }

    /** Plantilla de contratación creada por la API, con su borrador. */
    private function plantilla(array $cambios = []): PlantillaChecklist
    {
        return PlantillaChecklist::findOrFail($this->crear($cambios)->assertCreated()->json('id'));
    }

    private function borrador(PlantillaChecklist $plantilla): CatalogoVersion
    {
        return $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
    }

    private function requisitos(CatalogoVersion $version): string
    {
        return "/GestPro/catalogo-requisitos/versiones/{$version->id}/requisitos";
    }

    private function crearRequisito(CatalogoVersion $version, array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->requisitos($version), [
            'texto' => 'Estudios previos', 'etapa_id' => $this->etapa('planeacion')->id, ...$datos,
        ]);
    }

    private function publicar(CatalogoVersion $version)
    {
        return $this->comoAdmin()->postJson("/GestPro/catalogo-requisitos/versiones/{$version->id}/publicar");
    }

    // ------------------------------------------------------------- uso

    public function test_crear_exige_el_uso(): void
    {
        $sinUso = $this->datos();
        unset($sinUso['uso']);

        $this->comoAdmin()->postJson('/GestPro/plantillas', $sinUso)->assertStatus(422)
            ->assertJsonPath('errors.uso.0', 'Elige el uso de la plantilla: viabilización o contratación.');
        $this->crear(['uso' => 'ejecucion'])->assertStatus(422)
            ->assertJsonPath('errors.uso.0', 'Elige el uso de la plantilla: viabilización o contratación.');
    }

    public function test_crear_una_plantilla_de_contratacion_la_deja_simple_con_sus_modalidades(): void
    {
        $minima = $this->modalidad('minima_cuantia');

        $r = $this->crear(['modalidad_ids' => [$this->modalidad('licitacion_publica')->id, $minima->id]])->assertCreated()
            ->assertJsonPath('uso', 'contratacion')
            ->assertJsonPath('motor', 'simple')
            ->assertJsonPath('todas_modalidades', false)
            ->assertJsonPath('borrador.numero', 1);

        $this->assertSame([
            ['id' => $this->modalidad('licitacion_publica')->id, 'nombre' => 'Licitación pública', 'activo' => true],
            ['id' => $minima->id, 'nombre' => 'Mínima cuantía', 'activo' => true],
        ], $r->json('modalidades'));
    }

    public function test_con_todas_las_modalidades_no_guarda_modalidades_sueltas(): void
    {
        $plantilla = $this->plantilla(['todas_modalidades' => true]);

        $this->assertTrue($plantilla->todas_modalidades);
        $this->assertSame(0, $plantilla->modalidades()->count());
    }

    public function test_una_plantilla_de_viabilizacion_no_acepta_modalidades(): void
    {
        $viabilizacion = ['uso' => PlantillaChecklist::USO_VIABILIZACION, 'nombre' => 'Inversión', 'modalidad_ids' => []];
        $mensaje = ['Una plantilla de viabilización no se asocia a modalidades: solo las de contratación las tienen.'];

        $this->crear([...$viabilizacion, 'todas_modalidades' => true])->assertStatus(422)->assertJsonPath('errores', $mensaje);
        $this->crear([...$viabilizacion, 'modalidad_ids' => [$this->modalidad('minima_cuantia')->id]])->assertStatus(422)
            ->assertJsonPath('errores', $mensaje);

        $r = $this->crear([...$viabilizacion, 'todas_modalidades' => false])->assertCreated()
            ->assertJsonPath('uso', 'viabilizacion')
            ->assertJsonPath('modalidades', [])
            ->assertJsonPath('todas_modalidades', false);
        $this->assertSame(0, PlantillaChecklist::findOrFail($r->json('id'))->modalidades()->count());
    }

    public function test_el_uso_no_cambia_al_editar(): void
    {
        $plantilla = $this->plantilla();

        $this->editar($plantilla->id, $this->datos(['uso' => PlantillaChecklist::USO_VIABILIZACION]))->assertStatus(422)
            ->assertJsonPath('errores', ['El uso de una plantilla no cambia después de crearla: esta es de contratación. Si necesitas otro uso, crea una plantilla nueva.']);

        $this->editar($plantilla->id, $this->datos(['descripcion' => 'Con el mismo uso']))->assertOk()->assertJsonPath('uso', 'contratacion');
        $sinUso = $this->datos(['descripcion' => 'Sin indicar el uso']);
        unset($sinUso['uso']);
        $this->editar($plantilla->id, $sinUso)->assertOk()->assertJsonPath('uso', 'contratacion');
        $this->assertSame(PlantillaChecklist::USO_CONTRATACION, $plantilla->fresh()->uso);

        $sgr = PlantillaChecklist::sgr();
        $this->editar($sgr->id, ['nombre' => 'SGR', 'uso' => 'contratacion', 'fuente_ids' => [FuenteFinanciacion::where('clave', 'sgr')->value('id')], 'todos_sectores' => true])
            ->assertStatus(422)
            ->assertJsonPath('errores.0', 'El uso de una plantilla no cambia después de crearla: esta es de viabilización. Si necesitas otro uso, crea una plantilla nueva.');
    }

    // ------------------------------------------------------------- modalidades

    public function test_editar_cambia_las_modalidades_y_sin_ellas_en_el_pedido_las_conserva(): void
    {
        $plantilla = $this->plantilla();
        $minima = $this->modalidad('minima_cuantia');

        $this->editar($plantilla->id, $this->datos(['modalidad_ids' => [$minima->id]]))->assertOk()
            ->assertJsonPath('modalidades', [['id' => $minima->id, 'nombre' => 'Mínima cuantía', 'activo' => true]]);

        $sinModalidades = $this->datos(['descripcion' => 'Otra']);
        unset($sinModalidades['modalidad_ids']);
        $this->editar($plantilla->id, $sinModalidades)->assertOk()
            ->assertJsonPath('modalidades.0.id', $minima->id)
            ->assertJsonPath('todas_modalidades', false);

        $this->editar($plantilla->id, $this->datos(['todas_modalidades' => true]))->assertOk()
            ->assertJsonPath('todas_modalidades', true)
            ->assertJsonPath('modalidades', []);
    }

    public function test_no_asocia_una_modalidad_inactiva_o_inexistente_pero_conserva_la_inactiva_que_ya_tenia(): void
    {
        $plantilla = $this->plantilla();
        $licitacion = $this->modalidad('licitacion_publica');
        $licitacion->update(['activo' => false]);

        $this->editar($plantilla->id, $this->datos(['descripcion' => 'Editada']))->assertOk()
            ->assertJsonPath('modalidades', [['id' => $licitacion->id, 'nombre' => 'Licitación pública', 'activo' => false]]);

        $this->crear(['nombre' => 'Otra', 'modalidad_ids' => [$licitacion->id, 99999]])->assertStatus(422)
            ->assertJsonPath('errores', [
                'La modalidad "Licitación pública" está inactiva.',
                'La modalidad con id 99999 no existe.',
            ]);
    }

    public function test_la_lista_filtra_por_uso_y_trae_el_uso_y_las_modalidades(): void
    {
        $this->plantilla();

        $contratacion = $this->comoAdmin()->getJson('/GestPro/plantillas?uso=contratacion')->assertOk()->json();
        $this->assertSame(['Licitación pública – SECOP II'], array_column($contratacion, 'nombre'));
        $this->assertSame('contratacion', $contratacion[0]['uso']);
        $this->assertFalse($contratacion[0]['todas_modalidades']);
        $this->assertSame('Licitación pública', $contratacion[0]['modalidades'][0]['nombre']);

        $viabilizacion = $this->comoAdmin()->getJson('/GestPro/plantillas?uso=viabilizacion')->assertOk()->json();
        $this->assertSame(['SGR'], array_column($viabilizacion, 'nombre'));

        $this->assertCount(2, $this->comoAdmin()->getJson('/GestPro/plantillas')->assertOk()->json());
        $this->comoAdmin()->getJson('/GestPro/plantillas?uso=otro')->assertStatus(422)
            ->assertJsonPath('errors.uso.0', 'Elige el uso de la plantilla: viabilización o contratación.');
    }

    // ------------------------------------------------------------- etapa de los requisitos

    public function test_un_requisito_de_contratacion_exige_su_etapa(): void
    {
        $borrador = $this->borrador($this->plantilla());

        $this->crearRequisito($borrador, ['etapa_id' => null])->assertStatus(422)
            ->assertJsonPath('errores', ['Elige la etapa de contratación del requisito.']);
        $sinEtapa = $this->comoAdmin()->postJson($this->requisitos($borrador), ['texto' => 'Estudios previos'])->assertStatus(422);
        $sinEtapa->assertJsonPath('errores', ['Elige la etapa de contratación del requisito.']);

        $this->crearRequisito($borrador)->assertCreated()->assertJsonPath('etapa_id', $this->etapa('planeacion')->id);
    }

    public function test_la_etapa_debe_ser_un_entero_de_una_etapa_activa(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $adendas = $this->etapa('adendas');
        $adendas->update(['activo' => false]);

        foreach (['1', true, 1.5] as $valor) {
            $this->crearRequisito($borrador, ['etapa_id' => $valor])->assertStatus(422)
                ->assertJsonPath('errors.etapa_id.0', 'Revisa la etapa: debe ser un número entero.');
        }
        $this->crearRequisito($borrador, ['etapa_id' => 99999])->assertStatus(422)
            ->assertJsonPath('errores', ['La etapa de contratación con id 99999 no existe.']);
        $this->crearRequisito($borrador, ['etapa_id' => $adendas->id])->assertStatus(422)
            ->assertJsonPath('errores', ['La etapa "Adendas" está inactiva.']);
    }

    public function test_al_editar_conserva_la_etapa_inactiva_que_ya_tenia_y_sin_etapa_en_el_pedido_no_la_cambia(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $adendas = $this->etapa('adendas');
        $id = $this->crearRequisito($borrador, ['etapa_id' => $adendas->id])->assertCreated()->json('id');
        $adendas->update(['activo' => false]);
        $url = $this->requisitos($borrador) . "/{$id}";

        $this->comoAdmin()->putJson($url, ['texto' => 'Adendas publicadas', 'etapa_id' => $adendas->id])->assertOk()
            ->assertJsonPath('etapa_id', $adendas->id);
        $this->comoAdmin()->putJson($url, ['texto' => 'Adendas publicadas a tiempo'])->assertOk()
            ->assertJsonPath('etapa_id', $adendas->id);
        $this->comoAdmin()->putJson($url, ['texto' => 'Adendas', 'etapa_id' => null])->assertStatus(422)
            ->assertJsonPath('errores', ['Elige la etapa de contratación del requisito.']);

        $cierre = $this->etapa('cierre');
        $this->comoAdmin()->putJson($url, ['texto' => 'Acta de cierre', 'etapa_id' => $cierre->id])->assertOk()
            ->assertJsonPath('etapa_id', $cierre->id);
        $this->comoAdmin()->putJson($url, ['texto' => 'Acta de cierre', 'etapa_id' => $adendas->id])->assertStatus(422)
            ->assertJsonPath('errores', ['La etapa "Adendas" está inactiva.']);
    }

    public function test_un_requisito_de_viabilizacion_no_acepta_etapa(): void
    {
        $simple = PlantillaChecklist::findOrFail($this->crear(['uso' => PlantillaChecklist::USO_VIABILIZACION, 'nombre' => 'Inversión', 'modalidad_ids' => []])->assertCreated()->json('id'));
        $mensaje = ['Los requisitos de una plantilla de viabilización no llevan etapa de contratación.'];

        $this->crearRequisito($this->borrador($simple))->assertStatus(422)
            ->assertJsonPath('errores', $mensaje)->assertJsonPath('errors', ['etapa_id' => $mensaje]);
        $this->comoAdmin()->postJson($this->requisitos($this->borrador($simple)), ['texto' => 'Sin etapa'])->assertCreated()
            ->assertJsonPath('etapa_id', null);

        $borradorSgr = app(CatalogoService::class)->crearBorrador('Ajustes');
        $this->comoAdmin()->postJson($this->requisitos($borradorSgr), [
            'clave' => 'a99', 'seccion' => 'A', 'fuente' => 'a12', 'codigo' => '99', 'texto' => 'Nuevo', 'condicion' => 'siempre',
            'etapa_id' => $this->etapa('planeacion')->id,
        ])->assertStatus(422)->assertJsonPath('errores', $mensaje)->assertJsonPath('errors', ['etapa_id' => $mensaje]);
    }

    public function test_los_errores_de_la_etapa_vienen_ademas_en_su_campo(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $sinEtapa = 'Elige la etapa de contratación del requisito.';

        // Solo la etapa: en la lista, para quien lee el aviso, y en errors.etapa_id, para el campo.
        $this->crearRequisito($borrador, ['etapa_id' => null])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => [$sinEtapa],
            'errors' => ['etapa_id' => [$sinEtapa]],
        ]);

        // Con otros errores: la lista los trae todos, la etapa primero; el campo, solo los suyos.
        $this->crearRequisito($borrador, ['etapa_id' => 99999, 'caracter' => RequisitoCatalogo::CONDICIONADO])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => ['La etapa de contratación con id 99999 no existe.', 'Un requisito condicionado necesita describir cuándo aplica.'],
            'errors' => ['etapa_id' => ['La etapa de contratación con id 99999 no existe.']],
        ]);

        // Sin error de etapa no hay errors.
        $this->crearRequisito($borrador, ['caracter' => RequisitoCatalogo::CONDICIONADO])->assertStatus(422)->assertExactJson([
            'mensaje' => 'El requisito no es válido.',
            'errores' => ['Un requisito condicionado necesita describir cuándo aplica.'],
        ]);
    }

    public function test_la_lista_de_requisitos_trae_la_etapa(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $cierre = $this->etapa('cierre');
        $this->crearRequisito($borrador, ['etapa_id' => $cierre->id])->assertCreated();

        $fila = $this->comoAdmin()->getJson($this->requisitos($borrador))->assertOk()->json('0');

        $this->assertSame($cierre->id, $fila['etapa_id']);
        $this->assertSame(['id' => $cierre->id, 'clave' => 'cierre', 'nombre' => 'Cierre y apertura de ofertas', 'orden' => 7, 'activo' => true], $fila['etapa']);
    }

    // ------------------------------------------------------------- publicar, borrador y copia

    public function test_publicar_exige_modalidades_o_todas(): void
    {
        $plantilla = $this->plantilla(['modalidad_ids' => []]);
        $borrador = $this->borrador($plantilla);
        $this->crearRequisito($borrador)->assertCreated();

        $this->publicar($borrador)->assertStatus(422)
            ->assertJsonPath('mensaje', 'Asocia la plantilla de contratación a sus modalidades, o marca "Todas las modalidades", antes de publicar.');

        $plantilla->update(['todas_modalidades' => true]);
        $this->publicar($borrador)->assertOk()->assertJsonPath('estado', 'publicada');
    }

    public function test_publicar_exige_la_etapa_de_cada_requisito_activo(): void
    {
        $borrador = $this->borrador($this->plantilla());
        $this->crearRequisito($borrador, ['texto' => 'Estudios previos'])->assertCreated();
        $this->crearRequisito($borrador, ['texto' => 'CDP'])->assertCreated();
        $this->crearRequisito($borrador, ['texto' => 'Retirado', 'activo' => false])->assertCreated();
        // Lo que dejaría un requisito anterior a las etapas, o una copia de datos sin ellas.
        $borrador->requisitos()->whereIn('texto', ['CDP', 'Retirado'])->update(['etapa_id' => null]);

        $this->publicar($borrador)->assertStatus(422)
            ->assertJsonPath('mensaje', 'La versión tiene requisitos sin etapa de contratación y no puede publicarse.')
            ->assertJsonPath('errores', ['CDP: elige su etapa de contratación.']);

        $borrador->requisitos()->where('texto', 'CDP')->update(['etapa_id' => $this->etapa('planeacion')->id]);
        $this->publicar($borrador)->assertOk()->assertJsonPath('estado', 'publicada');
    }

    public function test_un_borrador_nuevo_copia_la_etapa_de_cada_requisito(): void
    {
        $plantilla = $this->plantilla();
        $primero = $this->borrador($plantilla);
        $this->crearRequisito($primero, ['etapa_id' => $this->etapa('cierre')->id])->assertCreated();
        $this->publicar($primero)->assertOk();

        $segundo = app(CatalogoService::class)->crearBorrador('Ajustes', $plantilla);

        $this->assertSame([$this->etapa('cierre')->id], $segundo->requisitos()->pluck('etapa_id')->all());
    }

    public function test_duplicar_conserva_el_uso_las_modalidades_y_las_etapas(): void
    {
        $minima = $this->modalidad('minima_cuantia');
        $plantilla = $this->plantilla(['modalidad_ids' => [$this->modalidad('licitacion_publica')->id, $minima->id]]);
        $borrador = $this->borrador($plantilla);
        $this->crearRequisito($borrador, ['etapa_id' => $this->etapa('adendas')->id])->assertCreated();
        $this->publicar($borrador)->assertOk();
        $this->etapa('adendas')->update(['activo' => false]);
        $minima->update(['activo' => false]);

        $r = $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Licitación pública – sin plataforma'])
            ->assertCreated()
            ->assertJsonPath('uso', 'contratacion')
            ->assertJsonPath('motor', 'simple')
            ->assertJsonPath('todas_modalidades', false)
            ->assertJsonPath('modalidades', [['id' => $this->modalidad('licitacion_publica')->id, 'nombre' => 'Licitación pública', 'activo' => true]]);

        $copia = PlantillaChecklist::findOrFail($r->json('id'));
        $this->assertSame([$this->etapa('adendas')->id], $this->borrador($copia)->requisitos()->pluck('etapa_id')->all());

        $plantilla->update(['todas_modalidades' => true]);
        $plantilla->modalidades()->sync([]);
        $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Copia con todas'])
            ->assertCreated()->assertJsonPath('todas_modalidades', true);
    }

    public function test_una_plantilla_de_contratacion_solo_usa_el_motor_simple(): void
    {
        $plantilla = $this->plantilla();
        $borrador = $this->borrador($plantilla);
        $this->crearRequisito($borrador)->assertCreated();
        $this->publicar($borrador)->assertOk();
        // Forzada en la base: la administración no deja crearla así.
        $plantilla->update(['motor' => PlantillaChecklist::MOTOR_SGR]);

        $this->comoAdmin()->postJson("/GestPro/plantillas/{$plantilla->id}/duplicar", ['nombre' => 'Copia SGR', 'motor' => 'sgr'])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Una plantilla de contratación solo puede usar el motor simple.');
    }

    public function test_duplicar_una_de_viabilizacion_la_deja_de_viabilizacion(): void
    {
        $sgr = PlantillaChecklist::sgr();
        $requisito = $sgr->versionVigente()->requisitos()->orderBy('orden')->first();

        $this->comoAdmin()->postJson("/GestPro/plantillas/{$sgr->id}/duplicar", ['nombre' => 'Copia SGR', 'requisito_ids' => [$requisito->id]])
            ->assertCreated()
            ->assertJsonPath('uso', 'viabilizacion')
            ->assertJsonPath('modalidades', []);
        $this->assertNull(RequisitoCatalogo::where('texto', $requisito->texto)->latest('id')->value('etapa_id'));
    }
}
