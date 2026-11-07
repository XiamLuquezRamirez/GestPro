<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\ChecklistHistorial;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HistorialRequisitoApiTest extends TestCase
{
    use RefreshDatabase;

    private ProyectoExpediente $expediente;
    private ProyectoRequisito $fila;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        Storage::fake('local');

        $this->gestor = User::factory()->create(['rol' => Rol::Gestor, 'name' => 'Gestora Uno']);
        $servicio = app(ExpedienteService::class);
        $this->expediente = $servicio->crear(Proyecto::create(['nombre' => 'Acueducto']), ['fase_sgr' => 3], $this->gestor);
        $servicio->confirmarConfiguracion($this->expediente, ['fase_sgr' => 3], $this->gestor);
        $this->fila = $this->expediente->requisitos()->where('aplica', true)->orderBy('orden')->first();
    }

    private function como(Rol|User $quien): static
    {
        $usuario = $quien instanceof User ? $quien : User::factory()->create(['rol' => $quien]);

        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($usuario));
    }

    private function url(string $resto = ''): string
    {
        return "/GestPro/expedientes/{$this->expediente->id}/requisitos/{$this->fila->id}{$resto}";
    }

    private function historial(): array
    {
        return $this->como(Rol::Consulta)->getJson($this->url('/historial'))->assertOk()->json();
    }

    private function subir(string $nombre): void
    {
        $this->como($this->gestor)->post($this->url('/soporte'), [
            'archivo' => UploadedFile::fake()->create($nombre, 20, 'application/pdf'),
        ])->assertOk();
    }

    public function test_marcar_y_desmarcar_quedan_registrados_con_quien_lo_hizo(): void
    {
        $this->como($this->gestor)->patchJson($this->url(), ['cumplido' => true])->assertOk();
        $this->como($this->gestor)->patchJson($this->url(), ['cumplido' => true])->assertOk(); // sin cambio, sin registro
        $this->como($this->gestor)->patchJson($this->url(), ['cumplido' => false])->assertOk();

        $historial = $this->historial();

        $this->assertSame(['cumplido_desmarcado', 'cumplido_marcado'], array_column($historial, 'evento'));
        $this->assertSame('Gestora Uno', $historial[1]['usuario']);
        $this->assertSame(['cumplido' => false], $historial[1]['antes']);
        $this->assertSame(['cumplido' => true], $historial[1]['despues']);
    }

    public function test_cambiar_la_observacion_queda_registrado(): void
    {
        $this->como($this->gestor)->patchJson($this->url(), ['observacion' => 'Falta firma'])->assertOk();

        $this->assertSame([
            'evento' => 'observacion_cambiada',
            'antes' => ['observacion' => null],
            'despues' => ['observacion' => 'Falta firma'],
        ], array_intersect_key($this->historial()[0], array_flip(['evento', 'antes', 'despues'])));
    }

    public function test_un_soporte_reemplazado_se_conserva_y_se_descarga_desde_el_historial(): void
    {
        $this->subir('v1.pdf');
        $primera = $this->fila->fresh()->soporte_archivo;
        $this->subir('v2.pdf');

        Storage::disk('local')->assertExists($primera);
        $respuesta = $this->como(Rol::Consulta)->getJson($this->url('/historial'))->assertOk();
        $this->assertStringNotContainsString(basename($primera), $respuesta->getContent()); // la ruta nunca sale
        $reemplazo = $respuesta->json('0');
        $this->assertArrayNotHasKey('soporte_archivo', $reemplazo['antes']);
        $this->assertSame('soporte_reemplazado', $reemplazo['evento']);
        $this->assertSame(['nombre' => 'v1.pdf'], $reemplazo['soporte']);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$this->expediente->id}/historial/{$reemplazo['id']}/soporte")
            ->assertOk()->assertDownload('v1.pdf');
    }

    public function test_un_soporte_eliminado_se_conserva_en_el_historial(): void
    {
        $this->subir('acta.pdf');
        $ruta = $this->fila->fresh()->soporte_archivo;

        $this->como($this->gestor)->deleteJson($this->url('/soporte'))->assertOk();

        Storage::disk('local')->assertExists($ruta);
        $this->assertSame('soporte_eliminado', $this->historial()[0]['evento']);
    }

    public function test_no_se_descarga_un_soporte_de_otro_expediente(): void
    {
        $this->subir('v1.pdf');
        $this->subir('v2.pdf');
        $entrada = $this->historial()[0]['id'];
        $servicio = app(ExpedienteService::class);
        $otro = $servicio->crear(Proyecto::create(['nombre' => 'Vía']), ['fase_sgr' => 3], null);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$otro->id}/historial/{$entrada}/soporte")->assertNotFound();
    }

    public function test_el_expediente_registra_su_creacion_configuracion_y_radicacion(): void
    {
        DB::table('proyectos_requisitos')->where('expediente_id', $this->expediente->id)->update(['cumplido' => true]);

        $this->como($this->gestor)->postJson("/GestPro/expedientes/{$this->expediente->id}/radicar")->assertOk();

        $this->assertSame(
            ['expediente_creado', 'configuracion_confirmada', 'expediente_radicado'],
            DB::table('checklist_historial')->where('entidad_tipo', 'expediente')->orderBy('id')->pluck('evento')->all()
        );
        $this->assertSame([$this->gestor->id], DB::table('checklist_historial')->distinct()->pluck('usuario_id')->all());
    }

    public function test_eliminar_el_expediente_borra_tambien_los_soportes_conservados_y_deja_el_registro(): void
    {
        $this->subir('v1.pdf');
        $primera = $this->fila->fresh()->soporte_archivo;
        $this->subir('v2.pdf');
        $segunda = $this->fila->fresh()->soporte_archivo;

        $this->como($this->gestor)->deleteJson("/GestPro/expedientes/{$this->expediente->id}")->assertOk();

        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertMissing($segunda);
        $this->assertDatabaseHas('checklist_historial', [
            'expediente_id' => $this->expediente->id, 'evento' => 'expediente_eliminado', 'usuario_id' => $this->gestor->id,
        ]);
    }

    public function test_si_falla_el_registro_no_cambia_nada_y_se_borra_el_archivo_nuevo(): void
    {
        $this->subir('v1.pdf');
        $primera = $this->fila->fresh()->soporte_archivo;
        ChecklistHistorial::creating(fn () => throw new \RuntimeException('falla simulada'));

        $this->como($this->gestor)->post($this->url('/soporte'), [
            'archivo' => UploadedFile::fake()->create('v2.pdf', 20, 'application/pdf'),
        ])->assertStatus(500);

        $this->assertSame($primera, $this->fila->fresh()->soporte_archivo);
        $this->assertSame([$primera], Storage::disk('local')->allFiles("expedientes/{$this->expediente->id}"));
    }

    public function test_cambiar_la_configuracion_ya_confirmada_queda_registrado(): void
    {
        $this->como($this->gestor)->putJson("/GestPro/expedientes/{$this->expediente->id}/configuracion", ['fase_sgr' => 2])->assertOk();

        $registro = DB::table('checklist_historial')->where('evento', 'configuracion_cambiada')->first();
        $this->assertSame(['fase_sgr' => 3], json_decode($registro->antes, true));
        $this->assertSame(2, json_decode($registro->despues, true)['fase_sgr']);
    }

    public function test_no_se_descarga_un_archivo_fuera_de_la_carpeta_del_expediente(): void
    {
        Storage::disk('local')->put('otros/secreto.pdf', 'x');
        $entrada = ChecklistHistorial::create([
            'entidad_tipo' => 'requisito', 'entidad_id' => $this->fila->id, 'expediente_id' => $this->expediente->id,
            'evento' => 'soporte_reemplazado', 'antes' => ['soporte_archivo' => 'otros/secreto.pdf', 'soporte_nombre' => 's.pdf'],
        ]);

        $this->como(Rol::Consulta)->get("/GestPro/expedientes/{$this->expediente->id}/historial/{$entrada->id}/soporte")->assertNotFound();
    }
}
