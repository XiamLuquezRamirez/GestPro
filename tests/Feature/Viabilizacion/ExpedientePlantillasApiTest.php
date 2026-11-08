<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\CatalogoVersion;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Crear, configurar y presentar un expediente con sus plantillas. */
class ExpedientePlantillasApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $sgr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sgr = PlantillaChecklist::sgr();
    }

    private function proyecto(array $fuentes = ['sgr']): Proyecto
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync(FuenteFinanciacion::whereIn('clave', $fuentes)->pluck('id')->all());

        return $proyecto;
    }

    /** @param array<string, array{0: ?string, 1?: string, 2?: ?string}> $requisitos texto => [codigo_comun, caracter, categoria] */
    private function plantilla(string $nombre, array $requisitos, array $datos = []): PlantillaChecklist
    {
        $plantilla = PlantillaChecklist::create([
            'nombre' => $nombre, 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true,
            'todas_fuentes' => true, 'todos_sectores' => true, ...$datos,
        ]);
        $version = $plantilla->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        $orden = 0;
        foreach ($requisitos as $texto => $r) {
            $orden++;
            RequisitoCatalogo::create([
                'catalogo_version_id' => $version->id, 'clave' => "r{$orden}", 'texto' => $texto, 'orden' => $orden,
                'codigo_comun' => $r[0], 'caracter' => $r[1] ?? RequisitoCatalogo::OBLIGATORIO, 'categoria' => $r[2] ?? null,
            ]);
        }

        return $plantilla;
    }

    private function como(Rol $rol = Rol::Gestor): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function crear(Proyecto $proyecto, array $datos)
    {
        return $this->como()->postJson("/GestPro/proyectos/{$proyecto->id}/expedientes", $datos);
    }

    private function configurar(int $expediente, array $datos, bool $confirmar = false)
    {
        return $confirmar
            ? $this->como()->postJson("/GestPro/expedientes/{$expediente}/configuracion/confirmar", $datos)
            : $this->como()->putJson("/GestPro/expedientes/{$expediente}/configuracion", $datos);
    }

    private function sgrA1(): RequisitoCatalogo
    {
        return RequisitoCatalogo::where('catalogo_version_id', $this->sgr->versionVigente()->id)->where('clave', 'a1')->sole();
    }

    private function filaPorTexto($respuesta, string $texto): ?array
    {
        return collect($respuesta->json('requisitos'))->firstWhere('requisito.texto', $texto);
    }

    // --------------------------------------------------------------- creación

    public function test_sin_plantillas_en_el_pedido_preselecciona_la_sgr_compatible(): void
    {
        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated();

        $version = $this->sgr->versionVigente();
        $r->assertJsonPath('plantillas', [[
            'plantilla_id' => $this->sgr->id, 'nombre' => 'SGR', 'motor' => 'sgr', 'es_principal' => true,
            'compatible' => true, 'version' => ['id' => $version->id, 'numero' => 1],
        ]])
            ->assertJsonPath('tiene_sgr', true)
            ->assertJsonPath('catalogo_version', ['id' => $version->id, 'numero' => 1])
            ->assertJsonPath('progreso.total', 8)
            ->assertJsonPath('avisos_duplicados', []);
    }

    public function test_sin_plantilla_sgr_compatible_nace_sin_plantillas_y_sin_pedir_la_fase(): void
    {
        $r = $this->crear($this->proyecto(['recursos_propios']), [])->assertCreated();

        $r->assertJsonPath('plantillas', [])
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('fase_sgr', 3)
            ->assertJsonPath('requisitos', [])
            ->assertJsonPath('progreso.total', 0);
        $this->assertNull(ProyectoExpediente::find($r->json('id'))->catalogo_version_id);
    }

    public function test_crea_con_las_plantillas_pedidas(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null], 'Licencia' => [null]]);

        $r = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id]])->assertCreated();

        $r->assertJsonPath('plantillas.0.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('tiene_sgr', false)
            ->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('progreso.total', 2);
        $this->assertSame(['Planos firmados', 'Licencia'], collect($r->json('requisitos'))->pluck('requisito.texto')->all());
    }

    public function test_con_la_sgr_la_fase_es_obligatoria(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $proyecto = $this->proyecto();

        $this->crear($proyecto, ['plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
        $this->crear($proyecto, ['plantilla_ids' => [$agua->id]])->assertCreated();
        $this->assertDatabaseCount('proyectos_expedientes', 1);
    }

    public function test_combina_la_sgr_con_otra_plantilla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);

        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertCreated();

        $r->assertJsonPath('progreso.total', 9)
            ->assertJsonPath('plantillas.0.plantilla_id', $this->sgr->id)
            ->assertJsonPath('plantillas.1.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.1.es_principal', false);
        $this->assertSame('Planos firmados', collect($r->json('requisitos'))->last()['requisito']['texto']);
    }

    public function test_rechaza_una_seleccion_invalida_sin_crear_nada(): void
    {
        $nacion = $this->plantilla('Nación', ['Planos' => [null]], ['todas_fuentes' => false]);
        $nacion->fuentes()->sync(FuenteFinanciacion::where('clave', 'pgn')->pluck('id')->all());

        $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$nacion->id]])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'La configuración del expediente no es válida.')
            ->assertJsonPath('errores', ['La plantilla "Nación" no es compatible con las fuentes o el sector del proyecto.']);
        $this->crear($this->proyecto(), ['plantilla_ids' => 'sgr'])->assertStatus(422)->assertJsonValidationErrors('plantilla_ids');
        $this->assertDatabaseCount('proyectos_expedientes', 0);
    }

    /**
     * El frontend crea el nuevo expediente enviando la configuración del abierto, ahora con
     * plantilla_ids y principal_id: el backend no necesita nada más. Cada plantilla entra con
     * su versión vigente y no se copian el cumplimiento ni los requisitos propios.
     */
    public function test_un_nuevo_expediente_desde_un_radicado_copia_las_plantillas_con_su_vigente_actual(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $proyecto = $this->proyecto();
        $seleccion = ['fase_sgr' => 2, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id];
        $radicado = ProyectoExpediente::findOrFail($this->crear($proyecto, $seleccion)->json('id'));
        $servicio = app(ExpedienteService::class);
        $servicio->confirmarConfiguracion($radicado, $seleccion);
        $radicado->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000, 'titulo_propio' => 'Acta de socialización',
        ]);
        $radicado->requisitos()->update(['cumplido' => true]);
        $servicio->radicar($radicado, null);

        // Obras de agua publica una versión 2 después de radicar.
        $v1 = $agua->versionVigente();
        $v1->update(['vigente' => false]);
        $v2 = $agua->versiones()->create(['numero' => 2, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create(['catalogo_version_id' => $v2->id, 'clave' => 'r1', 'texto' => 'Planos firmados y aprobados', 'orden' => 1]);

        $nuevo = $this->crear($proyecto, $seleccion)->assertCreated();

        $nuevo->assertJsonPath('plantillas.0.plantilla_id', $agua->id)
            ->assertJsonPath('plantillas.0.es_principal', true)
            ->assertJsonPath('plantillas.0.version', ['id' => $v2->id, 'numero' => 2])
            ->assertJsonPath('plantillas.1.plantilla_id', $this->sgr->id)
            ->assertJsonPath('fase_sgr', 2)
            ->assertJsonPath('configurado', false)
            ->assertJsonPath('progreso.cumplidos', 0);
        $this->assertSame('Planos firmados y aprobados', $nuevo->json('requisitos.0.requisito.texto'));
        $this->assertSame([], collect($nuevo->json('requisitos'))->whereNotNull('propio')->all());
        $this->assertSame($v1->id, $radicado->versionesPlantilla()->where('plantilla_id', $agua->id)->sole()->id);
    }

    // --------------------------------------------------------- configuración

    public function test_configurar_sin_plantillas_en_el_pedido_conserva_la_seleccion(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->json('id');

        $this->configurar($id, ['fase_sgr' => 2])->assertOk()
            ->assertJsonPath('fase_sgr', 2)
            ->assertJsonCount(2, 'plantillas');
    }

    public function test_configurar_con_plantillas_cambia_la_seleccion_y_recalcula(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->json('id');

        $con = $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id])->assertOk();
        $con->assertJsonPath('plantillas.0.plantilla_id', $agua->id)->assertJsonPath('progreso.total', 9);
        $this->assertSame('Planos firmados', $con->json('requisitos.0.requisito.texto'));

        // Sin la SGR no hace falta la fase, y sus filas quedan retiradas.
        $sin = $this->configurar($id, ['plantilla_ids' => [$agua->id]])->assertOk();
        $sin->assertJsonPath('tiene_sgr', false)->assertJsonPath('catalogo_version', null)
            ->assertJsonPath('progreso.total', 1)->assertJsonPath('fase_sgr', 3);
        $this->assertSame(8, ProyectoExpediente::find($id)->requisitos()->where('aplica', false)->count());

        $this->configurar($id, ['plantilla_ids' => [$this->sgr->id]])->assertStatus(422)->assertJsonValidationErrors('fase_sgr');
    }

    public function test_confirmar_exige_al_menos_una_plantilla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(['recursos_propios']), [])->assertCreated()->json('id');
        $mensaje = 'Elige al menos una plantilla para armar el checklist.';

        $this->configurar($id, [], true)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->configurar($id, ['plantilla_ids' => []], true)->assertStatus(422)->assertJsonPath('mensaje', $mensaje);
        $this->assertNull(ProyectoExpediente::find($id)->fecha_configuracion);

        $this->configurar($id, ['plantilla_ids' => [$agua->id]], true)->assertOk()->assertJsonPath('configurado', true);
    }

    public function test_un_expediente_confirmado_no_se_queda_sin_plantillas_y_uno_sin_confirmar_si(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id]])->assertCreated()->json('id');

        // Mientras se arma, puede quedarse sin plantillas.
        $this->configurar($id, ['plantilla_ids' => []])->assertOk()->assertJsonPath('plantillas', []);
        $this->configurar($id, ['plantilla_ids' => [$agua->id]], true)->assertOk();

        $this->configurar($id, ['plantilla_ids' => []])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Elige al menos una plantilla para armar el checklist.');
        $this->assertSame(1, ProyectoExpediente::find($id)->versionesPlantilla()->count());
        $this->assertSame(1, ProyectoExpediente::find($id)->requisitos()->where('aplica', true)->count());

        // Sin plantilla_ids, la selección no cambia y el cambio se acepta.
        $this->configurar($id, [])->assertOk()->assertJsonCount(1, 'plantillas');
    }

    public function test_un_cambio_de_plantillas_queda_en_el_historial(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->json('id');
        $this->configurar($id, ['fase_sgr' => 3], true)->assertOk();

        $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->assertOk();

        $registro = DB::table('checklist_historial')->where('evento', 'configuracion_cambiada')->sole();
        $this->assertCount(1, json_decode($registro->antes, true)['plantillas']);
        $this->assertCount(2, json_decode($registro->despues, true)['plantillas']);
    }

    // ------------------------------------------------------------ presentación

    public function test_una_fila_compartida_muestra_el_requisito_de_la_principal_y_sus_origenes(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Presupuesto detallado de agua' => ['a1', 'obligatorio', 'financiero']]);
        $proyecto = $this->proyecto();
        $id = $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->json('id');
        $a1 = $this->sgrA1();

        $r = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->assertOk();

        $fila = collect($r->json('requisitos'))->firstWhere('requisito.id', $a1->id);
        $this->assertSame(['id' => $this->sgr->id, 'nombre' => 'SGR', 'motor' => 'sgr'], $fila['plantilla']);
        $this->assertSame([['plantilla_id' => $this->sgr->id, 'nombre' => 'SGR'], ['plantilla_id' => $agua->id, 'nombre' => 'Obras de agua']], $fila['origenes']);
        $this->assertNull($fila['propio']);
        $this->assertNull($fila['categoria']);
        $this->assertSame(8, $r->json('progreso.total'));

        // Con la otra como principal, se muestra el requisito de esa plantilla.
        $otra = $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $agua->id]);
        $fila = $this->filaPorTexto($otra, 'Presupuesto detallado de agua');
        $this->assertSame(['id' => $agua->id, 'nombre' => 'Obras de agua', 'motor' => 'simple'], $fila['plantilla']);
        $this->assertSame('financiero', $fila['categoria']);
        $this->assertSame([['plantilla_id' => $agua->id, 'nombre' => 'Obras de agua'], ['plantilla_id' => $this->sgr->id, 'nombre' => 'SGR']], $fila['origenes']);
        $this->assertNull($this->filaPorTexto($otra, $a1->texto));
    }

    public function test_una_fila_sin_origenes_muestra_su_ancla(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->json('id');
        $this->configurar($id, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id]])->assertOk();
        $retirada = ProyectoExpediente::find($id)->requisitos()->where('aplica', false)->sole();

        $fila = app(ExpedienteService::class)->presentarFila($retirada);

        $this->assertSame('Planos firmados', $fila['requisito']['texto']);
        $this->assertSame(['id' => $agua->id, 'nombre' => 'Obras de agua', 'motor' => 'simple'], $fila['plantilla']);
        $this->assertSame([], $fila['origenes']);
    }

    public function test_una_fila_trae_la_descripcion_la_condicion_y_el_soporte_del_requisito(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Permiso de vertimientos' => [null, RequisitoCatalogo::CONDICIONADO]]);
        RequisitoCatalogo::where('texto', 'Permiso de vertimientos')->update([
            'descripcion' => 'Lo expide la autoridad ambiental.',
            'condicion_descripcion' => 'Si la obra descarga aguas residuales.',
            'soporte_requerido' => 'Resolución del permiso',
        ]);

        $r = $this->crear($this->proyecto(), ['plantilla_ids' => [$agua->id]])->assertCreated();

        $fila = $this->filaPorTexto($r, 'Permiso de vertimientos');
        $this->assertSame('por_definir', $fila['estado']);
        $this->assertSame('Lo expide la autoridad ambiental.', $fila['requisito']['descripcion']);
        $this->assertSame('Si la obra descarga aguas residuales.', $fila['requisito']['condicion_descripcion']);
        $this->assertSame('Resolución del permiso', $fila['requisito']['soporte_requerido']);

        // Sin esos datos llegan como null, también en la plantilla SGR.
        $a1 = collect($this->crear($this->proyecto(), ['fase_sgr' => 3])->json('requisitos'))->firstWhere('requisito.id', $this->sgrA1()->id);
        $this->assertArrayHasKey('condicion_descripcion', $a1['requisito']);
        $this->assertNull($a1['requisito']['condicion_descripcion']);
    }

    public function test_un_requisito_propio_se_presenta_con_sus_textos(): void
    {
        $id = $this->crear($this->proyecto(), ['fase_sgr' => 3])->json('id');
        $propio = ProyectoExpediente::find($id)->requisitos()->create([
            'requisito_catalogo_id' => null, 'aplica' => true, 'orden' => 900_000_000,
            'titulo_propio' => 'Acta de socialización', 'descripcion_propia' => 'Con la junta de acción comunal', 'categoria_propia' => 'social',
        ]);

        $fila = collect($this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->json('requisitos'))->firstWhere('id', $propio->id);

        $this->assertNull($fila['requisito']);
        $this->assertSame(['titulo' => 'Acta de socialización', 'descripcion' => 'Con la junta de acción comunal', 'categoria' => 'social'], $fila['propio']);
        $this->assertSame('social', $fila['categoria']);
        $this->assertNull($fila['plantilla']);
        $this->assertSame([], $fila['origenes']);
    }

    public function test_la_cabecera_avisa_si_una_plantilla_dejo_de_ser_compatible(): void
    {
        $propios = $this->plantilla('Propios', ['Planos firmados' => [null]], ['todas_fuentes' => false]);
        $propios->fuentes()->sync(FuenteFinanciacion::where('clave', 'recursos_propios')->pluck('id')->all());
        $proyecto = $this->proyecto(['sgr', 'recursos_propios']);
        $this->crear($proyecto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $propios->id], 'principal_id' => $this->sgr->id])->assertCreated();

        $proyecto->fuentes()->sync(FuenteFinanciacion::where('clave', 'sgr')->pluck('id')->all());

        $this->como(Rol::Consulta)->getJson("/GestPro/proyectos/{$proyecto->id}/expedientes")->assertOk()
            ->assertJsonPath('expedientes.0.tiene_sgr', true)
            ->assertJsonPath('expedientes.0.plantillas.0.compatible', true)
            ->assertJsonPath('expedientes.0.plantillas.1.nombre', 'Propios')
            ->assertJsonPath('expedientes.0.plantillas.1.compatible', false);
    }

    // ------------------------------------------------------ avisos duplicados

    public function test_avisa_de_textos_parecidos_entre_plantillas_sin_fusionarlos(): void
    {
        $a1 = $this->sgrA1();
        // Mismas palabras largas que a1, con otros signos, tildes y palabras cortas: Jaccard 1.
        $parecido = mb_strtoupper(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $a1->texto)) . ' — de la';
        $agua = $this->plantilla('Obras de agua', [$parecido => ['agua-1'], 'Planos firmados por el ingeniero civil' => ['agua-2']]);

        $r = $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])
            ->assertCreated();

        $filaA1 = collect($r->json('requisitos'))->firstWhere('requisito.id', $a1->id)['id'];
        $filaAgua = $this->filaPorTexto($r, $parecido)['id'];
        $r->assertJsonPath('progreso.total', 10)
            ->assertJsonPath('avisos_duplicados', [[
                'filas' => [$filaA1, $filaAgua],
                'textos' => [$a1->texto, $parecido],
                'plantillas' => ['SGR', 'Obras de agua'],
                'similitud' => 1, // JSON no conserva el .0
            ]]);
    }

    public function test_no_avisa_dentro_de_una_plantilla_ni_de_textos_poco_parecidos(): void
    {
        // Jaccard 4/5 = 0,8: serían aviso entre plantillas distintas.
        $agua = $this->plantilla('Obras de agua', [
            'Certificado de disponibilidad del servicio de acueducto' => ['agua-1'],
            'Certificado de disponibilidad del servicio de acueducto vigente' => ['agua-2'],
        ]);
        // Jaccard 3/5 con la primera de Obras de agua.
        $energia = $this->plantilla('Energía', ['Certificado de disponibilidad del servicio de energía' => ['energia-1']]);

        $sola = $this->crear($this->proyecto(), ['plantilla_ids' => [$agua->id]])->assertCreated();
        $sola->assertJsonPath('avisos_duplicados', []);

        $this->configurar($sola->json('id'), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id, $energia->id], 'principal_id' => $agua->id])
            ->assertOk()->assertJsonPath('progreso.total', 11)->assertJsonPath('avisos_duplicados', []);
    }

    public function test_el_umbral_de_parecido_es_ocho_decimos(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Certificado de disponibilidad del servicio de acueducto' => ['agua-1']]);
        $vias = $this->plantilla('Vías', ['Certificado de disponibilidad del servicio de acueducto vigente' => ['vias-1']]);

        $r = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id, $vias->id], 'principal_id' => $agua->id])
            ->assertCreated();

        $r->assertJsonCount(1, 'avisos_duplicados')
            ->assertJsonPath('avisos_duplicados.0.plantillas', ['Obras de agua', 'Vías'])
            ->assertJsonPath('avisos_duplicados.0.similitud', 0.8);
    }

    /** @return array<string, array{0: null}> "Anexo 1" … "Anexo n", cada uno en su propia fila */
    private function anexos(int $n): array
    {
        return collect(range(1, $n))->mapWithKeys(fn (int $i) => ["Anexo {$i}" => [null]])->all();
    }

    public function test_los_numeros_cuentan_al_comparar_textos(): void
    {
        $agua = $this->plantilla('Obras de agua', $this->anexos(30));
        $vias = $this->plantilla('Vías', $this->anexos(30));

        $r = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id, $vias->id], 'principal_id' => $agua->id])
            ->assertCreated();

        // Solo cada anexo con el del mismo número, no 30 × 30.
        $r->assertJsonCount(30, 'avisos_duplicados')->assertJsonPath('avisos_duplicados_total', 30);
        foreach ($r->json('avisos_duplicados') as $aviso) {
            $this->assertSame($aviso['textos'][0], $aviso['textos'][1]);
        }
    }

    public function test_no_avisa_de_una_fila_en_no_aplica_y_si_de_una_pendiente_o_por_definir(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Certificación técnica del acueducto veredal' => [null]]);
        $vias = $this->plantilla('Vías', ['Certificacion tecnica del acueducto veredal' => [null]]);
        $id = $this->crear($this->proyecto(['recursos_propios']), ['plantilla_ids' => [$agua->id, $vias->id], 'principal_id' => $agua->id])
            ->assertCreated()->json('id');
        $fila = ProyectoExpediente::find($id)->requisitos()->whereHas('requisito', fn ($q) => $q->where('texto', 'Certificacion tecnica del acueducto veredal'))->sole();
        $avisos = function (string $estado) use ($fila, $id) {
            $fila->update(['estado_aplicabilidad' => $estado]);

            return $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$id}")->assertOk();
        };

        $avisos(ProyectoRequisito::NO_APLICA)->assertJsonPath('avisos_duplicados', [])->assertJsonPath('avisos_duplicados_total', 0);
        $avisos(ProyectoRequisito::NO_APLICA_PENDIENTE)->assertJsonCount(1, 'avisos_duplicados');
        $avisos(ProyectoRequisito::POR_DEFINIR)->assertJsonCount(1, 'avisos_duplicados');
    }

    public function test_los_avisos_van_del_mas_parecido_al_menos_y_llegan_hasta_cincuenta(): void
    {
        $certificado = 'Certificado de disponibilidad del servicio de acueducto';
        // Jaccard 0,8 en el primer par y 1 en los demás.
        $agua = $this->plantilla('Obras de agua', [$certificado => [null], ...$this->anexos(55)]);
        $vias = $this->plantilla('Vías', ["{$certificado} vigente" => [null], ...$this->anexos(55)]);
        $proyecto = $this->proyecto(['recursos_propios']);

        $r = $this->crear($proyecto, ['plantilla_ids' => [$agua->id, $vias->id], 'principal_id' => $agua->id])->assertCreated();

        $r->assertJsonCount(50, 'avisos_duplicados')->assertJsonPath('avisos_duplicados_total', 56);
        $this->assertSame([1], collect($r->json('avisos_duplicados'))->pluck('similitud')->unique()->values()->all());

        // Con pocos, el menos parecido va al final aunque sus filas vengan primero.
        $pocas = ['plantilla_ids' => [$this->plantilla('Agua 2', [$certificado => [null], 'Anexo 1' => [null]])->id,
            $this->plantilla('Vías 2', ["{$certificado} vigente" => [null], 'Anexo 1' => [null]])->id]];
        $id = ProyectoExpediente::where('proyecto_id', $proyecto->id)->value('id');
        $this->configurar($id, [...$pocas, 'principal_id' => $pocas['plantilla_ids'][0]])->assertOk()
            ->assertJsonPath('avisos_duplicados.0.similitud', 1)
            ->assertJsonPath('avisos_duplicados.1.similitud', 0.8)
            ->assertJsonPath('avisos_duplicados_total', 2);
    }

    // ---------------------------------------------------------------- resumen

    public function test_el_resumen_trae_las_plantillas_con_la_principal_primero_y_si_tiene_sgr(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $vias = $this->plantilla('Vías', ['Licencia' => [null]]);
        $mixto = $this->proyecto();
        $this->crear($mixto, ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id, $vias->id], 'principal_id' => $vias->id])->assertCreated();
        $soloSgr = $this->proyecto();
        $this->crear($soloSgr, ['fase_sgr' => 3])->assertCreated();
        $sinPlantillas = $this->proyecto(['recursos_propios']);
        $this->crear($sinPlantillas, [])->assertCreated();

        $resumen = collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())->keyBy('proyecto_id');

        $this->assertSame(['Vías', 'SGR', 'Obras de agua'], $resumen[$mixto->id]['plantillas']);
        $this->assertTrue($resumen[$mixto->id]['tiene_sgr']);
        $this->assertSame(['SGR'], $resumen[$soloSgr->id]['plantillas']);
        $this->assertTrue($resumen[$soloSgr->id]['tiene_sgr']);
        $this->assertSame([], $resumen[$sinPlantillas->id]['plantillas']);
        $this->assertFalse($resumen[$sinPlantillas->id]['tiene_sgr']);
    }

    public function test_el_resumen_hace_tres_consultas_sin_importar_cuantos_proyectos_haya(): void
    {
        $agua = $this->plantilla('Obras de agua', ['Planos firmados' => [null]]);
        $servicio = app(ExpedienteService::class);
        $contar = function () use ($servicio) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $servicio->resumenPorProyecto();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->crear($this->proyecto(), ['fase_sgr' => 3, 'plantilla_ids' => [$this->sgr->id, $agua->id], 'principal_id' => $this->sgr->id])->assertCreated();
        $this->assertSame(3, $contar());

        foreach (range(1, 3) as $_) {
            $this->crear($this->proyecto(), ['fase_sgr' => 3])->assertCreated();
        }
        $this->assertSame(3, $contar());
    }
}
