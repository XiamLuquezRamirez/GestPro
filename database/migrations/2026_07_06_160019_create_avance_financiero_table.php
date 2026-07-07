<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avance_financiero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->text('descripcion')->nullable();
            $table->date('fecha_acta')->nullable();
            $table->decimal('valor_facturado', 15, 2)->nullable();
            $table->decimal('amortizacion_50', 15, 2)->nullable();
            $table->decimal('valor_presente_acta', 15, 2)->nullable();
            $table->unsignedTinyInteger('porcentaje_ejecutado')->nullable();
            $table->text('anexo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avance_financiero');
    }
};
