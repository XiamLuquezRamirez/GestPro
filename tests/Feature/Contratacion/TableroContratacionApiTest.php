<?php

namespace Tests\Feature\Contratacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\Responsable;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tablero de Contratación (fase 6): todos los procesos, de todos los proyectos, con su expediente
 * resumido, sus avisos, su etapa actual y su semáforo; el resumen por estado, etapa actual y
 * semáforo; las opciones de filtro y los filtros en el servidor. Hoy es el 19 de noviembre de 2026.
 */
class TableroContratacionApiTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-19';
    private const RUTA = '/GestPro/tablero/contratacion';

    private Proyecto $proyecto;
    private Proyecto $otro;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOY . ' 10:00:00');
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $sector = DB::table('sectores')->insertGetId(['nombre' => 'Agua Potable', 'activo' => true]);
        $this->proyecto = Proyecto::create(['nombre' => 'Acueducto veredal', 'sector' => $sector]);
        $this->otro = Proyecto::create(['nombre' => 'Vía terciaria', 'sector' => $sector]);
        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora']);
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        $usuario = $rol === Rol::Gestor ? $this->gestor : User::factory()->create(['rol' => $rol]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function etapa(string $clave): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    private function modalidad(string $clave = 'licitacion_publica'): Modalidad
    {
        return Modalidad::where('clave', $clave)->firstOrFail();
    }

    /** Plantilla publicada: dos requisitos de planeación, uno del pliego definitivo y uno del cierre. */
    private function plantilla(): PlantillaChecklist
    {
        $admin = $this->como(Rol::Administrador);
        $id = $admin->postJson('/GestPro/plantillas', [
            'nombre' => 'Licitación',
            'uso' => PlantillaChecklist::USO_CONTRATACION,
            'todas_fuentes' => true,
            'todos_sectores' => true,
            'modalidad_ids' => [$this->modalidad()->id],
        ])->assertCreated()->json('id');
        $plantilla = PlantillaChecklist::findOrFail($id);
        $borrador = $plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->firstOrFail();
        foreach ([
            ['Estudios previos', 'planeacion'], ['CDP', 'planeacion'],
            ['Pliego de condiciones', 'pliego_definitivo'], ['Acta de cierre', 'cierre'],
        ] as [$texto, $etapa]) {
            $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/requisitos", [
                'texto' => $texto, 'etapa_id' => $this->etapa($etapa)->id,
            ])->assertCreated();
        }
        $admin->postJson("/GestPro/catalogo-requisitos/versiones/{$borrador->id}/publicar")->assertOk();

        return $plantilla->fresh();
    }

    private function proceso(array $cambios = [], ?Proyecto $proyecto = null): ProcesoLicitacion
    {
        return ProcesoLicitacion::create([
            'proyecto' => ($proyecto ?? $this->proyecto)->id,
            'objeto' => 'Construcción del acueducto',
            'modalidad' => $this->modalidad()->id,
            'plataforma' => 'secop_ii',
            'codigo_proceso' => 'LP-' . (ProcesoLicitacion::count() + 1),
            ...$cambios,
        ]);
    }

    private function expediente(PlantillaChecklist $plantilla, ProcesoLicitacion $proceso): ProyectoExpediente
    {
        $id = $this->como()->postJson("/GestPro/procesos/{$proceso->id}/expediente", ['plantillas' => [$plantilla->id]])
            ->assertCreated()->json('id');

        return ProyectoExpediente::findOrFail($id);
    }

    private function fechas(ProyectoExpediente $expediente, string $etapa, array $datos): void
    {
        $this->como()->putJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}", $datos)->assertOk();
    }

    private function aprobar(ProyectoExpediente $expediente, string $etapa): void
    {
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa($etapa)->id}/aprobar", ['confirmar' => true])->assertOk();
    }

    private function marcar(ProyectoExpediente $expediente, string $texto): void
    {
        $fila = $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$fila->id}", ['cumplido' => true])->assertOk();
    }

    private function tablero(array $filtros = []): array
    {
        return $this->como(Rol::Consulta)->getJson(self::RUTA . ($filtros ? '?' . http_build_query($filtros) : ''))->assertOk()->json();
    }

    /** @return array<int, array> proceso_id => fila del tablero */
    private function porId(array $tablero): array
    {
        return array_column($tablero['procesos'], null, 'id');
    }

    private function etapaActual(string $clave, string $estado): array
    {
        $etapa = $this->etapa($clave);

        return ['id' => $etapa->id, 'nombre' => $etapa->nombre, 'orden' => $etapa->orden, 'estado_revision' => $estado];
    }

    private function fuente(string $nombre): FuenteFinanciacion
    {
        return FuenteFinanciacion::create(['clave' => 'f_' . uniqid(), 'nombre' => $nombre, 'orden' => 99, 'activo' => true]);
    }

    // ------------------------------------------------------------ lista

    public function test_lista_los_procesos_de_todos_los_proyectos_con_su_expediente_y_sus_avisos(): void
    {
        $plantilla = $this->plantilla();
        $fuente = $this->fuente('Fondo local');
        $responsable = Responsable::create(['nombre' => 'Ana Pérez', 'activo' => true]);
        $conExpediente = $this->proceso([
            'monto' => 1500000, 'fecha_cierre' => '2026-11-21', 'fuente_id' => $fuente->id, 'responsable_id' => $responsable->id,
        ]);
        $expediente = $this->expediente($plantilla, $conExpediente);
        $sinExpediente = $this->proceso(['objeto' => 'Mejoramiento de la vía'], $this->otro);

        $tablero = $this->tablero();

        $this->assertSame([$conExpediente->id, $sinExpediente->id], array_column($tablero['procesos'], 'id'));
        $fila = $tablero['procesos'][0];
        $this->assertSame([
            'id', 'codigo_proceso', 'objeto', 'modalidad', 'plataforma', 'fuente', 'responsable', 'monto', 'estado',
            'fecha_cierre', 'proyecto', 'expediente', 'avisos', 'etapa_actual', 'semaforo',
        ], array_keys($fila));
        $this->assertSame('LP-1', $fila['codigo_proceso']);
        $this->assertSame(['id' => $this->modalidad()->id, 'nombre' => $this->modalidad()->nombre], $fila['modalidad']);
        $this->assertSame('secop_ii', $fila['plataforma']);
        $this->assertSame(['id' => $fuente->id, 'nombre' => 'Fondo local'], $fila['fuente']);
        $this->assertSame(['id' => $responsable->id, 'nombre' => 'Ana Pérez'], $fila['responsable']);
        $this->assertSame('1500000.00', $fila['monto']);
        $this->assertSame('preparacion', $fila['estado']);
        $this->assertSame('2026-11-21', $fila['fecha_cierre']);
        $this->assertSame(['id' => $this->proyecto->id, 'nombre' => 'Acueducto veredal'], $fila['proyecto']);

        // El expediente y los avisos son los mismos de la lista de procesos del proyecto.
        $delProyecto = $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$this->proyecto->id}/procesos")->assertOk()->json('procesos.0');
        $this->assertSame($delProyecto['expediente'], $fila['expediente']);
        $this->assertSame($expediente->id, $fila['expediente']['id']);
        $this->assertSame($delProyecto['avisos'], $fila['avisos']);

        $sin = $tablero['procesos'][1];
        $this->assertSame(['id' => $this->otro->id, 'nombre' => 'Vía terciaria'], $sin['proyecto']);
        $this->assertNull($sin['fuente']);
        $this->assertNull($sin['responsable']);
        $this->assertNull($sin['monto']);
        $this->assertNull($sin['fecha_cierre']);
        $this->assertNull($sin['expediente']);
        $this->assertNull($sin['etapa_actual']);
        $this->assertSame('sin_expediente', $sin['semaforo']);
        $this->assertSame(0, $sin['avisos']['etapas_vencidas']);
    }

    public function test_cualquier_usuario_autenticado_lo_lee(): void
    {
        $this->proceso();
        $this->getJson(self::RUTA)->assertUnauthorized();
        foreach ([Rol::Consulta, Rol::Gestor, Rol::Administrador] as $rol) {
            $this->como($rol)->getJson(self::RUTA)->assertOk()->assertJsonCount(1, 'procesos');
        }
    }

    public function test_sin_procesos_responde_vacio(): void
    {
        $tablero = $this->tablero();

        $this->assertSame([], $tablero['procesos']);
        $this->assertSame(0, $tablero['resumen']['total']);
        $this->assertSame([], $tablero['resumen']['por_etapa']);
        $this->assertSame(['modalidades' => [], 'plataformas' => [], 'fuentes' => [], 'responsables' => []], $tablero['opciones']);
    }

    // ------------------------------------------------------------ etapa actual

    public function test_la_etapa_actual_es_la_primera_con_requisitos_sin_aprobar(): void
    {
        $plantilla = $this->plantilla();
        $proceso = $this->proceso();
        $expediente = $this->expediente($plantilla, $proceso);
        $etapa = fn () => $this->porId($this->tablero())[$proceso->id]['etapa_actual'];

        $this->assertSame($this->etapaActual('planeacion', 'pendiente'), $etapa());

        $this->marcar($expediente, 'Estudios previos');
        $this->marcar($expediente, 'CDP');
        $this->assertSame($this->etapaActual('planeacion', 'lista'), $etapa());

        // Aprobada la planeación, la actual es la siguiente aunque una posterior esté lista.
        $this->marcar($expediente, 'Acta de cierre');
        $this->aprobar($expediente, 'planeacion');
        $this->assertSame($this->etapaActual('pliego_definitivo', 'pendiente'), $etapa());

        // Devuelta, sigue siendo la actual con su estado visible.
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/etapas/{$this->etapa('pliego_definitivo')->id}/devolver", ['observacion' => 'Falta el pliego'])->assertOk();
        $this->assertSame($this->etapaActual('pliego_definitivo', 'devuelta'), $etapa());

        // Una aprobada con cambios después cuenta como aprobada.
        $this->aprobar($expediente, 'pliego_definitivo');
        $this->como()->patchJson("/GestPro/expedientes/{$expediente->id}/requisitos/{$expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', 'CDP'))->value('id')}", ['observacion' => 'Revisado'])->assertOk();
        $this->assertSame($this->etapaActual('cierre', 'lista'), $etapa());

        // Todas aprobadas: no hay etapa actual.
        $this->aprobar($expediente, 'cierre');
        $this->assertNull($etapa());
    }

    public function test_un_expediente_cerrado_no_tiene_etapa_actual(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente($this->plantilla(), $proceso);
        $this->como()->postJson("/GestPro/expedientes/{$expediente->id}/cerrar", ['confirmar' => true])->assertOk();

        $fila = $this->porId($this->tablero())[$proceso->id];

        $this->assertNull($fila['etapa_actual']);
        $this->assertSame('terminado', $fila['semaforo']);
    }

    public function test_la_etapa_actual_salta_las_etapas_sin_requisitos_que_cuenten(): void
    {
        $proceso = $this->proceso();
        $expediente = $this->expediente($this->plantilla(), $proceso);
        // La planeación queda sin requisitos que cuenten: los dos con "no aplica" aprobado.
        foreach (['Estudios previos', 'CDP'] as $texto) {
            $fila = $expediente->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', $texto))->firstOrFail();
            ProyectoRequisito::whereKey($fila->id)->update(['estado_aplicabilidad' => ProyectoRequisito::NO_APLICA]);
        }

        $this->assertSame($this->etapaActual('pliego_definitivo', 'pendiente'), $this->porId($this->tablero())[$proceso->id]['etapa_actual']);
    }

    // ------------------------------------------------------------ semáforo

    public function test_el_semaforo_de_cada_proceso(): void
    {
        $plantilla = $this->plantilla();
        $vencida = $this->proceso();
        $this->fechas($this->expediente($plantilla, $vencida), 'planeacion', ['fecha_programada' => '2026-11-18']);
        $cierreVencido = $this->proceso(['fecha_cierre' => '2026-11-18']);
        $this->expediente($plantilla, $cierreVencido);
        $proxima = $this->proceso();
        $this->fechas($this->expediente($plantilla, $proxima), 'pliego_definitivo', ['fecha_programada' => '2026-11-22']);
        $cierreProximo = $this->proceso(['fecha_cierre' => '2026-11-20']);
        $this->expediente($plantilla, $cierreProximo);
        $alDia = $this->proceso(['fecha_cierre' => '2026-12-20']);
        $this->fechas($this->expediente($plantilla, $alDia), 'planeacion', ['fecha_programada' => '2026-12-01']);
        $sinExpediente = $this->proceso(['fecha_cierre' => '2026-11-10']);
        $celebrado = $this->proceso();
        $this->fechas($this->expediente($plantilla, $celebrado), 'planeacion', ['fecha_programada' => '2026-11-18']);
        $celebrado->update(['estado' => ProcesoLicitacion::CELEBRADO]);
        $desiertoSinExpediente = $this->proceso(['estado' => ProcesoLicitacion::DESIERTO]);

        $semaforos = array_map(fn (array $f) => $f['semaforo'], $this->porId($this->tablero()));

        $this->assertSame([
            $vencida->id => 'vencida',
            $cierreVencido->id => 'vencida',
            $proxima->id => 'proxima',
            $cierreProximo->id => 'proxima',
            $alDia->id => 'al_dia',
            $sinExpediente->id => 'sin_expediente',
            $celebrado->id => 'terminado',
            $desiertoSinExpediente->id => 'terminado',
        ], $semaforos);
    }

    // ------------------------------------------------------------ resumen, opciones y filtros

    /** Tres procesos en dos proyectos, con modalidades, plataformas, fuentes y responsables distintos. */
    private function variados(): array
    {
        $plantilla = $this->plantilla();
        $propios = $this->fuente('Fondo local');
        $sgr = $this->fuente('Cofinanciación nacional');
        $ana = Responsable::create(['nombre' => 'Ana Pérez', 'activo' => true]);
        $beto = Responsable::create(['nombre' => 'Beto Ruiz', 'activo' => true]);
        Responsable::create(['nombre' => 'Sin procesos', 'activo' => true]);
        $minima = $this->modalidad('minima_cuantia');

        $a = $this->proceso(['fuente_id' => $propios->id, 'responsable_id' => $ana->id]);
        $expediente = $this->expediente($plantilla, $a);
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $b = $this->proceso(['fuente_id' => $sgr->id, 'responsable_id' => $beto->id, 'plataforma' => 'secop_i'], $this->otro);
        $this->expediente($plantilla, $b);
        $c = $this->proceso([
            'modalidad' => $minima->id, 'plataforma' => 'ninguna', 'fuente_id' => $propios->id, 'estado' => ProcesoLicitacion::PUBLICADO,
        ]);

        return compact('a', 'b', 'c', 'propios', 'sgr', 'ana', 'beto', 'minima');
    }

    public function test_el_resumen_cuenta_por_estado_etapa_actual_y_semaforo_y_trae_las_opciones_presentes(): void
    {
        ['propios' => $propios, 'sgr' => $sgr, 'ana' => $ana, 'beto' => $beto, 'minima' => $minima] = $this->variados();

        $tablero = $this->tablero();
        $planeacion = $this->etapa('planeacion');

        $this->assertSame([
            'total' => 3,
            'por_estado' => [
                'preparacion' => 2, 'publicado' => 1, 'evaluacion' => 0, 'adjudicado' => 0,
                'desierto' => 0, 'celebrado' => 0, 'cancelado' => 0, 'revocado' => 0,
            ],
            'por_semaforo' => ['vencida' => 1, 'proxima' => 0, 'al_dia' => 1, 'sin_expediente' => 1, 'terminado' => 0],
            'por_etapa' => [
                ['id' => $planeacion->id, 'nombre' => $planeacion->nombre, 'orden' => $planeacion->orden, 'procesos' => 2],
            ],
        ], $tablero['resumen']);
        // Por nombre: Licitación pública antes que Mínima cuantía.
        $licitacion = $this->modalidad();
        $this->assertSame([
            'modalidades' => [
                ['id' => $licitacion->id, 'nombre' => 'Licitación pública'],
                ['id' => $minima->id, 'nombre' => 'Mínima cuantía'],
            ],
            'plataformas' => ['secop_ii', 'secop_i', 'ninguna'],
            'fuentes' => [['id' => $sgr->id, 'nombre' => 'Cofinanciación nacional'], ['id' => $propios->id, 'nombre' => 'Fondo local']],
            'responsables' => [['id' => $ana->id, 'nombre' => 'Ana Pérez'], ['id' => $beto->id, 'nombre' => 'Beto Ruiz']],
        ], $tablero['opciones']);
    }

    public function test_los_filtros_se_aplican_en_el_servidor_y_el_resumen_los_refleja(): void
    {
        ['a' => $a, 'b' => $b, 'c' => $c, 'propios' => $propios, 'beto' => $beto, 'minima' => $minima] = $this->variados();
        $ids = fn (array $filtros) => array_column($this->tablero($filtros)['procesos'], 'id');

        $this->assertSame([$c->id], $ids(['modalidad_id' => $minima->id]));
        $this->assertSame([$b->id], $ids(['plataforma' => 'secop_i']));
        $this->assertSame([$a->id, $c->id], $ids(['fuente_id' => $propios->id]));
        $this->assertSame([$b->id], $ids(['responsable_id' => $beto->id]));
        $this->assertSame([$c->id], $ids(['estado' => 'publicado']));
        $this->assertSame([$a->id], $ids(['semaforo' => 'vencida']));
        $this->assertSame([$a->id], $ids(['fuente_id' => $propios->id, 'estado' => 'preparacion']));
        $this->assertSame([], $ids(['fuente_id' => $propios->id, 'semaforo' => 'al_dia']));
        // Vacíos: como si no vinieran.
        $this->assertSame([$a->id, $b->id, $c->id], $ids(['fuente_id' => '', 'semaforo' => '']));

        $filtrado = $this->tablero(['fuente_id' => $propios->id]);
        $this->assertSame(2, $filtrado['resumen']['total']);
        $this->assertSame(['vencida' => 1, 'proxima' => 0, 'al_dia' => 0, 'sin_expediente' => 1, 'terminado' => 0], $filtrado['resumen']['por_semaforo']);
        $this->assertSame(1, $filtrado['resumen']['por_estado']['publicado']);
        $this->assertSame([1], array_column($filtrado['resumen']['por_etapa'], 'procesos'));
        // Las opciones no dependen de los filtros: se puede cambiar de filtro.
        $this->assertSame($this->tablero()['opciones'], $filtrado['opciones']);
    }

    public function test_un_filtro_no_valido_responde_422(): void
    {
        foreach ([
            ['modalidad_id' => 'uno'], ['fuente_id' => 'x'], ['responsable_id' => '1.5'],
            ['plataforma' => 'otra'], ['estado' => 'abierto'], ['semaforo' => 'rojo'],
        ] as $filtro) {
            $this->como(Rol::Consulta)->getJson(self::RUTA . '?' . http_build_query($filtro))
                ->assertUnprocessable()->assertJsonValidationErrors(array_keys($filtro));
        }
    }

    // ------------------------------------------------------------ consultas

    public function test_el_tablero_no_hace_una_consulta_por_proceso(): void
    {
        $plantilla = $this->plantilla();
        // El primero ya tiene todo lo que se lee: fuente, responsable y un evento tras la aprobación.
        $expediente = $this->expediente($plantilla, $this->proceso([
            'fecha_cierre' => '2026-11-21',
            'fuente_id' => $this->fuente('Fuente 0')->id,
            'responsable_id' => Responsable::create(['nombre' => 'Responsable 0', 'activo' => true])->id,
        ]));
        $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
        $this->aprobar($expediente, 'planeacion');
        $this->marcar($expediente, 'Acta de cierre');
        $consultas = function (): int {
            $peticion = $this->como(Rol::Consulta);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $peticion->getJson(self::RUTA)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $conUno = $consultas();

        foreach (range(1, 3) as $i) {
            $proyecto = $i % 2 ? $this->otro : $this->proyecto;
            $expediente = $this->expediente($plantilla, $this->proceso([
                'fecha_cierre' => '2026-11-0' . $i,
                'fuente_id' => $this->fuente("Fuente {$i}")->id,
                'responsable_id' => Responsable::create(['nombre' => "Responsable {$i}", 'activo' => true])->id,
            ], $proyecto));
            $this->fechas($expediente, 'planeacion', ['fecha_programada' => '2026-11-18']);
            $this->aprobar($expediente, 'planeacion');
            $this->marcar($expediente, 'Acta de cierre');
        }
        $this->proceso();

        $this->assertSame($conUno, $consultas());
        $this->assertCount(5, $this->tablero()['procesos']);
    }
}
