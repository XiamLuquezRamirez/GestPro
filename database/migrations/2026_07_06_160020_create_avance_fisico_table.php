<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_fisico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->text('descripcion_avance_fisico')->nullable();
            $table->date('fecha_avance_fisico')->nullable();
            $table->unsignedTinyInteger('valor_avance_fisico')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_fisico');
    }
};
