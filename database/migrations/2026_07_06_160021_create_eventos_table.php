<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->text('titulo')->nullable();
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->foreignId('tipo_eventos')->nullable()->constrained('tipo_eventos')->restrictOnDelete();
            $table->foreignId('prioridad')->nullable()->constrained('prioridades')->restrictOnDelete();
            $table->string('estado_evento', 20)->nullable();
            $table->foreignId('proyecto')->nullable()->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('responsable')->nullable()->constrained('responsable')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
