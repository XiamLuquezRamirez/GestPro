<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requisitos de viabilización, uno por fila y por versión del catálogo.
        // - seccion A/B/D: aplican según `condicion` (DSL: siempre, fase=3, fase=1|2,
        //   fase!=1, comp=<k>, esp=<k>, combinables con & y ( | )).
        // - seccion C: aplican según sector_id/tipo_id; tipo_id null = todo el sector.
        // `clave` es el id estable del requisito (p. ej. "a1", "j2a"): permite
        // reconocer el mismo requisito al publicar una versión nueva.
        Schema::create('requisitos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_version_id')->constrained('catalogo_versiones')->cascadeOnDelete();
            $table->string('clave', 40);
            $table->char('seccion', 1); // A | B | C | D
            $table->string('fuente', 5); // a12 | ot
            $table->foreignId('sector_id')->nullable()->constrained('requisitos_sectores')->restrictOnDelete();
            $table->foreignId('tipo_id')->nullable()->constrained('requisitos_tipos')->restrictOnDelete();
            $table->string('subtitulo', 255)->nullable();
            $table->string('codigo', 60);
            $table->text('texto');
            $table->text('nota')->nullable();
            $table->string('condicion', 255)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->unique(['catalogo_version_id', 'clave'], 'req_catalogo_version_clave_unique');
            $table->index(['catalogo_version_id', 'seccion', 'orden'], 'req_catalogo_version_seccion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos_catalogo');
    }
};
