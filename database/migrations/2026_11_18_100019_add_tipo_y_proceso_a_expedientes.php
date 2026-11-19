<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un expediente es de viabilización (el de siempre, por proyecto) o de contratación (uno por
 * proceso de contratación). `tipo` lo dice; todos los expedientes que ya existen quedan de
 * viabilización. `proceso_id` es el proceso del expediente de contratación: único (un expediente
 * por proceso) y con restricción (un proceso con expediente no se borra). El índice por proyecto
 * y tipo sirve a las consultas de viabilización, que filtran siempre por tipo.
 */
return new class extends Migration
{
    private const TABLA = 'proyectos_expedientes';
    private const UNICO = 'proy_expedientes_proceso_unique';
    private const INDICE = 'proy_expedientes_proyecto_tipo_idx';

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. El índice único va antes de la clave foránea,
        // que así lo usa en vez de crear el suyo.
        if (! Schema::hasColumn(self::TABLA, 'tipo')) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->string('tipo', 20)->default('viabilizacion');
            });
        }
        if (! Schema::hasColumn(self::TABLA, 'proceso_id')) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unsignedBigInteger('proceso_id')->nullable();
            });
        }
        if (! $this->indice(['proceso_id'], true)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique('proceso_id', self::UNICO);
            });
        }
        if (! $this->foranea()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->foreign('proceso_id')->references('id')->on('procesos_licitacion')->restrictOnDelete();
            });
        }
        if (! $this->indice(['proyecto_id', 'tipo'], false)) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->index(['proyecto_id', 'tipo'], self::INDICE);
            });
        }
    }

    public function down(): void
    {
        $contratacion = Schema::hasColumn(self::TABLA, 'tipo')
            && DB::table(self::TABLA)->where('tipo', '!=', 'viabilizacion')->exists();
        $conProceso = Schema::hasColumn(self::TABLA, 'proceso_id')
            && DB::table(self::TABLA)->whereNotNull('proceso_id')->exists();
        if ($contratacion || $conProceso) {
            throw new RuntimeException('No se puede revertir: hay expedientes de contratación o asociados a un proceso de contratación, que se perderían. Restaure un respaldo.');
        }

        // Primero la clave foránea y los índices, después las columnas; cada paso con su guarda.
        // Se quitan por el nombre que entrega el esquema; SQLite no da nombre a la clave foránea
        // y ahí se deja la deducción de Laravel.
        if ($foranea = $this->foranea()) {
            $nombre = $foranea['name'] ?? ['proceso_id'];
            Schema::table(self::TABLA, function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
        }
        if ($unico = $this->indice(['proceso_id'], true)) {
            Schema::table(self::TABLA, function (Blueprint $table) use ($unico) {
                $table->dropUnique($unico['name']);
            });
        }
        if ($indice = $this->indice(['proyecto_id', 'tipo'], false)) {
            Schema::table(self::TABLA, function (Blueprint $table) use ($indice) {
                $table->dropIndex($indice['name']);
            });
        }
        foreach (['proceso_id', 'tipo'] as $columna) {
            if (Schema::hasColumn(self::TABLA, $columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function indice(array $columnas, bool $unico): ?array
    {
        return collect(Schema::getIndexes(self::TABLA))
            ->first(fn ($i) => $i['columns'] === $columnas && $i['unique'] === $unico && ! $i['primary']);
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLA))->first(fn ($f) => $f['columns'] === ['proceso_id']);
    }
};
