<!DOCTYPE html>
<html lang="en">
<head>
@php
    // El subdirectorio real bajo el que corre la app (ej. "/GestPro" cuando se
    // sirve desde una subcarpeta de htdocs; "" cuando corre en la raíz, como con
    // `php artisan serve`). Se deriva del path de APP_URL (.env) en vez de
    // asumirlo por nombre de entorno o de la petición — la cadena de rewrites de
    // .htaccess (raíz -> public/ -> index.php) hace que request()->getBaseUrl()
    // no calcule esto de forma confiable en este proyecto.
    $base = parse_url(config('app.url'), PHP_URL_PATH) ?? '';
@endphp
    <meta charset="UTF-8">
    <title>GestPro - Sistema de Gestión de Proyectos</title>
    <link rel="preload" href="{{ $base . mix('css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ $base . mix('css/app.css') }}"></noscript>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <script>window.__APP_BASE__ = @json($base);</script>
</head>
<body>
    <div id="root"></div>

    <script src="{{ $base . mix('js/app.js') }}" defer></script>
</body>
</html>
