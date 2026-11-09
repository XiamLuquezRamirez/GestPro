<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aplicar a un expediente la versión vigente de una de sus plantillas, con vista previa.
 *
 * Reglas:
 * - Solo en borrador, configurado o no. Radicado, el checklist está congelado.
 * - La plantilla debe estar en el expediente, activa y con una versión vigente distinta de la
 *   que usa el expediente. Se aplica la vigente, y solo si es la que se previsualizó.
 * - Los requisitos se reconocen por `clave`: las filas ancladas en la versión anterior de la
 *   plantilla, también las retiradas, pasan al requisito de la nueva con la misma clave, si
 *   existe. Así conservan su trabajo aunque no tengan código común o este haya cambiado.
 * - El trabajo sigue al ancla: si la fila también tenía un origen de otra plantilla por su
 *   código común y la versión nueva cambia ese código, la fila se va con su clave y para la
 *   otra plantilla nace una fila pendiente con el código anterior.
 * - Si al re-anclar dos filas quedan con la misma llave (un requisito sin código que toma uno
 *   que ya usa otra plantilla), decide la regla de sincronizar: conserva la llave la anclada en
 *   un origen, luego la que aplica, luego la que tiene trabajo (ProyectoRequisito::conTrabajo) y
 *   luego la de id menor; la otra queda retirada con su trabajo, si lo tiene. Así una fila
 *   vacía de otra plantilla no desplaza a la que ya tenía el trabajo y el avance no baja.
 * - Luego se recalcula como al configurar (ExpedienteService::sincronizar): las filas que
 *   siguen conservan cumplimiento, observación, soporte y responsable; las nuevas nacen
 *   pendientes (por definir si son condicionadas) y las que salen quedan con aplica = false y
 *   su trabajo. Los propios, las demás plantillas y la principal no cambian.
 * - Queda en el historial del expediente (version_aplicada).
 * - La vista previa ejecuta la misma aplicación en una transacción que siempre se revierte:
 *   muestra exactamente lo que haría aplicar. Por eso también bloquea el expediente (FOR
 *   UPDATE) mientras dura, aunque sea una lectura.
 */
final class VersionesExpediente
{
    private const OTRA_VERSION = 'La plantilla publicó otra versión mientras revisabas. Vuelve a abrir la vista previa.';

    // Filas por UPDATE del re-anclado: 3 parámetros por fila (WHEN, THEN e IN), 900 en total,
    // por debajo del límite de 999 de las versiones viejas de SQLite.
    private const LOTE_REANCLAR = 300;

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly PlantillasExpediente $plantillas,
        private readonly HistorialChecklist $historial,
    ) {
    }

    /**
     * Lo que cambiaría en el checklist al aplicar la versión vigente de la plantilla, sin cambiar nada.
     *
     * @return array{plantilla: array, version_actual: array, version_nueva: array, nuevos: list<array>,
     *               retirados: list<array>, cambian: list<array{antes: array, despues: array}>, siguen: int,
     *               progreso_antes: array, progreso_despues: array}
     */
    public function vistaPrevia(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): array
    {
        // Una instancia propia: tras revertir, el modelo de quien llama no queda con un estado que no existe.
        $copia = ProyectoExpediente::query()->findOrFail($expediente->id);

        DB::beginTransaction();
        try {
            $this->expedientes->bloquear($copia);
            [$actual, $nueva] = $this->versionesAplicables($copia, $plantilla);
            $antes = $this->foto($copia);
            $progresoAntes = $this->expedientes->progresoDe($copia);

            $this->aplicarEnTransaccion($copia, $plantilla, $nueva->id, null);

            $despues = $this->foto($copia);
            $progresoDespues = $this->expedientes->progresoDe($copia);
            $retirados = $antes->filter(fn (array $f, int $id) => $f['aplica'] && ! $despues[$id]['aplica'])->keys();
            // Todas las retiradas con trabajo, sin una lista de ids que podría pasar el límite de parámetros.
            $conTrabajo = $copia->requisitos()->where('aplica', false)->conTrabajo()->pluck('id')->flip();
        } finally {
            DB::rollBack();
        }

        $nuevos = [];
        $cambian = [];
        $siguen = 0;
        foreach ($despues->filter(fn (array $f) => $f['aplica']) as $id => $fila) {
            $previa = $antes->get($id);
            if (! $previa || ! $previa['aplica']) {
                // Una fila creada al aplicar no existe todavía: no tiene id. Una retirada que vuelve, sí.
                $nuevos[] = [...$fila['fila'], 'id' => $previa ? $id : null];
            } elseif ($previa['fila'] !== $fila['fila']) {
                $cambian[] = ['antes' => $previa['fila'], 'despues' => $fila['fila']];
            } else {
                $siguen++;
            }
        }

        return [
            'plantilla' => ['id' => $actual->plantilla->id, 'nombre' => $actual->plantilla->nombre],
            'version_actual' => ['id' => $actual->id, 'numero' => $actual->numero],
            'version_nueva' => [
                'id' => $nueva->id,
                'numero' => $nueva->numero,
                'descripcion' => $nueva->descripcion,
                'fecha_publicacion' => $nueva->fecha_publicacion?->toIso8601String(),
            ],
            'nuevos' => $nuevos,
            'retirados' => $retirados
                ->map(fn (int $id) => [...$antes[$id]['fila'], 'con_trabajo' => $conTrabajo->has($id)])
                ->values()->all(),
            'cambian' => $cambian,
            'siguen' => $siguen,
            'progreso_antes' => $progresoAntes,
            'progreso_despues' => $progresoDespues,
        ];
    }

    /** @param int $versionId la versión que se previsualizó: debe seguir siendo la vigente */
    public function aplicar(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, int $versionId, ?User $usuario): ProyectoExpediente
    {
        DB::transaction(fn () => $this->aplicarEnTransaccion($expediente, $plantilla, $versionId, $usuario));

        return $expediente;
    }

    /** Lo que hace aplicar(), dentro de una transacción ya abierta. */
    private function aplicarEnTransaccion(ProyectoExpediente $expediente, PlantillaChecklist $plantilla, int $versionId, ?User $usuario): void
    {
        // Todo se decide sobre la fila bloqueada, que es lo primero que se bloquea.
        $this->expedientes->bloquear($expediente);
        [$actual, $nueva] = $this->versionesAplicables($expediente, $plantilla);
        if ($nueva->id !== $versionId) {
            throw new ReglaNegocioException(self::OTRA_VERSION);
        }

        $this->reanclar($expediente, $actual, $nueva);
        // es_principal no cambia: solo la versión de esta plantilla.
        DB::table('expediente_plantillas')
            ->where('expediente_id', $expediente->id)
            ->where('catalogo_version_id', $actual->id)
            ->update(['catalogo_version_id' => $nueva->id]);
        $expediente->unsetRelation('versionesPlantilla');
        $this->expedientes->sincronizar($expediente);

        $this->historial->registrarExpediente(
            $expediente,
            'version_aplicada',
            $this->registrable($actual->plantilla, $actual),
            $this->registrable($actual->plantilla, $nueva),
            $usuario
        );
        $expediente->touch();
    }

    /**
     * La versión que usa el expediente para la plantilla y la que se le puede aplicar. Se llama
     * con el expediente bloqueado.
     *
     * @return array{CatalogoVersion, CatalogoVersion} la actual (con su plantilla) y la vigente
     */
    private function versionesAplicables(ProyectoExpediente $expediente, PlantillaChecklist $plantilla): array
    {
        $this->expedientes->exigirBorrador($expediente);

        $actual = $this->plantillas->versionesDe($expediente)->firstWhere('plantilla_id', $plantilla->id)
            ?? throw new ReglaNegocioException("La plantilla \"{$plantilla->nombre}\" no está en este expediente.");
        if (! $actual->plantilla->activo) {
            throw new ReglaNegocioException("La plantilla \"{$actual->plantilla->nombre}\" está inactiva: no puedes aplicarle una versión nueva.");
        }
        $nueva = $this->plantillas->versionesNuevas(collect([$actual]))[$actual->id]
            ?? throw new ReglaNegocioException("El expediente ya usa la versión vigente de \"{$actual->plantilla->nombre}\".");

        return [$actual, $nueva];
    }

    /**
     * Pasa las filas ancladas en la versión anterior al requisito de la nueva con la misma clave,
     * en una sentencia por lote. Las que no la encuentran conservan su ancla: el recálculo las
     * reconoce por su código común o las retira con su trabajo.
     *
     * No choca con el unique (expediente_id, requisito_catalogo_id): la clave es única en cada
     * versión, así que dos filas nunca van al mismo requisito, y los destinos son de otra versión
     * que los anclas actuales, así que un UPDATE que revisa fila a fila (MariaDB) tampoco ve un
     * duplicado de paso. Ninguna fila debería estar anclada ya en la versión nueva, porque la
     * versión de una plantilla en el expediente solo avanza (siempre entra la vigente, que es la
     * última publicada). Si datos anteriores lo hicieran, ese requisito no se usa como destino.
     */
    private function reanclar(ProyectoExpediente $expediente, CatalogoVersion $actual, CatalogoVersion $nueva): void
    {
        $porClave = RequisitoCatalogo::query()->where('catalogo_version_id', $nueva->id)->pluck('id', 'clave');
        $ocupados = $expediente->requisitos()
            ->whereIn('requisito_catalogo_id', RequisitoCatalogo::query()->select('id')->where('catalogo_version_id', $nueva->id))
            ->pluck('requisito_catalogo_id')->flip();
        $claves = $expediente->requisitos()
            ->join('requisitos_catalogo', 'requisitos_catalogo.id', '=', 'proyectos_requisitos.requisito_catalogo_id')
            ->where('requisitos_catalogo.catalogo_version_id', $actual->id)
            ->pluck('requisitos_catalogo.clave', 'proyectos_requisitos.id');

        $destinos = [];
        foreach ($claves as $fila => $clave) {
            $destino = $porClave->get($clave);
            if ($destino !== null && ! $ocupados->has($destino)) {
                $destinos[(int) $fila] = (int) $destino;
            }
        }

        $tabla = (new ProyectoRequisito())->getTable();
        foreach (array_chunk($destinos, self::LOTE_REANCLAR, true) as $lote) {
            $casos = implode(' ', array_fill(0, count($lote), 'WHEN ? THEN ?'));
            $ids = implode(', ', array_fill(0, count($lote), '?'));
            $pares = [];
            foreach ($lote as $fila => $destino) {
                array_push($pares, $fila, $destino);
            }
            DB::update(
                "UPDATE {$tabla} SET requisito_catalogo_id = CASE id {$casos} END WHERE id IN ({$ids})",
                [...$pares, ...array_keys($lote)]
            );
        }
    }

    /**
     * Cada fila del checklist, en su orden, con si aplica y lo que muestra.
     *
     * @return Collection<int, array{aplica: bool, fila: array}> id de la fila => datos
     */
    private function foto(ProyectoExpediente $expediente): Collection
    {
        $posiciones = $this->expedientes->posiciones($this->plantillas->versionesDe($expediente));

        return $expediente->requisitos()
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProyectoRequisito $f) => [$f->id => ['aplica' => $f->aplica, 'fila' => $this->resumen($f, $posiciones)]]);
    }

    /** Lo que muestra la fila, como presentarFila(): el requisito del primer origen o, sin orígenes, el ancla. */
    private function resumen(ProyectoRequisito $fila, array $posiciones): array
    {
        if ($fila->esPropio()) {
            // Como en la pantalla, un propio se muestra por su título y no tiene código.
            return ['id' => $fila->id, 'codigo' => null, 'texto' => $fila->titulo_propio, 'caracter' => $fila->caracter, 'titulo' => $fila->titulo_propio];
        }
        $requisito = $this->expedientes->requisitoMostrado($fila, $posiciones);

        return ['id' => $fila->id, 'codigo' => $requisito->codigo, 'texto' => $requisito->texto, 'caracter' => $fila->caracter];
    }

    /** @return array{plantilla_id: int, plantilla: string, version: int, catalogo_version_id: int} */
    private function registrable(PlantillaChecklist $plantilla, CatalogoVersion $version): array
    {
        return [
            'plantilla_id' => $plantilla->id,
            'plantilla' => $plantilla->nombre,
            'version' => $version->numero,
            'catalogo_version_id' => $version->id,
        ];
    }
}
