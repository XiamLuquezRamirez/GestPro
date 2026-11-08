<?php

use App\Models\Proyecto;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('expedientes:crear-faltantes {--simular : Solo lista los proyectos y la plantilla que se preseleccionaría, sin crear nada}', function (ExpedienteService $servicio, PlantillasExpediente $plantillas) {
    $proyectos = Proyecto::query()->orderBy('id')->get()->filter(fn (Proyecto $p) => $servicio->correspondeAutomatico($p));

    if ($this->option('simular')) {
        $proyectos->each(function (Proyecto $p) use ($plantillas) {
            $plantilla = $plantillas->sgrCompatible($p);
            $this->line("#{$p->id} {$p->nombre}: " . ($plantilla ? "plantilla {$plantilla->nombre}" : 'sin plantilla'));
        });
        $this->info("Se crearían {$proyectos->count()} expedientes.");

        return;
    }

    $creados = $proyectos->filter(fn (Proyecto $p) => $servicio->crearAutomatico($p, null) !== null)->count();
    $this->info("Expedientes creados: {$creados} de {$proyectos->count()}.");
})->purpose('Crea el expediente, sin configurar, a los proyectos en Formulación con al menos una fuente definida que no tienen');

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
    $textoVisible = fn (?string $t) => trim((string) $t) === '' ? '(vacío)' : $t;

    $conversiones = Proyecto::query()->whereNotNull('fuente_financiacion_original')->with('fuentes:id,nombre')->get()
        ->groupBy(fn (Proyecto $p) => $p->fuente_financiacion_original . "\0" . ($p->fuentes->pluck('nombre')->implode(', ') ?: 'Por definir'))
        ->map(fn ($grupo) => [
            $textoVisible($grupo->first()->fuente_financiacion_original),
            $grupo->first()->fuentes->pluck('nombre')->implode(', ') ?: 'Por definir',
            $grupo->count(),
        ]);
    $this->table(['Texto original', 'Resultado', 'Proyectos'], $conversiones->values()->all());

    $pendientes = Proyecto::query()->where('financiacion_por_definir', true)->orderBy('id')->get();
    $this->table(
        ['Id', 'Proyecto', 'Texto original'],
        $pendientes->map(fn (Proyecto $p) => [$p->id, $p->nombre, $textoVisible($p->fuente_financiacion_original)])->all()
    );
    $this->info("Proyectos por definir: {$pendientes->count()}");
})->purpose('Muestra los proyectos por fuente y los que quedaron por definir, con su texto original');
