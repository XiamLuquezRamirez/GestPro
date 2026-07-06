<?php

namespace Database\Seeders;

use App\Models\Municipio;
use Illuminate\Database\Seeder;

class MunicipiosSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/municipios.json');
        $filas = json_decode(file_get_contents($ruta), true);

        foreach ($filas as $fila) {
            Municipio::updateOrCreate(
                ['id' => $fila['id']],
                [
                    'codigo' => $fila['codigo'],
                    'nombre' => $fila['nombre'],
                    'activo' => (bool) $fila['activo'],
                    'departamento' => $fila['departamento'],
                    'imagen' => $fila['imagen'],
                ]
            );
        }
    }
}
