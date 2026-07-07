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
            $table->string('municipio', 20)->nullable();
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

            // References municipios.codigo (not .id): the real proyecto-creation form
            // (Parametros.jsx) submits the municipio's codigo, matching the app's
            // original pre-migration schema — not the Eloquent default id-based FK.
            $table->foreign('municipio')->references('codigo')->on('municipios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
