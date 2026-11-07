<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEXTOS = [
        'descripcion', 'categoria', 'tipo_requisito', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna es una sentencia y se puede repetir.
        $this->agregar('descripcion', fn (Blueprint $t) => $t->text('descripcion')->nullable());
        $this->agregar('categoria', fn (Blueprint $t) => $t->string('categoria', 100)->nullable());
        $this->agregar('tipo_requisito', fn (Blueprint $t) => $t->string('tipo_requisito', 100)->nullable());
        $this->agregar('caracter', fn (Blueprint $t) => $t->string('caracter', 20)->default('obligatorio'));
        $this->agregar('cargo_cumplimiento', fn (Blueprint $t) => $t->string('cargo_cumplimiento', 150)->nullable());
        $this->agregar('cargo_revision', fn (Blueprint $t) => $t->string('cargo_revision', 150)->nullable());
        $this->agregar('soporte_requerido', fn (Blueprint $t) => $t->string('soporte_requerido', 255)->nullable());
        $this->agregar('referencia_normativa', fn (Blueprint $t) => $t->string('referencia_normativa', 255)->nullable());
        $this->agregar('condicion_descripcion', fn (Blueprint $t) => $t->text('condicion_descripcion')->nullable());
        // Nace en true para que los requisitos existentes, todos del SGR, exijan aprobación del
        // "no aplica" (decisión del usuario). Abajo el valor por defecto pasa a false.
        $this->agregar('requiere_aprobacion_no_aplica', fn (Blueprint $t) => $t->boolean('requiere_aprobacion_no_aplica')->default(true));
        $this->agregar('codigo_comun', fn (Blueprint $t) => $t->string('codigo_comun', 40)->nullable());

        // Hasta hoy todos los requisitos son del SGR, y allí el código común es la clave.
        DB::table('requisitos_catalogo')->whereNull('codigo_comun')->update(['codigo_comun' => DB::raw('clave')]);

        // Solo el SGR se organiza por sección, fuente y código.
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->char('seccion', 1)->nullable()->change();
            $table->string('fuente', 5)->nullable()->change();
            $table->string('codigo', 60)->nullable()->change();
            $table->boolean('requiere_aprobacion_no_aplica')->default(false)->change();
        });
    }

    public function down(): void
    {
        $sinSgr = DB::table('requisitos_catalogo')
            ->where(fn ($q) => $q->whereNull('seccion')->orWhereNull('fuente')->orWhereNull('codigo'))
            ->exists();
        if ($sinSgr) {
            throw new RuntimeException('No se puede revertir: hay requisitos sin sección, fuente o código. Elimínelos o restaure un respaldo.');
        }

        $conDatos = DB::table('requisitos_catalogo')
            ->where(function ($q) {
                foreach (self::TEXTOS as $columna) {
                    $q->orWhereNotNull($columna);
                }
                // requiere_aprobacion_no_aplica no se revisa: en el SGR lo puso esta misma migración.
                $q->orWhere('caracter', '!=', 'obligatorio')
                    ->orWhereNull('codigo_comun')->orWhereColumn('codigo_comun', '!=', 'clave');
            })
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay requisitos con datos de seguimiento que se perderían. Restaure un respaldo.');
        }

        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->char('seccion', 1)->nullable(false)->change();
            $table->string('fuente', 5)->nullable(false)->change();
            $table->string('codigo', 60)->nullable(false)->change();
        });
        Schema::table('requisitos_catalogo', function (Blueprint $table) {
            $table->dropColumn([...self::TEXTOS, 'caracter', 'requiere_aprobacion_no_aplica', 'codigo_comun']);
        });
    }

    private function agregar(string $columna, callable $definicion): void
    {
        if (! Schema::hasColumn('requisitos_catalogo', $columna)) {
            Schema::table('requisitos_catalogo', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
        }
    }
};
