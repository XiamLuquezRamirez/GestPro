<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Una sentencia ALTER por columna y sin transaccion (el DDL de MariaDB no es transaccional):
        // cada columna se agrega solo si falta para que un reintento pueda continuar donde quedo.
        if (! Schema::hasColumn('proyectos', 'financiacion_por_definir')) {
            Schema::table('proyectos', function (Blueprint $table) {
                $table->boolean('financiacion_por_definir')->default(false)->after('fuente_financiacion');
            });
        }

        if (! Schema::hasColumn('proyectos', 'fuente_financiacion_original')) {
            Schema::table('proyectos', function (Blueprint $table) {
                $table->text('fuente_financiacion_original')->nullable()->after('financiacion_por_definir');
            });
        }

        // Todo en una transaccion: si fallara a medias, un reintento tomaria los nombres ya
        // convertidos como si fueran el texto original y se perderia lo que habia escrito.
        DB::transaction(function () {
            $catalogo = $this->catalogo();

            DB::table('proyectos')->select('id', 'fuente_financiacion')
                ->eachById(function (object $proyecto) use ($catalogo) {
                    $reconocidas = $this->reconocidas($proyecto->fuente_financiacion, $catalogo);

                    foreach ($reconocidas as $fuente) {
                        DB::table('proyecto_fuentes')->insert(['proyecto_id' => $proyecto->id, 'fuente_id' => $fuente->id]);
                    }

                    // Sin reconocer: queda por definir y se sigue viendo lo que estaba escrito.
                    DB::table('proyectos')->where('id', $proyecto->id)->update([
                        'financiacion_por_definir' => $reconocidas->isEmpty(),
                        'fuente_financiacion_original' => $proyecto->fuente_financiacion,
                        'fuente_financiacion' => $this->textoConvertido($proyecto->fuente_financiacion, $reconocidas),
                    ]);
                });
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $catalogo = $this->catalogo();

            DB::table('proyecto_fuentes')->delete();

            DB::table('proyectos')->select('id', 'fuente_financiacion', 'fuente_financiacion_original')
                ->whereNotNull('fuente_financiacion_original')
                ->eachById(function (object $proyecto) use ($catalogo) {
                    $original = $proyecto->fuente_financiacion_original;
                    $convertido = $this->textoConvertido($original, $this->reconocidas($original, $catalogo));

                    // Si el texto ya no es el que dejo la conversion, alguien lo edito despues: ese texto
                    // ya nombra las fuentes del proyecto, asi que se deja tal cual y no se pierde nada.
                    if ($proyecto->fuente_financiacion === $convertido) {
                        DB::table('proyectos')->where('id', $proyecto->id)->update(['fuente_financiacion' => $original]);
                    }
                });
        });

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['financiacion_por_definir', 'fuente_financiacion_original']);
        });
    }

    private function catalogo(): Collection
    {
        return DB::table('fuentes_financiacion')
            ->whereIn('clave', ['sgr', 'recursos_propios'])
            ->orderBy('orden')
            ->get()
            ->keyBy('clave');
    }

    private function reconocidas(?string $original, Collection $catalogo): Collection
    {
        $texto = Str::lower(Str::ascii(trim((string) $original)));

        return $catalogo->filter(fn (object $fuente) => match ($fuente->clave) {
            'sgr' => Str::contains($texto, 'regalia') || preg_match('/\bsgr\b/', $texto) === 1,
            'recursos_propios' => Str::contains($texto, 'recursos propios'),
            default => false,
        });
    }

    private function textoConvertido(?string $original, Collection $reconocidas): ?string
    {
        return $reconocidas->isEmpty() ? $original : $reconocidas->pluck('nombre')->implode(', ');
    }
};
