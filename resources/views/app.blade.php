<!DOCTYPE html>
<html lang="en">
<head>
@php
    $base = App::environment('production') ? '/GestPro' : '';
@endphp
    <meta charset="UTF-8">
    <title>GestPro - Sistema de Gestión de Proyectos</title>
    <link rel="preload" href="{{ $base . mix('css/app.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="{{ $base . mix('css/app.css') }}"></noscript>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
</head>
<body>
    <div id="root"></div>

    <script src="{{ $base . mix('js/app.js') }}" defer></script>
</body>
</html>
