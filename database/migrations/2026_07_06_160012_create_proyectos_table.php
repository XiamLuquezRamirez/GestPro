<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio')->nullable()->constrained('municipios')->restrictOnDelete();
            $table->text('nombre')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->foreignId('estado')->nullable()->constrained('estados')->restrictOnDelete();
            $table->foreignId('fase')->nullable()->constrained('fases')->restrictOnDelete();
            $table->decimal('presupuesto', 15, 2)->nullable();
            $table->foreignId('entidad_presenta')->nullable()->constrained('entidades')->restrictOnDelete();
            $table->foreignId('entidad_financia')->nullable()->constrained('entidades')->restrictOnDelete();
            $table->text('fuente_financiacion')->nullable();
            $table->unsignedTinyInteger('progreso')->nullable();
            $table->foreignId('sector')->nullable()->constrained('sectores')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
