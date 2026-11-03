<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expediente de viabilización SGR de un proyecto. Un proyecto puede tener
        // varios (re-radicación tras devolución), por eso no hay unique(proyecto_id).
        // `catalogo_version_id` congela la versión del catálogo con la que se armó.
        // `fase_sgr` (1 Perfil, 2 Prefactibilidad, 3 Factibilidad) es distinta de
        // proyectos.fase, que es la etapa del ciclo de vida en GestPro.
        Schema::create('proyectos_expedientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('catalogo_version_id')->constrained('catalogo_versiones')->restrictOnDelete();
            $table->unsignedTinyInteger('fase_sgr')->default(3);
            $table->string('estado', 20)->default('borrador'); // borrador | radicado
            $table->dateTime('fecha_radicado')->nullable();
            $table->foreignId('usuario_radicado_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('usuario_creo_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['proyecto_id', 'estado'], 'proy_expedientes_proyecto_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos_expedientes');
    }
};
