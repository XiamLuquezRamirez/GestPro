<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catálogo maestro de requisitos de viabilización SGR, versión 1.
 *
 * Fuente: database/seeders/data/requisitos_sgr_v1.json, extraído del prototipo
 * checklist-viabilizacion-sgr.html (207 requisitos: Orientaciones Transitorias +
 * Acuerdo 12 de 2024). Las condiciones A/B/D del prototipo eran funciones JS; en
 * el JSON ya están traducidas al DSL. En la sección C, el prototipo no filtra por
 * fase; el JSON agrega condición solo a las 12 filas que indican su fase en el
 * subtítulo, el texto o la nota. Las demás aplican en toda fase.
 *
 * Idempotente: los catálogos (sectores, tipos, componentes, tipologías) se
 * actualizan por `clave`; los requisitos de una versión existente NO se tocan,
 * porque las versiones son inmutables (de eso depende el snapshot de los
 * expedientes ya evaluados).
 */
class CatalogoRequisitosSgrSeeder extends Seeder
{
    private const ARCHIVO = 'seeders/data/requisitos_sgr_v1.json';
    private const VERSION = 1;

    public function run(): void
    {
        $datos = $this->leerDatos();

        DB::transaction(function () use ($datos) {
            $sectores = $this->sembrarPorClave('requisitos_sectores', $datos['sectores'], fn ($s) => [
                'nombre' => $s['nombre'],
                'fuente' => $s['fuente'],
                'orden' => $s['orden'],
            ]);

            $tipos = [];
            foreach ($datos['tipos'] as $t) {
                $sectorId = $sectores[$t['sector']] ?? throw new RuntimeException("Sector inexistente: {$t['sector']}");
                DB::table('requisitos_tipos')->updateOrInsert(
                    ['sector_id' => $sectorId, 'clave' => $t['clave']],
                    ['nombre' => $t['nombre'], 'orden' => $t['orden']]
                );
                $tipos[$t['sector'] . '::' . $t['clave']] = DB::table('requisitos_tipos')
                    ->where('sector_id', $sectorId)->where('clave', $t['clave'])->value('id');
            }

            $this->sembrarPorClave('requisitos_componentes', $datos['componentes'], fn ($c) => [
                'nombre' => $c['nombre'], 'orden' => $c['orden'],
            ]);
            $this->sembrarPorClave('requisitos_tipologias', $datos['tipologias'], fn ($c) => [
                'nombre' => $c['nombre'], 'orden' => $c['orden'],
            ]);

            if (DB::table('catalogo_versiones')->where('numero', self::VERSION)->exists()) {
                return; // versión ya sembrada: inmutable
            }

            $versionId = DB::table('catalogo_versiones')->insertGetId([
                'numero' => self::VERSION,
                'estado' => 'publicada',
                'descripcion' => 'Orientaciones Transitorias + Acuerdo 12 de 2024 (carga inicial desde el prototipo)',
                'fecha_publicacion' => now(),
                'vigente' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Solo puede haber una versión vigente.
            DB::table('catalogo_versiones')->where('id', '!=', $versionId)->update(['vigente' => false]);

            $filas = array_map(function ($r) use ($versionId, $sectores, $tipos) {
                $sectorId = $r['sector'] ? ($sectores[$r['sector']] ?? null) : null;
                $tipoId = ($r['sector'] && $r['tipo']) ? ($tipos[$r['sector'] . '::' . $r['tipo']] ?? null) : null;

                if ($r['sector'] && ! $sectorId) {
                    throw new RuntimeException("Requisito {$r['clave']}: sector {$r['sector']} inexistente");
                }
                if ($r['tipo'] && ! $tipoId) {
                    throw new RuntimeException("Requisito {$r['clave']}: tipo {$r['tipo']} inexistente");
                }

                return [
                    'catalogo_version_id' => $versionId,
                    'clave' => $r['clave'],
                    'seccion' => $r['seccion'],
                    'fuente' => $r['fuente'],
                    'sector_id' => $sectorId,
                    'tipo_id' => $tipoId,
                    'subtitulo' => $r['subtitulo'],
                    'codigo' => $r['codigo'],
                    'texto' => $r['texto'],
                    'nota' => $r['nota'],
                    'condicion' => $r['condicion'],
                    'orden' => $r['orden'],
                    'activo' => true,
                ];
            }, $datos['requisitos']);

            foreach (array_chunk($filas, 100) as $lote) {
                DB::table('requisitos_catalogo')->insert($lote);
            }
        });
    }

    private function leerDatos(): array
    {
        $ruta = database_path(self::ARCHIVO);
        $datos = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);

        foreach (['sectores', 'tipos', 'componentes', 'tipologias', 'requisitos'] as $clave) {
            if (empty($datos[$clave])) {
                throw new RuntimeException("El archivo {$ruta} no trae '{$clave}'");
            }
        }

        return $datos;
    }

    /** Inserta o actualiza por `clave` y devuelve el mapa clave => id. */
    private function sembrarPorClave(string $tabla, array $items, callable $valores): array
    {
        foreach ($items as $item) {
            DB::table($tabla)->updateOrInsert(['clave' => $item['clave']], $valores($item));
        }

        return DB::table($tabla)->pluck('id', 'clave')->all();
    }
}
