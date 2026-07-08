<!DOCTYPE html>
<html lang="en">
<head>
@php
    // El subdirectorio real bajo el que viven los archivos ESTÁTICOS compilados
    // (ej. "/GestPro" cuando Apache sirve la app desde una subcarpeta de htdocs;
    // "" cuando se sirve directo en la raíz, como con `php artisan serve` o
    // `php -S -t public`). Esto NO es lo mismo que el prefijo de la API
    // ('/GestPro', fijo en bootstrap/app.php) — depende de qué servidor está
    // sirviendo esta petición en concreto. Se detecta comparando el PUERTO real
    // de la petición contra el de APP_URL (.env) — no el host, para que acceder
    // por la IP de red (u otro hostname) en vez de "localhost" siga
    // reconociendo a Apache correctamente. El puerto es un buen indicador
    // porque Apache (con la subcarpeta real) siempre escucha en el mismo
    // puerto fijo (80 aquí), mientras que cualquier servidor de desarrollo
    // ad-hoc (`php artisan serve`, `php -S`) usa otro puerto alto, sin
    // importar por qué IP se acceda a él. `request()->getBaseUrl()` no sirve
    // aquí porque la cadena de rewrites de .htaccess (raíz -> public/ ->
    // index.php) hace que no lo calcule de forma confiable.
    $appUrl = parse_url(config('app.url'));
    $appPort = $appUrl['port'] ?? (($appUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);
    $mismoServidor = $appPort === request()->getPort();
    $base = $mismoServidor ? ($appUrl['path'] ?? '') : '';

    // El subdirectorio bajo el que el navegador está posicionado AHORA MISMO
    // (para basename de React Router) — es un concepto distinto de $base:
    // aquí lo que importa es con qué URL literal llegó esta petición, no dónde
    // viven los archivos estáticos. Se lee el REQUEST_URI crudo (no el de
    // Symfony/Laravel, que en este proyecto lo recalcula mal por la cadena de
    // rewrites de .htaccess) para que funcione igual si Apache sirve la app
    // bajo /GestPro o si un servidor de desarrollo (sin esa subcarpeta real)
    // es accedido manualmente con /GestPro en la URL.
    $rutaActual = $_SERVER['REQUEST_URI'] ?? '/';
    $routerBase = str_starts_with($rutaActual, '/GestPro') ? '/GestPro' : '';
@endphp
    <meta charset="UTF-8">
    <title>GestPro - Sistema de Gestión de Proyectos</title>
    <link rel="preload" href="{{ $base . mix('css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ $base . mix('css/app.css') }}"></noscript>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <script>
        window.__APP_BASE__ = @json($base);
        window.__ROUTER_BASE__ = @json($routerBase);
    </script>
</head>
<body>
    <div id="root"></div>

    <script src="{{ $base . mix('js/app.js') }}" defer></script>
</body>
</html>
