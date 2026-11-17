<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un proceso de contratación registra su objeto, la plataforma (secop_ii, secop_i, tvec o
 * ninguna), el enlace, su estado (preparacion, publicado, evaluacion, adjudicado, desierto,
 * celebrado o cancelado) y quién lo creó. Los valores válidos los controla la aplicación
 * (ProcesoLicitacion::PLATAFORMAS y ::ESTADOS), como en el resto de la serie.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. La clave foránea va después de su columna.
        $this->agregar('objeto', fn (Blueprint $t) => $t->text('objeto')->nullable());
        $this->agregar('plataforma', fn (Blueprint $t) => $t->string('plataforma', 20)->nullable());
        $this->agregar('url_proceso', fn (Blueprint $t) => $t->string('url_proceso', 500)->nullable());
        $this->agregar('estado', fn (Blueprint $t) => $t->string('estado', 20)->default('preparacion'));
        $this->agregar('usuario_creo_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_creo_id')->nullable());
        if (! $this->foraneaUsuario()) {
            Schema::table('procesos_licitacion', function (Blueprint $table) {
                $table->foreign('usuario_creo_id')->references('id')->on('users')->nullOnDelete();
            });
        }
        $this->agregar('created_at', fn (Blueprint $t) => $t->timestamp('created_at')->nullable());
        $this->agregar('updated_at', fn (Blueprint $t) => $t->timestamp('updated_at')->nullable());
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(
            ['objeto', 'plataforma', 'url_proceso', 'estado', 'usuario_creo_id', 'created_at', 'updated_at'],
            fn (string $c) => Schema::hasColumn('procesos_licitacion', $c)
        ));

        $conDatos = $existentes && DB::table('procesos_licitacion')
            ->where(function ($q) use ($existentes) {
                foreach ($existentes as $columna) {
                    $columna === 'estado'
                        ? $q->orWhere('estado', '!=', 'preparacion')
                        : $q->orWhereNotNull($columna);
                }
            })
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay procesos de contratación con objeto, plataforma, enlace, estado, usuario que los creó o fechas registrados, que se perderían. Restaure un respaldo.');
        }

        // Primero la clave foránea, después las columnas; cada paso con su guarda. Se quita por
        // el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la deducción de Laravel.
        if ($foranea = $this->foraneaUsuario()) {
            $nombre = $foranea['name'] ?? ['usuario_creo_id'];
            Schema::table('procesos_licitacion', function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
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

    private function foraneaUsuario(): ?array
    {
        return collect(Schema::getForeignKeys('procesos_licitacion'))->first(fn ($f) => $f['columns'] === ['usuario_creo_id']);
    }
};
