<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cada requisito de una plantilla de contratación pertenece a una etapa del catálogo
 * (requisitos_catalogo.etapa_id), y un requisito propio de un expediente de contratación elige la
 * suya (proyectos_requisitos.etapa_propia_id). Ambas son opcionales: la viabilización no usa
 * etapas. Una etapa asignada no se borra: se desactiva.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'requisitos_catalogo' => 'etapa_id',
        'proyectos_requisitos' => 'etapa_propia_id',
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la columna, después la clave foránea.
        foreach (self::COLUMNAS as $tabla => $columna) {
            if (! Schema::hasColumn($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->unsignedBigInteger($columna)->nullable();
                });
            }
            if (! $this->foranea($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->foreign($columna)->references('id')->on('etapas_contratacion')->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $existentes = array_filter(self::COLUMNAS, fn (string $columna, string $tabla) => Schema::hasColumn($tabla, $columna), ARRAY_FILTER_USE_BOTH);

        $usadas = [];
        foreach ($existentes as $tabla => $columna) {
            if (DB::table($tabla)->whereNotNull($columna)->exists()) {
                $usadas[] = $tabla === 'requisitos_catalogo' ? 'requisitos de plantilla' : 'requisitos propios de expedientes';
            }
        }
        if ($usadas) {
            throw new RuntimeException('No se puede revertir: hay ' . implode(' y ', $usadas) . ' con una etapa de contratación asignada, que se perdería. Restaure un respaldo.');
        }

        // Primero las claves foráneas, después las columnas; cada paso con su guarda. Se quitan
        // por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción
        // de Laravel.
        foreach ($existentes as $tabla => $columna) {
            if ($foranea = $this->foranea($tabla, $columna)) {
                $nombre = $foranea['name'] ?? [$columna];
                Schema::table($tabla, function (Blueprint $table) use ($nombre) {
                    $table->dropForeign($nombre);
                });
            }
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function foranea(string $tabla, string $columna): ?array
    {
        return collect(Schema::getForeignKeys($tabla))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
