<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Production Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración específica para el entorno de producción
    |
    */

    'app_url' => env('APP_URL', 'https://tu-dominio.com/GestPro'),
    
    'force_https' => env('FORCE_HTTPS', true),
    
    'trust_proxies' => env('TRUST_PROXIES', true),
    
    'session_secure' => env('SESSION_SECURE_COOKIES', true),
    
    'cors' => [
        'allowed_origins' => [
            env('FRONTEND_URL', 'https://tu-dominio.com'),
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => true,
    ],
]; 