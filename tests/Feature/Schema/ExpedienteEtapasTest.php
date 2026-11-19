<?php

namespace Tests\Feature\Schema;

use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 22: cronograma de las etapas de un expediente de contratación (expediente_etapas),
 * con la fecha programada y la real de cada etapa.
 */
class ExpedienteEtapasTest extends TestCase
{
    use RefreshDatabase;

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100021_create_expediente_etapas_table.php');
    }

    private function expediente(): ProyectoExpediente
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);

        return ProyectoExpediente::create(['proyecto_id' => $proyecto->id, 'estado' => ProyectoExpediente::BORRADOR]);
    }

    private function etapa(string $clave = 'planeacion'): EtapaContratacion
    {
        return EtapaContratacion::where('clave', $clave)->firstOrFail();
    }

    public function test_la_tabla_tiene_las_columnas_del_cronograma(): void
    {
        $columnas = collect(Schema::getColumns('expediente_etapas'))->keyBy('name');

        $this->assertSame(
            ['id', 'expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id', 'created_at', 'updated_at'],
            $columnas->keys()->all()
        );
        $this->assertTrue($columnas['fecha_programada']['nullable']);
        $this->assertTrue($columnas['fecha_real']['nullable']);
        $this->assertTrue($columnas['usuario_actualizo_id']['nullable']);
        $this->assertFalse($columnas['fecha_real_sugerida']['nullable']);
        $this->assertNotNull(collect(Schema::getIndexes('expediente_etapas'))
            ->first(fn ($i) => $i['unique'] && $i['columns'] === ['expediente_id', 'etapa_id']));
    }

    public function test_una_fila_por_expediente_y_etapa_y_cae_con_el_expediente(): void
    {
        $expediente = $this->expediente();
        $fila = ExpedienteEtapa::create(['expediente_id' => $expediente->id, 'etapa_id' => $this->etapa()->id, 'fecha_programada' => '2026-11-20']);

        $this->assertFalse($fila->fresh()->fecha_real_sugerida);
        $this->assertSame('2026-11-20', $fila->fresh()->fecha_programada->format('Y-m-d'));
        $this->assertSame([$fila->id], $expediente->etapasCronograma()->pluck('id')->all());
        $this->assertSame($expediente->id, $fila->expediente->id);
        $this->assertSame('planeacion', $fila->etapa->clave);

        try {
            ExpedienteEtapa::create(['expediente_id' => $expediente->id, 'etapa_id' => $this->etapa()->id]);
            $this->fail('La etapa no debía repetirse en el expediente.');
        } catch (QueryException) {
        }

        $expediente->delete();
        $this->assertSame(0, DB::table('expediente_etapas')->count());
    }

    public function test_una_etapa_con_cronograma_no_se_elimina(): void
    {
        $etapa = $this->etapa('adendas');
        ExpedienteEtapa::create(['expediente_id' => $this->expediente()->id, 'etapa_id' => $etapa->id]);

        $this->expectException(QueryException::class);
        $etapa->delete();
    }

    public function test_el_rollback_se_detiene_si_hay_registros_y_ambos_sentidos_se_repiten(): void
    {
        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $fila = ExpedienteEtapa::create(['expediente_id' => $this->expediente()->id, 'etapa_id' => $this->etapa()->id]);

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasTable('expediente_etapas'));

        $fila->delete();
        $migracion->down();
        $migracion->down();
        $this->assertFalse(Schema::hasTable('expediente_etapas'));
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasTable('expediente_etapas'));
    }
}
