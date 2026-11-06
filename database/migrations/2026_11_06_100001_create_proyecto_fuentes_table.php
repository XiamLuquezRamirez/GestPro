<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_fuentes', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('fuente_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->primary(['proyecto_id', 'fuente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_fuentes');
    }
};
