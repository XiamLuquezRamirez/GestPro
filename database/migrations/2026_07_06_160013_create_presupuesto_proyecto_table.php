<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_proyecto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto')->constrained('proyectos')->cascadeOnDelete();
            $table->text('componente')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_proyecto');
    }
};
