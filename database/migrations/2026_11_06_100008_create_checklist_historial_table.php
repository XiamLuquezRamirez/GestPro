<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // up() solo corre si la migración no quedó registrada: si la tabla existe, es un resto
        // de un intento fallido, quizá sin sus índices.
        Schema::dropIfExists('checklist_historial');

        // Sin claves foráneas a propósito: el historial debe sobrevivir a la eliminación de
        // la fila, del expediente o del usuario que registra.
        Schema::create('checklist_historial', function (Blueprint $table) {
            $table->id();
            $table->string('entidad_tipo', 30);
            $table->unsignedBigInteger('entidad_id');
            $table->unsignedBigInteger('expediente_id')->nullable();
            $table->string('evento', 50);
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['entidad_tipo', 'entidad_id'], 'checklist_historial_entidad_idx');
            $table->index('expediente_id', 'checklist_historial_expediente_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('checklist_historial') && DB::table('checklist_historial')->exists()) {
            throw new RuntimeException('No se puede revertir: el historial del checklist tiene registros que se perderían. Restaure un respaldo.');
        }

        Schema::dropIfExists('checklist_historial');
    }
};
