<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Configuración elegida en cada expediente, en tablas pivote (no JSON): la
    // integridad referencial impide borrar del catálogo algo que un expediente usa,
    // y permite reportar expedientes por sector o componente.
    private const PIVOTES = [
        'expediente_sectores' => ['sector_id', 'requisitos_sectores'],
        'expediente_tipos' => ['tipo_id', 'requisitos_tipos'],
        'expediente_componentes' => ['componente_id', 'requisitos_componentes'],
        'expediente_tipologias' => ['tipologia_id', 'requisitos_tipologias'],
    ];

    public function up(): void
    {
        foreach (self::PIVOTES as $tabla => [$columna, $referencia]) {
            Schema::create($tabla, function (Blueprint $table) use ($columna, $referencia) {
                $table->foreignId('expediente_id')->constrained('proyectos_expedientes')->cascadeOnDelete();
                $table->foreignId($columna)->constrained($referencia)->restrictOnDelete();
                $table->primary(['expediente_id', $columna]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys(self::PIVOTES)) as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
