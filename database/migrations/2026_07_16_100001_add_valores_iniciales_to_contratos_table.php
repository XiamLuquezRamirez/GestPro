<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->decimal('valor_inicial', 15, 2)->nullable()->after('valor');
            $table->date('fecha_fin_inicial')->nullable()->after('fecha_fin');
        });

        // Backfill: para contratos existentes, la base inmutable es el valor y
        // la fecha fin actuales (aún no hay modificaciones registradas).
        DB::statement('UPDATE contratos SET valor_inicial = valor WHERE valor_inicial IS NULL');
        DB::statement('UPDATE contratos SET fecha_fin_inicial = fecha_fin WHERE fecha_fin_inicial IS NULL');
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['valor_inicial', 'fecha_fin_inicial']);
        });
    }
};
