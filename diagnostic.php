<?php

/**
 * Script de diagnóstico para GestPro en producción
 * Ejecutar desde la línea de comandos: php diagnostic.php
 */

echo "=== DIAGNÓSTICO GESTPRO ===\n\n";

// Verificar si Laravel está cargado
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ ERROR: No se encontró vendor/autoload.php\n";
    echo "Ejecuta: composer install\n\n";
    exit(1);
}

require_once __DIR__ . '/vendor/autoload.php';

// Cargar Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';

echo "✅ Laravel cargado correctamente\n";

// Verificar configuración
echo "\n=== CONFIGURACIÓN ===\n";
echo "APP_ENV: " . env('APP_ENV', 'no definido') . "\n";
echo "APP_DEBUG: " . (env('APP_DEBUG', false) ? 'true' : 'false') . "\n";
echo "APP_URL: " . env('APP_URL', 'no definido') . "\n";
echo "APP_KEY: " . (env('APP_KEY') ? 'definido' : 'no definido') . "\n";

// Verificar base de datos
echo "\n=== BASE DE DATOS ===\n";
try {
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', '127.0.0.1') . 
        ';port=' . env('DB_PORT', '3306') . 
        ';dbname=' . env('DB_DATABASE', 'gestpro'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', '')
    );
    echo "✅ Conexión a base de datos exitosa\n";
    
    // Verificar tabla users
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabla 'users' existe\n";
        
        // Contar usuarios
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        echo "📊 Usuarios en la base de datos: $count\n";
    } else {
        echo "❌ Tabla 'users' no existe\n";
    }
} catch (PDOException $e) {
    echo "❌ Error de conexión a base de datos: " . $e->getMessage() . "\n";
}

// Verificar rutas
echo "\n=== RUTAS ===\n";
try {
    $router = $app->make('router');
    $routes = $router->getRoutes();
    
    $apiRoutes = collect($routes)->filter(function ($route) {
        return str_starts_with($route->uri(), 'api/');
    });
    
    echo "📊 Total de rutas API: " . $apiRoutes->count() . "\n";
    
    // Buscar la ruta de login
    $loginRoute = $apiRoutes->first(function ($route) {
        return str_contains($route->uri(), 'login');
    });
    
    if ($loginRoute) {
        echo "✅ Ruta de login encontrada: " . $loginRoute->uri() . "\n";
        echo "   Método: " . implode(',', $loginRoute->methods()) . "\n";
    } else {
        echo "❌ Ruta de login no encontrada\n";
    }
} catch (Exception $e) {
    echo "❌ Error al verificar rutas: " . $e->getMessage() . "\n";
}

// Verificar permisos de archivos
echo "\n=== PERMISOS ===\n";
$directories = [
    'storage' => '0755',
    'storage/logs' => '0755',
    'storage/framework' => '0755',
    'storage/framework/cache' => '0755',
    'storage/framework/sessions' => '0755',
    'storage/framework/views' => '0755',
    'bootstrap/cache' => '0755'
];

foreach ($directories as $dir => $expectedPerms) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $status = ($perms >= $expectedPerms) ? '✅' : '⚠️';
        echo "$status $dir: $perms (esperado: $expectedPerms)\n";
    } else {
        echo "❌ $dir: no existe\n";
    }
}

// Verificar logs
echo "\n=== LOGS ===\n";
$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    echo "📄 Log file: $logFile ($size bytes)\n";
    
    // Mostrar últimas líneas del log
    $lines = file($logFile);
    $lastLines = array_slice($lines, -10);
    echo "\nÚltimas 10 líneas del log:\n";
    foreach ($lastLines as $line) {
        echo trim($line) . "\n";
    }
} else {
    echo "❌ Archivo de log no encontrado\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n"; 