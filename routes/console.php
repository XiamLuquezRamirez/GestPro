<?php

use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

Artisan::command('proyectos:informe-fuentes', function () {
    $porFuente = DB::table('fuentes_financiacion as f')
        ->leftJoin('proyecto_fuentes as pf', 'pf.fuente_id', 'f.id')
        ->select('f.nombre', DB::raw('COUNT(pf.proyecto_id) as proyectos'))
        ->groupBy('f.id', 'f.nombre', 'f.orden')
        ->orderBy('f.orden')
        ->get();
    $this->table(['Fuente', 'Proyectos'], $porFuente->map(fn ($f) => [$f->nombre, $f->proyectos])->all());

    // Cada texto distinto una vez: las coincidencias por palabra no detectan una negación
    // ("Sin regalías") ni una fuente omitida, y eso solo se ve revisando el resultado.
    $conversiones = Proyecto::query()->whereNotNull('fuente_financiacion_original')->with('fuentes:id,nombre')->get()
        ->groupBy('fuente_financiacion_original')
        ->map(fn ($grupo, $texto) => [
            $texto,
            $grupo->first()->fuentes->pluck('nombre')->implode(', ') ?: 'Por definir',
            $grupo->count(),
        ]);
    $this->table(['Texto original', 'Resultado', 'Proyectos'], $conversiones->values()->all());

    $pendientes = Proyecto::query()->where('financiacion_por_definir', true)->orderBy('id')->get();
    $this->table(
        ['Id', 'Proyecto', 'Texto original'],
        $pendientes->map(fn (Proyecto $p) => [$p->id, $p->nombre, $p->fuente_financiacion_original ?? '(vacío)'])->all()
    );
    $this->info("Proyectos por definir: {$pendientes->count()}");
})->purpose('Muestra los proyectos por fuente y los que quedaron por definir, con su texto original');
