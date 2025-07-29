<?php

use Illuminate\Support\Facades\Route;

// Ruta de prueba para verificar que funciona
Route::get('/test-web', function () {
    return response()->json([
        'message' => 'Web routes funcionando correctamente',
        'timestamp' => now(),
        'environment' => app()->environment()
    ]);
});

// Ruta principal que carga la aplicación React
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
