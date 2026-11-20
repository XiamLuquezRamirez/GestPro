<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Suspensión y reinicio del contrato (fase 7B): una suspensión es una fila de
 * modificaciones_contrato con tipo = 'suspension'. Se agregan la fecha de reinicio (null mientras
 * la suspensión está abierta) y la fecha prevista de reinicio (opcional). Ambas admiten nulo: las
 * adiciones y prórrogas que ya existen quedan sin ellas.
 */
return new class extends Migration
{
    private const TABLA = 'modificaciones_contrato';

    private const COLUMNAS = ['fecha_reinicio', 'fecha_reinicio_prevista'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna lleva su guarda, para poder
        // reintentar sin dejar nada a medias.
        $columnas = [
            'fecha_reinicio' => fn (Blueprint $t) => $t->date('fecha_reinicio')->nullable()->after('fecha_modificacion'),
            'fecha_reinicio_prevista' => fn (Blueprint $t) => $t->date('fecha_reinicio_prevista')->nullable()->after('fecha_reinicio'),
        ];
        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, $definir);
            }
        }
    }

    public function down(): void
    {
        if (DB::table(self::TABLA)->where('tipo', 'suspension')->exists()) {
            throw new RuntimeException('No se puede revertir: hay suspensiones de contratos registradas, que se perderían. Restaure un respaldo.');
        }

        // Cada columna con su guarda.
        foreach (array_reverse(self::COLUMNAS) as $columna) {
            if (Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
