<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use App\Services\Viabilizacion\CatalogoService;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** La plantilla guarda la persona responsable de cumplir y de revisar cada requisito. */
class ResponsablesPlantillaApiTest extends TestCase
{
    use RefreshDatabase;

    private PlantillaChecklist $plantilla;
    private CatalogoVersion $borrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->plantilla = PlantillaChecklist::create([
            'nombre' => 'Inversión pública', 'motor' => PlantillaChecklist::MOTOR_SIMPLE,
            'todas_fuentes' => true, 'todos_sectores' => true,
        ]);
        $this->borrador = app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
    }

    private function comoAdmin(): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => Rol::Administrador])));
    }

    private function url(string $resto = '', ?CatalogoVersion $version = null): string
    {
        return '/GestPro/catalogo-requisitos/versiones/' . ($version ?? $this->borrador)->id . $resto;
    }

    private function crear(array $datos = [])
    {
        return $this->comoAdmin()->postJson($this->url('/requisitos'), ['texto' => 'Certificado de disponibilidad presupuestal', ...$datos]);
    }

    private function editar(int $id, array $datos)
    {
        return $this->comoAdmin()->putJson($this->url("/requisitos/{$id}"), ['texto' => 'Certificado de disponibilidad presupuestal', ...$datos]);
    }

    private function responsable(string $nombre, ?string $cargo, bool $activo = true): int
    {
        return DB::table('responsable')->insertGetId(['nombre' => $nombre, 'cargo' => $cargo, 'activo' => $activo]);
    }

    private function fila(int $id): RequisitoCatalogo
    {
        return RequisitoCatalogo::findOrFail($id);
    }

    // ------------------------------------------------------------- guardar

    public function test_elegir_una_persona_guarda_su_id_y_copia_su_cargo(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $beto = $this->responsable('Beto Gómez', 'Supervisor');

        $id = $this->crear([
            // El cargo enviado no cuenta: manda el de la persona.
            'responsable_cumplimiento_id' => $ana, 'cargo_cumplimiento' => 'Otro cargo',
            'responsable_revision_id' => $beto,
        ])->assertCreated()->json('id');

        $fila = $this->fila($id);
        $this->assertSame($ana, $fila->responsable_cumplimiento_id);
        $this->assertSame('Interventora', $fila->cargo_cumplimiento);
        $this->assertSame($beto, $fila->responsable_revision_id);
        $this->assertSame('Supervisor', $fila->cargo_revision);
    }

    public function test_una_persona_sin_cargo_deja_el_cargo_vacio(): void
    {
        $ana = $this->responsable('Ana Pérez', null);

        $id = $this->crear(['responsable_cumplimiento_id' => $ana, 'cargo_cumplimiento' => 'Interventor'])->assertCreated()->json('id');

        $this->assertNull($this->fila($id)->cargo_cumplimiento);
    }

    public function test_sin_persona_se_guarda_null_y_el_cargo_como_llega(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $id = $this->crear(['responsable_cumplimiento_id' => $ana, 'responsable_revision_id' => $ana])->json('id');

        // Conserva un cargo sin persona (dato anterior) y vacía el otro.
        $this->editar($id, [
            'responsable_cumplimiento_id' => null, 'cargo_cumplimiento' => 'Interventor',
            'responsable_revision_id' => null, 'cargo_revision' => null,
        ])->assertOk();

        $fila = $this->fila($id);
        $this->assertNull($fila->responsable_cumplimiento_id);
        $this->assertSame('Interventor', $fila->cargo_cumplimiento);
        $this->assertNull($fila->responsable_revision_id);
        $this->assertNull($fila->cargo_revision);
    }

    public function test_editar_sin_enviar_las_personas_las_conserva(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $id = $this->crear(['responsable_cumplimiento_id' => $ana])->json('id');

        $this->editar($id, ['texto' => 'Nombre nuevo'])->assertOk();

        $fila = $this->fila($id);
        $this->assertSame($ana, $fila->responsable_cumplimiento_id);
        $this->assertSame('Interventora', $fila->cargo_cumplimiento);
    }

    public function test_una_persona_que_no_existe_se_rechaza(): void
    {
        $this->crear(['responsable_cumplimiento_id' => 999, 'responsable_revision_id' => 998])
            ->assertStatus(422)
            ->assertJsonPath('errors.responsable_cumplimiento_id.0', 'El responsable de cumplimiento no existe.')
            ->assertJsonPath('errors.responsable_revision_id.0', 'El responsable de revisión no existe.');
        $this->assertSame(0, $this->borrador->requisitos()->count());
    }

    public function test_la_persona_debe_ser_un_entero_de_verdad(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');

        foreach ([(string) $ana, true, 1.5, 'Ana'] as $valor) {
            $this->crear(['responsable_cumplimiento_id' => $valor])
                ->assertStatus(422)
                ->assertJsonPath('errors.responsable_cumplimiento_id.0', 'Revisa el responsable de cumplimiento: debe ser un número entero.');
        }
        $this->assertSame(0, $this->borrador->requisitos()->count());
    }

    public function test_no_se_puede_elegir_una_persona_inactiva(): void
    {
        $ines = $this->responsable('Inés Ruiz', 'Revisora', activo: false);

        $this->crear(['responsable_revision_id' => $ines])
            ->assertStatus(422)
            ->assertJsonPath('errores.0', 'El responsable de revisión "Inés Ruiz" está inactivo.');

        // Tampoco en el otro campo, aunque ya sea la de revisión.
        $id = $this->crear()->json('id');
        DB::table('requisitos_catalogo')->where('id', $id)->update(['responsable_revision_id' => $ines]);
        $this->editar($id, ['responsable_cumplimiento_id' => $ines, 'responsable_revision_id' => $ines])
            ->assertStatus(422)
            ->assertJsonPath('errores.0', 'El responsable de cumplimiento "Inés Ruiz" está inactivo.');
    }

    public function test_una_persona_inactiva_ya_asignada_se_conserva_al_editar(): void
    {
        $ines = $this->responsable('Inés Ruiz', 'Revisora');
        $id = $this->crear(['responsable_revision_id' => $ines])->json('id');
        DB::table('responsable')->where('id', $ines)->update(['activo' => false, 'cargo' => 'Revisora jefe']);

        $this->editar($id, ['responsable_revision_id' => $ines, 'nota' => 'Cambio menor'])->assertOk();

        $fila = $this->fila($id);
        $this->assertSame($ines, $fila->responsable_revision_id);
        $this->assertSame('Revisora jefe', $fila->cargo_revision); // su cargo actual
    }

    public function test_el_servicio_tambien_rechaza_una_persona_que_no_existe(): void
    {
        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('El responsable de cumplimiento no existe.');

        app(CatalogoService::class)->guardarRequisito($this->borrador, ['texto' => 'X', 'responsable_cumplimiento_id' => 999]);
    }

    public function test_en_el_sgr_tambien_se_guarda_la_persona(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $borrador = app(CatalogoService::class)->crearBorrador(null);
        $a1 = DB::table('requisitos_catalogo')->where('catalogo_version_id', $borrador->id)->where('clave', 'a1')->first();

        $this->comoAdmin()->putJson($this->url("/requisitos/{$a1->id}", $borrador), [
            'clave' => $a1->clave, 'seccion' => $a1->seccion, 'fuente' => $a1->fuente, 'codigo' => $a1->codigo,
            'texto' => $a1->texto, 'condicion' => $a1->condicion, 'responsable_cumplimiento_id' => $ana,
        ])->assertOk();

        $fila = $this->fila($a1->id);
        $this->assertSame($ana, $fila->responsable_cumplimiento_id);
        $this->assertSame('Interventora', $fila->cargo_cumplimiento);
    }

    // ------------------------------------------------------------- listado

    public function test_el_listado_trae_las_personas(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $this->crear(['responsable_cumplimiento_id' => $ana]);

        $this->comoAdmin()->getJson($this->url('/requisitos'))->assertOk()
            ->assertJsonPath('0.responsable_cumplimiento_id', $ana)
            ->assertJsonPath('0.responsable_cumplimiento', ['id' => $ana, 'nombre' => 'Ana Pérez', 'cargo' => 'Interventora', 'activo' => true])
            ->assertJsonPath('0.responsable_revision_id', null)
            ->assertJsonPath('0.responsable_revision', null);
    }

    // ------------------------------------------------------------- copias

    public function test_el_borrador_nuevo_copia_las_personas(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $beto = $this->responsable('Beto Gómez', 'Supervisor');
        $this->crear(['responsable_cumplimiento_id' => $ana, 'responsable_revision_id' => $beto]);
        $this->comoAdmin()->postJson($this->url('/publicar'))->assertOk();

        $v2 = app(CatalogoService::class)->crearBorrador(null, $this->plantilla);

        $copia = $v2->requisitos()->sole();
        $this->assertSame($ana, $copia->responsable_cumplimiento_id);
        $this->assertSame($beto, $copia->responsable_revision_id);
        $this->assertSame('Interventora', $copia->cargo_cumplimiento);
    }

    public function test_un_inactivo_copiado_al_borrador_se_conserva_al_editarlo(): void
    {
        $ines = $this->responsable('Inés Ruiz', 'Revisora');
        $this->crear(['responsable_revision_id' => $ines]);
        $this->comoAdmin()->postJson($this->url('/publicar'))->assertOk();
        DB::table('responsable')->where('id', $ines)->update(['activo' => false]);
        $v2 = app(CatalogoService::class)->crearBorrador(null, $this->plantilla);
        $copia = $v2->requisitos()->sole();

        $this->comoAdmin()->putJson($this->url("/requisitos/{$copia->id}", $v2), [
            'texto' => 'Otro nombre', 'responsable_revision_id' => $ines,
        ])->assertOk();

        $this->assertSame($ines, $copia->refresh()->responsable_revision_id);
    }

    public function test_duplicar_la_plantilla_copia_las_personas(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $beto = $this->responsable('Beto Gómez', 'Supervisor');
        $this->crear(['responsable_cumplimiento_id' => $ana, 'responsable_revision_id' => $beto]);
        $this->comoAdmin()->postJson($this->url('/publicar'))->assertOk();

        $id = $this->comoAdmin()->postJson("/GestPro/plantillas/{$this->plantilla->id}/duplicar", ['nombre' => 'Copia'])
            ->assertCreated()->json('id');

        $copia = PlantillaChecklist::find($id)->versiones()->sole()->requisitos()->sole();
        $this->assertSame($ana, $copia->responsable_cumplimiento_id);
        $this->assertSame($beto, $copia->responsable_revision_id);
        $this->assertSame('Supervisor', $copia->cargo_revision);
    }

    // ------------------------------------------------------------- borrar la persona

    public function test_borrar_la_persona_deja_el_requisito_con_su_cargo(): void
    {
        $ana = $this->responsable('Ana Pérez', 'Interventora');
        $id = $this->crear(['responsable_cumplimiento_id' => $ana])->json('id');

        $this->comoAdmin()->postJson('/GestPro/eliminarResponsable', ['id' => $ana])->assertOk();

        $fila = $this->fila($id);
        $this->assertNull($fila->responsable_cumplimiento_id);
        $this->assertSame('Interventora', $fila->cargo_cumplimiento);
        $this->comoAdmin()->getJson($this->url('/requisitos'))->assertOk()
            ->assertJsonPath('0.responsable_cumplimiento', null)
            ->assertJsonPath('0.cargo_cumplimiento', 'Interventora');
    }
}
