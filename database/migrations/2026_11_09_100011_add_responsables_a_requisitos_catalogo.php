<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La plantilla guarda la persona responsable de cumplir y de revisar cada requisito, no solo su
 * cargo: con dos funcionarios del mismo cargo, el cargo no dice quién es (decisión del usuario).
 * cargo_cumplimiento y cargo_revision se conservan: guardan el cargo de la persona al elegirla y,
 * si la persona se elimina, el requisito sigue diciendo el cargo.
 */
return new class extends Migration
{
    private const COLUMNAS = ['responsable_cumplimiento_id', 'responsable_revision_id'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la columna, después la clave foránea.
        foreach (self::COLUMNAS as $columna) {
            if (! Schema::hasColumn('requisitos_catalogo', $columna)) {
                Schema::table('requisitos_catalogo', function (Blueprint $table) use ($columna) {
                    $table->unsignedBigInteger($columna)->nullable();
                });
            }
            if (! $this->foranea($columna)) {
                // Un responsable eliminado no rompe la plantilla: queda sin persona y con su cargo.
                Schema::table('requisitos_catalogo', function (Blueprint $table) use ($columna) {
                    $table->foreign($columna)->references('id')->on('responsable')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(self::COLUMNAS, fn ($c) => Schema::hasColumn('requisitos_catalogo', $c)));

        $conPersona = $existentes && DB::table('requisitos_catalogo')
            ->where(function ($q) use ($existentes) {
                foreach ($existentes as $columna) {
                    $q->orWhereNotNull($columna);
                }
            })
            ->exists();
        if ($conPersona) {
            throw new RuntimeException('No se puede revertir: hay requisitos de plantilla con un responsable de cumplimiento o de revisión asignado, que se perdería. Restaure un respaldo.');
        }

        // Primero las claves foráneas, después las columnas; cada paso con su guarda. Se quitan
        // por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción
        // de Laravel.
        foreach ($existentes as $columna) {
            if ($foranea = $this->foranea($columna)) {
                $nombre = $foranea['name'] ?? [$columna];
                Schema::table('requisitos_catalogo', function (Blueprint $table) use ($nombre) {
                    $table->dropForeign($nombre);
                });
            }
            Schema::table('requisitos_catalogo', function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('requisitos_catalogo'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
