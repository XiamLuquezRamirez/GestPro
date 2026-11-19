<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Revisión de cada etapa de un expediente de contratación (R15): `estado_revision` (sin_revisar,
 * aprobada o devuelta), quién la revisó y cuándo, la observación de la revisión y cuántos
 * requisitos de la etapa faltaban al aprobarla. Las filas que ya existen quedan sin revisar. El
 * usuario que revisó queda en null si se elimina. Solo avisa: nada se bloquea por la revisión.
 */
return new class extends Migration
{
    private const TABLA = 'expediente_etapas';

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna y la clave foránea llevan su
        // guarda, para poder reintentar sin dejar nada a medias.
        $columnas = [
            'estado_revision' => fn (Blueprint $t) => $t->string('estado_revision', 20)->default('sin_revisar')->after('fecha_real_sugerida'),
            'usuario_reviso_id' => fn (Blueprint $t) => $t->unsignedBigInteger('usuario_reviso_id')->nullable()->after('estado_revision'),
            'fecha_revision' => fn (Blueprint $t) => $t->timestamp('fecha_revision')->nullable()->after('usuario_reviso_id'),
            'observacion_revision' => fn (Blueprint $t) => $t->text('observacion_revision')->nullable()->after('fecha_revision'),
            'pendientes_al_aprobar' => fn (Blueprint $t) => $t->unsignedSmallInteger('pendientes_al_aprobar')->nullable()->after('observacion_revision'),
        ];
        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, $definir);
            }
        }
        if (! $this->foranea()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->foreign('usuario_reviso_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLA, 'estado_revision')) {
            return;
        }
        if (DB::table(self::TABLA)->where('estado_revision', '!=', 'sin_revisar')->exists()) {
            throw new RuntimeException('No se puede revertir: hay etapas aprobadas o devueltas en los expedientes de contratación, cuya revisión se perdería. Restaure un respaldo.');
        }

        // La clave foránea cae antes que su columna; cada paso con su guarda.
        if ($this->foranea()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->dropForeign(['usuario_reviso_id']);
            });
        }
        foreach (['pendientes_al_aprobar', 'observacion_revision', 'fecha_revision', 'usuario_reviso_id', 'estado_revision'] as $columna) {
            if (Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLA))->first(fn ($f) => $f['columns'] === ['usuario_reviso_id']);
    }
};
