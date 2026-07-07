<?php

namespace App\Providers;

use App\Enums\Rol;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(\Tymon\JWTAuth\Providers\LaravelServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('gestionar-catalogos', fn ($user) => $user->rol === Rol::Administrador);
        Gate::define('gestionar-usuarios', fn ($user) => $user->rol === Rol::Administrador);
        Gate::define('editar-datos', fn ($user) => in_array($user->rol, [Rol::Administrador, Rol::Gestor], true));
    }
}
