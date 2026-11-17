<?php

namespace Tests\Feature\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\EtapaContratacion;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Comando plantillas:sembrar-contratacion: el borrador "Licitación pública – SECOP II". */
class SembrarPlantillaContratacionTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'licitacion_publica_secop_ii';
    private const NOMBRE = 'Licitación pública – SECOP II';
    private const REFERENCIA = 'Guía CCE-SEC-G1-18 (SECOP II, licitación pública)';

    /** Requisitos que siembra cada etapa (sección 4 del diseño). */
    private const POR_ETAPA = [
        'planeacion' => 4,
        'pliego_borrador' => 6,
        'publicacion_borrador' => 2,
        'observaciones_borrador' => 1,
        'pliego_definitivo' => 2,
        'adendas' => 1,
        'cierre' => 1,
        'evaluacion' => 2,
        'observaciones_informe' => 2,
        'adjudicacion' => 2,
        'contrato' => 2,
    ];

    /** Los condicionados y cuándo aplican; el resto es obligatorio. */
    private const CONDICIONADOS = [
        'Código BPIN' => 'Si el gasto es de inversión.',
        'Porcentaje mínimo para pequeños productores (Decreto 248 de 2021)' => 'Si se compran alimentos.',
        'Adendas publicadas a más tardar 3 días hábiles antes del cierre' => 'Si hubo adendas.',
        'Subsanaciones solicitadas e incorporadas' => 'Si hubo subsanaciones.',
        'Nuevo informe de evaluación' => 'Si cambió la evaluación.',
    ];

    private function sembrada(): ?PlantillaChecklist
    {
        return PlantillaChecklist::where('clave', self::CLAVE)->first();
    }

    private function requisitos(PlantillaChecklist $plantilla)
    {
        return RequisitoCatalogo::query()
            ->whereIn('catalogo_version_id', $plantilla->versiones()->select('id'))
            ->with('etapa')
            ->orderBy('orden')
            ->get();
    }

    public function test_crea_la_plantilla_de_contratacion_en_borrador_con_los_requisitos_de_cada_etapa(): void
    {
        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Plantilla "' . self::NOMBRE . '" creada en borrador con 25 requisitos.')
            ->assertSuccessful();

        $plantilla = $this->sembrada();
        $this->assertNotNull($plantilla);
        $this->assertSame(self::NOMBRE, $plantilla->nombre);
        $this->assertSame(PlantillaChecklist::USO_CONTRATACION, $plantilla->uso);
        $this->assertSame(PlantillaChecklist::MOTOR_SIMPLE, $plantilla->motor);
        $this->assertTrue($plantilla->activo);
        $this->assertTrue($plantilla->todas_fuentes);
        $this->assertTrue($plantilla->todos_sectores);
        $this->assertFalse($plantilla->todas_modalidades);
        $this->assertSame(['licitacion_publica'], $plantilla->modalidades()->pluck('clave')->all());

        // Solo un borrador: no se publica hasta que la entidad la valide.
        $this->assertSame([CatalogoVersion::BORRADOR], $plantilla->versiones()->pluck('estado')->all());
        $this->assertNull($plantilla->versionVigente());

        $requisitos = $this->requisitos($plantilla);
        $this->assertSame(self::POR_ETAPA, $requisitos->countBy(fn ($r) => $r->etapa->clave)->all());
        $this->assertSame(range(1, 25), $requisitos->pluck('orden')->all());
        // En el orden del catálogo de etapas.
        $this->assertSame($requisitos->pluck('etapa.orden')->sort()->values()->all(), $requisitos->pluck('etapa.orden')->all());
        $this->assertTrue($requisitos->every(fn ($r) => $r->activo && $r->referencia_normativa === self::REFERENCIA));

        $condicionados = $requisitos->where('caracter', RequisitoCatalogo::CONDICIONADO);
        $this->assertSame(self::CONDICIONADOS, $condicionados->pluck('condicion_descripcion', 'texto')->all());
        $this->assertSame(20, $requisitos->where('caracter', RequisitoCatalogo::OBLIGATORIO)->count());

        $this->assertSame('pliego_borrador', $requisitos->firstWhere('texto', 'Porcentaje mínimo para pequeños productores (Decreto 248 de 2021)')->etapa->clave);
        $this->assertSame('adendas', $requisitos->firstWhere('texto', 'Adendas publicadas a más tardar 3 días hábiles antes del cierre')->etapa->clave);
        $this->assertStringContainsString('la guía no lo detalla', $requisitos->firstWhere('texto', 'Garantías aprobadas')->nota);
    }

    public function test_no_se_ofrece_a_los_expedientes_de_viabilizacion_ni_publicada(): void
    {
        $this->artisan('plantillas:sembrar-contratacion')->assertSuccessful();
        $plantilla = $this->sembrada();
        $proyecto = Proyecto::create(['nombre' => 'Acueducto']);
        $proyecto->fuentes()->sync(FuenteFinanciacion::pluck('id')->all());
        $plantillas = app(PlantillasExpediente::class);

        $this->assertNotContains($plantilla->id, $plantillas->idsCompatibles($proyecto));

        // Publicarla (lo hará un administrador) tampoco la ofrece: es de contratación.
        $borrador = $plantilla->versiones()->sole();
        app(CatalogoService::class)->publicar($borrador);
        $this->assertSame(CatalogoVersion::PUBLICADA, $borrador->fresh()->estado);

        $this->assertNotContains($plantilla->id, $plantillas->idsCompatibles($proyecto));
        $this->assertNotContains($plantilla->id, array_column($plantillas->disponibles($proyecto)['plantillas'], 'id'));
        $this->assertNull($plantillas->sgrCompatible($proyecto));
    }

    public function test_una_segunda_ejecucion_no_duplica_nada(): void
    {
        $this->artisan('plantillas:sembrar-contratacion')->assertSuccessful();

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('La plantilla "' . self::NOMBRE . '" ya existe: no se creó nada.')
            ->assertSuccessful();

        $this->assertSame(1, PlantillaChecklist::where('clave', self::CLAVE)->count());
        $this->assertSame(1, $this->sembrada()->versiones()->count());
        $this->assertCount(25, $this->requisitos($this->sembrada()));
    }

    public function test_en_simulacion_lista_lo_que_crearia_sin_escribir(): void
    {
        $plantillas = PlantillaChecklist::count();

        $this->artisan('plantillas:sembrar-contratacion', ['--simular' => true])
            ->expectsOutputToContain('Planeación y estudios previos: Código BPIN (condicionado: Si el gasto es de inversión.)')
            ->expectsOutputToContain('Contrato y garantías: Garantías aprobadas')
            ->expectsOutputToContain('Se crearía la plantilla "' . self::NOMBRE . '" en borrador con 25 requisitos.')
            ->assertSuccessful();

        $this->assertSame($plantillas, PlantillaChecklist::count());
        $this->assertDatabaseCount('requisitos_catalogo', 0);
    }

    public function test_falla_con_un_mensaje_claro_si_falta_una_etapa_sembrada(): void
    {
        EtapaContratacion::whereIn('clave', ['adendas', 'cierre'])->delete();

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Faltan etapas de contratación sembradas: adendas, cierre.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_falla_con_un_mensaje_claro_si_una_etapa_esta_inactiva(): void
    {
        EtapaContratacion::where('clave', 'adendas')->update(['activo' => false]);

        $this->artisan('plantillas:sembrar-contratacion', ['--simular' => true])
            ->expectsOutputToContain('Hay etapas de contratación inactivas: adendas.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_falla_con_un_mensaje_claro_si_falta_la_modalidad_sembrada(): void
    {
        Modalidad::where('clave', 'licitacion_publica')->delete();

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Falta la modalidad sembrada licitacion_publica.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_si_ya_hay_una_plantilla_con_el_mismo_nombre_no_crea_nada_y_lo_dice(): void
    {
        DB::table('plantillas_checklist')->insert(['nombre' => self::NOMBRE, 'motor' => 'simple', 'todas_fuentes' => true, 'todos_sectores' => true]);

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Ya existe una plantilla llamada "' . self::NOMBRE . '".')
            ->assertFailed();

        $this->assertNull($this->sembrada());
        $this->assertDatabaseCount('requisitos_catalogo', 0);
    }

    public function test_en_simulacion_tambien_avisa_si_ya_hay_una_plantilla_con_el_mismo_nombre(): void
    {
        // Sin la clave y con otras mayúsculas y sin tildes: PlantillaService lo tomaría por el mismo nombre.
        DB::table('plantillas_checklist')->insert(['nombre' => 'LICITACION PUBLICA – SECOP II', 'motor' => 'simple', 'todas_fuentes' => true, 'todos_sectores' => true]);

        $this->artisan('plantillas:sembrar-contratacion', ['--simular' => true])
            ->expectsOutputToContain('Ya existe una plantilla llamada "LICITACION PUBLICA – SECOP II".')
            ->doesntExpectOutputToContain('Se crearía la plantilla')
            ->assertFailed();

        $this->assertNull($this->sembrada());
    }

    public function test_si_falla_a_mitad_de_la_siembra_no_deja_nada(): void
    {
        $plantillas = PlantillaChecklist::count();
        $versiones = CatalogoVersion::count();
        // Un error al guardar el requisito 15, cuando ya hay plantilla, modalidad, borrador y 14 requisitos.
        RequisitoCatalogo::creating(function (RequisitoCatalogo $requisito) {
            if ($requisito->texto === 'Pliego definitivo publicado') {
                throw new ReglaNegocioException('El requisito no es válido.', ['Falla simulada.']);
            }
        });

        $this->artisan('plantillas:sembrar-contratacion')
            ->expectsOutputToContain('Falla simulada.')
            ->assertFailed();

        $this->assertNull($this->sembrada());
        $this->assertSame($plantillas, PlantillaChecklist::count());
        $this->assertDatabaseCount('requisitos_catalogo', 0);
        $this->assertDatabaseCount('plantilla_modalidades', 0);
        $this->assertSame($versiones, CatalogoVersion::count());
    }

    public function test_el_comando_describe_lo_que_hace(): void
    {
        $this->artisan('list')
            ->expectsOutputToContain('Crea en borrador la plantilla de contratación "Licitación pública – SECOP II"')
            ->assertSuccessful();
    }
}
