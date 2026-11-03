<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sectores de inversión del SGR. Independiente de la tabla `sectores` de
        // GestPro, que es un catálogo propio más corto (ver mapeo en sectores).
        Schema::create('requisitos_sectores', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('nombre', 199);
            $table->string('fuente', 199)->nullable(); // p. ej. "Anexo 08 del Acuerdo 12/2024"
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos_sectores');
    }
};
