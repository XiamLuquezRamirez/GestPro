<?php

/*
 * Módulo de contratación.
 * Se incluye desde routes/api.php DENTRO del grupo auth:api, así que todas
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ProcesoContratacionController;
use App\Http\Controllers\SecopController;
use App\Http\Controllers\TableroContratacionController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
Route::get('/proyectos/{proyecto}/procesos', [ProcesoContratacionController::class, 'index'])
    ->name('procesos.index');
Route::get('/procesos/{proceso}/precarga-contrato', [ProcesoContratacionController::class, 'precargaContrato'])
    ->name('procesos.precarga-contrato');
Route::get('/procesos/{proceso}/plantillas-compatibles', [ProcesoContratacionController::class, 'plantillasCompatibles'])
    ->name('procesos.plantillas-compatibles');

// Contratos de todos los proyectos (y los que no tienen proyecto). Sin GET /contratos ni
// GET /contratos/{id}: esas rutas son del SPA y no deben coincidir con la API.
Route::get('/contratos/buscar', [ContratoController::class, 'buscar'])
    ->name('contratos.buscar');
Route::get('/contratos/{contrato}/detalle', [ContratoController::class, 'detalle'])
    ->whereNumber('contrato')
    ->name('contratos.detalle');

// Vista "Contratación" del tablero: todos los procesos, con filtros. El tablero del SPA es
// /dashboard?vista=contratacion: esta ruta no coincide con ninguna del SPA.
Route::get('/tablero/contratacion', [TableroContratacionController::class, 'index'])
    ->name('tablero.contratacion');

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
    Route::post('/procesos/{proceso}/expediente', [ProcesoContratacionController::class, 'crearExpediente'])
        ->name('procesos.expediente.store');
});

// SECOP II (datos abiertos): consultar para importar contratos. Administrador o Gestor, con
// límite de consultas: cada una llama a datos.gov.co.
Route::middleware(['can:editar-datos', 'throttle:30,1'])->prefix('secop')->group(function () {
    Route::get('/contratos', [SecopController::class, 'buscar'])
        ->name('secop.buscar');
    Route::get('/contratos-empresa', [SecopController::class, 'deEmpresa'])
        ->name('secop.empresa');
    Route::get('/contratos/{idContrato}', [SecopController::class, 'porId'])
        ->name('secop.contrato');
});
