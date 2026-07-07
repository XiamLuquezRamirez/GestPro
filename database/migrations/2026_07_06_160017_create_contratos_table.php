<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->string('n_contrato', 100)->nullable();
            $table->text('objeto')->nullable();
            $table->text('contratante')->nullable();
            $table->text('contratista')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->text('interventoria')->nullable();
            $table->unsignedTinyInteger('avance_fisico')->nullable();
            $table->unsignedTinyInteger('avance_financiero')->nullable();
            $table->string('estado', 30)->nullable();
            $table->boolean('anticipo')->nullable()->default(false);
            $table->foreignId('proceso_licitacion')->nullable()->constrained('procesos_licitacion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
