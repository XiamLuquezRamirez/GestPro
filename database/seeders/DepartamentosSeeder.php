<?php

namespace Database\Seeders;

use App\Models\Departamento;
use Illuminate\Database\Seeder;

class DepartamentosSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = database_path('seeders/data/departamentos.json');
        $filas = json_decode(file_get_contents($ruta), true);

        foreach ($filas as $fila) {
            Departamento::updateOrCreate(
                ['codigo' => $fila['codigo']],
                [
                    'nombre' => $fila['nombre'],
                    'activo' => $fila['habilitado'] === 'SI',
                ]
            );
        }
    }
}
