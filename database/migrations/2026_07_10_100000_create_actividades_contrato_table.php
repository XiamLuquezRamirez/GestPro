<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedTinyInteger('peso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades_contrato');
    }
};
