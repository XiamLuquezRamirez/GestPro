<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsable', function (Blueprint $table) {
            $table->id();
            $table->text('nombre')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsable');
    }
};
