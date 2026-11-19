<?php

namespace Tests\Feature\Schema;

use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/*
 * Migración 23: revisión de cada etapa de un expediente de contratación (expediente_etapas):
 * estado, quién y cuándo, la observación y los requisitos que faltaban al aprobarla.
 */
class RevisionEtapasTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNAS = ['estado_revision', 'usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar'];

    private function migracion(): object
    {
        return require database_path('migrations/2026_11_19_100022_add_revision_a_expediente_etapas.php');
    }

    private function fila(array $datos = []): ExpedienteEtapa
    {
        $proyecto = Proyecto::create(['nombre' => 'Acueducto veredal']);
        $expediente = ProyectoExpediente::create(['proyecto_id' => $proyecto->id, 'estado' => ProyectoExpediente::BORRADOR]);

        return ExpedienteEtapa::create([
            'expediente_id' => $expediente->id,
            'etapa_id' => EtapaContratacion::where('clave', 'planeacion')->value('id'),
            ...$datos,
        ]);
    }

    public function test_agrega_las_columnas_de_la_revision(): void
    {
        $columnas = collect(Schema::getColumns('expediente_etapas'))->keyBy('name');

        foreach (self::COLUMNAS as $columna) {
            $this->assertTrue($columnas->has($columna), "Falta {$columna}.");
        }
        $this->assertFalse($columnas['estado_revision']['nullable']);
        foreach (['usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar'] as $columna) {
            $this->assertTrue($columnas[$columna]['nullable'], "{$columna} debía admitir null.");
        }
        $this->assertNotNull(collect(Schema::getForeignKeys('expediente_etapas'))
            ->first(fn ($f) => $f['columns'] === ['usuario_reviso_id'] && $f['foreign_table'] === 'users'));
    }

    public function test_una_fila_nace_sin_revisar_y_el_revisor_eliminado_queda_en_null(): void
    {
        $this->assertSame(ExpedienteEtapa::SIN_REVISAR, $this->fila()->fresh()->estado_revision);

        $revisor = User::factory()->create();
        $fila = $this->fila(['estado_revision' => ExpedienteEtapa::APROBADA, 'usuario_reviso_id' => $revisor->id, 'fecha_revision' => now(), 'pendientes_al_aprobar' => 2]);
        $this->assertSame([$revisor->id, 2], [$fila->fresh()->usuario_reviso_id, $fila->fresh()->pendientes_al_aprobar]);
        $this->assertSame($revisor->id, $fila->usuarioReviso->id);

        $revisor->delete();
        $this->assertNull($fila->fresh()->usuario_reviso_id);
    }

    public function test_el_rollback_se_detiene_si_hay_revisiones_y_ambos_sentidos_se_repiten(): void
    {
        $migracion = $this->migracion();
        $migracion->up(); // ya migrada: no falla
        $fila = $this->fila(['estado_revision' => ExpedienteEtapa::DEVUELTA, 'observacion_revision' => 'Falta el CDP.']);

        try {
            $migracion->down();
            $this->fail('El rollback debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Restaure un respaldo.', $e->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('expediente_etapas', 'estado_revision'));

        // Sin revisiones (una fila con solo fechas no se pierde) se revierte, y se repite sin fallar.
        $fila->update(['estado_revision' => ExpedienteEtapa::SIN_REVISAR, 'observacion_revision' => null]);
        $migracion->down();
        $migracion->down();
        foreach (self::COLUMNAS as $columna) {
            $this->assertFalse(Schema::hasColumn('expediente_etapas', $columna), "{$columna} debía caer.");
        }
        $this->assertSame(1, ExpedienteEtapa::query()->count());
        $migracion->up();
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('expediente_etapas', 'pendientes_al_aprobar'));
        $this->assertSame(ExpedienteEtapa::SIN_REVISAR, ExpedienteEtapa::query()->value('estado_revision'));
    }
}
