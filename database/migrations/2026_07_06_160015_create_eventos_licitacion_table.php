<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_licitacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('proceso')->constrained('procesos_licitacion')->cascadeOnDelete();
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->boolean('cumplido')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_licitacion');
    }
};
