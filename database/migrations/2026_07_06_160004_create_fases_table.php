<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fases', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 199)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('dashboard')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fases');
    }
};
