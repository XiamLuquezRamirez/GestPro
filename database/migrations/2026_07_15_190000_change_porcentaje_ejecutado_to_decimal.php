<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avance_financiero', function (Blueprint $table) {
            // Antes unsignedTinyInteger: truncaba el porcentaje a entero (ej. 38.83 -> 38).
            // Se pasa a decimal(5,2) para conservar dos decimales (hasta 999.99%).
            $table->decimal('porcentaje_ejecutado', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('avance_financiero', function (Blueprint $table) {
            $table->unsignedTinyInteger('porcentaje_ejecutado')->nullable()->change();
        });
    }
};
