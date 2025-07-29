<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            // Log para debugging en producción
            Log::info('Intento de login desde: ' . $request->ip());
            Log::info('Headers: ' . json_encode($request->headers->all()));
            Log::info('Request URL: ' . $request->fullUrl());
            
            $credentials = $request->only('email', 'password');
            
            // Validar que los campos requeridos estén presentes
            if (empty($credentials['email']) || empty($credentials['password'])) {
                Log::warning('Credenciales incompletas en login');
                return response()->json(['error' => 'Email y password son requeridos'], 400);
            }

            if (!$token = JWTAuth::attempt($credentials)) {
                Log::warning('Credenciales inválidas para: ' . $credentials['email']);
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            $user = Auth::user();
            Log::info('Login exitoso para usuario: ' . $user->email);
            
            return response()->json([
                'token' => $token,
                'user' => $user,
                'message' => 'Login exitoso'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en login: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }
            return response()->json($user);
        } catch (\Exception $e) {
            Log::error('Error en me: ' . $e->getMessage());
            return response()->json(['error' => 'Error del servidor'], 500);
        }
    }
}
