<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FUENTES = [
        ['sgr', 'Sistema General de Regalías (SGR)'],
        ['pgn', 'Presupuesto General de la Nación (PGN)'],
        ['sgp', 'Sistema General de Participaciones (SGP)'],
        ['recursos_propios', 'Recursos propios'],
        ['cofinanciacion', 'Cofinanciación'],
        ['credito_publico', 'Crédito público'],
        ['cooperacion_internacional', 'Cooperación internacional'],
        ['obras_por_impuestos', 'Obras por Impuestos'],
        ['app', 'Asociaciones Público-Privadas (APP)'],
        ['otras', 'Otras fuentes'],
    ];

    public function up(): void
    {
        // Catálogo administrable. `clave` identifica las fuentes que el sistema necesita
        // reconocer (p. ej. sgr); las que crea un administrador no la llevan.
        Schema::create('fuentes_financiacion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->nullable()->unique();
            $table->string('nombre', 199)->unique();
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
        });

        foreach (self::FUENTES as $indice => [$clave, $nombre]) {
            DB::table('fuentes_financiacion')->insert([
                'clave' => $clave,
                'nombre' => $nombre,
                'orden' => $indice + 1,
                'activo' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuentes_financiacion');
    }
};
