<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\Estado;
use App\Models\Evento;
use App\Models\Fase;
use App\Models\PresupuestoProyecto;
use App\Models\Prioridad;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\Sector;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para visualizar el dashboard localmente.
 * No se ejecuta automáticamente con DatabaseSeeder — correr a demanda:
 *   php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $fases = $this->seedFases();
        $estados = $this->seedEstados($fases);
        $sectores = $this->seedSectores();
        $entidades = $this->seedEntidades();

        // Códigos DANE reales del catálogo de municipios (Antioquia) ya sembrado por
        // MunicipiosSeeder. proyectos.municipio referencia municipios.codigo, no .id.
        $municipios = [
            'medellin' => '05001',
            'bello' => '05088',
            'itagui' => '05360',
            'envigado' => '05266',
            'rionegro' => '05615',
        ];

        $proyectos = $this->seedProyectos($fases, $estados, $sectores, $entidades, $municipios);
        $this->seedContratos($proyectos);
        $this->seedEventos($proyectos);
    }

    private function seedFases(): array
    {
        return [
            'Formulación' => Fase::updateOrCreate(['nombre' => 'Formulación'], ['color' => '#fbc02d', 'activo' => true, 'dashboard' => true]),
            'Licitación' => Fase::updateOrCreate(['nombre' => 'Licitación'], ['color' => '#1976d2', 'activo' => true, 'dashboard' => true]),
            'Ejecución' => Fase::updateOrCreate(['nombre' => 'Ejecución'], ['color' => '#43a047', 'activo' => true, 'dashboard' => true]),
        ];
    }

    private function seedEstados(array $fases): array
    {
        $definiciones = [
            ['nombre' => 'En estructuración', 'color' => '#fbc02d', 'icono' => 'edit', 'fase' => 'Formulación'],
            ['nombre' => 'Aprobado', 'color' => '#43a047', 'icono' => 'check', 'fase' => 'Formulación'],
            ['nombre' => 'Publicado', 'color' => '#1976d2', 'icono' => 'megaphone', 'fase' => 'Licitación'],
            ['nombre' => 'En evaluación', 'color' => '#8e24aa', 'icono' => 'search', 'fase' => 'Licitación'],
            ['nombre' => 'En ejecución', 'color' => '#1976d2', 'icono' => 'hammer', 'fase' => 'Ejecución'],
            ['nombre' => 'Con retraso', 'color' => '#e53935', 'icono' => 'alert', 'fase' => 'Ejecución'],
        ];

        $estados = [];
        foreach ($definiciones as $def) {
            $estados[$def['nombre']] = Estado::updateOrCreate(
                ['nombre' => $def['nombre']],
                [
                    'color' => $def['color'],
                    'icono' => $def['icono'],
                    'activo' => true,
                    'fase' => $fases[$def['fase']]->id,
                ]
            );
        }

        return $estados;
    }

    private function seedSectores(): array
    {
        $nombres = ['Vías', 'Educación', 'Salud', 'Agua Potable', 'Vivienda'];
        $sectores = [];
        foreach ($nombres as $nombre) {
            $sectores[$nombre] = Sector::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }

        return $sectores;
    }

    private function seedEntidades(): array
    {
        $nombres = ['Alcaldía Municipal', 'Gobernación de Antioquia', 'Ministerio de Vivienda', 'Findeter'];
        $entidades = [];
        foreach ($nombres as $nombre) {
            $entidades[$nombre] = Entidad::updateOrCreate(['nombre' => $nombre], ['activo' => true]);
        }

        return $entidades;
    }

    private function seedProyectos(array $fases, array $estados, array $sectores, array $entidades, array $municipios): array
    {
        $sectorNombres = array_keys($sectores);
        $entidadNombres = array_keys($entidades);

        // [fase, estado, progreso]
        $combinaciones = [
            ['Formulación', 'En estructuración', 15],
            ['Formulación', 'Aprobado', 35],
            ['Licitación', 'Publicado', 45],
            ['Licitación', 'En evaluación', 55],
            ['Ejecución', 'En ejecución', 70],
            ['Ejecución', 'Con retraso', 40],
        ];

        $nombresProyecto = [
            'Pavimentación vía rural', 'Construcción sede educativa', 'Ampliación centro de salud',
            'Optimización acueducto veredal', 'Mejoramiento de vivienda rural', 'Construcción polideportivo',
        ];

        $proyectos = [];
        $i = 0;
        foreach ($municipios as $municipioNombre => $municipioCodigo) {
            foreach ($combinaciones as [$faseNombre, $estadoNombre, $progresoBase]) {
                $i++;
                $nombre = $nombresProyecto[$i % count($nombresProyecto)] . ' - ' . ucfirst($municipioNombre);
                $sector = $sectores[$sectorNombres[$i % count($sectorNombres)]];
                $entidadPresenta = $entidades[$entidadNombres[$i % count($entidadNombres)]];
                $entidadFinancia = $entidades[$entidadNombres[($i + 1) % count($entidadNombres)]];

                $proyecto = Proyecto::updateOrCreate(
                    ['nombre' => $nombre],
                    [
                        'municipio' => $municipioCodigo,
                        'descripcion' => 'Proyecto de demostración para visualizar el dashboard.',
                        'fecha_inicio' => now()->subMonths(rand(1, 10))->format('Y-m-d'),
                        'estado' => $estados[$estadoNombre]->id,
                        'fase' => $fases[$faseNombre]->id,
                        'presupuesto' => rand(80, 950) * 1_000_000,
                        'entidad_presenta' => $entidadPresenta->id,
                        'entidad_financia' => $entidadFinancia->id,
                        'fuente_financiacion' => 'Recursos propios y regalías',
                        'progreso' => min(100, $progresoBase + rand(-5, 5)),
                        'sector' => $sector->id,
                    ]
                );

                $proyectos[] = ['proyecto' => $proyecto, 'fase' => $faseNombre];
            }
        }

        return $proyectos;
    }

    private function seedContratos(array $proyectos): void
    {
        $enEjecucion = array_values(array_filter($proyectos, fn ($p) => $p['fase'] === 'Ejecución'));

        foreach (array_slice($enEjecucion, 0, 4) as $index => $item) {
            $proyecto = $item['proyecto'];

            $contrato = Contrato::updateOrCreate(
                ['n_contrato' => sprintf('CT-2026-%03d', $index + 1)],
                [
                    'proyecto' => $proyecto->id,
                    'objeto' => 'Ejecución de obra civil para ' . $proyecto->nombre,
                    'contratante' => 'Municipio',
                    'contratista' => 'Constructora Demo S.A.S.',
                    'valor' => (float) $proyecto->presupuesto,
                    'fecha_inicio' => now()->subMonths(4)->format('Y-m-d'),
                    'fecha_fin' => now()->addMonths(6)->format('Y-m-d'),
                    'interventoria' => 'Interventoría Técnica Ltda.',
                    'avance_fisico' => rand(30, 90),
                    'avance_financiero' => rand(30, 90),
                    'estado' => 'En ejecución',
                    'anticipo' => true,
                ]
            );

            PresupuestoProyecto::updateOrCreate(
                ['proyecto' => $proyecto->id, 'componente' => 'Interventoría'],
                ['valor' => round(((float) $proyecto->presupuesto) * 0.08, 2)]
            );
        }
    }

    private function seedEventos(array $proyectos): void
    {
        $prioridades = [
            'Alta' => Prioridad::updateOrCreate(['nombre' => 'Alta'], ['color' => '#e53935', 'activo' => true]),
            'Media' => Prioridad::updateOrCreate(['nombre' => 'Media'], ['color' => '#fbc02d', 'activo' => true]),
            'Baja' => Prioridad::updateOrCreate(['nombre' => 'Baja'], ['color' => '#43a047', 'activo' => true]),
        ];

        $tipos = [
            'Revisión' => TipoEvento::updateOrCreate(['nombre' => 'Revisión'], ['icono' => 'revision', 'activo' => true]),
            'Contrato' => TipoEvento::updateOrCreate(['nombre' => 'Contrato'], ['icono' => 'contrato', 'activo' => true]),
            'Inspección' => TipoEvento::updateOrCreate(['nombre' => 'Inspección'], ['icono' => 'inspeccion', 'activo' => true]),
            'Documentación' => TipoEvento::updateOrCreate(['nombre' => 'Documentación'], ['icono' => 'documentacion', 'activo' => true]),
        ];

        $responsables = [
            'Ana Gómez' => Responsable::updateOrCreate(['nombre' => 'Ana Gómez'], ['cargo' => 'Supervisora de obra', 'activo' => true]),
            'Carlos Ruiz' => Responsable::updateOrCreate(['nombre' => 'Carlos Ruiz'], ['cargo' => 'Interventor', 'activo' => true]),
            'Laura Pérez' => Responsable::updateOrCreate(['nombre' => 'Laura Pérez'], ['cargo' => 'Coordinadora jurídica', 'activo' => true]),
        ];

        // [índice en $proyectos, título, tipo, prioridad, días desde hoy (negativo = pasado), responsable, estado_evento]
        $definiciones = [
            [0, 'Revisión de avance físico', 'Revisión', 'Alta', 3, 'Ana Gómez', 'pendiente'],
            [2, 'Publicación de pliego de condiciones', 'Documentación', 'Alta', 5, 'Laura Pérez', 'pendiente'],
            [4, 'Inspección de obra en sitio', 'Inspección', 'Alta', 7, 'Carlos Ruiz', 'pendiente'],
            [6, 'Firma de contrato de interventoría', 'Contrato', 'Media', 10, 'Carlos Ruiz', 'pendiente'],
            [8, 'Revisión de informe mensual', 'Revisión', 'Media', 14, 'Ana Gómez', 'pendiente'],
            [10, 'Entrega de documentación técnica', 'Documentación', 'Baja', 20, 'Laura Pérez', 'pendiente'],
            [12, 'Auditoría de cierre', 'Inspección', 'Alta', -5, 'Carlos Ruiz', 'completado'],
            [1, 'Seguimiento a hallazgos', 'Revisión', 'Alta', 2, 'Ana Gómez', 'cancelado'],
        ];

        foreach ($definiciones as [$index, $titulo, $tipoNombre, $prioridadNombre, $dias, $responsableNombre, $estadoEvento]) {
            $proyecto = $proyectos[$index]['proyecto'];

            Evento::updateOrCreate(
                ['titulo' => $titulo, 'proyecto' => $proyecto->id],
                [
                    'descripcion' => 'Evento de demostración para ' . $proyecto->nombre . '.',
                    'fecha' => now()->addDays($dias)->format('Y-m-d'),
                    'tipo_eventos' => $tipos[$tipoNombre]->id,
                    'prioridad' => $prioridades[$prioridadNombre]->id,
                    'estado_evento' => $estadoEvento,
                    'responsable' => $responsables[$responsableNombre]->id,
                ]
            );
        }
    }
}
