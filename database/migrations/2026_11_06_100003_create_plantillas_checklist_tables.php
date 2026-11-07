<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NOMBRE_SGR = 'SGR';
    private const DESCRIPCION_SGR = 'Orientaciones Transitorias + Acuerdo 12 de 2024';

    public function up(): void
    {
        $fuenteSgr = DB::table('fuentes_financiacion')->where('clave', 'sgr')->value('id')
            ?? throw new RuntimeException('Falta la fuente con clave sgr en fuentes_financiacion.');

        // up() solo corre si la migración no quedó registrada, así que estas tablas solo pueden ser
        // restos de un intento fallido. En MariaDB cada tabla son varias sentencias: una guarda
        // hasTable aceptaría una tabla sin sus claves foráneas o sin su índice único.
        $this->eliminarTablas();

        Schema::create('plantillas_checklist', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->nullable()->unique();
            $table->string('nombre', 199);
            $table->text('descripcion')->nullable();
            $table->text('alcance')->nullable();
            $table->boolean('todas_fuentes')->default(false);
            $table->boolean('todos_sectores')->default(false);
            $table->string('motor', 20)->default('simple');
            $table->boolean('activo')->default(true);
            $table->foreignId('usuario_creo_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plantilla_fuentes', function (Blueprint $table) {
            $table->foreignId('plantilla_id')->constrained('plantillas_checklist')->cascadeOnDelete();
            $table->foreignId('fuente_id')->constrained('fuentes_financiacion')->restrictOnDelete();
            $table->primary(['plantilla_id', 'fuente_id']);
        });

        Schema::create('plantilla_sectores', function (Blueprint $table) {
            $table->foreignId('plantilla_id')->constrained('plantillas_checklist')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectores')->restrictOnDelete();
            $table->primary(['plantilla_id', 'sector_id']);
        });

        DB::transaction(function () use ($fuenteSgr) {
            $plantilla = DB::table('plantillas_checklist')->insertGetId([
                'clave' => 'sgr',
                'nombre' => self::NOMBRE_SGR,
                'descripcion' => self::DESCRIPCION_SGR,
                'todas_fuentes' => false,
                'todos_sectores' => true,
                'motor' => 'sgr',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('plantilla_fuentes')->insert(['plantilla_id' => $plantilla, 'fuente_id' => $fuenteSgr]);
        });
    }

    public function down(): void
    {
        $otras = Schema::hasTable('plantillas_checklist')
            && DB::table('plantillas_checklist')->where('clave', '!=', 'sgr')->orWhereNull('clave')->exists();

        if ($otras) {
            throw new RuntimeException('No se puede revertir: hay plantillas distintas de SGR. Elimínelas o restaure un respaldo.');
        }

        if ($this->sgrEditada()) {
            throw new RuntimeException('No se puede revertir: la plantilla SGR fue editada y esos cambios se perderían. Restaure un respaldo.');
        }

        // Sin esto, se borrarían las asociaciones antes de que falle la clave foránea de las versiones.
        if (Schema::hasColumn('catalogo_versiones', 'plantilla_id')) {
            throw new RuntimeException('No se puede revertir: catalogo_versiones todavía depende de las plantillas. Revierta primero la migración de versiones.');
        }

        $this->eliminarTablas();
    }

    /** La plantilla SGR ya no es la que creó up(): sus datos o asociaciones cambiaron. */
    private function sgrEditada(): bool
    {
        $sgr = Schema::hasTable('plantillas_checklist') ? DB::table('plantillas_checklist')->where('clave', 'sgr')->first() : null;
        if (! $sgr) {
            return false;
        }

        $fuentes = DB::table('plantilla_fuentes')
            ->join('fuentes_financiacion', 'fuentes_financiacion.id', 'plantilla_fuentes.fuente_id')
            ->where('plantilla_id', $sgr->id)
            ->pluck('fuentes_financiacion.clave')
            ->all();

        return $sgr->nombre !== self::NOMBRE_SGR
            || $sgr->descripcion !== self::DESCRIPCION_SGR
            || $sgr->alcance !== null
            || (bool) $sgr->todas_fuentes
            || ! (bool) $sgr->todos_sectores
            || ! (bool) $sgr->activo
            || $fuentes !== ['sgr']
            || DB::table('plantilla_sectores')->where('plantilla_id', $sgr->id)->exists();
    }

    private function eliminarTablas(): void
    {
        Schema::dropIfExists('plantilla_sectores');
        Schema::dropIfExists('plantilla_fuentes');
        Schema::dropIfExists('plantillas_checklist');
    }
};
