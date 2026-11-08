<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEXTOS_PROPIOS = ['titulo_propio', 'descripcion_propia', 'categoria_propia'];

    /** Referencias que pasan a opcionales: tabla => [columna, tabla referida]. */
    private const REFERENCIAS = [
        'proyectos_expedientes' => ['catalogo_version_id', 'catalogo_versiones'],
        'proyectos_requisitos' => ['requisito_catalogo_id', 'requisitos_catalogo'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Quitar la clave foránea, cambiar la columna
        // y volver a ponerla son tres pasos.
        // null en catalogo_version_id: el expediente no tiene plantilla con motor sgr.
        // null en requisito_catalogo_id: requisito propio del proyecto, con sus textos en la fila.
        foreach (self::REFERENCIAS as $tabla => [$columna, $referida]) {
            $this->cambiarNulabilidad($tabla, $columna, $referida, true);
        }

        $this->agregar('titulo_propio', fn (Blueprint $t) => $t->text('titulo_propio')->nullable());
        $this->agregar('descripcion_propia', fn (Blueprint $t) => $t->text('descripcion_propia')->nullable());
        $this->agregar('categoria_propia', fn (Blueprint $t) => $t->string('categoria_propia', 30)->nullable());

        // up() solo corre si la migración no quedó registrada: si la tabla existe, es un resto de
        // un intento fallido, quizá sin su clave primaria o sus claves foráneas. Sus filas se
        // reconstruyen abajo.
        Schema::dropIfExists('proyecto_requisito_origenes');

        // Requisitos de catálogo que dan origen a cada fila del checklist. Varias plantillas con
        // el mismo código común comparten una fila, con un origen por plantilla.
        Schema::create('proyecto_requisito_origenes', function (Blueprint $table) {
            $table->foreignId('proyecto_requisito_id')->constrained('proyectos_requisitos')->cascadeOnDelete();
            $table->foreignId('requisito_catalogo_id')->constrained('requisitos_catalogo')->restrictOnDelete();
            $table->primary(['proyecto_requisito_id', 'requisito_catalogo_id']);
        });

        // Solo aporta origen lo que aplica: una fila retirada no tiene ninguno.
        DB::table('proyecto_requisito_origenes')->insertUsing(
            ['proyecto_requisito_id', 'requisito_catalogo_id'],
            DB::table('proyectos_requisitos')->select('id', 'requisito_catalogo_id')
                ->where('aplica', true)
                ->whereNotNull('requisito_catalogo_id')
        );
    }

    public function down(): void
    {
        $propios = Schema::hasColumn('proyectos_requisitos', 'titulo_propio')
            ? DB::table('proyectos_requisitos')->where(function ($q) {
                $q->whereNull('requisito_catalogo_id');
                foreach (self::TEXTOS_PROPIOS as $columna) {
                    $q->orWhereNotNull($columna);
                }
            })->exists()
            : DB::table('proyectos_requisitos')->whereNull('requisito_catalogo_id')->exists();
        if ($propios) {
            throw new RuntimeException('No se puede revertir: hay requisitos propios de los proyectos que se perderían. Elimínelos o restaure un respaldo.');
        }

        if (DB::table('proyectos_expedientes')->whereNull('catalogo_version_id')->exists()) {
            throw new RuntimeException('No se puede revertir: hay expedientes sin versión SGR. Elimínelos o restaure un respaldo.');
        }

        // Al revertir, expediente_plantillas solo se reconstruye con la versión de catalogo_version_id.
        $variasPlantillas = DB::table('expediente_plantillas')
            ->select('expediente_id')->groupBy('expediente_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($variasPlantillas) {
            throw new RuntimeException('No se puede revertir: hay expedientes con más de una plantilla y las complementarias se perderían. Restaure un respaldo.');
        }

        $variosOrigenes = Schema::hasTable('proyecto_requisito_origenes') && DB::table('proyecto_requisito_origenes')
            ->select('proyecto_requisito_id')->groupBy('proyecto_requisito_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($variosOrigenes) {
            throw new RuntimeException('No se puede revertir: hay requisitos con más de un origen que se perderían. Restaure un respaldo.');
        }

        // Antes de esta migración, cada fila venía de la versión SGR de su expediente. Una fila
        // anclada en otra versión (de una plantilla que el expediente tiene o tuvo) quedaría
        // retirada al revertir, con su trabajo.
        $otrasPlantillas = DB::table('proyectos_requisitos')
            ->join('proyectos_expedientes', 'proyectos_expedientes.id', '=', 'proyectos_requisitos.expediente_id')
            ->join('requisitos_catalogo', 'requisitos_catalogo.id', '=', 'proyectos_requisitos.requisito_catalogo_id')
            ->whereColumn('requisitos_catalogo.catalogo_version_id', '!=', 'proyectos_expedientes.catalogo_version_id')
            ->exists();
        if ($otrasPlantillas) {
            throw new RuntimeException('No se puede revertir: hay requisitos que vienen de plantillas distintas de la SGR y se perderían. Restaure un respaldo.');
        }

        Schema::dropIfExists('proyecto_requisito_origenes');

        $existentes = array_values(array_filter(self::TEXTOS_PROPIOS, fn ($c) => Schema::hasColumn('proyectos_requisitos', $c)));
        if ($existentes) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($existentes) {
                $table->dropColumn($existentes);
            });
        }

        // Orden inverso al de up(): primero el checklist y luego los expedientes.
        foreach (array_reverse(self::REFERENCIAS, true) as $tabla => [$columna, $referida]) {
            $this->cambiarNulabilidad($tabla, $columna, $referida, false);
        }
    }

    /** Quita la clave foránea, cambia la columna y la repone; cada paso con su guarda. */
    private function cambiarNulabilidad(string $tabla, string $columna, string $referida, bool $nullable): void
    {
        if ($this->esNullable($tabla, $columna) !== $nullable) {
            if ($this->tieneForanea($tabla, $columna)) {
                Schema::table($tabla, function (Blueprint $table) use ($columna) {
                    $table->dropForeign([$columna]);
                });
            }
            Schema::table($tabla, function (Blueprint $table) use ($columna, $nullable) {
                $table->unsignedBigInteger($columna)->nullable($nullable)->change();
            });
        }
        if (! $this->tieneForanea($tabla, $columna)) {
            Schema::table($tabla, function (Blueprint $table) use ($columna, $referida) {
                $table->foreign($columna)->references('id')->on($referida)->restrictOnDelete();
            });
        }
    }

    private function agregar(string $columna, callable $definicion): void
    {
        if (! Schema::hasColumn('proyectos_requisitos', $columna)) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
        }
    }

    private function esNullable(string $tabla, string $columna): bool
    {
        return (bool) collect(Schema::getColumns($tabla))->firstWhere('name', $columna)['nullable'];
    }

    private function tieneForanea(string $tabla, string $columna): bool
    {
        return collect(Schema::getForeignKeys($tabla))->contains(fn ($f) => $f['columns'] === [$columna]);
    }
};
