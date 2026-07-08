<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_ubicaciones');
    }
};
