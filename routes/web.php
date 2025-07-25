<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProyectoController;

// Rutas de autenticación con Sanctum
Route::get('/sanctum/csrf-cookie', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});

Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }

    $request->session()->regenerate();
    return response()->json(['message' => 'Login exitoso']);
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['message' => 'Logout exitoso']);
});

Route::get('/user', function (Request $request) {

    return $request->user();

})->middleware('auth:sanctum');


Route::get('/proyectos', [ProyectoController::class, 'proyectos'])->middleware('auth');
Route::get('/municipios', [ProyectoController::class, 'municipios'])->middleware('auth');
Route::get('/estados', [ProyectoController::class, 'estados'])->middleware('auth');
Route::get('/fases', [ProyectoController::class, 'fases'])->middleware('auth');
Route::get('/entidades', [ProyectoController::class, 'entidades'])->middleware('auth');
Route::post('/guardarProyecto', [ProyectoController::class, 'guardarProyecto'])->middleware('auth');
Route::post('/eliminarProyecto', [ProyectoController::class, 'eliminarProyecto'])->middleware('auth');
Route::post('/guardarEvento', [ProyectoController::class, 'guardarEvento'])->middleware('auth');
Route::get('/eventos', [ProyectoController::class, 'eventos'])->middleware('auth');
Route::get('/tiposEventos', [ProyectoController::class, 'tiposEventos'])->middleware('auth');
Route::get('/prioridades', [ProyectoController::class, 'prioridades'])->middleware('auth');
Route::get('/responsables', [ProyectoController::class, 'responsables'])->middleware('auth');
Route::post('/activarMunicipio', [ProyectoController::class, 'activarMunicipio'])->middleware('auth');
Route::post('/eliminarEvento', [ProyectoController::class, 'eliminarEvento'])->middleware('auth');
Route::get('/departamentos', [ProyectoController::class, 'departamentos'])->middleware('auth');
Route::post('/guardarMunicipio', [ProyectoController::class, 'guardarMunicipio'])->middleware('auth');
Route::post('/eliminarMunicipio', [ProyectoController::class, 'eliminarMunicipio'])->middleware('auth');
Route::post('/guardarEstado', [ProyectoController::class, 'guardarEstado'])->middleware('auth');
Route::post('/eliminarEstado', [ProyectoController::class, 'eliminarEstado'])->middleware('auth');
Route::post('/activarEstado', [ProyectoController::class, 'activarEstado'])->middleware('auth');
Route::post('/activarFase', [ProyectoController::class, 'activarFase'])->middleware('auth');
Route::post('/guardarFase', [ProyectoController::class, 'guardarFase'])->middleware('auth');
Route::post('/eliminarFase', [ProyectoController::class, 'eliminarFase'])->middleware('auth');
Route::post('/guardarTipoEvento', [ProyectoController::class, 'guardarTipoEvento'])->middleware('auth');
Route::post('/eliminarTipoEvento', [ProyectoController::class, 'eliminarTipoEvento'])->middleware('auth');
Route::post('/activarTipoEvento', [ProyectoController::class, 'activarTipoEvento'])->middleware('auth');
Route::post('/guardarPrioridad', [ProyectoController::class, 'guardarPrioridad'])->middleware('auth');
Route::post('/activarPrioridad', [ProyectoController::class, 'activarPrioridad'])->middleware('auth');
Route::post('/eliminarPrioridad', [ProyectoController::class, 'eliminarPrioridad'])->middleware('auth');
Route::post('/activarPrioridad', [ProyectoController::class, 'activarPrioridad'])->middleware('auth');
Route::post('/guardarResponsable', [ProyectoController::class, 'guardarResponsable'])->middleware('auth');
Route::post('/eliminarResponsable', [ProyectoController::class, 'eliminarResponsable'])->middleware('auth');
Route::post('/activarResponsable', [ProyectoController::class, 'activarResponsable'])->middleware('auth');
Route::post('/guardarEntidad', [ProyectoController::class, 'guardarEntidad'])->middleware('auth');
Route::post('/eliminarEntidad', [ProyectoController::class, 'eliminarEntidad'])->middleware('auth');
Route::post('/activarEntidad', [ProyectoController::class, 'activarEntidad'])->middleware('auth');
Route::post('/activarFaseDashboard', [ProyectoController::class, 'activarFaseDashboard'])->middleware('auth');
// 👇 Ruta que sirve el frontend React
Route::get('/{any}', function () {
    return view('app'); // asegúrate de tener resources/views/app.blade.php
})->where('any', '.*');