<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cierre del contrato (fase 7A): la fecha de terminación real, la fecha de liquidación y el valor
 * liquidado (decimal(15, 2), como `valor`). Todas admiten nulo: los contratos que ya existen
 * quedan sin datos de cierre, también los que ya están terminados o liquidados.
 */
return new class extends Migration
{
    private const TABLA = 'contratos';

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna lleva su guarda, para poder
        // reintentar sin dejar nada a medias.
        $columnas = [
            'fecha_terminacion_real' => fn (Blueprint $t) => $t->date('fecha_terminacion_real')->nullable()->after('fecha_fin'),
            'fecha_liquidacion' => fn (Blueprint $t) => $t->date('fecha_liquidacion')->nullable()->after('fecha_terminacion_real'),
            'valor_liquidado' => fn (Blueprint $t) => $t->decimal('valor_liquidado', 15, 2)->nullable()->after('fecha_liquidacion'),
        ];
        foreach ($columnas as $columna => $definir) {
            if (! Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, $definir);
            }
        }
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(
            ['fecha_terminacion_real', 'fecha_liquidacion', 'valor_liquidado'],
            fn (string $c) => Schema::hasColumn(self::TABLA, $c)
        ));
        if ($existentes && DB::table(self::TABLA)->where(function ($q) use ($existentes) {
            foreach ($existentes as $columna) {
                $q->orWhereNotNull($columna);
            }
        })->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos con fecha de terminación, fecha de liquidación o valor liquidado registrados, que se perderían. Restaure un respaldo.');
        }

        // Cada columna con su guarda.
        foreach (array_reverse($existentes) as $columna) {
            Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }
};
