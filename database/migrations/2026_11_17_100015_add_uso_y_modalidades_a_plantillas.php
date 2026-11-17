<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Una plantilla de checklist es de viabilización o de contratación (`uso`). El uso se elige al
 * crearla y no cambia; las que ya existen, también la SGR, quedan de viabilización. Una
 * plantilla de contratación se asocia además a modalidades (plantilla_modalidades) o a todas
 * (`todas_modalidades`). Los valores válidos los controla la aplicación (PlantillaChecklist::USOS).
 */
return new class extends Migration
{
    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Las claves foráneas van después de la tabla.
        if (! Schema::hasColumn('plantillas_checklist', 'uso')) {
            Schema::table('plantillas_checklist', function (Blueprint $table) {
                $table->string('uso', 20)->default('viabilizacion');
            });
        }
        if (! Schema::hasColumn('plantillas_checklist', 'todas_modalidades')) {
            Schema::table('plantillas_checklist', function (Blueprint $table) {
                $table->boolean('todas_modalidades')->default(false);
            });
        }

        if (! Schema::hasTable('plantilla_modalidades')) {
            Schema::create('plantilla_modalidades', function (Blueprint $table) {
                $table->unsignedBigInteger('plantilla_id');
                $table->unsignedBigInteger('modalidad_id');
                $table->primary(['plantilla_id', 'modalidad_id']);
            });
        }
        if (! $this->foranea('plantilla_id')) {
            Schema::table('plantilla_modalidades', function (Blueprint $table) {
                $table->foreign('plantilla_id')->references('id')->on('plantillas_checklist')->cascadeOnDelete();
            });
        }
        if (! $this->foranea('modalidad_id')) {
            // Una modalidad asociada a una plantilla no se borra: se desactiva.
            Schema::table('plantilla_modalidades', function (Blueprint $table) {
                $table->foreign('modalidad_id')->references('id')->on('modalidades')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        $contratacion = Schema::hasColumn('plantillas_checklist', 'uso')
            && DB::table('plantillas_checklist')->where('uso', '!=', 'viabilizacion')->exists();
        $todas = Schema::hasColumn('plantillas_checklist', 'todas_modalidades')
            && DB::table('plantillas_checklist')->where('todas_modalidades', true)->exists();
        $asociadas = Schema::hasTable('plantilla_modalidades') && DB::table('plantilla_modalidades')->exists();
        if ($contratacion || $todas || $asociadas) {
            throw new RuntimeException('No se puede revertir: hay plantillas de contratación o plantillas asociadas a modalidades, que se perderían. Restaure un respaldo.');
        }

        // Primero el pivote, después las columnas; cada paso con su guarda.
        Schema::dropIfExists('plantilla_modalidades');
        foreach (['todas_modalidades', 'uso'] as $columna) {
            if (Schema::hasColumn('plantillas_checklist', $columna)) {
                Schema::table('plantillas_checklist', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('plantilla_modalidades'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
