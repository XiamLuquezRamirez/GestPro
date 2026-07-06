<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gestpro.local'],
            [
                'name' => 'Administrador GestPro',
                'password' => Hash::make('Admin123!'),
                'rol' => Rol::Administrador,
                'activo' => true,
            ]
        );
    }
}
