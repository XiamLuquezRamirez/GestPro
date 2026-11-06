<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->boolean('financiacion_por_definir')->default(false)->after('fuente_financiacion');
            $table->text('fuente_financiacion_original')->nullable()->after('financiacion_por_definir');
        });

        $catalogo = DB::table('fuentes_financiacion')
            ->whereIn('clave', ['sgr', 'recursos_propios'])
            ->orderBy('orden')
            ->get()
            ->keyBy('clave');

        DB::table('proyectos')->select('id', 'fuente_financiacion')->orderBy('id')
            ->each(function (object $proyecto) use ($catalogo) {
                $texto = Str::lower(Str::ascii(trim((string) $proyecto->fuente_financiacion)));

                $reconocidas = $catalogo->filter(fn (object $fuente) => match ($fuente->clave) {
                    'sgr' => Str::contains($texto, ['regalia', 'sgr']),
                    'recursos_propios' => Str::contains($texto, 'recursos propios'),
                });

                foreach ($reconocidas as $fuente) {
                    DB::table('proyecto_fuentes')->insert(['proyecto_id' => $proyecto->id, 'fuente_id' => $fuente->id]);
                }

                // Sin reconocer: queda por definir y se sigue viendo lo que estaba escrito.
                DB::table('proyectos')->where('id', $proyecto->id)->update([
                    'financiacion_por_definir' => $reconocidas->isEmpty(),
                    'fuente_financiacion_original' => $proyecto->fuente_financiacion,
                    'fuente_financiacion' => $reconocidas->isEmpty()
                        ? $proyecto->fuente_financiacion
                        : $reconocidas->pluck('nombre')->implode(', '),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('proyecto_fuentes')->delete();

        DB::table('proyectos')->whereNotNull('fuente_financiacion_original')->update([
            'fuente_financiacion' => DB::raw('fuente_financiacion_original'),
        ]);

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropColumn(['financiacion_por_definir', 'fuente_financiacion_original']);
        });
    }
};
