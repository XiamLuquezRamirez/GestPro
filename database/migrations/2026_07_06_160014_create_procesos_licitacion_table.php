<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_licitacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->string('codigo_proceso', 100)->nullable();
            $table->foreignId('tipo_proceso')->nullable()->constrained('tipos_procesos')->restrictOnDelete();
            $table->foreignId('modalidad')->nullable()->constrained('modalidades')->restrictOnDelete();
            $table->foreignId('entidad_contratante')->nullable()->constrained('entidades')->restrictOnDelete();
            $table->unsignedTinyInteger('tipo_proponente')->nullable();
            $table->text('entidad_proponente')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos_licitacion');
    }
};
