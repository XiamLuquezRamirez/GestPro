<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Versiones del catálogo de requisitos de viabilización. Las filas de
        // requisitos_catalogo pertenecen a una versión y son inmutables una vez
        // publicada: así un cambio futuro no altera expedientes ya evaluados.
        Schema::create('catalogo_versiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero')->unique();
            $table->string('estado', 20)->default('borrador'); // borrador | publicada
            $table->string('descripcion', 255)->nullable();
            $table->dateTime('fecha_publicacion')->nullable();
            $table->boolean('vigente')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_versiones');
    }
};
