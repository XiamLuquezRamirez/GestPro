<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORANEAS = [
        'usuario_no_aplica_id' => 'users',
        'usuario_aprobo_id' => 'users',
        'responsable_id' => 'responsable',
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada columna y cada clave foránea es una
        // sentencia aparte con su guarda, para poder reintentar sin dejar nada a medias.
        $this->agregar('estado_aplicabilidad', fn (Blueprint $t) => $t->string('estado_aplicabilidad', 30)->default('aplica'));
        $this->agregar('justificacion_no_aplica', fn (Blueprint $t) => $t->text('justificacion_no_aplica')->nullable());
        $this->agregar('usuario_no_aplica_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_no_aplica_id')->nullable());
        $this->agregar('usuario_aprobo_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_aprobo_id')->nullable());
        $this->agregar('fecha_aprobacion', fn (Blueprint $t) => $t->dateTime('fecha_aprobacion')->nullable());
        $this->agregar('caracter', fn (Blueprint $t) => $t->string('caracter', 20)->default('obligatorio'));
        $this->agregar('responsable_id', fn (Blueprint $t) => $t->unsignedBigInteger('responsable_id')->nullable());

        foreach (self::FORANEAS as $columna => $tabla) {
            if (! $this->tieneForanea($columna)) {
                Schema::table('proyectos_requisitos', function (Blueprint $table) use ($columna, $tabla) {
                    $table->foreign($columna)->references('id')->on($tabla)->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $conDatos = DB::table('proyectos_requisitos')
            ->where(fn ($q) => $q->where('estado_aplicabilidad', '!=', 'aplica')
                ->orWhere('caracter', '!=', 'obligatorio')
                ->orWhereNotNull('responsable_id'))
            ->exists();
        if ($conDatos) {
            throw new RuntimeException('No se puede revertir: hay requisitos con no aplica, carácter o responsable que se perderían. Restaure un respaldo.');
        }

        foreach (array_keys(self::FORANEAS) as $columna) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($columna) {
                $table->dropForeign([$columna]);
            });
        }
        Schema::table('proyectos_requisitos', function (Blueprint $table) {
            $table->dropColumn([
                'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
                'fecha_aprobacion', 'caracter', 'responsable_id',
            ]);
        });
    }

    private function agregar(string $columna, callable $definicion): void
    {
        if (! Schema::hasColumn('proyectos_requisitos', $columna)) {
            Schema::table('proyectos_requisitos', function (Blueprint $table) use ($definicion) {
                $definicion($table);
            });
        }
    }

    private function tieneForanea(string $columna): bool
    {
        return collect(Schema::getForeignKeys('proyectos_requisitos'))->contains(fn ($f) => $f['columns'] === [$columna]);
    }
};
