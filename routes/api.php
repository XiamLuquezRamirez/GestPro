<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\AuthController;

// 🔓 Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Ruta de prueba para verificar que las rutas funcionan
Route::get('/test', function () {
    return response()->json([
        'message' => 'API funcionando correctamente',
        'timestamp' => now(),
        'environment' => app()->environment(),
        'url' => request()->fullUrl()
    ]);
});

// 🔒 Rutas protegidas con JWT
Route::middleware('auth:api')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Rutas de proyectos
    Route::get('/proyectos', [ProyectoController::class, 'proyectos']);
    Route::get('/municipios', [ProyectoController::class, 'municipios']);
    Route::get('/estados', [ProyectoController::class, 'estados']);
    Route::get('/fases', [ProyectoController::class, 'fases']);
    Route::get('/entidades', [ProyectoController::class, 'entidades']);
    Route::get('/eventos', [ProyectoController::class, 'eventos']);
    Route::get('/tiposEventos', [ProyectoController::class, 'tiposEventos']);
    Route::get('/prioridades', [ProyectoController::class, 'prioridades']);
    Route::get('/responsables', [ProyectoController::class, 'responsables']);
    Route::get('/departamentos', [ProyectoController::class, 'departamentos']);

    Route::post('/guardarProyecto', [ProyectoController::class, 'guardarProyecto']);
    Route::post('/eliminarProyecto', [ProyectoController::class, 'eliminarProyecto']);
    Route::post('/guardarEvento', [ProyectoController::class, 'guardarEvento']);
    Route::post('/activarMunicipio', [ProyectoController::class, 'activarMunicipio']);
    Route::post('/eliminarEvento', [ProyectoController::class, 'eliminarEvento']);
    Route::post('/guardarMunicipio', [ProyectoController::class, 'guardarMunicipio']);
    Route::post('/eliminarMunicipio', [ProyectoController::class, 'eliminarMunicipio']);
    Route::post('/guardarEstado', [ProyectoController::class, 'guardarEstado']);
    Route::post('/eliminarEstado', [ProyectoController::class, 'eliminarEstado']);
    Route::post('/activarEstado', [ProyectoController::class, 'activarEstado']);
    Route::post('/guardarFase', [ProyectoController::class, 'guardarFase']);
    Route::post('/eliminarFase', [ProyectoController::class, 'eliminarFase']);
    Route::post('/activarFase', [ProyectoController::class, 'activarFase']);
    Route::post('/guardarTipoEvento', [ProyectoController::class, 'guardarTipoEvento']);
    Route::post('/eliminarTipoEvento', [ProyectoController::class, 'eliminarTipoEvento']);
    Route::post('/activarTipoEvento', [ProyectoController::class, 'activarTipoEvento']);
    Route::post('/guardarPrioridad', [ProyectoController::class, 'guardarPrioridad']);
    Route::post('/eliminarPrioridad', [ProyectoController::class, 'eliminarPrioridad']);
    Route::post('/activarPrioridad', [ProyectoController::class, 'activarPrioridad']);
    Route::post('/guardarResponsable', [ProyectoController::class, 'guardarResponsable']);
    Route::post('/eliminarResponsable', [ProyectoController::class, 'eliminarResponsable']);
    Route::post('/activarResponsable', [ProyectoController::class, 'activarResponsable']);
    Route::post('/guardarEntidad', [ProyectoController::class, 'guardarEntidad']);
    Route::post('/eliminarEntidad', [ProyectoController::class, 'eliminarEntidad']);
    Route::post('/activarEntidad', [ProyectoController::class, 'activarEntidad']);
    Route::post('/activarFaseDashboard', [ProyectoController::class, 'activarFaseDashboard']);

    //gestion de contratos
    Route::post('/guardarContrato', [ProyectoController::class, 'guardarContrato']);
    Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
    Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
    Route::get('/listarContratos', [ProyectoController::class, 'listarContratos']);
    
});
