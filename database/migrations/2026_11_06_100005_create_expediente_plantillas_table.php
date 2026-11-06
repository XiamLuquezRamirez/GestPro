<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // up() solo corre si la migración no quedó registrada: si la tabla existe, es un resto de
        // un intento fallido, quizá sin sus claves foráneas. Sus filas se reconstruyen abajo.
        Schema::dropIfExists('expediente_plantillas');

        Schema::create('expediente_plantillas', function (Blueprint $table) {
            $table->foreignId('expediente_id')->constrained('proyectos_expedientes')->cascadeOnDelete();
            $table->foreignId('catalogo_version_id')->constrained('catalogo_versiones')->restrictOnDelete();
            $table->boolean('es_principal')->default(false);
            $table->primary(['expediente_id', 'catalogo_version_id']);
        });

        DB::table('expediente_plantillas')->insertUsing(
            ['expediente_id', 'catalogo_version_id', 'es_principal'],
            DB::table('proyectos_expedientes')->select('id', 'catalogo_version_id', DB::raw('1'))
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_plantillas');
    }
};
