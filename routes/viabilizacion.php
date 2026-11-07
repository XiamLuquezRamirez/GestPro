<?php

/*
 * Módulo de expedientes de viabilización SGR.
 * Se incluye desde routes/api.php DENTRO del grupo auth:api, así que todas
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\CatalogoRequisitosController;
use App\Http\Controllers\ExpedienteController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
Route::get('/catalogo-requisitos/vigente', [CatalogoRequisitosController::class, 'vigente'])
    ->name('catalogo-requisitos.vigente');
Route::get('/proyectos/{proyecto}/expedientes', [ExpedienteController::class, 'index'])
    ->name('expedientes.index');
Route::get('/expedientes', [ExpedienteController::class, 'resumen'])
    ->name('expedientes.resumen');
Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show'])
    ->name('expedientes.show');
Route::get('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'descargarSoporte'])
    ->scopeBindings()->name('expedientes.requisitos.soporte.descargar');
Route::get('/expedientes/{expediente}/requisitos/{requisito}/historial', [ExpedienteController::class, 'historialRequisito'])
    ->scopeBindings()->name('expedientes.requisitos.historial');
Route::get('/expedientes/{expediente}/historial/{entrada}/soporte', [ExpedienteController::class, 'descargarSoporteHistorial'])
    ->name('expedientes.historial.soporte');

// Expediente y cumplimiento: Administrador o Gestor.
Route::middleware('can:editar-datos')->group(function () {
    Route::post('/proyectos/{proyecto}/expedientes', [ExpedienteController::class, 'store'])
        ->name('expedientes.store');
    Route::put('/expedientes/{expediente}/configuracion', [ExpedienteController::class, 'updateConfiguracion'])
        ->name('expedientes.configuracion');
    Route::post('/expedientes/{expediente}/configuracion/confirmar', [ExpedienteController::class, 'confirmarConfiguracion'])
        ->name('expedientes.configuracion.confirmar');
    Route::post('/expedientes/{expediente}/radicar', [ExpedienteController::class, 'radicar'])
        ->name('expedientes.radicar');
    Route::delete('/expedientes/{expediente}', [ExpedienteController::class, 'destroy'])
        ->name('expedientes.destroy');

    Route::scopeBindings()->group(function () {
        Route::patch('/expedientes/{expediente}/requisitos/{requisito}', [ExpedienteController::class, 'updateRequisito'])
            ->name('expedientes.requisitos.update');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'subirSoporte'])
            ->name('expedientes.requisitos.soporte.subir');
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/soporte', [ExpedienteController::class, 'eliminarSoporte'])
            ->name('expedientes.requisitos.soporte.eliminar');
    });
});

// Administración del catálogo maestro: solo Administrador.
Route::middleware('can:gestionar-catalogos')->prefix('catalogo-requisitos')->name('catalogo-requisitos.')->group(function () {
    Route::get('/versiones', [CatalogoRequisitosController::class, 'versiones'])->name('versiones.index');
    Route::post('/versiones', [CatalogoRequisitosController::class, 'crearVersion'])->name('versiones.store');
    Route::post('/versiones/{version}/publicar', [CatalogoRequisitosController::class, 'publicarVersion'])->name('versiones.publicar');
    Route::delete('/versiones/{version}', [CatalogoRequisitosController::class, 'eliminarVersion'])->name('versiones.destroy');

    Route::get('/versiones/{version}/requisitos', [CatalogoRequisitosController::class, 'requisitos'])->name('requisitos.index');
    Route::post('/versiones/{version}/requisitos', [CatalogoRequisitosController::class, 'crearRequisito'])->name('requisitos.store');
    Route::put('/versiones/{version}/requisitos/{requisito}', [CatalogoRequisitosController::class, 'actualizarRequisito'])->name('requisitos.update');
    Route::delete('/versiones/{version}/requisitos/{requisito}', [CatalogoRequisitosController::class, 'eliminarRequisito'])->name('requisitos.destroy');

    Route::post('/validar-condicion', [CatalogoRequisitosController::class, 'validarCondicion'])->name('validar-condicion');

    Route::post('/{entidad}', [CatalogoRequisitosController::class, 'crearEntidad'])
        ->whereIn('entidad', ['sectores', 'tipos', 'componentes', 'tipologias'])->name('entidades.store');
    Route::put('/{entidad}/{id}', [CatalogoRequisitosController::class, 'actualizarEntidad'])
        ->whereIn('entidad', ['sectores', 'tipos', 'componentes', 'tipologias'])->whereNumber('id')->name('entidades.update');
});
