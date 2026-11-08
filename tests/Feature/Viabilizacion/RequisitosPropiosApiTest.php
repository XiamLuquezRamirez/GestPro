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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Requisitos propios del proyecto: se agregan en el checklist, sin plantilla. */
class RequisitosPropiosApiTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;
    private ProyectoExpediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->servicio = app(ExpedienteService::class);

        // Fase III sin sectores: 8 requisitos SGR.
        $this->expediente = $this->expedienteSgr();
        $this->servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3]);
    }

    private function expedienteSgr(): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $proyecto->fuentes()->sync([FuenteFinanciacion::where('clave', FuenteFinanciacion::SGR)->value('id')]);

        return $this->servicio->crear($proyecto, ['fase_sgr' => 3], null);
    }

    private function como(Rol|User $quien = Rol::Gestor): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(?ProyectoRequisito $fila = null, ?ProyectoExpediente $expediente = null): string
    {
        $expediente ??= $this->expediente;

        return "/GestPro/expedientes/{$expediente->id}/requisitos-propios" . ($fila ? "/{$fila->id}" : '');
    }

    private function agregar(array $datos = [])
    {
        return $this->como()->postJson($this->url(), [
            'titulo' => 'Acta de socialización con la comunidad', ...$datos,
        ]);
    }

    private function propio(array $datos = []): ProyectoRequisito
    {
        return ProyectoRequisito::findOrFail($this->agregar($datos)->assertCreated()->json('requisito.id'));
    }

    private function eventos(ProyectoRequisito $fila): array
    {
        return DB::table('checklist_historial')->where('entidad_tipo', 'requisito')->where('entidad_id', $fila->id)
            ->orderBy('id')->get()
            ->map(fn ($h) => [$h->evento, json_decode((string) $h->antes, true), json_decode((string) $h->despues, true)])
            ->all();
    }

    private function filaSgr(): ProyectoRequisito
    {
        return $this->expediente->requisitos()->whereNotNull('requisito_catalogo_id')->where('aplica', true)->orderBy('orden')->first();
    }

    // ------------------------------------------------------------------ crear

    public function test_agrega_un_requisito_propio_al_final_del_checklist(): void
    {
        $r = $this->agregar([
            'titulo' => '  Acta de socialización con la comunidad  ',
            'descripcion' => 'Firmada por la junta de acción comunal',
            'categoria' => 'social',
        ])->assertCreated();

        $r->assertJsonPath('requisito.requisito', null)
            ->assertJsonPath('requisito.propio', [
                'titulo' => 'Acta de socialización con la comunidad',
                'descripcion' => 'Firmada por la junta de acción comunal',
                'categoria' => 'social',
            ])
            ->assertJsonPath('requisito.categoria', 'social')
            ->assertJsonPath('requisito.caracter', 'obligatorio')
            ->assertJsonPath('requisito.estado', 'pendiente')
            ->assertJsonPath('requisito.cuenta', true)
            ->assertJsonPath('requisito.plantilla', null)
            ->assertJsonPath('requisito.origenes', [])
            ->assertJsonPath('progreso.total', 9);

        $fila = ProyectoRequisito::findOrFail($r->json('requisito.id'));
        $this->assertTrue($fila->esPropio());
        $this->assertTrue($fila->aplica);
        $this->assertSame(900_000_000 + $fila->id, $fila->orden);

        $requisitos = $this->como(Rol::Consulta)->getJson("/GestPro/expedientes/{$this->expediente->id}")->json('requisitos');
        $this->assertCount(9, $requisitos);
        $this->assertSame($fila->id, end($requisitos)['id']);
    }

    public function test_un_propio_opcional_no_cuenta_y_sin_descripcion_ni_categoria_quedan_vacias(): void
    {
        $this->agregar(['caracter' => 'opcional'])->assertCreated()
            ->assertJsonPath('requisito.caracter', 'opcional')
            ->assertJsonPath('requisito.cuenta', false)
            ->assertJsonPath('requisito.propio.descripcion', null)
            ->assertJsonPath('requisito.propio.categoria', null)
            ->assertJsonPath('requisito.categoria', null)
            ->assertJsonPath('progreso.total', 8);
    }

    public function test_agregar_queda_en_el_historial(): void
    {
        $fila = $this->propio(['categoria' => 'juridico']);

        $this->assertSame([['requisito_propio_agregado', null, [
            'titulo' => 'Acta de socialización con la comunidad', 'descripcion' => null, 'categoria' => 'juridico', 'caracter' => 'obligatorio',
        ]]], $this->eventos($fila));
    }

    public function test_valida_los_campos_con_mensajes_en_espanol(): void
    {
        $this->como()->postJson($this->url(), ['titulo' => '   '])->assertStatus(422)
            ->assertJsonValidationErrors(['titulo' => 'Escribe el título del requisito.']);
        $this->agregar(['titulo' => str_repeat('a', 501)])->assertStatus(422)
            ->assertJsonValidationErrors(['titulo' => 'El título admite hasta 500 caracteres.']);
        $this->agregar(['descripcion' => str_repeat('a', 2001)])->assertStatus(422)
            ->assertJsonValidationErrors(['descripcion' => 'La descripción admite hasta 2000 caracteres.']);
        $this->agregar(['categoria' => 'deportivo'])->assertStatus(422)
            ->assertJsonValidationErrors(['categoria' => 'Elige una categoría de la lista.']);
        $this->agregar(['caracter' => 'condicionado'])->assertStatus(422)
            ->assertJsonValidationErrors(['caracter' => 'El carácter de un requisito propio es obligatorio u opcional.']);
        $this->agregar(['titulo' => ['a']])->assertStatus(422)
            ->assertJsonValidationErrors(['titulo' => 'Revisa el título: debe ser un texto.']);

        $this->assertSame(0, $this->expediente->requisitos()->whereNull('requisito_catalogo_id')->count());
    }

    public function test_solo_se_agrega_en_un_borrador_configurado(): void
    {
        $this->expediente->update(['estado' => ProyectoExpediente::RADICADO]);
        $this->agregar()->assertStatus(422)
            ->assertJsonPath('mensaje', 'El expediente está radicado: su checklist está congelado y no admite cambios.');

        $otro = $this->expedienteSgr(); // sin confirmar
        $this->como()->postJson($this->url(null, $otro), ['titulo' => 'Acta'])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Confirma la configuración del expediente antes de chequear sus requisitos.');

        $this->assertSame(0, ProyectoRequisito::whereNull('requisito_catalogo_id')->count());
    }

    public function test_consulta_no_puede_agregar_editar_ni_eliminar(): void
    {
        $fila = $this->propio();

        $this->como(Rol::Consulta)->postJson($this->url(), ['titulo' => 'Acta'])->assertForbidden();
        $this->como(Rol::Consulta)->putJson($this->url($fila), ['titulo' => 'Otro'])->assertForbidden();
        $this->como(Rol::Consulta)->deleteJson($this->url($fila))->assertForbidden();
    }

    // ----------------------------------------------------------------- editar

    public function test_edita_un_propio_y_registra_solo_lo_que_cambio(): void
    {
        $fila = $this->propio(['descripcion' => 'Con la junta', 'categoria' => 'social']);

        $this->como()->putJson($this->url($fila), ['titulo' => 'Acta de socialización firmada', 'categoria' => null, 'caracter' => 'opcional'])
            ->assertOk()
            ->assertJsonPath('requisito.propio', ['titulo' => 'Acta de socialización firmada', 'descripcion' => 'Con la junta', 'categoria' => null])
            ->assertJsonPath('requisito.caracter', 'opcional')
            ->assertJsonPath('progreso.total', 8);

        $this->assertSame(['requisito_propio_editado', [
            'titulo' => 'Acta de socialización con la comunidad', 'categoria' => 'social', 'caracter' => 'obligatorio',
        ], [
            'titulo' => 'Acta de socialización firmada', 'categoria' => null, 'caracter' => 'opcional',
        ]], $this->eventos($fila)[1]);
    }

    public function test_editar_sin_cambios_no_registra_nada(): void
    {
        $fila = $this->propio();

        $this->como()->putJson($this->url($fila), ['titulo' => 'Acta de socialización con la comunidad'])->assertOk();

        $this->assertCount(1, $this->eventos($fila));
    }

    public function test_editar_conserva_el_cumplimiento_y_el_orden(): void
    {
        $fila = $this->propio();
        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true, 'observacion' => 'Entregada'], null);

        $this->como()->putJson($this->url($fila), ['titulo' => 'Acta firmada'])->assertOk()
            ->assertJsonPath('requisito.cumplido', true)
            ->assertJsonPath('requisito.observacion', 'Entregada');
        $this->assertSame(900_000_000 + $fila->id, $fila->fresh()->orden);
    }

    public function test_un_requisito_de_plantilla_no_se_edita_ni_se_elimina_como_propio(): void
    {
        $fila = $this->filaSgr();

        $this->como()->putJson($this->url($fila), ['titulo' => 'Otro'])->assertStatus(422)
            ->assertJsonPath('mensaje', 'Este requisito viene de una plantilla: solo se editan los requisitos propios del proyecto.');
        $this->como()->deleteJson($this->url($fila))->assertStatus(422)
            ->assertJsonPath('mensaje', 'Este requisito viene de una plantilla: solo se eliminan los requisitos propios del proyecto.');

        $this->assertNull($fila->fresh()->titulo_propio);
    }

    public function test_un_propio_de_otro_expediente_no_sirve(): void
    {
        $fila = $this->propio();
        $otro = $this->expedienteSgr();
        $this->servicio->confirmarConfiguracion($otro, ['fase_sgr' => 3]);

        $this->como()->putJson($this->url($fila, $otro), ['titulo' => 'Otro'])->assertNotFound();
        $this->como()->deleteJson($this->url($fila, $otro))->assertNotFound();
    }

    public function test_un_expediente_radicado_no_admite_editar_ni_eliminar(): void
    {
        $fila = $this->propio();
        $this->expediente->update(['estado' => ProyectoExpediente::RADICADO]);

        $this->como()->putJson($this->url($fila), ['titulo' => 'Otro'])->assertStatus(422);
        $this->como()->deleteJson($this->url($fila))->assertStatus(422);
        $this->assertSame('Acta de socialización con la comunidad', $fila->fresh()->titulo_propio);
    }

    // --------------------------------------------------------------- eliminar

    public function test_elimina_un_propio_y_queda_en_el_historial(): void
    {
        $fila = $this->propio(['categoria' => 'social']);

        $this->como()->deleteJson($this->url($fila))->assertOk()
            ->assertJsonPath('mensaje', 'Requisito eliminado.')
            ->assertJsonPath('progreso.total', 8);

        $this->assertNull(ProyectoRequisito::find($fila->id));
        $this->assertSame(['requisito_propio_eliminado', [
            'titulo' => 'Acta de socialización con la comunidad', 'descripcion' => null, 'categoria' => 'social',
            'caracter' => 'obligatorio', 'observacion' => null,
        ], null], $this->eventos($fila)[1]);
        $this->assertSame($this->expediente->id, (int) DB::table('checklist_historial')->where('evento', 'requisito_propio_eliminado')->value('expediente_id'));
    }

    public function test_no_se_elimina_un_propio_cumplido_ni_con_soporte(): void
    {
        $fila = $this->propio();
        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true], null);
        $this->como()->deleteJson($this->url($fila))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito está cumplido: desmárcalo antes de eliminarlo.');

        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => false], null);
        $this->servicio->guardarSoporte($this->expediente, $fila, UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf'));
        $this->como()->deleteJson($this->url($fila))->assertStatus(422)
            ->assertJsonPath('mensaje', 'El requisito tiene un soporte: quítalo antes de eliminarlo.');

        $this->assertNotNull(ProyectoRequisito::find($fila->id));
    }

    // ---------------------------------------------- trabajo diario con propios

    public function test_un_propio_se_cumple_con_soporte_responsable_e_historial(): void
    {
        $fila = $this->propio();
        $url = "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$fila->id}";

        $this->como()->patchJson($url, ['cumplido' => true, 'observacion' => 'Entregada'])->assertOk()
            ->assertJsonPath('requisito.estado', 'cumplido')
            ->assertJsonPath('progreso.cumplidos', 1);
        $this->como()->post("{$url}/soporte", ['archivo' => UploadedFile::fake()->create('acta.pdf', 10, 'application/pdf')])->assertOk()
            ->assertJsonPath('requisito.soporte.nombre', 'acta.pdf');
        $this->como(Rol::Consulta)->get("{$url}/soporte")->assertOk();
        $this->como()->deleteJson("{$url}/soporte")->assertOk()->assertJsonPath('requisito.soporte', null);

        $eventos = collect($this->como(Rol::Consulta)->getJson("{$url}/historial")->assertOk()->json())->pluck('evento')->all();
        $this->assertSame(['soporte_eliminado', 'soporte_subido', 'observacion_cambiada', 'cumplido_marcado', 'requisito_propio_agregado'], $eventos);
    }

    public function test_un_propio_admite_no_aplica_sin_aprobacion(): void
    {
        $fila = $this->propio();
        $url = "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$fila->id}/no-aplica";

        $this->como()->postJson($url, ['justificacion' => 'Ya se socializó en otro proyecto.'])->assertOk()
            ->assertJsonPath('requisito.estado', 'no_aplica')
            ->assertJsonPath('progreso.total', 8);
        $this->como()->deleteJson($url)->assertOk()->assertJsonPath('requisito.estado', 'pendiente');
    }

    public function test_un_propio_pendiente_impide_radicar_y_cumplido_lo_permite(): void
    {
        $fila = $this->propio();
        $this->expediente->requisitos()->whereNotNull('requisito_catalogo_id')->where('aplica', true)->update(['cumplido' => true]);

        $this->como()->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")->assertStatus(422)
            ->assertJsonPath('mensaje', 'No se puede radicar: falta 1 de 9 requisitos por cumplir.');

        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true], null);
        $this->como()->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")->assertOk()
            ->assertJsonPath('estado', 'radicado')
            ->assertJsonPath('progreso', ['total' => 9, 'cumplidos' => 9, 'porcentaje' => 100]);
    }

    public function test_recalcular_el_checklist_no_toca_los_propios(): void
    {
        $fila = $this->propio();
        $this->servicio->marcar($this->expediente, $fila, ['cumplido' => true], null);
        $antes = $fila->fresh()->only(['aplica', 'cumplido', 'orden', 'caracter', 'estado_aplicabilidad']);

        // Cambiar la SGR por otra plantilla retira las filas de la SGR y deja el propio como estaba.
        $agua = PlantillaChecklist::create(['nombre' => 'Obras de agua', 'motor' => PlantillaChecklist::MOTOR_SIMPLE, 'activo' => true, 'todas_fuentes' => true, 'todos_sectores' => true]);
        $version = $agua->versiones()->create(['numero' => 1, 'estado' => CatalogoVersion::PUBLICADA, 'vigente' => true]);
        RequisitoCatalogo::create(['catalogo_version_id' => $version->id, 'clave' => 'r1', 'texto' => 'Planos', 'orden' => 1]);
        $this->servicio->actualizarConfiguracion($this->expediente, ['plantilla_ids' => [$agua->id]]);

        $this->assertSame($antes, $fila->fresh()->only(['aplica', 'cumplido', 'orden', 'caracter', 'estado_aplicabilidad']));
        $this->assertSame(0, DB::table('proyecto_requisito_origenes')->where('proyecto_requisito_id', $fila->id)->count());
        $this->assertSame(['total' => 2, 'cumplidos' => 1, 'porcentaje' => 50], $this->servicio->progresoDe($this->expediente));
    }

    public function test_el_resumen_del_tablero_cuenta_los_propios(): void
    {
        $this->propio();
        $this->propio(['caracter' => 'opcional']);

        $resumen = collect($this->como(Rol::Consulta)->getJson('/GestPro/expedientes')->assertOk()->json())
            ->firstWhere('expediente_id', $this->expediente->id);
        $this->assertSame(9, $resumen['progreso']['total']);
    }
}
