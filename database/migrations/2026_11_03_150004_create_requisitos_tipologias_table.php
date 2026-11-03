<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipologías y fuentes especiales (Paz, FONPET, desastre, ...). Las claves
        // se usan en el DSL de condiciones: esp=<clave>.
        Schema::create('requisitos_tipologias', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('nombre', 199);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos_tipologias');
    }
};
