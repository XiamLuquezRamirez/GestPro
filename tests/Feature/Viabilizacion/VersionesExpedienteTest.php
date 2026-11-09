<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\ChecklistHistorial;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\RequisitosPropios;
use App\Services\Viabilizacion\VersionesExpediente;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Aplicar a un expediente la versión nueva de una de sus plantillas, con vista previa. */
class VersionesExpedienteTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;
    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function como(Rol $rol = Rol::Gestor, ?User $usuario = null): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario ?? User::factory()->create(['rol' => $rol])));
    }

    /**
     * Plantilla simple con su versión 1 vigente.
     *
     * @param array<string, array{0: string, 1: ?string}> $requisitos clave => [texto, codigo_comun]
     */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $clave => [$texto, $codigoComun]) {
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => $clave, 'texto' => $texto, 'orden' => ++$orden,
                'codigo' => strtoupper($clave), 'codigo_comun' => $codigoComun, 'caracter' => RequisitoCatalogo::OBLIGATORIO,
            ]);
        }

        return $plantilla;
    }

    /** Publica una versión nueva de la plantilla, copiada de la vigente y editada con $editar. */
    private function publicarVersion(PlantillaChecklist $plantilla, ?callable $editar = null): CatalogoVersion
    {
        $catalogo = app(CatalogoService::class);
        $borrador = $catalogo->crearBorrador('Ajustes', $plantilla);
        if ($editar) {
            $editar(fn (string $clave) => $this->requisito($borrador, $clave), $borrador);
        }

        return $catalogo->publicar($borrador);
    }

    private function requisito(CatalogoVersion $version, string $clave): RequisitoCatalogo
    {
        return RequisitoCatalogo::where('catalogo_version_id', $version->id)->where('clave', $clave)->sole();
    }

    private function version(PlantillaChecklist $plantilla, int $numero): CatalogoVersion
    {
        return $plantilla->versiones()->where('numero', $numero)->sole();
    }

    /** Expediente SGR en fase III (8 requisitos generales), con las plantillas dadas además de la SGR. */
    private function expediente(array $otras = [], bool $confirmar = true, ?Proyecto $proyecto = null): ProyectoExpediente
    {
        if (! $proyecto) {
            $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
            $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);
        }
        $datos = [
            'fase_sgr' => 3,
            'plantilla_ids' => [$this->sgr->id, ...array_map(fn (PlantillaChecklist $p) => $p->id, $otras)],
            'principal_id' => $this->sgr->id,
        ];
        $expediente = $this->servicio->crear($proyecto, $datos, null);
        if ($confirmar) {
            $this->servicio->confirmarConfiguracion($expediente, $datos);
        }

        return $expediente->refresh();
    }

    /** Fila anclada al requisito de esa clave en la versión dada. */
    private function fila(ProyectoExpediente $expediente, CatalogoVersion $version, string $clave): ProyectoRequisito
    {
        return $expediente->requisitos()->where('requisito_catalogo_id', $this->requisito($version, $clave)->id)->sole();
    }

    private function origenes(ProyectoRequisito $fila): array
    {
        return $fila->origenes()->orderBy('requisitos_catalogo.id')->pluck('requisitos_catalogo.id')->map(fn ($id) => (int) $id)->all();
    }

    private function versionDe(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): array
    {
        return DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->where('expediente_id', $expediente->id)->where('catalogo_versiones.plantilla_id', $plantilla->id)
            ->get(['catalogo_version_id', 'es_principal'])
            ->map(fn ($p) => ['catalogo_version_id' => (int) $p->catalogo_version_id, 'es_principal' => (bool) $p->es_principal])
            ->all();
    }

    private function aplicar(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, mixed $versionId, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva", ['version_id' => $versionId]);
    }

    private function vistaPrevia(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, Rol $rol = Rol::Gestor)
    {
        return $this->como($rol)->getJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$plantilla->id}/version-nueva");
    }

    private function plantillaEn($respuesta, string $ruta, PlantillaChecklist $plantilla): array
    {
        return collect($respuesta->json($ruta))->firstWhere('plantilla_id', $plantilla->id);
    }

    /** Todo lo que aplicar podría cambiar del expediente. */
    private function estado(ProyectoExpediente $expediente): array
    {
        $filas = $expediente->requisitos()->select('id');

        return [
            'expediente' => (array) DB::table('proyectos_expedientes')->where('id', $expediente->id)->first(),
            'filas' => DB::table('proyectos_requisitos')->where('expediente_id', $expediente->id)->orderBy('id')->get()->map(fn ($f) => (array) $f)->all(),
            'origenes' => DB::table('proyecto_requisito_origenes')->whereIn('proyecto_requisito_id', $filas)
                ->orderBy('proyecto_requisito_id')->orderBy('requisito_catalogo_id')->get()->map(fn ($o) => (array) $o)->all(),
            'plantillas' => DB::table('expediente_plantillas')->where('expediente_id', $expediente->id)
                ->orderBy('catalogo_version_id')->get()->map(fn ($p) => (array) $p)->all(),
            'historial' => DB::table('checklist_historial')->where('expediente_id', $expediente->id)->orderBy('id')->get()->map(fn ($h) => (array) $h)->all(),
        ];
    }

    // --------------------------------------------------------------- cabecera

    public function test_la_cabecera_ofrece_la_version_nueva_de_una_plantilla_activa(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $detalle = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$expediente->id}")->assertOk();
        $listado = fn () => $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$expediente->proyecto_id}/expedientes")->assertOk();

        // Misma versión: no hay nada que ofrecer.
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $agua)['version_nueva']);
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $this->sgr)['version_nueva']);

        $v2 = $this->publicarVersion($agua);
        $esperada = ['id' => $v2->id, 'numero' => 2];
        $this->assertSame($esperada, $this->plantillaEn($detalle(), 'plantillas', $agua)['version_nueva']);
        $this->assertSame(1, $this->plantillaEn($detalle(), 'plantillas', $agua)['version']['numero']);
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $this->sgr)['version_nueva']);
        $this->assertSame($esperada, $this->plantillaEn($listado(), 'expedientes.0.plantillas', $agua)['version_nueva']);

        // Inactiva: no se ofrece.
        $agua->update(['activo' => false]);
        $this->assertNull($this->plantillaEn($detalle(), 'plantillas', $agua)['version_nueva']);
        $this->assertNull($this->plantillaEn($listado(), 'expedientes.0.plantillas', $agua)['version_nueva']);

        // Aplicada: el expediente ya usa la vigente.
        $agua->update(['activo' => true]);
        $aplicada = $this->aplicar($expediente, $agua, $v2->id)->assertOk();
        $this->assertSame(['id' => $v2->id, 'numero' => 2], $this->plantillaEn($aplicada, 'plantillas', $agua)['version']);
        $this->assertNull($this->plantillaEn($aplicada, 'plantillas', $agua)['version_nueva']);
        $this->assertNull($this->plantillaEn($listado(), 'expedientes.0.plantillas', $agua)['version_nueva']);
    }

    public function test_el_listado_del_proyecto_calcula_las_versiones_nuevas_sin_una_consulta_por_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $proyecto = $this->expediente([$agua])->proyecto;
        $this->publicarVersion($agua);
        $contar = function () use ($proyecto) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->servicio->presentarDeProyecto($proyecto->fresh());
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $contar();

        foreach (range(1, 2) as $i) {
            ProyectoExpediente::where('proyecto_id', $proyecto->id)->update(['estado' => ProyectoExpediente::RADICADO]);
            $this->expediente([$agua], proyecto: $proyecto);
        }

        $this->assertSame($conUno, $contar());
    }

    // ---------------------------------------------------------------- aplicar

    public function test_aplicar_una_version_sgr_conserva_el_trabajo_de_lo_que_sigue(): void
    {
        $expediente = $this->expediente();
        $v1 = $this->version($this->sgr, 1);
        $responsable = Responsable::create(['nombre' => 'Juan Pérez', 'activo' => true]);
        $a2 = $this->fila($expediente, $v1, 'a2');
        $this->servicio->marcar($expediente, $this->fila($expediente, $v1, 'a1'), ['cumplido' => true], null);
        $this->servicio->marcar($expediente, $a2, ['cumplido' => true, 'observacion' => 'Revisado', 'responsable_id' => $responsable->id], null);
        $a2->refresh()->update(['soporte_archivo' => "expedientes/{$expediente->id}/a2.pdf", 'soporte_nombre' => 'a2.pdf']);
        $a8 = $this->fila($expediente, $v1, 'a8');
        $this->servicio->marcar($expediente, $a8, ['cumplido' => true, 'observacion' => 'Ya no aplica'], null);

        // v2: a1 cambia su texto, a8 sale de la fase III y a6 entra.
        $v2 = $this->publicarVersion($this->sgr, function ($req) {
            $req('a1')->update(['texto' => 'Texto v2']);
            $req('a8')->update(['condicion' => 'fase=1|2']);
            $req('a6')->update(['condicion' => 'fase=3']);
        });
        $usuario = User::factory()->create(['rol' => Rol::Gestor]);

        $r = $this->como(usuario: $usuario)->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$this->sgr->id}/version-nueva", ['version_id' => $v2->id])
            ->assertOk()
            ->assertJsonPath('progreso', ['total' => 8, 'cumplidos' => 2, 'porcentaje' => 25])
            ->assertJsonPath('retenidos', 1);
        $this->assertSame('Texto v2', collect($r->json('requisitos'))->firstWhere('requisito.clave', 'a1')['requisito']['texto']);

        // a2 sigue: la misma fila, anclada en v2, con todo su trabajo.
        $a2 = $a2->fresh();
        $this->assertSame($this->requisito($v2, 'a2')->id, $a2->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v2, 'a2')->id], $this->origenes($a2));
        $this->assertTrue($a2->aplica);
        $this->assertTrue($a2->cumplido);
        $this->assertSame('Revisado', $a2->observacion);
        $this->assertSame($responsable->id, $a2->responsable_id);
        $this->assertSame('a2.pdf', $a2->soporte_nombre);

        // a6 nace pendiente.
        $a6 = $this->fila($expediente, $v2, 'a6');
        $this->assertTrue($a6->aplica);
        $this->assertSame('pendiente', $a6->estadoVisible());

        // a8 queda retirada con su trabajo.
        $a8 = $a8->fresh();
        $this->assertFalse($a8->aplica);
        $this->assertTrue($a8->cumplido);
        $this->assertSame('Ya no aplica', $a8->observacion);
        $this->assertSame([], $this->origenes($a8));

        $this->assertSame([['catalogo_version_id' => $v2->id, 'es_principal' => true]], $this->versionDe($expediente, $this->sgr));
        $evento = ChecklistHistorial::where('expediente_id', $expediente->id)->where('evento', 'version_aplicada')->sole();
        $this->assertSame('expediente', $evento->entidad_tipo);
        $this->assertSame(['plantilla_id' => $this->sgr->id, 'plantilla' => 'SGR', 'version' => 1, 'catalogo_version_id' => $v1->id], $evento->antes);
        $this->assertSame(['plantilla_id' => $this->sgr->id, 'plantilla' => 'SGR', 'version' => 2, 'catalogo_version_id' => $v2->id], $evento->despues);
        $this->assertSame($usuario->id, $evento->usuario_id);
    }

    public function test_una_fila_sin_codigo_comun_se_reconoce_por_su_clave(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null], 'licencia' => ['Licencia', null]]);
        $expediente = $this->expediente([$agua]);
        $v1 = $this->version($agua, 1);
        $planos = $this->fila($expediente, $v1, 'planos');
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true, 'observacion' => 'Firmados'], null);
        $filas = $expediente->requisitos()->count();

        $v2 = $this->publicarVersion($agua, fn ($req) => $req('licencia')->update(['texto' => 'Licencia de construcción']));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);

        // Con otra llave (req:{id}), sincronizar sola habría creado otra fila y retirado esta.
        $planos = $planos->fresh();
        $this->assertSame($this->requisito($v2, 'planos')->id, $planos->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v2, 'planos')->id], $this->origenes($planos));
        $this->assertTrue($planos->aplica);
        $this->assertTrue($planos->cumplido);
        $this->assertSame('Firmados', $planos->observacion);
        $this->assertSame($filas, $expediente->requisitos()->count());
        $this->assertSame(0, $expediente->requisitos()->where('aplica', false)->count());
        $this->assertSame([['catalogo_version_id' => $v2->id, 'es_principal' => false]], $this->versionDe($expediente, $agua));
    }

    public function test_una_fila_cuyo_codigo_comun_cambia_conserva_su_trabajo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', 'planos']]);
        $expediente = $this->expediente([$agua]);
        $planos = $this->fila($expediente, $this->version($agua, 1), 'planos');
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true], null);

        $v2 = $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['codigo_comun' => 'planos-firmados']));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);

        $planos = $planos->fresh();
        $this->assertSame($this->requisito($v2, 'planos')->id, $planos->requisito_catalogo_id);
        $this->assertTrue($planos->aplica);
        $this->assertTrue($planos->cumplido);
        $this->assertSame(0, $expediente->requisitos()->where('aplica', false)->count());
    }

    public function test_al_tomar_un_codigo_comun_ya_usado_conserva_la_llave_la_fila_con_trabajo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v1Sgr = $this->version($this->sgr, 1);
        $planos = $this->fila($expediente, $this->version($agua, 1), 'planos');
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true, 'observacion' => 'Firmados'], null);
        // La fila SGR a1, sin trabajo, tiene un id menor que planos.
        $a1 = $this->fila($expediente, $v1Sgr, 'a1');
        $this->assertLessThan($planos->id, $a1->id);
        $progresoAntes = $this->servicio->progresoDe($expediente);

        // v2: planos toma el código común de a1. Al re-anclar, las dos filas quedan con la llave a1.
        $v2 = $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['codigo_comun' => 'a1']));

        $vista = $this->vistaPrevia($expediente, $agua)->assertOk();
        // Deja de aplicar solo a1, sin trabajo: planos no aparece como retirada con trabajo.
        $this->assertSame(
            [['id' => $a1->id, 'con_trabajo' => false]],
            collect($vista->json('retirados'))->map(fn (array $f) => ['id' => $f['id'], 'con_trabajo' => $f['con_trabajo']])->all()
        );
        $this->assertSame(1, $vista->json('progreso_despues.cumplidos'));

        $this->aplicar($expediente, $agua, $v2->id)->assertOk()
            ->assertJsonPath('progreso', $vista->json('progreso_despues'))
            ->assertJsonPath('retenidos', 0);

        // planos conserva la llave, con su trabajo y los dos orígenes; a1, vacía, se retira.
        $planos = $planos->fresh();
        $this->assertTrue($planos->aplica);
        $this->assertTrue($planos->cumplido);
        $this->assertSame('Firmados', $planos->observacion);
        $this->assertSame($this->requisito($v2, 'planos')->id, $planos->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v1Sgr, 'a1')->id, $this->requisito($v2, 'planos')->id], $this->origenes($planos));
        $a1 = $a1->fresh();
        $this->assertFalse($a1->aplica);
        $this->assertSame([], $this->origenes($a1));
        $progresoDespues = $this->servicio->progresoDe($expediente);
        $this->assertSame($progresoAntes['cumplidos'], $progresoDespues['cumplidos']);
        $this->assertGreaterThanOrEqual($progresoAntes['porcentaje'], $progresoDespues['porcentaje']);
    }

    public function test_una_fila_retirada_sin_codigo_comun_se_reancla_y_al_volver_recupera_su_trabajo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null], 'licencia' => ['Licencia', null]]);
        $expediente = $this->expediente([$agua]);
        $licencia = $this->fila($expediente, $this->version($agua, 1), 'licencia');
        $this->servicio->marcar($expediente, $licencia, ['cumplido' => true, 'observacion' => 'Radicada'], null);
        $filas = $expediente->requisitos()->count();

        // v2 desactiva licencia: la fila sale, anclada en v2, con su trabajo.
        $v2 = $this->publicarVersion($agua, fn ($req) => $req('licencia')->update(['activo' => false]));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);
        $licencia = $licencia->fresh();
        $this->assertFalse($licencia->aplica);
        $this->assertTrue($licencia->cumplido);
        $this->assertSame($this->requisito($v2, 'licencia')->id, $licencia->requisito_catalogo_id);

        // v3 la reactiva: la retirada se re-ancla por clave y vuelve con su trabajo, sin otra fila.
        $v3 = $this->publicarVersion($agua, fn ($req) => $req('licencia')->update(['activo' => true]));
        $this->vistaPrevia($expediente, $agua)->assertOk()
            ->assertJsonPath('nuevos.0.id', $licencia->id)
            ->assertJsonCount(1, 'nuevos');
        $this->aplicar($expediente, $agua, $v3->id)->assertOk()->assertJsonPath('retenidos', 0);

        $licencia = $licencia->fresh();
        $this->assertSame($this->requisito($v3, 'licencia')->id, $licencia->requisito_catalogo_id);
        $this->assertSame([$this->requisito($v3, 'licencia')->id], $this->origenes($licencia));
        $this->assertTrue($licencia->aplica);
        $this->assertTrue($licencia->cumplido);
        $this->assertSame('Radicada', $licencia->observacion);
        $this->assertSame($filas, $expediente->requisitos()->count());
    }

    public function test_un_requisito_propio_no_cambia(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $propio = app(RequisitosPropios::class)->crear($expediente, ['titulo' => 'Acta de socialización'], null);
        $this->servicio->marcar($expediente, $propio, ['cumplido' => true, 'observacion' => 'Firmada'], null);
        $antes = $propio->fresh()->getAttributes();

        $v2 = $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['texto' => 'Planos v2']));
        app(VersionesExpediente::class)->aplicar($expediente, $agua, $v2->id, null);

        $this->assertSame($antes, $propio->fresh()->getAttributes());
        $this->assertSame([], $this->origenes($propio));
    }

    public function test_se_aplica_tambien_en_un_borrador_sin_configurar(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua], confirmar: false);
        $v2 = $this->publicarVersion($agua);

        $this->aplicar($expediente, $agua, $v2->id)->assertOk()->assertJsonPath('configurado', false);
        $this->assertSame([['catalogo_version_id' => $v2->id, 'es_principal' => false]], $this->versionDe($expediente, $agua));
    }

    // ----------------------------------------------------------- vista previa

    public function test_la_vista_previa_no_cambia_nada_y_lista_lo_que_cambiaria(): void
    {
        $expediente = $this->expediente();
        $v1 = $this->version($this->sgr, 1);
        // a5 (comp=suelo) entra y sale: queda retirada, sin trabajo.
        $suelo = (int) DB::table('requisitos_componentes')->where('clave', 'suelo')->value('id');
        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 3, 'componente_ids' => [$suelo]]);
        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 3]);
        $a5 = $this->fila($expediente, $v1, 'a5');
        $this->assertFalse($a5->aplica);
        $a1 = $this->fila($expediente, $v1, 'a1');
        $a8 = $this->fila($expediente, $v1, 'a8');
        $a9 = $this->fila($expediente, $v1, 'a9');
        $this->servicio->marcar($expediente, $a1, ['cumplido' => true], null);
        $this->servicio->marcar($expediente, $a8, ['cumplido' => true], null);

        // v2: a1 cambia su texto, a8 y a9 salen de la fase III, a5 y a6 entran.
        $v2 = $this->publicarVersion($this->sgr, function ($req) {
            $req('a1')->update(['texto' => 'Texto v2']);
            $req('a8')->update(['condicion' => 'fase=1|2']);
            $req('a9')->update(['condicion' => 'fase=1|2']);
            $req('a5')->update(['condicion' => 'siempre']);
            $req('a6')->update(['condicion' => 'fase=3']);
        });
        $antes = $this->estado($expediente);
        $resumen = fn (ProyectoRequisito $fila, RequisitoCatalogo $r) => [
            'id' => $fila->id, 'codigo' => $r->codigo, 'texto' => $r->texto, 'caracter' => 'obligatorio',
        ];

        $r = $this->vistaPrevia($expediente, $this->sgr)->assertOk();

        $this->assertSame($antes, $this->estado($expediente));
        $r->assertJsonPath('plantilla', ['id' => $this->sgr->id, 'nombre' => 'SGR'])
            ->assertJsonPath('version_actual', ['id' => $v1->id, 'numero' => 1])
            ->assertJsonPath('version_nueva.id', $v2->id)
            ->assertJsonPath('version_nueva.numero', 2)
            ->assertJsonPath('version_nueva.descripcion', 'Ajustes')
            ->assertJsonPath('version_nueva.fecha_publicacion', $v2->fecha_publicacion->toIso8601String())
            ->assertJsonPath('siguen', 5)
            ->assertJsonPath('progreso_antes', ['total' => 8, 'cumplidos' => 2, 'porcentaje' => 25])
            ->assertJsonPath('progreso_despues', ['total' => 8, 'cumplidos' => 1, 'porcentaje' => 13]);
        // En el orden del checklist. La retirada que vuelve conserva su id; la que nace no tiene.
        $this->assertSame([
            $resumen($a5, $this->requisito($v2, 'a5')),
            ['id' => null, 'codigo' => $this->requisito($v2, 'a6')->codigo, 'texto' => $this->requisito($v2, 'a6')->texto, 'caracter' => 'obligatorio'],
        ], $r->json('nuevos'));
        $this->assertSame([
            [...$resumen($a8, $this->requisito($v1, 'a8')), 'con_trabajo' => true],
            [...$resumen($a9, $this->requisito($v1, 'a9')), 'con_trabajo' => false],
        ], $r->json('retirados'));
        $this->assertSame([[
            'antes' => $resumen($a1, $this->requisito($v1, 'a1')),
            'despues' => $resumen($a1, $this->requisito($v2, 'a1')),
        ]], $r->json('cambian'));
        $this->assertSame('Texto v2', $r->json('cambian.0.despues.texto'));

        // Aplicar hace lo que mostró la vista previa.
        $this->aplicar($expediente, $this->sgr, $v2->id)->assertOk()->assertJsonPath('progreso', $r->json('progreso_despues'));
    }

    public function test_la_vista_previa_no_toca_el_modelo_de_quien_la_pide(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $this->publicarVersion($agua, fn ($req) => $req('planos')->update(['texto' => 'Planos v2']));
        $expediente->load('versionesPlantilla');
        $atributos = $expediente->getAttributes();

        $vista = app(VersionesExpediente::class)->vistaPrevia($expediente, $agua);

        $this->assertSame('Planos v2', $vista['cambian'][0]['despues']['texto']);
        $this->assertSame($atributos, $expediente->getAttributes());
        $this->assertTrue($expediente->relationLoaded('versionesPlantilla'));
        $this->assertSame(1, $expediente->versionesPlantilla->firstWhere('plantilla_id', $agua->id)->numero);
    }

    public function test_una_propia_aparece_en_la_vista_previa_con_su_titulo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        app(RequisitosPropios::class)->crear($expediente, ['titulo' => 'Acta de socialización'], null);
        $this->publicarVersion($agua);

        // Ningún cambio de la plantilla: siguen las 8 SGR, planos y la propia.
        $this->vistaPrevia($expediente, $agua)->assertOk()
            ->assertJsonPath('nuevos', [])->assertJsonPath('retirados', [])->assertJsonPath('cambian', [])
            ->assertJsonPath('siguen', 10);
    }

    // ---------------------------------------------------------------- rechazos

    public function test_un_expediente_radicado_no_admite_otra_version(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);
        $expediente->update(['estado' => ProyectoExpediente::RADICADO]);
        $mensaje = 'El expediente está radicado: su checklist está congelado y no admite cambios.';

        $this->vistaPrevia($expediente, $agua)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->assertSame($this->version($agua, 1)->id, $this->versionDe($expediente, $agua)[0]['catalogo_version_id']);
    }

    public function test_la_plantilla_debe_estar_en_el_expediente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente();
        $v2 = $this->publicarVersion($agua);
        $mensaje = 'La plantilla "Obras de agua" no está en este expediente.';

        $this->vistaPrevia($expediente, $agua)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->assertSame([], $this->versionDe($expediente, $agua));
    }

    public function test_sin_version_nueva_no_hay_nada_que_aplicar(): void
    {
        $expediente = $this->expediente();
        $mensaje = 'El expediente ya usa la versión vigente de "SGR".';

        $this->vistaPrevia($expediente, $this->sgr)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $this->sgr, $this->version($this->sgr, 1)->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
    }

    public function test_una_plantilla_inactiva_no_admite_otra_version(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);
        $agua->update(['activo' => false]);
        $mensaje = 'La plantilla "Obras de agua" está inactiva: no puedes aplicarle una versión nueva.';

        $this->vistaPrevia($expediente, $agua)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
    }

    public function test_una_version_distinta_de_la_vigente_se_rechaza(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);
        // Se publica otra mientras se revisaba la v2.
        $v3 = $this->publicarVersion($agua);
        $antes = $this->estado($expediente);

        $this->aplicar($expediente, $agua, $v2->id)->assertStatus(422)
            ->assertJsonPath('mensaje', 'La plantilla publicó otra versión mientras revisabas. Vuelve a abrir la vista previa.');
        $this->assertSame($antes, $this->estado($expediente));

        $this->vistaPrevia($expediente, $agua)->assertOk()->assertJsonPath('version_nueva.id', $v3->id);
    }

    public function test_la_version_debe_ser_un_numero_entero(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);

        foreach ([(string) $v2->id, true, 2.5, [$v2->id]] as $valor) {
            $this->aplicar($expediente, $agua, $valor)->assertStatus(422)
                ->assertJsonPath('errors.version_id', ['Revisa la versión: debe ser un número entero.']);
        }
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/plantillas/{$agua->id}/version-nueva", [])
            ->assertStatus(422)->assertJsonPath('errors.version_id', ['Indica la versión que vas a aplicar.']);
        $this->assertSame($this->version($agua, 1)->id, $this->versionDe($expediente, $agua)[0]['catalogo_version_id']);
    }

    public function test_sin_permiso_de_edicion_no_se_previsualiza_ni_se_aplica(): void
    {
        $agua = $this->plantilla('Obras de agua', ['planos' => ['Planos firmados', null]]);
        $expediente = $this->expediente([$agua]);
        $v2 = $this->publicarVersion($agua);

        $this->vistaPrevia($expediente, $agua, Rol::Consulta)->assertForbidden();
        $this->aplicar($expediente, $agua, $v2->id, Rol::Consulta)->assertForbidden();
        $this->assertSame($this->version($agua, 1)->id, $this->versionDe($expediente, $agua)[0]['catalogo_version_id']);
    }
}
