<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Cronograma de un expediente de contratación: por cada etapa, la fecha programada y la fecha
 * real. `fecha_real_sugerida` es verdadero cuando la fecha real la puso el sistema al completarse
 * la etapa y nadie la ha tocado, o, sin fecha real, cuando una persona la vació con la etapa
 * completa (ExpedienteEtapa::descartada()). Una fila por expediente y etapa; cae con el expediente, una
 * etapa con cronograma no se elimina (las etapas se desactivan) y el usuario que la actualizó
 * queda en null si se elimina. Nace vacía.
 */
return new class extends Migration
{
    private const TABLA = 'expediente_etapas';
    private const UNICO = 'expediente_etapas_expediente_etapa_unique';

    /** Columna => [tabla, acción al eliminar]. */
    private const FORANEAS = [
        'expediente_id' => ['proyectos_expedientes', 'cascade'],
        'etapa_id' => ['etapas_contratacion', 'restrict'],
        'usuario_actualizo_id' => ['users', 'set null'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional y Laravel agrega el índice y las claves foráneas
        // con sentencias aparte: cada paso lleva su guarda, para poder reintentar sin dejar nada
        // a medias. El índice único va antes de las claves foráneas, que así lo usan.
        if (! Schema::hasTable(self::TABLA)) {
            Schema::create(self::TABLA, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('expediente_id');
                $table->unsignedBigInteger('etapa_id');
                $table->date('fecha_programada')->nullable();
                $table->date('fecha_real')->nullable();
                $table->boolean('fecha_real_sugerida')->default(false);
                $table->unsignedBigInteger('usuario_actualizo_id')->nullable();
                $table->timestamps();
            });
        }
        if (! $this->unico()) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->unique(['expediente_id', 'etapa_id'], self::UNICO);
            });
        }
        foreach (self::FORANEAS as $columna => [$tabla, $accion]) {
            if (! $this->foranea($columna)) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($columna, $tabla, $accion) {
                    $table->foreign($columna)->references('id')->on($tabla)->onDelete($accion);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }
        if (DB::table(self::TABLA)->exists()) {
            throw new RuntimeException('No se puede revertir: hay fechas en el cronograma de los expedientes de contratación, que se perderían. Restaure un respaldo.');
        }

        // La tabla cae con su índice y sus claves foráneas.
        Schema::drop(self::TABLA);
    }

    private function unico(): ?array
    {
        return collect(Schema::getIndexes(self::TABLA))
            ->first(fn ($i) => $i['unique'] && ! $i['primary'] && $i['columns'] === ['expediente_id', 'etapa_id']);
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys(self::TABLA))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
