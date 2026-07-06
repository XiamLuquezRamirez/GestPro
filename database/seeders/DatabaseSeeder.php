<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartamentosSeeder::class,
            MunicipiosSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
