<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mapeo opcional sector de GestPro -> sector SGR, solo para preseleccionar el
 * sector al crear un expediente.
 *
 * El catálogo `sectores` de GestPro es propio y más corto que el del SGR, y no
 * coincide 1:1: el SGR ubica "Agua Potable" dentro de Vivienda, ciudad y
 * territorio, y "Vías" dentro de Transporte.
 *
 * Idempotente y conservador: solo mapea sectores reconocidos por nombre y solo
 * si aún no tienen mapeo, para no pisar uno asignado a mano.
 */
class MapeoSectoresSgrSeeder extends Seeder
{
    // nombre normalizado del sector GestPro => clave del sector SGR
    private const MAPEO = [
        'vias' => 'transporte',
        'transporte' => 'transporte',
        'educacion' => 'educacion',
        'salud' => 'salud',
        'agua potable' => 'vivienda',
        'vivienda' => 'vivienda',
    ];

    public function run(): void
    {
        $sgr = DB::table('requisitos_sectores')->pluck('id', 'clave');

        foreach (DB::table('sectores')->whereNull('requisitos_sector_id')->get() as $sector) {
            $clave = self::MAPEO[Str::lower(Str::ascii(trim((string) $sector->nombre)))] ?? null;

            if ($clave && isset($sgr[$clave])) {
                DB::table('sectores')->where('id', $sector->id)->update(['requisitos_sector_id' => $sgr[$clave]]);
            }
        }
    }
}
