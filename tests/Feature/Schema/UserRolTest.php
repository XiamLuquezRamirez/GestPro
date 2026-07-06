<?php

namespace Tests\Feature\Schema;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRolTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_rol_is_cast_to_the_rol_enum(): void
    {
        $user = User::factory()->create(['rol' => Rol::Gestor]);

        $this->assertInstanceOf(Rol::class, $user->fresh()->rol);
        $this->assertSame(Rol::Gestor, $user->fresh()->rol);
    }

    public function test_user_activo_is_cast_to_boolean(): void
    {
        $user = User::factory()->create(['activo' => 1]);

        $this->assertIsBool($user->fresh()->activo);
        $this->assertTrue($user->fresh()->activo);
    }

    public function test_admin_seeder_creates_a_default_administrador(): void
    {
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $admin = User::where('email', 'admin@gestpro.local')->first();

        $this->assertNotNull($admin);
        $this->assertSame(Rol::Administrador, $admin->rol);
        $this->assertTrue($admin->activo);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Admin123!', $admin->password));
    }
}
