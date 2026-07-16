<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\AuthController;

// 🔓 Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Ruta para obtener token CSRF
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

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

    // Lectura: cualquier rol autenticado (Administrador, Gestor o Consulta)
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
    Route::get('/eventosListar', [ProyectoController::class, 'eventosListar']);
    Route::get('/listarContratos', [ProyectoController::class, 'listarContratos']);
    Route::get('/sectores', [ProyectoController::class, 'sectores']);
    Route::get('/tiposProcesos', [ProyectoController::class, 'tiposProcesos']);
    Route::get('/modalidades', [ProyectoController::class, 'modalidades']);

    // Escritura de datos de negocio: Administrador o Gestor
    Route::middleware('can:editar-datos')->group(function () {
        Route::post('/guardarProyecto', [ProyectoController::class, 'guardarProyecto']);
        Route::post('/eliminarProyecto', [ProyectoController::class, 'eliminarProyecto']);
        Route::post('/guardarEvento', [ProyectoController::class, 'guardarEvento']);
        Route::post('/eliminarEvento', [ProyectoController::class, 'eliminarEvento']);
        Route::post('/guardarContrato', [ProyectoController::class, 'guardarContrato']);
        Route::post('/subirAnexo', [ProyectoController::class, 'subirAnexo']);
        Route::post('/subirActa', [ProyectoController::class, 'subirActa']);
        Route::post('/eliminarAnexo', [ProyectoController::class, 'eliminarAnexo']);
        Route::post('/eliminarContrato', [ProyectoController::class, 'eliminarContrato']);
        Route::post('/guardarActaFinanciera', [ProyectoController::class, 'guardarActaFinanciera']);
        Route::post('/eliminarActaFinanciera', [ProyectoController::class, 'eliminarActaFinanciera']);
        Route::post('/guardarActividad', [ProyectoController::class, 'guardarActividad']);
        Route::post('/eliminarActividad', [ProyectoController::class, 'eliminarActividad']);
        Route::post('/registrarAvanceActividad', [ProyectoController::class, 'registrarAvanceActividad']);
        Route::post('/guardarModificacionContrato', [ProyectoController::class, 'guardarModificacionContrato']);
        Route::post('/eliminarModificacionContrato', [ProyectoController::class, 'eliminarModificacionContrato']);
    });

    // Gestión de catálogos: solo Administrador
    Route::middleware('can:gestionar-catalogos')->group(function () {
        Route::post('/activarMunicipio', [ProyectoController::class, 'activarMunicipio']);
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
        Route::post('/guardarSector', [ProyectoController::class, 'guardarSector']);
        Route::post('/eliminarSector', [ProyectoController::class, 'eliminarSector']);
        Route::post('/activarSector', [ProyectoController::class, 'activarSector']);
        Route::post('/guardarTipoProceso', [ProyectoController::class, 'guardarTipoProceso']);
        Route::post('/eliminarTipoProceso', [ProyectoController::class, 'eliminarTipoProceso']);
        Route::post('/activarTipoProceso', [ProyectoController::class, 'activarTipoProceso']);
        Route::post('/guardarModalidad', [ProyectoController::class, 'guardarModalidad']);
        Route::post('/eliminarModalidad', [ProyectoController::class, 'eliminarModalidad']);
        Route::post('/activarModalidad', [ProyectoController::class, 'activarModalidad']);
    });

    // Gestión de usuarios: solo Administrador
    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::get('/usuarios', [ProyectoController::class, 'usuarios']);
        Route::post('/activarUsuario', [ProyectoController::class, 'activarUsuario']);
        Route::post('/guardarUsuario', [ProyectoController::class, 'guardarUsuario']);
        Route::post('/validarEmail', [ProyectoController::class, 'validarEmail']);
    });
});
