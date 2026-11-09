<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FORANEA = 'proyectos_expedientes_catalogo_version_id_foreign';

    /** Cuántos expedientes nombra, como mucho, el mensaje que detiene la migración. */
    private const MAX_LISTADOS = 10;

    public function up(): void
    {
        // La versión de cada plantilla del expediente vive en expediente_plantillas. Una rama
        // anterior que cree expedientes sobre la base ya migrada escribiría solo esta columna:
        // antes de quitarla se comprueba que no se pierde ninguna versión.
        if (Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id')) {
            $this->exigirVersionesRegistradas();
        }

        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Primero la clave foránea, después la columna.
        // Se quita por el nombre que entrega el esquema; SQLite no da nombre y ahí se deja la
        // deducción de Laravel.
        if ($foranea = $this->foranea()) {
            $nombre = $foranea['name'] ?? ['catalogo_version_id'];
            Schema::table('proyectos_expedientes', function (Blueprint $table) use ($nombre) {
                $table->dropForeign($nombre);
            });
        }
        if (Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id')) {
            Schema::table('proyectos_expedientes', function (Blueprint $table) {
                $table->dropColumn('catalogo_version_id');
            });
        }
    }

    public function down(): void
    {
        // Se restaura como la dejó la migración 10: opcional, con la versión de la plantilla con
        // motor sgr del expediente o null si no la tiene. SQLite ignora la posición.
        if (! Schema::hasColumn('proyectos_expedientes', 'catalogo_version_id')) {
            Schema::table('proyectos_expedientes', function (Blueprint $table) {
                $table->unsignedBigInteger('catalogo_version_id')->nullable()->after('proyecto_id');
            });
        }

        // Se llena antes de la clave foránea: en SQLite agregarla reconstruye la tabla. El
        // servicio admite como máximo una plantilla sgr por expediente; volver a llenarla en
        // un reintento da el mismo resultado.
        $versionSgr = DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->join('plantillas_checklist', 'plantillas_checklist.id', '=', 'catalogo_versiones.plantilla_id')
            ->whereColumn('expediente_plantillas.expediente_id', 'proyectos_expedientes.id')
            ->where('plantillas_checklist.motor', 'sgr')
            ->select('expediente_plantillas.catalogo_version_id')
            ->limit(1);
        // Con una consulta como valor de update(), la gramática de SQLite de Laravel no pasa sus
        // parámetros: la sentencia se arma aquí, con la subconsulta correlacionada.
        $gramatica = DB::getQueryGrammar();
        DB::update(
            sprintf('update %s set %s = (%s)',
                $gramatica->wrapTable('proyectos_expedientes'), $gramatica->wrap('catalogo_version_id'), $versionSgr->toSql()),
            $versionSgr->getBindings()
        );

        if (! $this->foranea()) {
            Schema::table('proyectos_expedientes', function (Blueprint $table) {
                $table->foreign('catalogo_version_id', self::FORANEA)
                    ->references('id')->on('catalogo_versiones')->restrictOnDelete();
            });
        }
    }

    /** Se detiene si algún expediente tiene una versión que no figura en expediente_plantillas. */
    private function exigirVersionesRegistradas(): void
    {
        $sinRegistro = DB::table('proyectos_expedientes')
            ->whereNotNull('catalogo_version_id')
            ->whereNotExists(fn ($q) => $q->from('expediente_plantillas')
                ->whereColumn('expediente_plantillas.expediente_id', 'proyectos_expedientes.id')
                ->whereColumn('expediente_plantillas.catalogo_version_id', 'proyectos_expedientes.catalogo_version_id'));

        $total = (clone $sinRegistro)->count();
        if ($total === 0) {
            return;
        }

        $ids = $sinRegistro->orderBy('id')->limit(self::MAX_LISTADOS)->pluck('id')->implode(', ');
        $resto = $total > self::MAX_LISTADOS ? ' y ' . ($total - self::MAX_LISTADOS) . ' más' : '';
        throw new RuntimeException($total === 1
            ? "No se puede migrar: el expediente {$ids} tiene una versión de catálogo que no figura en expediente_plantillas y se perdería. Revíselo antes de migrar."
            : "No se puede migrar: los expedientes {$ids}{$resto} tienen una versión de catálogo que no figura en expediente_plantillas y se perdería. Revíselos antes de migrar."
        );
    }

    private function foranea(): ?array
    {
        return collect(Schema::getForeignKeys('proyectos_expedientes'))
            ->first(fn ($f) => $f['columns'] === ['catalogo_version_id']);
    }
};
