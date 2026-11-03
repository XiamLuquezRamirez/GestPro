<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Checklist instanciado de un expediente. Nunca se chequea sobre el
        // catálogo: cada requisito aplicable se copia aquí como una fila.
        // `aplica`: al recalcular en borrador, un requisito que deja de aplicar se
        // marca false en vez de borrarse, conservando su cumplimiento por si vuelve.
        // `soporte_archivo` es una ruta en almacenamiento privado, no en public/.
        Schema::create('proyectos_requisitos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('proyectos_expedientes')->cascadeOnDelete();
            $table->foreignId('requisito_catalogo_id')->constrained('requisitos_catalogo')->restrictOnDelete();
            $table->boolean('aplica')->default(true);
            $table->boolean('cumplido')->default(false);
            $table->dateTime('fecha_cumplido')->nullable();
            $table->foreignId('usuario_cumplido_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->string('soporte_archivo', 255)->nullable();
            $table->string('soporte_nombre', 255)->nullable();
            $table->unsignedInteger('orden')->default(0);

            $table->unique(['expediente_id', 'requisito_catalogo_id'], 'proy_req_expediente_requisito_unique');
            $table->index(['expediente_id', 'aplica'], 'proy_req_expediente_aplica_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyectos_requisitos');
    }
};
