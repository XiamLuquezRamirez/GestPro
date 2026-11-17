<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Modalidades de contratación: el catálogo existía vacío. Se agrega `clave`, que identifica las
 * modalidades que el sistema necesita reconocer (las plantillas de contratación se asociarán a
 * ellas), y `orden`, y se siembran las seis modalidades de la propuesta.
 */
return new class extends Migration
{
    private const MODALIDADES = [
        ['licitacion_publica', 'Licitación pública'],
        ['seleccion_abreviada', 'Selección abreviada'],
        ['concurso_meritos', 'Concurso de méritos'],
        ['contratacion_directa', 'Contratación directa'],
        ['minima_cuantia', 'Mínima cuantía'],
        ['regimen_especial', 'Régimen especial'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la columna, después su índice único.
        if (! Schema::hasColumn('modalidades', 'clave')) {
            Schema::table('modalidades', function (Blueprint $table) {
                $table->string('clave', 40)->nullable();
            });
        }
        if (! $this->indiceClave()) {
            Schema::table('modalidades', function (Blueprint $table) {
                $table->unique('clave');
            });
        }
        if (! Schema::hasColumn('modalidades', 'orden')) {
            Schema::table('modalidades', function (Blueprint $table) {
                $table->unsignedInteger('orden')->default(0);
            });
        }

        DB::transaction(function () {
            // Una modalidad que ya existe con el mismo nombre (sin contar tildes, mayúsculas ni
            // espacios) recibe la clave en vez de duplicarse; conserva su nombre y su estado.
            $sinClave = DB::table('modalidades')->whereNull('clave')->orderBy('id')->get(['id', 'nombre']);

            foreach (self::MODALIDADES as $indice => [$clave, $nombre]) {
                if (DB::table('modalidades')->where('clave', $clave)->exists()) {
                    continue;
                }

                $igual = $sinClave->first(fn (object $m) => $this->normalizar($m->nombre) === $this->normalizar($nombre));
                if ($igual) {
                    DB::table('modalidades')->where('id', $igual->id)->update(['clave' => $clave, 'orden' => $indice + 1]);
                    $sinClave = $sinClave->reject(fn (object $m) => $m->id === $igual->id);
                } else {
                    DB::table('modalidades')->insert(['clave' => $clave, 'nombre' => $nombre, 'orden' => $indice + 1, 'activo' => true]);
                }
            }

            // Las demás quedan después de las sembradas, en el orden en que se crearon.
            $siguiente = (int) DB::table('modalidades')->max('orden');
            foreach (DB::table('modalidades')->whereNull('clave')->where('orden', 0)->orderBy('id')->pluck('id') as $id) {
                DB::table('modalidades')->where('id', $id)->update(['orden' => ++$siguiente]);
            }
        });
    }

    public function down(): void
    {
        // Es un catálogo: las filas se conservan, también las sembradas. Al volver a subir, cada
        // una recupera su clave por el nombre. Límite conocido y aceptado: una sembrada que se
        // renombró no se reconoce, queda sin clave y la subida inserta otra con el nombre
        // original. Primero el índice, después las columnas.
        if ($indice = $this->indiceClave()) {
            Schema::table('modalidades', function (Blueprint $table) use ($indice) {
                $table->dropUnique($indice['name']);
            });
        }
        foreach (['clave', 'orden'] as $columna) {
            if (Schema::hasColumn('modalidades', $columna)) {
                Schema::table('modalidades', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }

    private function indiceClave(): ?array
    {
        return collect(Schema::getIndexes('modalidades'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['clave']);
    }

    private function normalizar(?string $texto): string
    {
        return preg_replace('/\s+/', ' ', Str::lower(Str::ascii(trim((string) $texto))));
    }
};
