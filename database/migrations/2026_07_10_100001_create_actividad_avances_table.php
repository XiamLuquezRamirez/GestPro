<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_avances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades_contrato')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedTinyInteger('porcentaje_ejecucion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_avances');
    }
};
