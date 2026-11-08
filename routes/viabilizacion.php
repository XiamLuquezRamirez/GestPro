<?php

/*
 * Módulo de expedientes de viabilización SGR.
 * Se incluye desde routes/api.php DENTRO del grupo auth:api, así que todas
 * estas rutas exigen JWT; aquí solo se agrega el permiso de escritura.
 */

use App\Http\Controllers\CatalogoRequisitosController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\NoAplicaRequisitoController;
use App\Http\Controllers\PlantillaChecklistController;
use App\Http\Controllers\PlantillasExpedienteController;
use Illuminate\Support\Facades\Route;

// Lectura: cualquier usuario autenticado.
Route::get('/catalogo-requisitos/vigente', [CatalogoRequisitosController::class, 'vigente'])
    ->name('catalogo-requisitos.vigente');
Route::get('/proyectos/{proyecto}/expedientes', [ExpedienteController::class, 'index'])
    ->name('expedientes.index');
Route::get('/proyectos/{proyecto}/plantillas-compatibles', [PlantillasExpedienteController::class, 'compatibles'])
    ->name('expedientes.plantillas-compatibles');
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

        Route::post('/expedientes/{expediente}/requisitos/{requisito}/aplica', [NoAplicaRequisitoController::class, 'decidirAplica'])
            ->name('expedientes.requisitos.aplica');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'marcar'])
            ->name('expedientes.requisitos.no-aplica.marcar');
        Route::delete('/expedientes/{expediente}/requisitos/{requisito}/no-aplica', [NoAplicaRequisitoController::class, 'retirar'])
            ->name('expedientes.requisitos.no-aplica.retirar');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica/aprobar', [NoAplicaRequisitoController::class, 'aprobar'])
            ->name('expedientes.requisitos.no-aplica.aprobar');
        Route::post('/expedientes/{expediente}/requisitos/{requisito}/no-aplica/rechazar', [NoAplicaRequisitoController::class, 'rechazar'])
            ->name('expedientes.requisitos.no-aplica.rechazar');
    });
});

// Plantillas de checklist: solo Administrador. Sus requisitos y versiones se
// administran con las rutas de /catalogo-requisitos/versiones/{version}.
Route::middleware('can:gestionar-catalogos')->prefix('plantillas')->name('plantillas.')->group(function () {
    Route::get('/', [PlantillaChecklistController::class, 'index'])->name('index');
    Route::post('/', [PlantillaChecklistController::class, 'store'])->name('store');
    Route::get('/{plantilla}', [PlantillaChecklistController::class, 'show'])->name('show');
    Route::put('/{plantilla}', [PlantillaChecklistController::class, 'update'])->name('update');
    Route::post('/{plantilla}/activar', [PlantillaChecklistController::class, 'activar'])->name('activar');
    Route::post('/{plantilla}/duplicar', [PlantillaChecklistController::class, 'duplicar'])->name('duplicar');
    Route::post('/{plantilla}/versiones', [PlantillaChecklistController::class, 'crearVersion'])->name('versiones.store');
});

// Administración del catálogo maestro: solo Administrador.
Route::middleware('can:gestionar-catalogos')->prefix('catalogo-requisitos')->name('catalogo-requisitos.')->group(function () {
    Route::get('/versiones', [CatalogoRequisitosController::class, 'versiones'])->name('versiones.index');
    Route::post('/versiones', [CatalogoRequisitosController::class, 'crearVersion'])->name('versiones.store');
    Route::post('/versiones/{version}/publicar', [CatalogoRequisitosController::class, 'publicarVersion'])->name('versiones.publicar');
    Route::delete('/versiones/{version}', [CatalogoRequisitosController::class, 'eliminarVersion'])->name('versiones.destroy');

    Route::get('/versiones/{version}/requisitos', [CatalogoRequisitosController::class, 'requisitos'])->name('requisitos.index');
    Route::put('/versiones/{version}/requisitos-orden', [CatalogoRequisitosController::class, 'ordenarRequisitos'])->name('requisitos.orden');
    Route::post('/versiones/{version}/requisitos', [CatalogoRequisitosController::class, 'crearRequisito'])->name('requisitos.store');
    Route::put('/versiones/{version}/requisitos/{requisito}', [CatalogoRequisitosController::class, 'actualizarRequisito'])->name('requisitos.update');
    Route::delete('/versiones/{version}/requisitos/{requisito}', [CatalogoRequisitosController::class, 'eliminarRequisito'])->name('requisitos.destroy');

    Route::post('/validar-condicion', [CatalogoRequisitosController::class, 'validarCondicion'])->name('validar-condicion');

    Route::post('/{entidad}', [CatalogoRequisitosController::class, 'crearEntidad'])
        ->whereIn('entidad', ['sectores', 'tipos', 'componentes', 'tipologias'])->name('entidades.store');
    Route::put('/{entidad}/{id}', [CatalogoRequisitosController::class, 'actualizarEntidad'])
        ->whereIn('entidad', ['sectores', 'tipos', 'componentes', 'tipologias'])->whereNumber('id')->name('entidades.update');
});
