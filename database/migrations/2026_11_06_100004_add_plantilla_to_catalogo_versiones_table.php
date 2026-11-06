<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNICO_NUEVO = 'catalogo_versiones_plantilla_numero_unique';
    private const UNICO_ANTERIOR = 'catalogo_versiones_numero_unique';

    public function up(): void
    {
        $plantillaSgr = $this->plantillaSgr();

        // En MariaDB el DDL no es transaccional: cada paso se puede repetir si uno falla.
        if (! Schema::hasColumn('catalogo_versiones', 'plantilla_id')) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreignId('plantilla_id')->nullable()->after('id');
            });
        }
        if (! Schema::hasColumn('catalogo_versiones', 'usuario_publico_id')) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreignId('usuario_publico_id')->nullable()->after('fecha_publicacion');
            });
        }
        if (! $this->tieneForanea('usuario_publico_id')) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreign('usuario_publico_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        DB::table('catalogo_versiones')->whereNull('plantilla_id')->update(['plantilla_id' => $plantillaSgr]);

        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->foreignId('plantilla_id')->nullable(false)->change();
        });

        if (Schema::hasIndex('catalogo_versiones', self::UNICO_ANTERIOR)) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->dropUnique(self::UNICO_ANTERIOR);
            });
        }
        if (! Schema::hasIndex('catalogo_versiones', self::UNICO_NUEVO)) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->unique(['plantilla_id', 'numero'], self::UNICO_NUEVO);
            });
        }
        if (! $this->tieneForanea('plantilla_id')) {
            Schema::table('catalogo_versiones', function (Blueprint $table) {
                $table->foreign('plantilla_id')->references('id')->on('plantillas_checklist')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::table('catalogo_versiones')->where('plantilla_id', '!=', $this->plantillaSgr())->exists()) {
            throw new RuntimeException('No se puede revertir: hay versiones de plantillas distintas de SGR. Elimínelas o restaure un respaldo.');
        }
        if (DB::table('catalogo_versiones')->whereNotNull('usuario_publico_id')->exists()) {
            throw new RuntimeException('No se puede revertir: hay versiones que registran quién las publicó y ese dato se perdería. Restaure un respaldo.');
        }

        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropForeign(['plantilla_id']);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropUnique(self::UNICO_NUEVO);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropForeign(['usuario_publico_id']);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->dropColumn(['plantilla_id', 'usuario_publico_id']);
        });
        Schema::table('catalogo_versiones', function (Blueprint $table) {
            $table->unique('numero');
        });
    }

    private function tieneForanea(string $columna): bool
    {
        return collect(Schema::getForeignKeys('catalogo_versiones'))->contains(fn ($f) => $f['columns'] === [$columna]);
    }

    private function plantillaSgr(): int
    {
        return (int) (DB::table('plantillas_checklist')->where('clave', 'sgr')->value('id')
            ?? throw new RuntimeException('Falta la plantilla SGR en plantillas_checklist.'));
    }
};
