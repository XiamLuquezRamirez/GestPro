<?php

namespace Tests\Feature\Viabilizacion;

use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** El checklist de un expediente armado con varias plantillas, siempre con sincronizar. */
class ComposicionExpedienteTest extends TestCase
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

    private function proyecto(string $nombre = 'Acueducto veredal'): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => $nombre]);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $proyecto;
    }

    /**
     * Plantilla simple con su versión vigente. Cada requisito: [codigo_comun, caracter, activo?].
     *
     * @param array<string, array{0: ?string, 1: string, 2?: bool}> $requisitos texto => datos
     */
    private function plantilla(string $nombre, array $requisitos): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $datos) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $datos[0], 'caracter' => $datos[1], 'activo' => $datos[2] ?? true,
                'condicion_descripcion' => $datos[1] === RequisitoCatalogo::CONDICIONADO ? 'Si el proyecto lo necesita' : null,
            ]);
        }

        return $plantilla;
    }

    private function requisito(PlantillaChecklist $plantilla, string $texto): RequisitoCatalogo
    {
        return RequisitoCatalogo::where('catalogo_version_id', $plantilla->versionVigente()->id)->where('texto', $texto)->sole();
    }

    /** Expediente SGR (fase III sin nada: 8 requisitos generales), creado y confirmado como hoy. */
    private function expediente(array $config = ['fase_sgr' => 3]): ProyectoExpediente
    {
        $expediente = $this->servicio->crear($this->proyecto(), $config, null);
        $this->servicio->confirmarConfiguracion($expediente, $config);

        return $expediente;
    }

    /** Cambia las plantillas del expediente y recalcula, como lo hará la configuración. */
    private function componer(ProyectoExpediente $expediente, array $plantillas, ?PlantillaChecklist $principal = null): void
    {
        $seleccion = app(PlantillasExpediente::class)->validar([
            'plantilla_ids' => array_map(fn (PlantillaChecklist $p) => $p->id, $plantillas),
            'principal_id' => ($principal ?? $plantillas[0] ?? null)?->id,
        ], $expediente->proyecto, $expediente);
        app(PlantillasExpediente::class)->guardar($expediente, $seleccion);
        $this->servicio->sincronizar($expediente->refresh());
    }

    private function fila(ProyectoExpediente $expediente, RequisitoCatalogo $ancla): ProyectoRequisito
    {
        return $expediente->requisitos()->where('requisito_catalogo_id', $ancla->id)->sole();
    }

    private function origenes(ProyectoRequisito $fila): array
    {
        return $fila->origenes()->orderBy('requisitos_catalogo.id')->pluck('requisitos_catalogo.id')->map(fn ($id) => (int) $id)->all();
    }

    // ------------------------------------------------------------------ paridad

    private function id(string $tabla, string $clave, ?string $sector = null): int
    {
        $q = DB::table($tabla)->where('clave', $clave);
        if ($sector) {
            $q->where('sector_id', $this->id('requisitos_sectores', $sector));
        }

        return (int) $q->value('id');
    }

    private function configuracion(array $c): array
    {
        return [
            'fase_sgr' => $c['fase_sgr'],
            'sector_ids' => array_map(fn ($s) => $this->id('requisitos_sectores', $s), $c['sectores']),
            'tipo_ids' => array_map(function ($t) {
                [$sector, $clave] = explode('::', $t);

                return $this->id('requisitos_tipos', $clave, $sector);
            }, $c['tipos']),
            'componente_ids' => array_map(fn ($x) => $this->id('requisitos_componentes', $x), $c['componentes']),
            'tipologia_ids' => array_map(fn ($x) => $this->id('requisitos_tipologias', $x), $c['tipologias']),
        ];
    }

    /** Lo mismo que guardó el generador de tests/Fixtures/paridad_expediente_sgr.json. */
    private function foto(ProyectoExpediente $expediente): array
    {
        $filas = $expediente->requisitos()->with('requisito:id,clave')->orderBy('orden')->orderBy('id')->get();

        return [
            'filas' => $filas->map(fn ($f) => implode(':', [$f->requisito->clave, (int) $f->aplica, $f->orden, (int) $f->cumplido]))->all(),
            'caracteres' => $filas->pluck('caracter')->unique()->values()->all(),
            'estados' => $filas->pluck('estado_aplicabilidad')->unique()->values()->all(),
            'progreso' => $this->servicio->progresoDe($expediente),
            'retenidos' => $this->servicio->presentar($expediente)['retenidos'],
        ];
    }

    /** Cada fila que aplica tiene un solo origen, su ancla; las retiradas no tienen ninguno. */
    private function assertOrigenSgr(ProyectoExpediente $expediente, string $caso): void
    {
        foreach ($expediente->requisitos()->get() as $fila) {
            $this->assertSame($fila->aplica ? [$fila->requisito_catalogo_id] : [], $this->origenes($fila), "{$caso}: fila {$fila->id}");
        }
    }

    public function test_un_expediente_solo_sgr_queda_igual_que_antes_de_componer_plantillas(): void
    {
        $base = json_decode(file_get_contents(base_path('tests/Fixtures/paridad_evaluador_sgr.json')), true)['casos'];
        $esperados = json_decode(file_get_contents(base_path('tests/Fixtures/paridad_expediente_sgr.json')), true)['casos'];
        $this->assertCount(count($base), $esperados);

        foreach ($base as $i => $caso) {
            $esperado = $esperados[$i];
            $this->assertSame($caso['nombre'], $esperado['nombre']);
            $config = $this->configuracion($caso['configuracion']);
            $siguiente = $this->configuracion($base[($i + 1) % count($base)]['configuracion']);

            $expediente = $this->servicio->crear($this->proyecto("Caso {$i}"), $config, null);
            $this->servicio->confirmarConfiguracion($expediente, $config);
            foreach ($expediente->requisitos()->where('aplica', true)->orderBy('orden')->get()->values() as $k => $fila) {
                if ($k % 3 === 0) {
                    $this->servicio->marcar($expediente, $fila, ['cumplido' => true], null);
                }
            }
            $this->assertSame($esperado['inicial'], $this->foto($expediente), "{$caso['nombre']}: inicial");
            $this->assertOrigenSgr($expediente, $caso['nombre']);

            $this->servicio->actualizarConfiguracion($expediente, $siguiente);
            $this->assertSame($esperado['cambiada'], $this->foto($expediente->refresh()), "{$caso['nombre']}: cambiada");
            $this->assertOrigenSgr($expediente, $caso['nombre']);

            $this->servicio->actualizarConfiguracion($expediente, $config);
            $this->assertSame($esperado['restaurada'], $this->foto($expediente->refresh()), "{$caso['nombre']}: restaurada");
            $this->assertOrigenSgr($expediente, $caso['nombre']);
        }
    }

    // ------------------------------------------------------- código común

    public function test_dos_plantillas_con_el_mismo_codigo_comun_comparten_una_fila_con_el_caracter_mas_exigente(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Licencia ambiental' => ['licencia', 'opcional'], 'Planos' => [null, 'obligatorio']]);
        $vias = $this->plantilla('Vías', ['Licencia de la autoridad' => ['licencia', 'condicionado']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$agua, $vias]);

        $licencia = $this->fila($expediente, $this->requisito($agua, 'Licencia ambiental'));
        $this->assertSame(1, $expediente->requisitos()->where('aplica', true)->whereIn('requisito_catalogo_id', [
            $this->requisito($agua, 'Licencia ambiental')->id, $this->requisito($vias, 'Licencia de la autoridad')->id,
        ])->count());
        $this->assertTrue($licencia->aplica);
        $this->assertSame(RequisitoCatalogo::CONDICIONADO, $licencia->caracter);
        $this->assertSame([$this->requisito($agua, 'Licencia ambiental')->id, $this->requisito($vias, 'Licencia de la autoridad')->id], $this->origenes($licencia));
        $this->assertFalse($expediente->requisitos()->where('requisito_catalogo_id', $this->requisito($vias, 'Licencia de la autoridad')->id)->exists());
    }

    public function test_el_codigo_comun_se_comparte_con_la_sgr_y_solo_aporta_origen_lo_que_aplica(): void
    {
        // b1 (predios) no aplica en fase III sin componentes: la SGR no aporta origen.
        $predios = $this->plantilla('Predios', ['Estudio de títulos' => ['b1', 'obligatorio']]);
        $expediente = $this->expediente();
        $this->componer($expediente, [$this->sgr, $predios]);
        $estudio = $this->requisito($predios, 'Estudio de títulos');
        $b1 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'b1')->sole();

        $fila = $this->fila($expediente, $estudio);
        $this->assertSame([$estudio->id], $this->origenes($fila));

        // Con el componente, la SGR también lo pide: la misma fila suma su origen y conserva su ancla.
        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 2, 'componente_ids' => [$this->id('requisitos_componentes', 'predios')]]);

        $this->assertSame($fila->id, $this->fila($expediente, $estudio)->id);
        $this->assertSame([$b1->id, $estudio->id], $this->origenes($fila));
        $this->assertFalse($expediente->requisitos()->where('requisito_catalogo_id', $b1->id)->exists());
    }

    // ------------------------------------------------------- quitar y volver

    public function test_quitar_una_plantilla_retira_sus_filas_con_su_trabajo_y_volver_a_ponerla_las_recupera(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio']]);
        $expediente = $this->expediente();
        $this->componer($expediente, [$this->sgr, $agua]);
        $planos = $this->fila($expediente, $this->requisito($agua, 'Planos'));
        $this->servicio->marcar($expediente, $planos, ['cumplido' => true, 'observacion' => 'Firmados'], null);

        $this->componer($expediente, [$this->sgr]);

        $retirada = $planos->refresh();
        $this->assertFalse($retirada->aplica);
        $this->assertTrue($retirada->cumplido);
        $this->assertSame('Firmados', $retirada->observacion);
        $this->assertSame([], $this->origenes($retirada));
        $this->assertSame(8, $this->servicio->progresoDe($expediente)['total']);

        $this->componer($expediente, [$this->sgr, $agua]);

        $recuperada = $planos->refresh();
        $this->assertTrue($recuperada->aplica);
        $this->assertTrue($recuperada->cumplido);
        $this->assertSame('Firmados', $recuperada->observacion);
        $this->assertSame([$this->requisito($agua, 'Planos')->id], $this->origenes($recuperada));
    }

    public function test_quitar_una_de_dos_plantillas_que_comparten_fila_la_conserva_con_el_otro_origen(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Licencia ambiental' => ['licencia', 'obligatorio']]);
        $vias = $this->plantilla('Vías', ['Licencia de la autoridad' => ['licencia', 'opcional']]);
        $expediente = $this->expediente();
        $this->componer($expediente, [$agua, $vias]);
        $ancla = $this->requisito($agua, 'Licencia ambiental');
        $this->assertSame(RequisitoCatalogo::OBLIGATORIO, $this->fila($expediente, $ancla)->caracter);

        $this->componer($expediente, [$vias]);

        $fila = $this->fila($expediente, $ancla); // el ancla no cambia
        $this->assertTrue($fila->aplica);
        $this->assertSame(RequisitoCatalogo::OPCIONAL, $fila->caracter);
        $this->assertSame([$this->requisito($vias, 'Licencia de la autoridad')->id], $this->origenes($fila));
    }

    public function test_un_requisito_inactivo_no_aplica(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio'], 'Retirado' => [null, 'obligatorio', false]]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$agua]);

        $this->assertSame(['Planos'], $expediente->requisitos()->where('aplica', true)->with('requisito')->get()->pluck('requisito.texto')->all());
    }

    // ----------------------------------------------------------- por definir

    public function test_un_condicionado_nace_por_definir_y_recalcular_no_cambia_su_estado(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, 'condicionado'], 'Planos' => [null, 'obligatorio']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$this->sgr, $agua]);

        $permiso = $this->fila($expediente, $this->requisito($agua, 'Permiso de vertimientos'));
        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $permiso->estado_aplicabilidad);
        $this->assertSame(ProyectoRequisito::APLICA, $this->fila($expediente, $this->requisito($agua, 'Planos'))->estado_aplicabilidad);
        $this->assertSame(0, $expediente->requisitos()->where('aplica', true)->whereHas('requisito', fn ($q) => $q->whereNotNull('seccion'))
            ->where('estado_aplicabilidad', '!=', ProyectoRequisito::APLICA)->count());

        // Una persona decidió que aplica: recalcular no la devuelve a por definir.
        $permiso->update(['estado_aplicabilidad' => ProyectoRequisito::APLICA]);
        $this->componer($expediente, [$this->sgr, $agua]);
        $this->assertSame(ProyectoRequisito::APLICA, $permiso->refresh()->estado_aplicabilidad);
    }

    // ------------------------------------------------------------------ orden

    public function test_el_orden_sigue_a_la_plantilla_del_primer_origen_con_la_principal_primero(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio'], 'Presupuesto' => ['a2', 'obligatorio']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$this->sgr, $agua], $agua);

        $this->assertSame(1, $this->fila($expediente, $this->requisito($agua, 'Planos'))->orden);
        // a2 lo trae primero la principal, aunque la fila ya existía con el ancla SGR.
        $a2 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a2')->sole();
        $this->assertSame(2, $this->fila($expediente, $a2)->orden);
        // La SGR es la segunda plantilla: su posición suma 10 000 000 al orden por sección.
        $a1 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a1')->sole();
        $this->assertSame(10_000_000 + 100_000 + 1, $this->fila($expediente, $a1)->orden);

        $this->componer($expediente, [$this->sgr, $agua], $this->sgr);

        $this->assertSame(100_001, $this->fila($expediente, $a1)->orden);
        $this->assertSame(10_000_000 + 1, $this->fila($expediente, $this->requisito($agua, 'Planos'))->orden);
        $this->assertSame(100_002, $this->fila($expediente, $a2)->orden);
    }

    // --------------------------------------------------------------- propios

    public function test_sincronizar_no_toca_los_requisitos_propios(): void
    {
        $expediente = $this->expediente();
        $propio = $expediente->requisitos()->create([
            'requisito_catalogo_id' => null, 'titulo_propio' => 'Acta de socialización', 'aplica' => true,
            'caracter' => RequisitoCatalogo::OBLIGATORIO, 'orden' => 0,
        ]);
        $propio->update(['orden' => 900_000_000 + $propio->id, 'cumplido' => true]);

        $this->componer($expediente, [$this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio']])]);
        $this->componer($expediente, []);

        $despues = $propio->refresh();
        $this->assertTrue($despues->aplica);
        $this->assertTrue($despues->cumplido);
        $this->assertSame(900_000_000 + $propio->id, $despues->orden);
        $this->assertSame([], $this->origenes($despues));
        $this->assertSame(0, $expediente->requisitos()->where('aplica', true)->whereNotNull('requisito_catalogo_id')->count());
    }

    // ------------------------------------------------- revisión de la tarea 4

    public function test_una_fila_nueva_nace_por_definir_segun_el_caracter_mas_exigente_y_no_el_de_su_ancla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Licencia ambiental' => ['licencia', 'opcional'], 'Planos' => ['planos', 'condicionado']]);
        $vias = $this->plantilla('Vías', ['Licencia de la autoridad' => ['licencia', 'condicionado'], 'Planos viales' => ['planos', 'obligatorio']]);
        $expediente = $this->expediente();

        $this->componer($expediente, [$agua, $vias]);

        $this->assertSame(ProyectoRequisito::POR_DEFINIR, $this->fila($expediente, $this->requisito($agua, 'Licencia ambiental'))->estado_aplicabilidad);
        $planos = $this->fila($expediente, $this->requisito($agua, 'Planos'));
        $this->assertSame(RequisitoCatalogo::OBLIGATORIO, $planos->caracter);
        $this->assertSame(ProyectoRequisito::APLICA, $planos->estado_aplicabilidad);
    }

    public function test_sincronizar_devuelve_lo_mismo_que_la_etapa_4_en_un_expediente_solo_sgr(): void
    {
        $expediente = $this->expediente();
        $a1 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a1')->sole();
        $fila = $this->fila($expediente, $a1);
        $this->servicio->marcar($expediente, $fila, ['cumplido' => true], null);

        // Dato anterior a la regla de versiones inmutables: la etapa 4 retiraba la fila de un desactivado.
        $a1->update(['activo' => false]);

        $this->assertSame(['aplican' => 7, 'retenidos' => 1], $this->servicio->sincronizar($expediente->refresh()));
        $fila->refresh();
        $this->assertFalse($fila->aplica);
        $this->assertTrue($fila->cumplido);
        $this->assertSame([], $this->origenes($fila));
    }

    public function test_dos_filas_existentes_con_la_misma_llave_dejan_una_sola_activa_y_la_otra_sin_origenes(): void
    {
        $expediente = $this->expediente();
        $a1 = RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a1')->sole();
        // Otra versión SGR con el mismo código común, y una segunda fila activa anclada en ella.
        $otra = $this->sgr->versiones()->create(['numero' => 99, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => false]);
        $copia = $a1->replicate();
        $copia->catalogo_version_id = $otra->id;
        $copia->save();
        $duplicada = $expediente->requisitos()->create(['requisito_catalogo_id' => $copia->id, 'aplica' => true, 'orden' => 1]);

        $this->servicio->sincronizar($expediente->refresh());

        $filas = $expediente->requisitos()->whereIn('requisito_catalogo_id', [$a1->id, $copia->id])->get();
        $this->assertSame(1, $filas->where('aplica', true)->count());
        foreach ($filas as $f) {
            $this->assertSame($f->aplica ? [$a1->id] : [], $this->origenes($f));
        }
        $this->assertFalse($duplicada->refresh()->aplica);
    }

    public function test_la_misma_llave_dos_veces_en_una_version_es_una_fila_anclada_al_primero_por_orden(): void
    {
        // CatalogoService lo impide; si el dato llega igual, sincronizar no duplica la fila.
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio']]);
        $version = $agua->versionVigente();
        $primero = RequisitoCatalogo::create(['catalogo_version_id' => $version->id, 'clave' => 'r8', 'texto' => 'Licencia A', 'orden' => 2, 'codigo_comun' => 'licencia', 'caracter' => 'obligatorio']);
        $segundo = RequisitoCatalogo::create(['catalogo_version_id' => $version->id, 'clave' => 'r9', 'texto' => 'Licencia B', 'orden' => 9, 'codigo_comun' => 'licencia', 'caracter' => 'opcional']);
        $expediente = $this->expediente();

        $this->componer($expediente, [$agua]);

        $fila = $this->fila($expediente, $primero);
        $this->assertSame(2, $fila->orden);
        $this->assertSame(RequisitoCatalogo::OBLIGATORIO, $fila->caracter);
        $this->assertSame([$primero->id, $segundo->id], $this->origenes($fila));
        $this->assertFalse($expediente->requisitos()->where('requisito_catalogo_id', $segundo->id)->exists());
    }

    public function test_sincronizar_no_escribe_en_el_catalogo(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos' => [null, 'obligatorio'], 'Presupuesto' => ['a2', 'opcional']]);
        $expediente = $this->expediente();
        $escrituras = [];
        DB::listen(function ($q) use (&$escrituras) {
            if (preg_match('/^\s*(insert into|update|delete from)\s+"?(requisitos_catalogo|catalogo_versiones|plantillas_checklist)"?\s/i', $q->sql)) {
                $escrituras[] = $q->sql;
            }
        });

        $this->componer($expediente, [$this->sgr, $agua], $agua);
        $this->componer($expediente, [$agua]);

        $this->assertSame([], $escrituras);
    }
}
