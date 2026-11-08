<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\NoAplicaRequisito;
use App\Services\Viabilizacion\RequisitosPropios;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Las escrituras del expediente deciden sobre su fila releída con bloqueo, no sobre el modelo
 * que trajo la petición. SQLite no tiene concurrencia real: un modelo obsoleto hace de la
 * petición que leyó antes de que otra escribiera.
 */
class ExpedienteConcurrenciaTest extends TestCase
{
    use RefreshDatabase;

    private const RADICADO = 'El expediente está radicado: su checklist está congelado y no admite cambios.';
    private const SIN_PLANTILLAS = 'Elige al menos una plantilla para armar el checklist.';

    private ExpedienteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);
    }

    private function proyecto(string $fuente = FuenteFinanciacion::SGR): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', $fuente)->value('id')]);

        return $proyecto;
    }

    private function expedienteSgr(array $config = ['fase_sgr' => 3]): ProyectoExpediente
    {
        $expediente = $this->servicio->crear($this->proyecto(), $config, null);

        return $this->servicio->confirmarConfiguracion($expediente, $config);
    }

    private function plantillaSimple(): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => 'Obras de agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create(['catalogo_version_id' => $version->id, 'clave' => 'r1', 'texto' => 'Planos firmados', 'orden' => 1]);

        return $plantilla;
    }

    private function radicarEnOtraPeticion(ProyectoExpediente $expediente): void
    {
        ProyectoExpediente::whereKey($expediente->id)->update(['estado' => ProyectoExpediente::RADICADO]);
    }

    private function assertRechaza(string $mensaje, callable $accion): void
    {
        try {
            $accion();
            $this->fail("Debía rechazarse con: {$mensaje}");
        } catch (ReglaNegocioException $e) {
            $this->assertSame($mensaje, $e->getMessage());
        }
    }

    private function claves(ProyectoExpediente $expediente): array
    {
        return $expediente->requisitos()->where('aplica', true)->with('requisito:id,clave')->get()
            ->pluck('requisito.clave')->sort()->values()->all();
    }

    // ------------------------------------------------------------ sincronizar

    public function test_sincronizar_usa_la_configuracion_de_la_fila_releida(): void
    {
        // Operaciones urbanas pide vi4a en fase II y vi4b en fase III.
        $vivienda = (int) DB::table('requisitos_sectores')->where('clave', 'vivienda')->value('id');
        $tipo = (int) DB::table('requisitos_tipos')->where('sector_id', $vivienda)->where('clave', 'operaciones_urbanas')->value('id');
        $config = ['sector_ids' => [$vivienda], 'tipo_ids' => [$tipo]];
        $expediente = $this->expedienteSgr(['fase_sgr' => 3, ...$config]);
        $vieja = $expediente->fresh(); // la leyó otra petición, todavía en fase III

        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 2, ...$config]);
        $esperadas = $this->claves($expediente);
        $this->assertContains('vi4a', $esperadas);

        $this->servicio->sincronizar($vieja);

        $this->assertSame($esperadas, $this->claves($expediente));
        $this->assertSame(2, $vieja->fase_sgr);
    }

    // ---------------------------------------------------------- configuración

    public function test_configurar_con_una_lectura_vieja_no_cambia_un_radicado(): void
    {
        $expediente = $this->expedienteSgr();
        // Una lectura vieja por acción: bloquear() pone al día la que recibe.
        [$paraActualizar, $paraConfirmar] = [$expediente->fresh(), $expediente->fresh()];
        $this->radicarEnOtraPeticion($expediente);

        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->actualizarConfiguracion($paraActualizar, ['fase_sgr' => 2]));
        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->confirmarConfiguracion($paraConfirmar, ['fase_sgr' => 2]));

        $this->assertSame(3, $expediente->fresh()->fase_sgr);
    }

    public function test_quitar_las_plantillas_con_una_lectura_vieja_no_deja_un_confirmado_sin_ellas(): void
    {
        $agua = $this->plantillaSimple();
        $expediente = $this->servicio->crear($this->proyecto(FuenteFinanciacion::RECURSOS_PROPIOS), ['plantilla_ids' => [$agua->id]], null);
        $vieja = $expediente->fresh(); // la leyó otra petición, todavía sin confirmar

        $this->servicio->confirmarConfiguracion($expediente, []);

        $this->assertRechaza(self::SIN_PLANTILLAS, fn () => $this->servicio->actualizarConfiguracion($vieja, ['plantilla_ids' => []]));
        $this->assertSame(1, $expediente->versionesPlantilla()->count());
        $this->assertTrue($expediente->fresh()->estaConfigurado());
    }

    public function test_confirmar_con_una_lectura_vieja_no_confirma_un_expediente_que_quedo_sin_plantillas(): void
    {
        $agua = $this->plantillaSimple();
        $expediente = $this->servicio->crear($this->proyecto(FuenteFinanciacion::RECURSOS_PROPIOS), ['plantilla_ids' => [$agua->id]], null);
        $vieja = $expediente->fresh();

        $this->servicio->actualizarConfiguracion($expediente, ['plantilla_ids' => []]);

        $this->assertRechaza(self::SIN_PLANTILLAS, fn () => $this->servicio->confirmarConfiguracion($vieja, []));
        $this->assertNull($expediente->fresh()->fecha_configuracion);
    }

    // ---------------------------------------------------------------- radicar

    public function test_radicar_dos_veces_con_una_lectura_vieja_no_vuelve_a_radicar(): void
    {
        $expediente = $this->expedienteSgr();
        $expediente->requisitos()->update(['cumplido' => true]);
        $vieja = $expediente->fresh();

        $this->servicio->radicar($expediente, null);
        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->radicar($vieja, null));

        $this->assertSame(1, DB::table('checklist_historial')->where('evento', 'expediente_radicado')->count());
    }

    public function test_radicar_cuenta_el_progreso_dentro_de_la_transaccion(): void
    {
        $expediente = $this->expedienteSgr();
        $expediente->requisitos()->update(['cumplido' => true]);
        $vieja = $expediente->fresh();
        // Otra petición agregó un propio obligatorio, todavía pendiente.
        app(RequisitosPropios::class)->crear($expediente, ['titulo' => 'Acta de socialización'], null);

        $this->assertRechaza('No se puede radicar: falta 1 de 9 requisitos por cumplir.', fn () => $this->servicio->radicar($vieja, null));
        $this->assertTrue($expediente->fresh()->esBorrador());
    }

    // -------------------------------------------------------------- propios

    public function test_un_propio_no_se_agrega_edita_ni_elimina_con_una_lectura_vieja_de_un_radicado(): void
    {
        $expediente = $this->expedienteSgr();
        $propios = app(RequisitosPropios::class);
        $fila = $propios->crear($expediente, ['titulo' => 'Acta de socialización'], null);
        // Una lectura vieja por acción: bloquear() pone al día la que recibe.
        [$paraCrear, $paraEditar, $paraEliminar] = [$expediente->fresh(), $expediente->fresh(), $expediente->fresh()];
        $this->radicarEnOtraPeticion($expediente);

        $this->assertRechaza(self::RADICADO, fn () => $propios->crear($paraCrear, ['titulo' => 'Otro requisito'], null));
        $this->assertRechaza(self::RADICADO, fn () => $propios->editar($paraEditar, $fila, ['titulo' => 'Acta firmada'], null));
        $this->assertRechaza(self::RADICADO, fn () => $propios->eliminar($paraEliminar, $fila, null));

        $this->assertSame(1, $expediente->requisitos()->whereNull('requisito_catalogo_id')->count());
        $this->assertSame('Acta de socialización', $fila->fresh()->titulo_propio);
    }

    // ------------------------------------------- filas del checklist y soportes

    private function filaSgr(ProyectoExpediente $expediente): ProyectoRequisito
    {
        return $expediente->requisitos()->where('aplica', true)->orderBy('orden')->firstOrFail();
    }

    public function test_desmarcar_con_una_lectura_vieja_no_deja_pendientes_en_un_radicado(): void
    {
        $expediente = $this->expedienteSgr();
        $fila = $this->filaSgr($expediente);
        $this->servicio->marcar($expediente, $fila, ['cumplido' => true], null);
        $vieja = $expediente->fresh();
        $this->radicarEnOtraPeticion($expediente);

        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->marcar($vieja, $fila, ['cumplido' => false, 'observacion' => 'Otra'], null));

        $this->assertTrue($fila->fresh()->cumplido);
        $this->assertNull($fila->fresh()->observacion);
    }

    public function test_retirar_un_no_aplica_con_una_lectura_vieja_no_cambia_un_radicado(): void
    {
        $expediente = $this->expedienteSgr();
        $fila = $this->filaSgr($expediente);
        $usuario = User::factory()->create(['rol' => Rol::Administrador]);
        $noAplica = app(NoAplicaRequisito::class);
        $noAplica->marcar($expediente, $fila, 'No hay predios.', $usuario);
        $noAplica->aprobar($expediente, $fila, $usuario);
        $vieja = $expediente->fresh();
        $this->radicarEnOtraPeticion($expediente);

        $this->assertRechaza(self::RADICADO, fn () => $noAplica->retirar($vieja, $fila, $usuario));

        $this->assertSame(ProyectoRequisito::NO_APLICA, $fila->fresh()->estado_aplicabilidad);
    }

    public function test_subir_o_eliminar_un_soporte_con_una_lectura_vieja_no_cambia_un_radicado(): void
    {
        Storage::fake('local');
        $expediente = $this->expedienteSgr();
        [$conSoporte, $sinSoporte] = $expediente->requisitos()->where('aplica', true)->orderBy('orden')->take(2)->get()->all();
        $this->servicio->guardarSoporte($expediente, $conSoporte, UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'));
        [$paraSubir, $paraEliminar] = [$expediente->fresh(), $expediente->fresh()];
        $this->radicarEnOtraPeticion($expediente);

        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->guardarSoporte($paraSubir, $sinSoporte, UploadedFile::fake()->create('otro.pdf', 10, 'application/pdf')));
        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->eliminarSoporte($paraEliminar, $conSoporte));

        $this->assertNull($sinSoporte->fresh()->soporte_archivo);
        $this->assertSame('acta.pdf', $conSoporte->fresh()->soporte_nombre);
        // El archivo rechazado no queda en el disco.
        $this->assertSame([$conSoporte->fresh()->soporte_archivo], Storage::disk('local')->allFiles());
    }

    public function test_eliminar_con_una_lectura_vieja_no_borra_un_radicado_ni_sus_archivos(): void
    {
        Storage::fake('local');
        $expediente = $this->expedienteSgr();
        $fila = $this->filaSgr($expediente);
        $this->servicio->guardarSoporte($expediente, $fila, UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'));
        $vieja = $expediente->fresh();
        $this->radicarEnOtraPeticion($expediente);

        $this->assertRechaza(self::RADICADO, fn () => $this->servicio->eliminar($vieja));

        $this->assertNotNull($expediente->fresh());
        Storage::disk('local')->assertExists($fila->fresh()->soporte_archivo);
    }

    // ---------------------------------------------------------------- bloquear

    public function test_bloquear_descarta_las_relaciones_cargadas_por_la_peticion(): void
    {
        $vivienda = (int) DB::table('requisitos_sectores')->where('clave', 'vivienda')->value('id');
        $tipo = (int) DB::table('requisitos_tipos')->where('sector_id', $vivienda)->where('clave', 'operaciones_urbanas')->value('id');
        $expediente = $this->expedienteSgr(['fase_sgr' => 3, 'sector_ids' => [$vivienda], 'tipo_ids' => [$tipo]]);
        // Otra petición la leyó con su configuración cargada, todavía con vivienda.
        $vieja = $expediente->fresh()->load(['sectores', 'tipos', 'componentes', 'tipologias']);

        $this->servicio->actualizarConfiguracion($expediente, ['fase_sgr' => 3]);
        $esperadas = $this->claves($expediente);
        $this->assertNotContains('vi4b', $esperadas);

        $this->servicio->sincronizar($vieja);

        $this->assertSame($esperadas, $this->claves($expediente));
        // sincronizar volvió a leer los sectores: ya no son los que trajo la petición.
        $this->assertSame([], $vieja->sectores->pluck('id')->all());
    }
}
