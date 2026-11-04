<?php

use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('expedientes:crear-faltantes {--simular : Solo lista los proyectos, sin crear nada}', function (ExpedienteService $servicio) {
    $proyectos = Proyecto::query()->orderBy('id')->get()->filter(fn (Proyecto $p) => $servicio->correspondeAutomatico($p));

    if ($this->option('simular')) {
        $proyectos->each(fn (Proyecto $p) => $this->line("#{$p->id} {$p->nombre}"));
        $this->info("Se crearían {$proyectos->count()} expedientes.");

        return;
    }

    $creados = $proyectos->filter(fn (Proyecto $p) => $servicio->crearAutomatico($p, null) !== null)->count();
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente SGR, sin configurar, a los proyectos en Formulación con regalías que no tienen');
