<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('icono', 20)->nullable();
            $table->foreignId('fase')->nullable()->constrained('fases')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados');
    }
};
