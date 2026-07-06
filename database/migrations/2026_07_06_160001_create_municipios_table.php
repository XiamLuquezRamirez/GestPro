<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->string('departamento', 5);
            $table->longText('imagen')->nullable();

            $table->foreign('departamento')->references('codigo')->on('departamentos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
