<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Un contrato registra además su tipo, la identificación del contratista, la fecha de
 * suscripción, el supervisor, el registro presupuestal (número y fecha), la fecha del acta de
 * inicio, el usuario que lo creó y sus marcas de tiempo (nulas en los contratos antiguos).
 *
 * El estado pasa de texto libre a un valor controlado (Contrato::ESTADOS: celebrado, en_ejecucion,
 * suspendido, terminado, liquidado, anulado). El texto que tenía cada contrato se conserva en
 * `estado_original`, que sirve para revertir y para auditar la conversión.
 *
 * El proyecto del contrato pasa a ser opcional (la empresa también ejecuta contratos de proyectos
 * que no formuló): `proyecto` admite nulo y, al borrar un proyecto, sus contratos quedan sin
 * proyecto en vez de borrarse. Se agregan además la referencia de SECOP II y el NIT del contratante.
 *
 * Los tipos de contrato son el catálogo `tipos_procesos`: se siembran solo si está vacío.
 */
return new class extends Migration
{
    private const NUEVAS = [
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor',
        'numero_rp', 'fecha_rp', 'fecha_acta_inicio', 'usuario_creo_id',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    private const COLUMNAS = [...self::NUEVAS, 'estado_original', 'created_at', 'updated_at'];

    // Estado de hoy (sin mayúsculas, tildes ni espacios sobrantes) => estado controlado.
    private const ESTADOS = [
        'en ejecucion' => 'en_ejecucion',
        'vigente' => 'en_ejecucion',
        'liquidado' => 'liquidado',
        'suspendido' => 'suspendido',
        'anulado' => 'anulado',
        'ejecutado' => 'terminado',
        'terminado' => 'terminado',
        'celebrado' => 'celebrado',
    ];

    private const TIPOS = ['Obra', 'Interventoría', 'Consultoría', 'Suministro', 'Prestación de servicios', 'Orden de compra'];

    public function up(): void
    {
        // En MariaDB el DDL no es transaccional: cada paso es una sentencia con su guarda, para
        // poder reintentar sin dejar nada a medias. Cada clave foránea va después de su columna.
        $estadoOriginalNuevo = ! Schema::hasColumn('contratos', 'estado_original');

        $this->agregar('tipo_contrato_id', fn (Blueprint $t) => $t->unsignedBigInteger('tipo_contrato_id')->nullable());
        if (! $this->foranea('tipo_contrato_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('tipo_contrato_id')->references('id')->on('tipos_procesos')->restrictOnDelete();
            });
        }
        $this->agregar('contratista_identificacion', fn (Blueprint $t) => $t->string('contratista_identificacion', 30)->nullable());
        $this->agregar('fecha_suscripcion', fn (Blueprint $t) => $t->date('fecha_suscripcion')->nullable());
        $this->agregar('supervisor', fn (Blueprint $t) => $t->string('supervisor', 255)->nullable());
        $this->agregar('numero_rp', fn (Blueprint $t) => $t->string('numero_rp', 40)->nullable());
        $this->agregar('fecha_rp', fn (Blueprint $t) => $t->date('fecha_rp')->nullable());
        $this->agregar('fecha_acta_inicio', fn (Blueprint $t) => $t->date('fecha_acta_inicio')->nullable());
        $this->agregar('estado_original', fn (Blueprint $t) => $t->string('estado_original', 30)->nullable());
        $this->agregar('usuario_creo_id', fn (Blueprint $t) => $t->unsignedBigInteger('usuario_creo_id')->nullable());
        if (! $this->foranea('usuario_creo_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('usuario_creo_id')->references('id')->on('users')->nullOnDelete();
            });
        }
        $this->agregar('created_at', fn (Blueprint $t) => $t->timestamp('created_at')->nullable());
        $this->agregar('updated_at', fn (Blueprint $t) => $t->timestamp('updated_at')->nullable());

        $this->agregar('contratante_nit', fn (Blueprint $t) => $t->string('contratante_nit', 30)->nullable());
        $this->agregar('secop_id_contrato', fn (Blueprint $t) => $t->string('secop_id_contrato', 40)->nullable());
        if (! $this->indiceSecop()) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unique('secop_id_contrato');
            });
        }
        $this->agregar('secop_proceso', fn (Blueprint $t) => $t->string('secop_proceso', 40)->nullable());
        $this->agregar('url_secop', fn (Blueprint $t) => $t->string('url_secop', 500)->nullable());
        $this->agregar('secop_consultado_at', fn (Blueprint $t) => $t->timestamp('secop_consultado_at')->nullable());

        // Proyecto opcional: primero se quita la clave foránea en cascada, después la columna
        // admite nulo y al final se pone la clave foránea que deja el contrato sin proyecto.
        if (($foranea = $this->foranea('proyecto')) && strtolower($foranea['on_delete']) !== 'set null') {
            $this->quitarForanea($foranea, 'proyecto');
        }
        if (! $this->columnaProyecto()['nullable']) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('proyecto')->nullable()->change();
            });
        }
        if (! $this->foranea('proyecto')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('proyecto')->references('id')->on('proyectos')->nullOnDelete();
            });
        }

        // La conversión de estados es un solo paso atómico: copia el texto y lo convierte. Se
        // omite si ya se hizo (algún contrato conserva su estado original); así repetir la
        // migración, o reintentarla después de haber convertido, no pisa el estado original.
        // Solo no se distingue el caso en que todos los contratos tenían el estado nulo.
        if ($estadoOriginalNuevo || ! DB::table('contratos')->whereNotNull('estado_original')->exists()) {
            $this->convertirEstados();
        }

        if (! DB::table('tipos_procesos')->exists()) {
            DB::table('tipos_procesos')->insert(array_map(fn (string $nombre) => ['nombre' => $nombre, 'activo' => true], self::TIPOS));
        }
    }

    public function down(): void
    {
        $existentes = array_values(array_filter(self::COLUMNAS, fn (string $c) => Schema::hasColumn('contratos', $c)));

        $datos = array_values(array_filter(self::NUEVAS, fn (string $c) => in_array($c, $existentes, true)));
        if ($datos && DB::table('contratos')->where(function ($q) use ($datos) {
            foreach ($datos as $columna) {
                $q->orWhereNotNull($columna);
            }
        })->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos con tipo de contrato, identificación del contratista, fechas de suscripción, registro presupuestal o acta de inicio, supervisor o usuario que los creó registrados, que se perderían. Restaure un respaldo.');
        }

        if ($this->columnaProyecto()['nullable'] && DB::table('contratos')->whereNull('proyecto')->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos sin proyecto, que no podrían existir en el esquema anterior. Asígneles un proyecto o restaure un respaldo.');
        }

        if (in_array('created_at', $existentes, true) && DB::table('contratos')->whereNotNull('created_at')->exists()) {
            throw new RuntimeException('No se puede revertir: hay contratos creados después de la migración, que se perderían. Restaure un respaldo.');
        }

        if (in_array('estado_original', $existentes, true)) {
            // Vale el estado que dejó la conversión o el original (si un intento anterior ya lo
            // restauró); cualquier otro lo cambió alguien después y se perdería.
            $cambiados = DB::table('contratos')->get(['id', 'estado', 'estado_original'])
                ->filter(fn ($c) => $c->estado !== $this->convertir($c->estado_original) && $c->estado !== $c->estado_original)
                ->map(fn ($c) => '#' . $c->id)
                ->all();
            if ($cambiados) {
                throw new RuntimeException('No se puede revertir: el estado de algunos contratos (' . implode(', ', $cambiados) . ') se cambió después de la migración y ese cambio se perdería. Restaure un respaldo.');
            }

            DB::table('contratos')->update(['estado' => DB::raw('estado_original')]);
        }

        // Los tipos sembrados que nadie usa; los que usa un contrato o un proceso se conservan.
        $usados = DB::table('procesos_licitacion')->whereNotNull('tipo_proceso')->pluck('tipo_proceso');
        if (in_array('tipo_contrato_id', $existentes, true)) {
            $usados = $usados->merge(DB::table('contratos')->whereNotNull('tipo_contrato_id')->pluck('tipo_contrato_id'));
        }
        DB::table('tipos_procesos')->whereIn('nombre', self::TIPOS)->whereNotIn('id', $usados->all())->delete();

        // Proyecto obligatorio otra vez, con la cascada de antes: primero se cambia la clave
        // foránea, después la columna y al final se pone la clave en cascada.
        if (($foranea = $this->foranea('proyecto')) && strtolower($foranea['on_delete']) !== 'cascade') {
            $this->quitarForanea($foranea, 'proyecto');
        }
        if ($this->columnaProyecto()['nullable']) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->unsignedBigInteger('proyecto')->nullable(false)->change();
            });
        }
        if (! $this->foranea('proyecto')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreign('proyecto')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }

        // Primero el índice único y las claves foráneas, después las columnas; cada paso con su
        // guarda. Se quitan por el nombre que entrega el esquema; SQLite no da nombre y ahí se
        // deja la deducción de Laravel.
        if ($indice = $this->indiceSecop()) {
            Schema::table('contratos', function (Blueprint $table) use ($indice) {
                $table->dropUnique($indice['name']);
            });
        }
        foreach (['tipo_contrato_id', 'usuario_creo_id'] as $columna) {
            if ($foranea = $this->foranea($columna)) {
                $this->quitarForanea($foranea, $columna);
            }
        }
        foreach ($existentes as $columna) {
            Schema::table('contratos', function (Blueprint $table) use ($columna) {
                $table->dropColumn($columna);
            });
        }
    }

    private function convertirEstados(): void
    {
        $noReconocidos = [];

        DB::transaction(function () use (&$noReconocidos) {
            foreach (DB::table('contratos')->get(['id', 'estado']) as $contrato) {
                $texto = trim((string) $contrato->estado);
                if ($texto !== '' && ! isset(self::ESTADOS[$this->clave($texto)])) {
                    $noReconocidos[] = "#{$contrato->id} '{$contrato->estado}'";
                }

                DB::table('contratos')->where('id', $contrato->id)->update([
                    'estado_original' => $contrato->estado,
                    'estado' => $this->convertir($contrato->estado),
                ]);
            }
        });

        if ($noReconocidos) {
            $mensaje = 'Contratos con un estado no reconocido, que pasaron a en_ejecucion (su texto original queda en estado_original): ' . implode('; ', $noReconocidos) . '.';
            Log::warning($mensaje);
            if (defined('STDERR') && ! app()->runningUnitTests()) {
                fwrite(STDERR, "  AVISO: {$mensaje}\n");
            }
        }
    }

    private function convertir(?string $estado): string
    {
        return self::ESTADOS[$this->clave((string) $estado)] ?? 'en_ejecucion';
    }

    private function clave(string $estado): string
    {
        return Str::ascii(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $estado))));
    }

    private function agregar(string $columna, Closure $definicion): void
    {
        if (! Schema::hasColumn('contratos', $columna)) {
            Schema::table('contratos', fn (Blueprint $table) => $definicion($table));
        }
    }

    private function quitarForanea(array $foranea, string $columna): void
    {
        $nombre = $foranea['name'] ?? [$columna];
        Schema::table('contratos', function (Blueprint $table) use ($nombre) {
            $table->dropForeign($nombre);
        });
    }

    private function columnaProyecto(): array
    {
        return collect(Schema::getColumns('contratos'))->firstWhere('name', 'proyecto');
    }

    private function indiceSecop(): ?array
    {
        return collect(Schema::getIndexes('contratos'))->first(fn ($i) => $i['unique'] && $i['columns'] === ['secop_id_contrato']);
    }

    private function foranea(string $columna): ?array
    {
        return collect(Schema::getForeignKeys('contratos'))->first(fn ($f) => $f['columns'] === [$columna]);
    }
};
