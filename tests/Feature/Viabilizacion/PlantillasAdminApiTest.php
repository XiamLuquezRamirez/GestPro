<?php

namespace Tests\Feature\Viabilizacion;

use App\Enums\Rol;
use App\Models\FuenteFinanciacion;
use App\Models\PlantillaChecklist;
use App\Models\User;
use Database\Seeders\CatalogoRequisitosSgrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlantillasAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private int $sectorEducacion;
    private int $fuentePropios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoRequisitosSgrSeeder::class);
        $this->sectorEducacion = DB::table('sectores')->insertGetId(['nombre' => 'Educación', 'activo' => true]);
        $this->fuentePropios = FuenteFinanciacion::where('clave', FuenteFinanciacion::RECURSOS_PROPIOS)->value('id');
    }

    private function como(Rol $rol): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login(User::factory()->create(['rol' => $rol])));
    }

    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Requisitos generales de inversión pública',
            'descripcion' => 'Para todo proyecto de inversión',
            'alcance' => 'general',
            'todas_fuentes' => false,
            'fuente_ids' => [$this->fuentePropios],
            'todos_sectores' => true,
            ...$cambios,
        ];
    }

    private function crear(array $cambios = [])
    {
        return $this->como(Rol::Administrador)->postJson('/GestPro/plantillas', $this->datos($cambios));
    }

    public function test_crear_una_plantilla_la_deja_simple_activa_y_con_su_primera_version_en_borrador(): void
    {
        $respuesta = $this->crear()->assertCreated()
            ->assertJsonPath('motor', 'simple')
            ->assertJsonPath('activo', true)
            ->assertJsonPath('es_sgr', false)
            ->assertJsonPath('fuentes.0.id', $this->fuentePropios)
            ->assertJsonPath('vigente', null)
            ->assertJsonPath('borrador.numero', 1)
            ->assertJsonPath('versiones.0.estado', 'borrador');

        $this->assertSame(0, $respuesta->json('borrador.requisitos'));
    }

    public function test_la_lista_busca_por_nombre_y_muestra_la_version_vigente(): void
    {
        $this->crear()->assertCreated();

        $lista = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas')->assertOk()->json();
        $this->assertCount(2, $lista);
        $sgr = collect($lista)->firstWhere('es_sgr', true);
        $this->assertSame(207, $sgr['vigente']['requisitos']);

        $buscada = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas?buscar=inversi')->assertOk()->json();
        $this->assertSame(['Requisitos generales de inversión pública'], array_column($buscada, 'nombre'));
    }

    public function test_la_cuenta_de_requisitos_de_la_lista_solo_incluye_los_activos(): void
    {
        $sgr = PlantillaChecklist::sgr();
        $sgr->versionVigente()->requisitos()->orderBy('id')->first()->update(['activo' => false]);

        $lista = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas')->assertOk()->json();
        $this->assertSame(206, collect($lista)->firstWhere('es_sgr', true)['vigente']['requisitos']);

        // El historial de versiones sigue contando todo lo que tiene la versión.
        $this->como(Rol::Administrador)->getJson("/GestPro/plantillas/{$sgr->id}")->assertOk()
            ->assertJsonPath('vigente.requisitos', 206)
            ->assertJsonPath('versiones.0.requisitos', 207)
            ->assertJsonPath('versiones.0.requisitos_activos', 206);
    }

    public function test_el_nombre_no_se_repite(): void
    {
        $this->crear()->assertCreated();

        $this->crear(['nombre' => 'REQUISITOS GENERALES DE INVERSIÓN PÚBLICA'])->assertStatus(422);
        $this->crear(['nombre' => '  '])->assertStatus(422)->assertJsonPath('errors.nombre.0', 'Escribe el nombre de la plantilla.');
    }

    public function test_exige_fuentes_y_sectores_o_todas(): void
    {
        $this->crear(['fuente_ids' => [], 'todos_sectores' => false])->assertStatus(422)
            ->assertJsonPath('errores', [
                'Elige al menos una fuente de financiación o marca "Todas las fuentes".',
                'Elige al menos un sector o marca "Todos los sectores".',
            ]);
    }

    public function test_no_asocia_una_fuente_inactiva_nueva_pero_conserva_la_que_ya_tenia(): void
    {
        $id = $this->crear()->json('id');
        FuenteFinanciacion::whereKey($this->fuentePropios)->update(['activo' => false]);

        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$id}", $this->datos(['descripcion' => 'Editada']))->assertOk();

        $otra = $this->crear(['nombre' => 'Otra'])->assertStatus(422);
        $this->assertStringContainsString('está inactiva', $otra->json('errores.0'));
    }

    public function test_editar_cambia_datos_y_asociaciones(): void
    {
        $id = $this->crear()->json('id');

        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$id}", $this->datos([
            'nombre' => 'Sector educación', 'alcance' => 'especifica', 'todas_fuentes' => true, 'todos_sectores' => false,
            'sector_ids' => [$this->sectorEducacion],
        ]))->assertOk()
            ->assertJsonPath('nombre', 'Sector educación')
            ->assertJsonPath('alcance', 'especifica')
            ->assertJsonPath('fuentes', [])
            ->assertJsonPath('sectores.0.id', $this->sectorEducacion);
    }

    public function test_la_plantilla_sgr_no_se_desactiva_ni_pierde_la_fuente_sgr(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$sgr->id}/activar", ['activo' => false])->assertStatus(422)
            ->assertJsonPath('mensaje', 'La plantilla SGR no se puede desactivar: la usan los expedientes de regalías.');
        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$sgr->id}", $this->datos(['nombre' => 'SGR']))->assertStatus(422)
            ->assertJsonPath('errores.0', 'La plantilla SGR debe seguir asociada a la fuente SGR.');
    }

    public function test_activar_y_desactivar_otra_plantilla(): void
    {
        $id = $this->crear()->json('id');

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$id}/activar", ['activo' => false])->assertOk()->assertJsonPath('activo', false);
        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$id}/activar", ['activo' => true])->assertOk()->assertJsonPath('activo', true);
    }

    public function test_crear_una_version_nueva_copia_la_vigente(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$sgr->id}/versiones", ['descripcion' => 'Ajustes'])
            ->assertCreated()->assertJsonPath('borrador.numero', 2)->assertJsonPath('borrador.requisitos', 207);
    }

    public function test_el_detalle_dice_quien_publico_cada_version(): void
    {
        $admin = User::factory()->create(['rol' => Rol::Administrador, 'name' => 'Ana Admin']);
        $sgr = PlantillaChecklist::sgr();
        $sgr->versionVigente()->update(['usuario_publico_id' => $admin->id]);

        $this->como(Rol::Administrador)->getJson("/GestPro/plantillas/{$sgr->id}")->assertOk()
            ->assertJsonPath('versiones.0.publicada_por', 'Ana Admin')
            ->assertJsonPath('versiones.0.requisitos', 207);
    }

    public function test_la_plantilla_sgr_no_se_restringe_por_sectores(): void
    {
        $sgr = PlantillaChecklist::sgr();

        $this->como(Rol::Administrador)->putJson("/GestPro/plantillas/{$sgr->id}", $this->datos([
            'nombre' => 'SGR', 'todas_fuentes' => true, 'todos_sectores' => false, 'sector_ids' => [$this->sectorEducacion],
        ]))->assertStatus(422)
            ->assertJsonPath('errores.0', 'La plantilla SGR debe aplicar a todos los sectores: el evaluador decide qué requisitos sectoriales aplican.');
    }

    public function test_el_nombre_repetido_ignora_tildes_y_mayusculas(): void
    {
        $this->crear()->assertCreated();

        $this->crear(['nombre' => 'REQUISITOS GENERALES DE INVERSION PUBLICA'])->assertStatus(422)
            ->assertJsonPath('errores.0', 'Ya existe una plantilla llamada "Requisitos generales de inversión pública".');
    }

    public function test_la_busqueda_encuentra_por_descripcion_y_con_porcentaje(): void
    {
        $this->crear(['nombre' => 'Rural', 'descripcion' => 'Cobertura 100% rural'])->assertCreated();

        $admin = $this->como(Rol::Administrador);
        $this->assertSame(['Rural'], array_column($admin->getJson('/GestPro/plantillas?buscar=100%25')->assertOk()->json(), 'nombre'));
        $this->assertSame(['Rural'], array_column($this->como(Rol::Administrador)->getJson('/GestPro/plantillas?buscar=cobertura')->assertOk()->json(), 'nombre'));
    }

    public function test_la_busqueda_debe_ser_texto(): void
    {
        $this->como(Rol::Administrador)->getJson('/GestPro/plantillas?buscar[]=x')->assertStatus(422);
    }

    public function test_la_descripcion_tiene_limite_con_mensaje_en_espanol(): void
    {
        $this->crear(['descripcion' => str_repeat('a', 5001)])->assertStatus(422)
            ->assertJsonPath('errors.descripcion.0', 'En la descripción caben hasta 5000 caracteres.');
    }

    public function test_un_gestor_no_edita_activa_ni_versiona(): void
    {
        $id = $this->crear()->json('id');

        $this->como(Rol::Gestor)->putJson("/GestPro/plantillas/{$id}", $this->datos())->assertForbidden();
        $this->como(Rol::Gestor)->postJson("/GestPro/plantillas/{$id}/activar", ['activo' => false])->assertForbidden();
        $this->como(Rol::Gestor)->postJson("/GestPro/plantillas/{$id}/versiones", [])->assertForbidden();
    }

    public function test_el_detalle_ordena_las_versiones_de_la_mas_nueva_a_la_mas_antigua(): void
    {
        $sgr = PlantillaChecklist::sgr();
        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$sgr->id}/versiones", [])->assertCreated();

        $this->como(Rol::Administrador)->getJson("/GestPro/plantillas/{$sgr->id}")->assertOk()
            ->assertJsonPath('versiones.0.numero', 2)
            ->assertJsonPath('versiones.1.numero', 1);
    }

    public function test_solo_un_administrador_administra_plantillas(): void
    {
        $this->como(Rol::Gestor)->getJson('/GestPro/plantillas')->assertForbidden();
        $this->como(Rol::Gestor)->postJson('/GestPro/plantillas', $this->datos())->assertForbidden();
    }

    public function test_el_porcentaje_de_la_busqueda_no_actua_como_comodin(): void
    {
        $this->crear(['nombre' => 'Cobertura 100% rural'])->assertCreated();
        $this->crear(['nombre' => 'Cobertura 1000 rural'])->assertCreated();

        $encontradas = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas?buscar=100%25')->assertOk()->json();

        $this->assertSame(['Cobertura 100% rural'], array_column($encontradas, 'nombre'));
    }

    public function test_el_guion_bajo_de_la_busqueda_no_actua_como_comodin(): void
    {
        $this->crear(['nombre' => 'Zona_norte'])->assertCreated();
        $this->crear(['nombre' => 'Zona norte'])->assertCreated();

        $encontradas = $this->como(Rol::Administrador)->getJson('/GestPro/plantillas?buscar=zona_')->assertOk()->json();

        $this->assertSame(['Zona_norte'], array_column($encontradas, 'nombre'));
    }

    public function test_activar_valida_en_espanol(): void
    {
        $id = $this->crear()->json('id');

        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$id}/activar", [])->assertStatus(422)
            ->assertJsonPath('errors.activo.0', 'Completa el estado activo.');
        $this->como(Rol::Administrador)->postJson("/GestPro/plantillas/{$id}/activar", ['activo' => 'quizá'])->assertStatus(422)
            ->assertJsonPath('errors.activo.0', 'Revisa el estado activo: debe ser sí o no.');
    }
}
