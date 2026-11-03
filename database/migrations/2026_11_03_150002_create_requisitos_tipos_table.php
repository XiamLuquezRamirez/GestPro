<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de proyecto por sector SGR (equivale al mapa TIPOS del prototipo).
        Schema::create('requisitos_tipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained('requisitos_sectores')->restrictOnDelete();
            $table->string('clave', 60);
            $table->text('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->unique(['sector_id', 'clave'], 'req_tipos_sector_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos_tipos');
    }
};
