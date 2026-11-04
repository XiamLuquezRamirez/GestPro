<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null = sin configurar: el expediente aún no sirve para medir avance, porque
        // su configuración decide qué requisitos aplican.
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->dateTime('fecha_configuracion')->nullable()->after('fase_sgr');
        });

        // Los que ya existen se configuraron a mano al crearlos.
        DB::table('proyectos_expedientes')->update([
            'fecha_configuracion' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('proyectos_expedientes', function (Blueprint $table) {
            $table->dropColumn('fecha_configuracion');
        });
    }
};
