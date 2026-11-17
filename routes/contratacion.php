<?php

/*
 * Módulo de contratación.
 * Se incluye desde routes/api.php DENTRO del grupo auth:api, así que todas
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\ProcesoContratacionController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');

// Procesos de contratación: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'store'])
        ->name('procesos.store');
    Route::put('/procesos/{proceso}', [ProcesoContratacionController::class, 'update'])
        ->name('procesos.update');
    Route::post('/procesos/{proceso}/estado', [ProcesoContratacionController::class, 'cambiarEstado'])
        ->name('procesos.estado');
    Route::delete('/procesos/{proceso}', [ProcesoContratacionController::class, 'destroy'])
        ->name('procesos.destroy');
});
