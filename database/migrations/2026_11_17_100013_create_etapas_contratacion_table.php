<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Catálogo administrable de etapas de un proceso de contratación. Se siembra con las etapas de la
 * guía CCE-SEC-G1-18 de Colombia Compra (SECOP II, licitación pública), más la planeación al
 * inicio y el contrato al final. `clave` no se edita: la usarán las plantillas y el código.
 */
return new class extends Migration
{
    private const ETAPAS = [
        ['planeacion', 'Planeación y estudios previos', 'Configuración financiera (CDP, BPIN y Plan Anual de Adquisiciones) y estudios previos.'],
        ['pliego_borrador', 'Creación del proceso y pliego borrador', 'Guía CCE-SEC-G1-18, capítulos 2 y 3.'],
        ['publicacion_borrador', 'Publicación del proyecto de pliego y aviso de convocatoria', 'Guía CCE-SEC-G1-18, capítulo 4.'],
        ['observaciones_borrador', 'Observaciones al proyecto de pliego', 'Guía CCE-SEC-G1-18, capítulo 6.'],
        ['pliego_definitivo', 'Apertura y pliego definitivo', 'Guía CCE-SEC-G1-18, capítulos 7 y 8.'],
        ['adendas', 'Adendas', 'Guía CCE-SEC-G1-18, capítulo 9.'],
        ['cierre', 'Cierre y apertura de ofertas', 'Guía CCE-SEC-G1-18, capítulo 10.'],
        ['evaluacion', 'Evaluación y subsanaciones', 'Guía CCE-SEC-G1-18, capítulo 11.'],
        ['observaciones_informe', 'Observaciones al informe de evaluación', 'Guía CCE-SEC-G1-18, capítulo 12.'],
        ['adjudicacion', 'Adjudicación o declaratoria de desierto', 'Guía CCE-SEC-G1-18, capítulos 13 y 14.'],
        ['contrato', 'Contrato y garantías', 'Contrato electrónico (guía CCE-SEC-G1-18, capítulo 14).'],
    ];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: la tabla y su índice único son pasos separados,
        // cada uno con su guarda, para poder reintentar sin perder lo que ya se sembró.
        if (! Schema::hasTable('etapas_contratacion')) {
            Schema::create('etapas_contratacion', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 40);
                $table->string('nombre', 120);
                $table->text('descripcion')->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
        if (! $this->indiceClave()) {
            Schema::table('etapas_contratacion', function (Blueprint $table) {
                $table->unique('clave');
            });
        }

        DB::transaction(function () {
            foreach (self::ETAPAS as $indice => [$clave, $nombre, $descripcion]) {
                if (! DB::table('etapas_contratacion')->where('clave', $clave)->exists()) {
                    DB::table('etapas_contratacion')->insert([
                        'clave' => $clave,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'orden' => $indice + 1,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('etapas_contratacion')) {
            return;
        }

        $sembradas = collect(self::ETAPAS)->mapWithKeys(fn ($e, $i) => [$e[0] => [
            'nombre' => $e[1], 'descripcion' => $e[2], 'orden' => $i + 1, 'activo' => true,
        ]]);
        $nuevas = [];
        $editadas = [];
        foreach (DB::table('etapas_contratacion')->orderBy('orden')->get() as $etapa) {
            $original = $sembradas->get($etapa->clave);
            if (! $original) {
                $nuevas[] = $etapa->nombre;
                continue;
            }

            $campos = array_keys(array_filter([
                'nombre' => $etapa->nombre !== $original['nombre'],
                'descripcion' => $etapa->descripcion !== $original['descripcion'],
                'orden' => (int) $etapa->orden !== $original['orden'],
                'activo' => (bool) $etapa->activo !== $original['activo'],
            ]));
            if ($campos) {
                $editadas[] = $etapa->clave . ': ' . implode(', ', $campos);
            }
        }

        if ($nuevas) {
            throw new RuntimeException('No se puede revertir: hay etapas de contratación creadas después de la migración (' . implode(', ', $nuevas) . '), que se perderían. Restaure un respaldo.');
        }
        if ($editadas) {
            throw new RuntimeException('No se puede revertir: hay etapas de contratación editadas (' . implode('; ', $editadas) . ') y esos cambios se perderían. Deshaga esos cambios desde Parámetros → Etapas de contratación o restaure un respaldo.');
        }

        Schema::dropIfExists('etapas_contratacion');
    }

    private function indiceClave(): ?array
    {
        return collect(Schema::getIndexes('etapas_contratacion'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['clave']);
    }
};
