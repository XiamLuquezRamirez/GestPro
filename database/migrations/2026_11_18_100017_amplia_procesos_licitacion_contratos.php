<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un proceso de contratación registra además la fuente de financiación, las fechas de apertura y
 * cierre, un responsable y observaciones. Todo es opcional: los procesos que ya existen quedan
 * sin esos datos. Los estados válidos los controla la aplicación (ProcesoLicitacion::ESTADOS,
 * que ahora incluye revocado); la columna `estado` no cambia.
 */
return new class extends Migration
{
    private const COLUMNAS = ['fuente_id', 'fecha_apertura', 'fecha_cierre', 'responsable_id', 'observaciones'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Cada clave foránea va después de su columna.
        $this->agregar('fuente_id', fn (Blueprint $t) => $t->unsignedBigInteger('fuente_id')->nullable());
        if (! $this->foranea('fuente_id')) {
            Schema::table('procesos_licitacion', function (Blueprint $table) {
                $table->foreign('fuente_id')->references('id')->on('fuentes_financiacion')->restrictOnDelete();
            });
        }
        $this->agregar('fecha_apertura', fn (Blueprint $t) => $t->date('fecha_apertura')->nullable());
        $this->agregar('fecha_cierre', fn (Blueprint $t) => $t->date('fecha_cierre')->nullable());
        $this->agregar('responsable_id', fn (Blueprint $t) => $t->unsignedBigInteger('responsable_id')->nullable());
        if (! $this->foranea('responsable_id')) {
            Schema::table('procesos_licitacion', function (Blueprint $table) {
                $table->foreign('responsable_id')->references('id')->on('responsable')->nullOnDelete();
            });
        }
        $this->agregar('observaciones', fn (Blueprint $t) => $t->text('observaciones')->nullable());
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(self::COLUMNAS, fn (string $c) => Schema::hasColumn('procesos_licitacion', $c)));

        $conDatos = $existentes && DB::table('procesos_licitacion')
            ->where(function ($q) use ($existentes) {
                foreach ($existentes as $columna) {
                    $q->orWhereNotNull($columna);
                }
            })
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay procesos de contratación con fuente de financiación, fechas de apertura o cierre, responsable u observaciones registrados, que se perderían. Restaure un respaldo.');
        }

        // Primero las claves foráneas, después las columnas; cada paso con su guarda. Se quitan
        // por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción de Laravel.
        foreach (['fuente_id', 'responsable_id'] as $columna) {
            if ($foranea = $this->foranea($columna)) {
                $nombre = $foranea['name'] ?? [$columna];
                Schema::table('procesos_licitacion', function (Blueprint $table) use ($nombre) {
                    $table->dropForeign($nombre);
                });
            }
        }
        foreach ($existentes as $columna) {
            Schema::table('procesos_licitacion', function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function agregar(string $columna, Closure $definicion): void
    {
        if (! Schema::hasColumn('procesos_licitacion', $columna)) {
            Schema::table('procesos_licitacion', fn (Blueprint $table) => $definicion($table));
        }
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('procesos_licitacion'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
