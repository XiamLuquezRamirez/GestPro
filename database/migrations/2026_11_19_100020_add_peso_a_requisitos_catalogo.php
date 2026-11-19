<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cada requisito de plantilla tiene un peso (requisitos_catalogo.peso) con el que cuenta en el
 * avance de un expediente de contratación. Todos los requisitos que ya existen quedan con 1, que
 * es contar como hasta ahora. La viabilización no lo usa.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: el paso lleva su guarda, para poder reintentar.
        if (! Schema::hasColumn('requisitos_catalogo', 'peso')) {
            Schema::table('requisitos_catalogo', function (Blueprint $table) {
                $table->unsignedSmallInteger('peso')->default(1)->after('etapa_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('requisitos_catalogo', 'peso')) {
            return;
        }
        if (DB::table('requisitos_catalogo')->where('peso', '!=', 1)->exists()) {
            throw new RuntimeException('No se puede revertir: hay requisitos de plantilla con un peso distinto de 1, que se perdería. Restaure un respaldo.');
        }

        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->dropColumn('peso');
        });
    }
};
