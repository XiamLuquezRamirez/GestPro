<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fuenteSgr = DB::table('fuentes_financiacion')->where('clave', 'sgr')->value('id')
            ?? throw new RuntimeException('Falta la fuente con clave sgr en fuentes_financiacion.');

        if (! Schema::hasTable('plantillas_checklist')) {
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
        }

        if (! Schema::hasTable('plantilla_fuentes')) {
            Schema::create('plantilla_fuentes', function (Blueprint $table) {
                $table->foreignId('plantilla_id')->constrained('plantillas_checklist')->cascadeOnDelete();
                $table->foreignId('fuente_id')->constrained('fuentes_financiacion')->restrictOnDelete();
                $table->primary(['plantilla_id', 'fuente_id']);
            });
        }

        if (! Schema::hasTable('plantilla_sectores')) {
            Schema::create('plantilla_sectores', function (Blueprint $table) {
                $table->foreignId('plantilla_id')->constrained('plantillas_checklist')->cascadeOnDelete();
                $table->foreignId('sector_id')->constrained('sectores')->restrictOnDelete();
                $table->primary(['plantilla_id', 'sector_id']);
            });
        }

        DB::transaction(function () use ($fuenteSgr) {
            if (DB::table('plantillas_checklist')->where('clave', 'sgr')->exists()) {
                return;
            }

            $plantilla = DB::table('plantillas_checklist')->insertGetId([
                'clave' => 'sgr',
                'nombre' => 'SGR',
                'descripcion' => 'Orientaciones Transitorias + Acuerdo 12 de 2024',
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
        Schema::dropIfExists('plantilla_sectores');
        Schema::dropIfExists('plantilla_fuentes');
        Schema::dropIfExists('plantillas_checklist');
    }
};
