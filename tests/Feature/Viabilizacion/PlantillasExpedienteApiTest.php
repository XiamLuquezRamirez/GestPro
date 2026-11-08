<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Plantillas que se ofrecen a un proyecto y selección de las de un expediente. */
class PlantillasExpedienteApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;
    private int $agua;
    private int $vias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
        $this->agua = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->vias = DB::table('sectores')->insertGetId(['nombre' => 'Vías', 'activo' => true]);
    }

    private function fuente(string $clave): int
    {
        return (int) FuenteFinanciacion::where('clave', $clave)->value('id');
    }

    private function proyecto(array $fuentes = ['sgr'], ?int $sector = null): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $proyecto->fuentes()->sync(array_map(fn ($c) => $this->fuente($c), $fuentes));

        return $proyecto;
    }

    /** Plantilla simple con su versión 1 vigente y los requisitos indicados (texto => activo). */
    private function plantilla(string $nombre, array $asociacion = [], array $requisitos = ['Requisito' => true], array $datos = []): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre,
            'descripcion' => "Descripción de {$nombre}",
            'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'activo' => true,
            'todas_fuentes' => ! isset($asociacion['fuentes']),
            'todos_sectores' => ! isset($asociacion['sectores']),
            ...$datos,
        ]);
        $plantilla->fuentes()->sync(array_map(fn ($c) => $this->fuente($c), $asociacion['fuentes'] ?? []));
        $plantilla->sectores()->sync($asociacion['sectores'] ?? []);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $activo) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto,
                'orden' => $orden, 'activo' => $activo, 'codigo_comun' => "{$nombre}-{$orden}",
            ]);
        }

        return $plantilla;
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function compatibles(Proyecto $proyecto, ?int $expediente = null)
    {
        $consulta = $expediente ? "?expediente={$expediente}" : '';

        return $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$proyecto->id}/plantillas-compatibles{$consulta}");
    }

    private function ofrecida($respuesta, PlantillaChecklist $plantilla): ?array
    {
        return collect($respuesta->json('plantillas'))->firstWhere('id', $plantilla->id);
    }

    private function nombres($respuesta): array
    {
        return collect($respuesta->json('plantillas'))->pluck('nombre')->sort()->values()->all();
    }

    private function servicio(): PlantillasExpediente
    {
        return app(PlantillasExpediente::class);
    }

    /** Expediente SGR creado como hoy: con la plantilla SGR como principal. */
    private function expediente(Proyecto $proyecto): ProyectoExpediente
    {
        return app(ExpedienteService::class)->crear($proyecto, ['fase_sgr' => 3], null);
    }

    private function errores(callable $accion): array
    {
        try {
            $accion();
            $this->fail('La selección debía rechazarse.');
        } catch (ReglaNegocioException $e) {
            return $e->errores();
        }
    }

    // --------------------------------------------------------- compatibles

    public function test_ofrece_las_plantillas_de_alguna_fuente_del_proyecto_o_de_todas(): void
    {
        $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $this->plantilla('Nación', ['fuentes' => ['pgn']]);
        $this->plantilla('General');

        $r = $this->compatibles($this->proyecto(['sgr', 'recursos_propios'], $this->agua))->assertOk();

        $this->assertSame(['General', 'Propios', 'SGR'], $this->nombres($r));
        $r->assertJsonPath('sin_sector', false);
    }

    public function test_ofrece_las_plantillas_del_sector_del_proyecto_o_de_todos(): void
    {
        $this->plantilla('Acueductos', ['sectores' => [$this->agua]]);
        $this->plantilla('Carreteras', ['sectores' => [$this->vias]]);

        $r = $this->compatibles($this->proyecto(['sgr'], $this->agua))->assertOk();

        $this->assertSame(['Acueductos', 'SGR'], $this->nombres($r));
    }

    public function test_sin_sector_solo_ofrece_las_de_todos_los_sectores_y_lo_avisa(): void
    {
        $this->plantilla('Acueductos', ['sectores' => [$this->agua]]);
        $this->plantilla('General');

        $r = $this->compatibles($this->proyecto(['sgr']))->assertOk();

        $r->assertJsonPath('sin_sector', true);
        $this->assertSame(['General', 'SGR'], $this->nombres($r));
    }

    public function test_no_ofrece_plantillas_inactivas_ni_sin_version_publicada(): void
    {
        $this->plantilla('Inactiva', [], ['Requisito' => true], ['activo' => false]);
        $borrador = PlantillaChecklist::create(['nombre' => 'Sin publicar', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'todas_fuentes' => true, 'todos_sectores' => true]);
        $borrador->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::BORRADOR, 'vigente' => false]);

        $r = $this->compatibles($this->proyecto(['sgr'], $this->agua))->assertOk();

        $this->assertSame(['SGR'], $this->nombres($r));
    }

    public function test_cada_plantilla_trae_sus_datos_y_los_requisitos_activos_de_la_vigente(): void
    {
        $plantilla = $this->plantilla('Obras de agua', ['fuentes' => ['recursos_propios'], 'sectores' => [$this->agua]], [
            'Licencia' => true, 'Planos' => true, 'Retirado' => false,
        ]);

        $r = $this->compatibles($this->proyecto(['recursos_propios'], $this->agua))->assertOk();

        $version = $plantilla->versionVigente();
        $this->assertSame([
            'id' => $plantilla->id,
            'nombre' => 'Obras de agua',
            'descripcion' => 'Descripción de Obras de agua',
            'motor' => 'simple',
            'es_sgr' => false,
            'activa' => true,
            'compatible' => true,
            'fuentes' => [['id' => $this->fuente('recursos_propios'), 'nombre' => 'Recursos propios']],
            'sectores' => [['id' => $this->agua, 'nombre' => 'Agua Potable']],
            'todas_fuentes' => false,
            'todos_sectores' => false,
            'requisitos' => 2,
            'version' => ['id' => $version->id, 'numero' => 1],
            'en_expediente' => null,
        ], $this->ofrecida($r, $plantilla));

        $sgr = $this->ofrecida($this->compatibles($this->proyecto(['sgr'], $this->agua)), $this->sgr);
        $this->assertTrue($sgr['es_sgr']);
        $this->assertSame('sgr', $sgr['motor']);
        $this->assertSame(207, $sgr['requisitos']);
    }

    public function test_con_expediente_agrega_las_que_ya_tiene_aunque_ya_no_sean_compatibles(): void
    {
        $proyecto = $this->proyecto(['sgr', 'recursos_propios'], $this->agua);
        $propios = $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $expediente = $this->expediente($proyecto);
        $this->servicio()->guardar($expediente, $this->servicio()->validar(
            ['plantilla_ids' => [$this->sgr->id, $propios->id], 'principal_id' => $this->sgr->id], $proyecto, $expediente
        ));
        $anterior = $propios->versionVigente();

        // El proyecto deja la fuente, la plantilla se desactiva y publica una versión nueva.
        $proyecto->fuentes()->sync([$this->fuente('sgr')]);
        $propios->update(['activo' => false]);
        $anterior->update(['vigente' => false]);
        $nueva = $propios->versiones()->create(['numero' => 2, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        $sinExpediente = $this->compatibles($proyecto)->assertOk();
        $this->assertNull($this->ofrecida($sinExpediente, $propios));

        $r = $this->compatibles($proyecto, $expediente->id)->assertOk();
        $ofrecida = $this->ofrecida($r, $propios);
        $this->assertFalse($ofrecida['compatible']);
        $this->assertFalse($ofrecida['activa']);
        $this->assertSame(['id' => $nueva->id, 'numero' => 2], $ofrecida['version']);
        $this->assertSame(['version' => ['id' => $anterior->id, 'numero' => 1], 'es_principal' => false], $ofrecida['en_expediente']);
        $this->assertTrue($this->ofrecida($r, $this->sgr)['en_expediente']['es_principal']);
    }

    public function test_el_expediente_debe_ser_del_proyecto(): void
    {
        $otro = $this->expediente($this->proyecto(['sgr']));

        $this->compatibles($this->proyecto(['sgr']), $otro->id)
            ->assertNotFound()
            ->assertJsonPath('mensaje', 'El expediente no es de este proyecto.');
    }

    public function test_un_expediente_que_no_es_un_numero_se_rechaza_en_espanol(): void
    {
        $this->como(Rol::Consulta)
            ->getJson("/GestPro/proyectos/{$this->proyecto()->id}/plantillas-compatibles?expediente=abc")
            ->assertStatus(422)
            ->assertJsonPath('errors.expediente.0', 'Revisa el expediente: debe ser un número entero.');
    }

    public function test_un_proyecto_sin_fuentes_o_por_definir_solo_recibe_las_de_todas_las_fuentes(): void
    {
        $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $this->plantilla('General');

        $sinFuentes = $this->compatibles($this->proyecto([]))->assertOk();
        $porDefinir = $this->proyecto([]);
        $porDefinir->update(['financiacion_por_definir' => true]);

        $this->assertSame(['General'], $this->nombres($sinFuentes));
        $this->assertSame(['General'], $this->nombres($this->compatibles($porDefinir)->assertOk()));
    }

    public function test_exige_sesion(): void
    {
        $proyecto = $this->proyecto();

        $this->getJson("/GestPro/proyectos/{$proyecto->id}/plantillas-compatibles")->assertUnauthorized();
    }

    // ------------------------------------------------------------ selección

    public function test_rechaza_ids_inexistentes_e_incompatibles_inactivas_o_sin_version(): void
    {
        $proyecto = $this->proyecto(['sgr'], $this->agua);
        $nacion = $this->plantilla('Nación', ['fuentes' => ['pgn']]);
        $inactiva = $this->plantilla('Inactiva', [], ['Requisito' => true], ['activo' => false]);
        $sinVersion = PlantillaChecklist::create(['nombre' => 'Sin publicar', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'todas_fuentes' => true, 'todos_sectores' => true]);

        $errores = $this->errores(fn () => $this->servicio()->validar([
            'plantilla_ids' => [$this->sgr->id, 99999, $nacion->id, $inactiva->id, $sinVersion->id],
            'principal_id' => $this->sgr->id,
        ], $proyecto, null));

        $this->assertSame([
            'La plantilla con id 99999 no existe.',
            'La plantilla "Nación" no es compatible con las fuentes o el sector del proyecto.',
            'La plantilla "Inactiva" está inactiva.',
            'La plantilla "Sin publicar" no tiene una versión publicada.',
        ], $errores);
    }

    public function test_admite_como_maximo_una_plantilla_con_motor_sgr(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $copia = $this->plantilla('Copia SGR', [], ['Requisito' => true], ['motor' => PlantillaChecklist::MOTOR_SGR]);

        $errores = $this->errores(fn () => $this->servicio()->validar(
            ['plantilla_ids' => [$this->sgr->id, $copia->id], 'principal_id' => $this->sgr->id], $proyecto, null
        ));

        $this->assertSame(['Un expediente admite como máximo una plantilla con motor SGR.'], $errores);
    }

    public function test_los_ids_repetidos_cuentan_como_una_sola_plantilla(): void
    {
        $proyecto = $this->proyecto(['sgr']);

        $seleccion = $this->servicio()->validar(['plantilla_ids' => [$this->sgr->id, $this->sgr->id]], $proyecto, null);

        $this->assertSame([$this->sgr->id => $this->sgr->versionVigente()->id], $seleccion['versiones']);
        $this->assertSame($this->sgr->id, $seleccion['principal_id']);
        $this->assertSame($this->sgr->versionVigente()->id, $seleccion['version_sgr_id']);
    }

    public function test_versiones_de_pone_la_principal_primero_y_luego_por_plantilla(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $a = $this->plantilla('A');
        $b = $this->plantilla('B');
        $expediente = $this->expediente($proyecto);
        $this->servicio()->guardar($expediente, $this->servicio()->validar(
            ['plantilla_ids' => [$b->id, $this->sgr->id, $a->id], 'principal_id' => $b->id], $proyecto, $expediente
        ));

        $orden = $this->servicio()->versionesDe($expediente)->pluck('plantilla_id')->all();

        $otras = [$this->sgr->id, $a->id];
        sort($otras);
        $this->assertSame([$b->id, ...$otras], $orden);
        $this->assertGreaterThan($this->sgr->id, $b->id);
    }

    public function test_guardar_refresca_la_relacion_ya_cargada(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
        $expediente = $this->expediente($proyecto);
        $this->assertCount(1, $expediente->versionesPlantilla);

        $this->servicio()->guardar($expediente, $this->servicio()->validar(['plantilla_ids' => [$general->id]], $proyecto, $expediente));

        $this->assertSame([$general->versionVigente()->id], $expediente->versionesPlantilla->pluck('id')->all());
    }

    public function test_la_principal_debe_estar_entre_las_elegidas(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
        $validar = fn (array $datos) => $this->errores(fn () => $this->servicio()->validar($datos, $proyecto, null));

        $this->assertSame(['Elige cuál de las plantillas es la principal.'], $validar(['plantilla_ids' => [$this->sgr->id, $general->id]]));
        $this->assertSame(['La plantilla principal debe estar entre las elegidas.'], $validar(['plantilla_ids' => [$this->sgr->id], 'principal_id' => $general->id]));
        $this->assertSame(['La plantilla principal debe estar entre las elegidas.'], $validar(['plantilla_ids' => [], 'principal_id' => $general->id]));
    }

    public function test_una_sola_plantilla_es_la_principal_y_sin_plantillas_no_hay_principal(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');

        $una = $this->servicio()->validar(['plantilla_ids' => [$general->id]], $proyecto, null);
        $ninguna = $this->servicio()->validar(['plantilla_ids' => []], $proyecto, null);

        $this->assertSame($general->id, $una['principal_id']);
        $this->assertSame([$general->id => $general->versionVigente()->id], $una['versiones']);
        $this->assertNull($una['version_sgr_id']);
        $this->assertNull($ninguna['principal_id']);
        $this->assertSame([], $ninguna['versiones']);
    }

    public function test_guardar_registra_las_versiones_la_principal_y_la_version_sgr(): void
    {
        $proyecto = $this->proyecto(['sgr']);
        $general = $this->plantilla('General');
        $expediente = $this->expediente($proyecto);
        $sgrVersion = $this->sgr->versionVigente()->id;

        $this->servicio()->guardar($expediente, $this->servicio()->validar(
            ['plantilla_ids' => [$this->sgr->id, $general->id], 'principal_id' => $general->id], $proyecto, $expediente
        ));

        $this->assertEqualsCanonicalizing([
            ['catalogo_version_id' => $sgrVersion, 'es_principal' => 0],
            ['catalogo_version_id' => $general->versionVigente()->id, 'es_principal' => 1],
        ], DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)->get(['catalogo_version_id', 'es_principal'])
            ->map(fn ($f) => ['catalogo_version_id' => (int) $f->catalogo_version_id, 'es_principal' => (int) $f->es_principal])->all());
        $this->assertSame($sgrVersion, $expediente->refresh()->catalogo_version_id);

        // Sin la SGR, el expediente ya no tiene versión SGR.
        $this->servicio()->guardar($expediente, $this->servicio()->validar(['plantilla_ids' => [$general->id]], $proyecto, $expediente));

        $this->assertSame([$general->versionVigente()->id], DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)->pluck('catalogo_version_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNull($expediente->refresh()->catalogo_version_id);
    }

    public function test_una_plantilla_que_ya_tiene_el_expediente_conserva_su_version_aunque_ya_no_se_ofrezca(): void
    {
        $proyecto = $this->proyecto(['sgr', 'recursos_propios']);
        $propios = $this->plantilla('Propios', ['fuentes' => ['recursos_propios']]);
        $expediente = $this->expediente($proyecto);
        $datos = ['plantilla_ids' => [$this->sgr->id, $propios->id], 'principal_id' => $this->sgr->id];
        $this->servicio()->guardar($expediente, $this->servicio()->validar($datos, $proyecto, $expediente));
        $anterior = $propios->versionVigente()->id;

        $proyecto->fuentes()->sync([$this->fuente('sgr')]);
        $propios->update(['activo' => false]);
        $propios->versiones()->update(['vigente' => false]);
        $propios->versiones()->create(['numero' => 2, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);

        $seleccion = $this->servicio()->validar($datos, $proyecto, $expediente);

        $this->assertSame($anterior, $seleccion['versiones'][$propios->id]);
    }
}
