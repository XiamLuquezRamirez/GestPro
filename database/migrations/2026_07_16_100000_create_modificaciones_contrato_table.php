<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modificaciones_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('numero_otrosi', 50)->nullable();
            $table->string('tipo', 20); // adicion | prorroga | adicion_prorroga
            $table->decimal('valor_adicion', 15, 2)->nullable();
            $table->integer('dias_prorroga')->nullable();
            $table->date('fecha_modificacion');
            $table->text('justificacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modificaciones_contrato');
    }
};
