<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\Fase;
use App\Models\PlantillaChecklist;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
use App\Models\RequisitoSector;
use App\Models\RequisitoTipo;
use App\Models\RequisitoTipologia;
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida del expediente (la instancia por proyecto), que se arma con las plantillas del expediente.
 *
 * Reglas:
 * - Lleva una o varias plantillas, cada una con la versión con que entró (la vigente
 *   de ese momento) o la que se le aplicó después (VersionesExpediente). Sin plantillas
 *   en el pedido, nace con la plantilla SGR si el proyecto la admite, o sin plantillas.
 * - La fase SGR solo se exige si el expediente tiene una plantilla con motor sgr.
 * - Cada proyecto tiene como máximo un expediente en borrador.
 * - Nace sin configurar: solo se chequea o radica tras confirmar la configuración,
 *   porque ella decide qué requisitos aplican y, con eso, el porcentaje de avance.
 * - En borrador, cambiar la configuración recalcula el checklist: los requisitos
 *   que dejan de aplicar se marcan aplica=false (conservan cumplimiento,
 *   observación y soporte) y los que vuelven a aplicar se recuperan tal cual.
 * - Solo se radica con todos los requisitos que aplican cumplidos.
 * - Radicado, el expediente queda congelado: no se reconfigura ni se marca.
 */
final class ExpedienteService
{
    public const DISCO = 'local'; // storage/app/private: no accesible por URL pública

    private const ORDEN_SECCION = ['A' => 1, 'B' => 2, 'D' => 3, 'C' => 4];

    private const SIN_PLANTILLAS = 'Elige al menos una plantilla para armar el checklist.';

    // Avisos de textos parecidos que se devuelven, los más parecidos primero.
    private const MAX_AVISOS = 50;

    // Orden de una fila: posición de la plantilla del primer origen, sección (motor sgr) y orden
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
    private const ORDEN_POR_SECCION = 100_000;

    /** Del más al menos exigente: una fila con varios orígenes toma el primero que aparezca. */
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre',
        'requisito.version:id,plantilla_id', 'requisito.version.plantilla:id,nombre,motor',
        'origenes.sector:id,clave,nombre,fuente', 'origenes.tipo:id,clave,nombre',
        'origenes.version:id,plantilla_id', 'origenes.version.plantilla:id,nombre,motor',
        'usuarioCumplido:id,name', 'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];

    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
    ) {
    }

    // -------------------------------------------------------------- escritura

    /**
     * @param array{plantilla_ids?: list<int>, principal_id?: ?int, fase_sgr?: int} $datos sin
     *        plantilla_ids, se preselecciona la plantilla SGR compatible, si la hay
     */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProyectoExpediente
    {
        return $this->crearCon($proyecto, $datos, $usuario, $this->plantillas->idsCompatibles($proyecto));
    }

    /** @param list<int> $compatibles las plantillas que hoy se ofrecen al proyecto: se calculan una vez */
    private function crearCon(Proyecto $proyecto, array $datos, ?User $usuario, array $compatibles): ProyectoExpediente
    {
        $plantillas = $this->plantillas->validar(
            array_key_exists('plantilla_ids', $datos) ? $datos : $this->plantillas->preseleccion($proyecto, $compatibles),
            $proyecto,
            null,
            $compatibles
        );
        $this->exigirFaseSgr($datos, $plantillas['version_sgr_id'] !== null);
        $seleccion = $this->validarSeleccion($datos, null);

        return DB::transaction(function () use ($proyecto, $plantillas, $datos, $seleccion, $usuario) {
            // El bloqueo evita dos borradores por una doble petición simultánea.
            Proyecto::query()->whereKey($proyecto->id)->lockForUpdate()->first();
            $this->exigirSinBorrador($proyecto);

            // Sin fase en el pedido, la columna conserva su valor por defecto.
            $expediente = ProyectoExpediente::create([
                'proyecto_id' => $proyecto->id,
                'estado' => ProyectoExpediente::BORRADOR,
                'usuario_creo_id' => $usuario?->id,
                ...(isset($datos['fase_sgr']) ? ['fase_sgr' => (int) $datos['fase_sgr']] : []),
            ])->refresh();

            $this->plantillas->guardar($expediente, $plantillas);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], [
                'plantillas' => $this->plantillasRegistrables($this->plantillas->versionesDe($expediente)),
            ], $usuario);

            return $expediente;
        });
    }

    /**
     * Expediente que nace con el proyecto, si le corresponde. Con plantilla SGR compatible,
     * la preselecciona con la fase III y el sector sugerido por el proyecto; sin ella, nace
     * sin plantillas. Nunca interrumpe a quien guarda el proyecto: un fallo se registra y el
     * expediente se puede crear a mano.
     */
    public function crearAutomatico(Proyecto $proyecto, ?User $usuario): ?ProyectoExpediente
    {
        try {
            // Dentro del try: un fallo al consultarlo tampoco interrumpe el guardado.
            if (! $this->correspondeAutomatico($proyecto)) {
                return null;
            }
            $compatibles = $this->plantillas->idsCompatibles($proyecto);

            return $this->crearCon($proyecto, $this->seleccionAutomatica($proyecto, $compatibles), $usuario, $compatibles);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Proyecto en Formulación, con al menos una fuente definida y todavía sin expediente. */
    public function correspondeAutomatico(Proyecto $proyecto): bool
    {
        // "Por definir" no es una fuente definida, aunque quedaran fuentes de antes.
        return $this->esFormulacion($proyecto->fase)
            && ! $proyecto->financiacion_por_definir
            && $proyecto->fuentes()->exists()
            && ! $proyecto->expedientes()->exists();
    }

    /** @return array{plantilla_ids: list<int>, principal_id: ?int, fase_sgr?: int, sector_ids?: list<int>} */
    private function seleccionAutomatica(Proyecto $proyecto, array $compatibles): array
    {
        $seleccion = $this->plantillas->preseleccion($proyecto, $compatibles);
        if ($seleccion['plantilla_ids'] === []) {
            return $seleccion;
        }

        $proyecto->loadMissing('sectorRel');
        $sugerido = $proyecto->sectorRel?->requisitos_sector_id;

        return [...$seleccion, 'fase_sgr' => 3, 'sector_ids' => $sugerido ? [$sugerido] : []];
    }

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            // Todo se decide sobre la fila bloqueada: un PUT simultáneo que quite las
            // plantillas espera a que esto termine, y luego ya lo ve confirmado. Con el
            // bloqueo, esta es la única comprobación de "sin plantillas" que hace falta:
            // una lista no vacía que pasa validar() siempre deja alguna versión.
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);
            $sinPlantillas = array_key_exists('plantilla_ids', $datos)
                ? $datos['plantilla_ids'] === []
                : ! $expediente->versionesPlantilla()->exists();
            if ($sinPlantillas) {
                throw new ReglaNegocioException(self::SIN_PLANTILLAS);
            }

            $this->actualizarConfiguracion($expediente, $datos, $usuario);
            if (! $expediente->estaConfigurado()) {
                $expediente->update(['fecha_configuracion' => now()]);
                $this->historial->registrarExpediente($expediente, 'configuracion_confirmada', [], $this->configuracionRegistrable($expediente), $usuario);
            }

            return $expediente;
        });
    }

    /** Aviso, sin bloquear el cambio, si el proyecto deja Formulación con un borrador. */
    public function advertenciaAlSalirDeFormulacion(Proyecto $proyecto, mixed $faseAnterior): ?string
    {
        if (! $this->esFormulacion($faseAnterior) || $this->esFormulacion($proyecto->fase)) {
            return null;
        }

        $borrador = $proyecto->expedientes()->where('estado', ProyectoExpediente::BORRADOR)->latest('id')->first();

        return $borrador
            ? "El proyecto salió de Formulación con el expediente #{$borrador->id} sin radicar."
            : null;
    }

    /**
     * Sin plantilla_ids en el pedido, las plantillas del expediente no cambian. Mientras se
     * arma puede quedarse sin plantillas; ya confirmado, no.
     */
    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
            // Borrador, plantillas y "ya confirmado" se leen de la fila bloqueada.
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);
            $plantillas = array_key_exists('plantilla_ids', $datos)
                ? $this->plantillas->validar($datos, $expediente->proyecto, $expediente)
                : null;
            if ($plantillas && $plantillas['versiones'] === [] && $expediente->estaConfigurado()) {
                throw new ReglaNegocioException(self::SIN_PLANTILLAS);
            }
            $this->exigirFaseSgr($datos, $plantillas
                ? $plantillas['version_sgr_id'] !== null
                : $this->plantillas->tieneSgr($this->plantillas->versionesDe($expediente)));
            $seleccion = $this->validarSeleccion($datos, $expediente);

            $antes = $this->configuracionRegistrable($expediente);
            if (isset($datos['fase_sgr'])) {
                $expediente->update(['fase_sgr' => (int) $datos['fase_sgr']]);
            }
            if ($plantillas) {
                $this->plantillas->guardar($expediente, $plantillas);
            }
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $despues = $this->configuracionRegistrable($expediente);
            if ($expediente->estaConfigurado() && $antes !== $despues) {
                $this->historial->registrarExpediente($expediente, 'configuracion_cambiada', $antes, $despues, $usuario);
            }

            return $expediente;
        });
    }

    /** @param array{cumplido?: bool, observacion?: ?string, responsable_id?: ?int} $datos */
    public function marcar(ProyectoExpediente $expediente, ProyectoRequisito $fila, array $datos, ?User $usuario): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        DB::transaction(function () use ($expediente, $fila, $datos, $usuario) {
            // Primero el expediente (radicado entre medias, se rechaza), luego la fila: un
            // "no aplica" o una marca simultánea no puede quedar pisado por una lectura vieja.
            $this->bloquearChecklistEditable($expediente);
            ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            $fila->refresh();
            $this->exigirQueAplique($fila);
            $this->aplicarMarca($fila, $datos, $usuario);
            $this->registrarCambios($fila, $usuario);
            $fila->save();
            $expediente->touch();
        });

        return $fila;
    }

    /** Aplica a la fila lo pedido, sobre su estado actual. */
    private function aplicarMarca(ProyectoRequisito $fila, array $datos, ?User $usuario): void
    {
        if (array_key_exists('cumplido', $datos)) {
            $cumplido = (bool) $datos['cumplido'];
            if ($cumplido && $fila->estado_aplicabilidad !== ProyectoRequisito::APLICA) {
                throw new ReglaNegocioException('Este requisito está marcado como no aplica o por definir: resuelve eso antes de cumplirlo.');
            }
            if ($cumplido && ! $fila->cumplido) {
                $fila->fill(['cumplido' => true, 'fecha_cumplido' => now(), 'usuario_cumplido_id' => $usuario?->id]);
            } elseif (! $cumplido) {
                $fila->fill(['cumplido' => false, 'fecha_cumplido' => null, 'usuario_cumplido_id' => null]);
            }
        }
        if (array_key_exists('observacion', $datos)) {
            $fila->observacion = $datos['observacion'] !== null && trim($datos['observacion']) !== ''
                ? trim($datos['observacion'])
                : null;
        }
        if (array_key_exists('responsable_id', $datos)) {
            $fila->responsable_id = $this->responsableValido($datos['responsable_id'], $fila);
        }
    }

    public function guardarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila, UploadedFile $archivo, ?User $usuario = null): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        // Extensión deducida del contenido, no la que declara quien sube el archivo.
        $ruta = $archivo->storeAs("expedientes/{$expediente->id}", Str::uuid() . '.' . $archivo->extension(), self::DISCO);

        try {
            DB::transaction(function () use ($expediente, $fila, $archivo, $ruta, $usuario) {
                // Expediente y luego fila: con dos subidas a la vez, la segunda ve el soporte de la primera.
                $this->bloquearChecklistEditable($expediente);
                $bloqueada = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
                $this->exigirQueAplique($bloqueada);
                $anterior =$bloqueada->only(['soporte_archivo', 'soporte_nombre']);
                $bloqueada->update(['soporte_archivo' => $ruta, 'soporte_nombre' => $archivo->getClientOriginalName()]);
                $fila->refresh();
                // El anterior no se borra: queda en el historial y se puede descargar.
                $this->historial->registrarFila(
                    $fila,
                    $anterior['soporte_archivo'] ? 'soporte_reemplazado' : 'soporte_subido',
                    $anterior['soporte_archivo'] ? $anterior : [],
                    ['soporte_nombre' => $fila->soporte_nombre],
                    $usuario
                );
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISCO)->delete($ruta);
            throw $e;
        }

        return $fila;
    }

    public function eliminarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $fila, ?User $usuario = null): ProyectoRequisito
    {
        $this->exigirEditable($expediente, $fila);

        if (! $fila->soporte_archivo) {
            return $fila;
        }

        DB::transaction(function () use ($expediente, $fila, $usuario) {
            // Expediente y luego fila: si otra petición ya quitó el soporte, no hay nada que registrar.
            $this->bloquearChecklistEditable($expediente);
            $bloqueada = ProyectoRequisito::query()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
            $this->exigirQueAplique($bloqueada);
            $antes = $bloqueada->only(['soporte_archivo', 'soporte_nombre']);
            if (! $antes['soporte_archivo']) {
                return;
            }
            $fila->update(['soporte_archivo' => null, 'soporte_nombre' => null]);
            // El archivo no se borra: queda en el historial y se puede descargar.
            $this->historial->registrarFila($fila, 'soporte_eliminado', $antes, [], $usuario);
        });
        $fila->refresh();

        return $fila;
    }

    public function radicar(ProyectoExpediente $expediente, ?User $usuario): ProyectoExpediente
    {
        DB::transaction(function () use ($expediente, $usuario) {
            // Con la fila bloqueada, un propio que se agrega a la vez espera o ya cuenta.
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);
            $this->exigirConfigurado($expediente);

            ['total' => $total, 'cumplidos' => $cumplidos] = $this->progresoDe($expediente);
            if ($total === 0) {
                throw new ReglaNegocioException('No se puede radicar: el expediente no tiene requisitos aplicables.');
            }
            if ($cumplidos < $total) {
                $pendientes = $total - $cumplidos;
                $verbo = $pendientes === 1 ? 'falta' : 'faltan';
                throw new ReglaNegocioException("No se puede radicar: {$verbo} {$pendientes} de {$total} requisitos por cumplir.");
            }

            $expediente->update([
                'estado' => ProyectoExpediente::RADICADO,
                'fecha_radicado' => now(),
                'usuario_radicado_id' => $usuario?->id,
            ]);
            $this->historial->registrarExpediente($expediente, 'expediente_radicado', [], ['requisitos' => $total], $usuario);
        });

        return $expediente;
    }

    public function eliminar(ProyectoExpediente $expediente, ?User $usuario = null): void
    {
        $this->exigirBorrador($expediente);

        $archivos = DB::transaction(function () use ($expediente, $usuario) {
            // Sobre la fila bloqueada: un expediente radicado entre medias no se borra con sus archivos.
            $this->bloquear($expediente);
            $this->exigirBorrador($expediente);
            $archivos = [
                ...$expediente->requisitos()->whereNotNull('soporte_archivo')->pluck('soporte_archivo')->all(),
                ...$this->historial->archivosDe($expediente),
            ];
            // El registro sobrevive al expediente; los archivos no, como avisa la pantalla.
            $this->historial->registrarExpediente($expediente, 'expediente_eliminado', ['proyecto_id' => $expediente->proyecto_id], [], $usuario);
            $expediente->delete(); // pivotes y checklist caen en cascada

            return $archivos;
        });
        // Después del commit: si la transacción falla, los archivos siguen.
        Storage::disk(self::DISCO)->delete($archivos);
    }

    // ---------------------------------------------------------- recálculo

    /**
     * Alinea el checklist con las plantillas y la configuración actuales del expediente.
     *
     * - Recorre las versiones del expediente, la principal primero y luego por plantilla.
     *   Motor sgr: aplican los que decide el evaluador. Motor simple: todos los activos.
     * - Cada llave (el código común del requisito, o req:{id} si no tiene) es una sola fila.
     *   Sus orígenes son los requisitos que aplican con esa llave, de todas las plantillas.
     * - La fila la crea el primer origen (su ancla, que no cambia) y se reconoce por la llave
     *   de su ancla. Sin orígenes queda aplica = false y conserva su trabajo; al volver a
     *   tenerlos se recupera tal cual. Si datos anteriores dejaron dos filas con una llave, la
     *   conserva la anclada en un origen calculado, luego la que aplica y luego la de id menor.
     * - Carácter: el más exigente de los orígenes. Una fila nueva condicionada nace por definir;
     *   una existente no cambia de estado por recalcular.
     * - Orden: el del primer origen, desplazado por la posición de su plantilla.
     * - Los requisitos propios no se tocan.
     * - Corre en su propia transacción (un savepoint si ya hay una) y con la fila del
     *   expediente bloqueada: dos recálculos simultáneos no eligen anclas distintas para una llave.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        return DB::transaction(function () use ($expediente) {
            $this->bloquear($expediente);

            return $this->recalcular($expediente);
        });
    }

    /** @return array{aplican:int, retenidos:int} */
    private function recalcular(ProyectoExpediente $expediente): array
    {
        $llaves = $this->origenesPorLlave($expediente);

        $existentes = [];
        $sinLlave = [];
        $filas = $expediente->requisitos()->whereNotNull('requisito_catalogo_id')
            ->with('requisito:id,codigo_comun')->orderBy('id')->get();
        $grupos = $filas->groupBy(fn (ProyectoRequisito $f) => $this->llave($f->requisito));
        // Solo si hay empates: una consulta para todo el expediente, con el criterio de conTrabajo.
        $conTrabajo = $grupos->contains(fn (Collection $grupo) => $grupo->count() > 1)
            ? $expediente->requisitos()->whereNotNull('requisito_catalogo_id')->conTrabajo()->pluck('id')->flip()
            : collect();
        foreach ($grupos as $llave => $grupo) {
            // Dos filas con la misma llave vienen de datos anteriores a esta regla o de una
            // versión nueva que da a un requisito el código común que ya usa otra fila
            // (VersionesExpediente). Conserva la llave la anclada en un origen calculado; luego
            // la que aplica; luego la que tiene trabajo, para no retirarlo en favor de una fila
            // vacía; luego la de id menor. Las demás quedan sin orígenes.
            $origenes = array_flip(array_map(fn (RequisitoCatalogo $r) => $r->id, $llaves[$llave]['origenes'] ?? []));
            $ordenadas = $grupo->sortBy(fn (ProyectoRequisito $f) => [
                isset($origenes[$f->requisito_catalogo_id]) ? 0 : 1,
                $f->aplica ? 0 : 1,
                $conTrabajo->has($f->id) ? 0 : 1,
                $f->id,
            ])->values();
            $existentes[$llave] = $ordenadas->first();
            array_push($sinLlave, ...$ordenadas->slice(1)->all());
        }

        $deseados = [];
        foreach ($llaves as $llave => $datos) {
            $cambios = ['aplica' => true, 'caracter' => $datos['caracter'], 'orden' => $datos['orden']];
            $fila = $existentes[$llave] ?? null;
            unset($existentes[$llave]);

            if ($fila) {
                $fila->fill($cambios);
                if ($fila->isDirty()) {
                    $fila->save();
                }
            } else {
                $ancla = $datos['origenes'][0];
                $fila = $expediente->requisitos()->create([
                    ...$cambios,
                    'requisito_catalogo_id' => $ancla->id,
                    'estado_aplicabilidad' => $datos['caracter'] === RequisitoCatalogo::CONDICIONADO
                        ? ProyectoRequisito::POR_DEFINIR
                        : ProyectoRequisito::APLICA,
                ]);
            }
            $deseados[$fila->id] = array_map(fn (RequisitoCatalogo $r) => $r->id, $datos['origenes']);
        }

        // Sin orígenes: la fila deja de aplicar y conserva cumplimiento, observación y soporte.
        // Una sola sentencia por lote, no una por fila.
        $retirar = collect([...array_values($existentes), ...$sinLlave])
            ->filter(fn (ProyectoRequisito $f) => $f->aplica)->pluck('id')->all();
        foreach (array_chunk($retirar, 1000) as $lote) {
            ProyectoRequisito::query()->whereIn('id', $lote)->update(['aplica' => false]);
        }

        $this->guardarOrigenes($expediente, $deseados);

        return [
            'aplican' => count($llaves),
            'retenidos' => $expediente->requisitos()->where('aplica', false)->count(),
        ];
    }

    /**
     * Requisitos que aplican en cada plantilla del expediente, agrupados por llave en el
     * orden del checklist. Cada llave trae sus orígenes (el primero es el ancla de una fila
     * nueva), el carácter más exigente y el orden que da el primer origen.
     *
     * @return array<string, array{origenes: list<RequisitoCatalogo>, caracter: string, orden: int}>
     */
    private function origenesPorLlave(ProyectoExpediente $expediente): array
    {
        $llaves = [];
        $cfg = null;

        foreach ($this->plantillas->versionesDe($expediente)->values() as $posicion => $version) {
            $esSgr = $version->plantilla->motor === PlantillaChecklist::MOTOR_SGR;
            $requisitos = RequisitoCatalogo::query()
                ->where('catalogo_version_id', $version->id)
                ->where('activo', true)
                ->when($esSgr, fn ($q) => $q->with(['sector:id,clave', 'tipo:id,clave']))
                ->orderBy('orden')
                ->orderBy('id')
                ->get();

            if ($esSgr) {
                $cfg ??= $this->configuracionDe($expediente);
                $aplican = array_flip($this->evaluador->aplicables(
                    $requisitos->map(fn (RequisitoCatalogo $r) => [
                        'clave' => $r->clave,
                        'seccion' => $r->seccion,
                        'condicion' => $r->condicion,
                        'sector' => $r->sector?->clave,
                        'tipo' => $r->tipo?->clave,
                    ]),
                    $cfg
                ));
                $requisitos = $requisitos->filter(fn (RequisitoCatalogo $r) => isset($aplican[$r->clave]));
            }

            foreach ($requisitos as $requisito) {
                $llave = $this->llave($requisito);
                if (! isset($llaves[$llave])) {
                    $llaves[$llave] = [
                        'origenes' => [],
                        'caracter' => $requisito->caracter,
                        'orden' => $posicion * self::ORDEN_POR_PLANTILLA
                            + ($esSgr ? self::ORDEN_SECCION[$requisito->seccion] * self::ORDEN_POR_SECCION : 0)
                            + $requisito->orden,
                    ];
                }
                $llaves[$llave]['origenes'][] = $requisito;
                $llaves[$llave]['caracter'] = $this->masExigente($llaves[$llave]['caracter'], $requisito->caracter);
            }
        }

        return $llaves;
    }

    /** Llave de fila: el código común; sin él, el propio requisito. */
    private function llave(RequisitoCatalogo $requisito): string
    {
        return $requisito->codigo_comun ?? "req:{$requisito->id}";
    }

    private function masExigente(string $a, string $b): string
    {
        $rango = fn (string $c) => array_search($c, self::CARACTER_POR_EXIGENCIA, true);

        return $rango($b) < $rango($a) ? $b : $a;
    }

    /**
     * Deja en proyecto_requisito_origenes exactamente los orígenes calculados: las filas que
     * no están en $deseados se quedan sin ninguno. Los propios nunca tienen orígenes.
     *
     * @param array<int, list<int>> $deseados fila => requisitos de catálogo
     */
    private function guardarOrigenes(ProyectoExpediente $expediente, array $deseados): void
    {
        $actuales = DB::table('proyecto_requisito_origenes')
            ->whereIn('proyecto_requisito_id', $expediente->requisitos()->select('id'))
            ->get()
            ->groupBy('proyecto_requisito_id')
            ->map(fn ($origenes) => $origenes->pluck('requisito_catalogo_id')->map(fn ($id) => (int) $id)->all());

        $nuevos = [];
        foreach ($deseados as $fila => $requisitos) {
            foreach (array_diff($requisitos, $actuales->get($fila, [])) as $requisito) {
                $nuevos[] = ['proyecto_requisito_id' => $fila, 'requisito_catalogo_id' => $requisito];
            }
        }
        // Las filas que se quedan sin ninguno se limpian juntas; las que conservan alguno, una a una.
        $sinOrigenes = array_keys(array_diff_key($actuales->all(), $deseados));
        foreach (array_chunk($sinOrigenes, 1000) as $lote) {
            DB::table('proyecto_requisito_origenes')->whereIn('proyecto_requisito_id', $lote)->delete();
        }
        foreach ($actuales as $fila => $requisitos) {
            $sobran = isset($deseados[$fila]) ? array_diff($requisitos, $deseados[$fila]) : [];
            if ($sobran) {
                DB::table('proyecto_requisito_origenes')
                    ->where('proyecto_requisito_id', $fila)
                    ->whereIn('requisito_catalogo_id', $sobran)
                    ->delete();
            }
        }
        foreach (array_chunk($nuevos, 200) as $lote) {
            DB::table('proyecto_requisito_origenes')->insert($lote);
        }
    }

    public function configuracionDe(ProyectoExpediente $expediente): ConfiguracionExpediente
    {
        $expediente->loadMissing(['sectores:id,clave', 'tipos.sector:id,clave', 'componentes:id,clave', 'tipologias:id,clave']);

        return new ConfiguracionExpediente(
            $expediente->fase_sgr,
            $expediente->sectores->pluck('clave')->all(),
            $expediente->tipos->map(fn (RequisitoTipo $t) => $t->sector->clave . '::' . $t->clave)->all(),
            $expediente->componentes->pluck('clave')->all(),
            $expediente->tipologias->pluck('clave')->all(),
        );
    }

    // ------------------------------------------------------------- lectura

    public function presentar(ProyectoExpediente $expediente): array
    {
        $expediente->load([
            'proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);
        $versiones = $this->plantillas->versionesDe($expediente);
        $posiciones = $this->posiciones($versiones);
        $avisos = ['avisos' => [], 'total' => 0];

        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(self::RELACIONES_FILA)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        if ($versiones->count() > 1) {
            $avisos = $this->avisosDuplicados($filas, $posiciones);
        }

        return [
            ...$this->presentarCabecera($expediente, $versiones),
            'configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ],
            'progreso' => $this->progresoDe($expediente),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f, $posiciones))->values(),
            // Los más parecidos, hasta MAX_AVISOS; el total dice cuántos hay en realidad.
            'avisos_duplicados' => $avisos['avisos'],
            'avisos_duplicados_total' => $avisos['total'],
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)->conTrabajo()->count(),
        ];
    }

    /**
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance, sus
     * plantillas y los responsables con requisitos pendientes. Son cuatro consultas sin
     * importar cuántos proyectos haya: cantidades, plantillas, responsables y expedientes
     * con su avance.
     */
    public function resumenPorProyecto(): array
    {
        $cantidades = ProyectoExpediente::query()
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');

        // Mismo orden que versionesDe(): la principal primero y luego por plantilla.
        $plantillas = DB::table('expediente_plantillas')
            ->join('catalogo_versiones', 'catalogo_versiones.id', '=', 'expediente_plantillas.catalogo_version_id')
            ->join('plantillas_checklist', 'plantillas_checklist.id', '=', 'catalogo_versiones.plantilla_id')
            ->whereIn('expediente_plantillas.expediente_id', $cantidades->keys())
            ->orderByDesc('expediente_plantillas.es_principal')
            ->orderBy('plantillas_checklist.id')
            ->get(['expediente_plantillas.expediente_id', 'plantillas_checklist.nombre', 'plantillas_checklist.motor'])
            ->groupBy('expediente_id');

        // Lo que falta a cada responsable, con la misma definición del avance: suma lo que el
        // expediente tiene pendiente con responsable asignado.
        $responsables = ProyectoRequisito::query()
            ->pendienteQueCuenta()
            ->join('responsable', 'responsable.id', '=', 'proyectos_requisitos.responsable_id')
            ->whereIn('proyectos_requisitos.expediente_id', $cantidades->keys())
            ->groupBy('proyectos_requisitos.expediente_id', 'responsable.id', 'responsable.nombre')
            ->orderBy('responsable.nombre')
            ->orderBy('responsable.id')
            ->toBase()
            ->get(['proyectos_requisitos.expediente_id', 'responsable.id', 'responsable.nombre', DB::raw('COUNT(*) AS pendientes')])
            ->groupBy('expediente_id');

        return ProyectoExpediente::query()
            ->whereIn('id', $cantidades->keys())
            ->withCount([
                'requisitos as requisitos_aplican' => fn ($q) => $q->cuenta(),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->cumplidaQueCuenta(),
            ])
            ->orderBy('proyecto_id')
            ->get()
            ->map(fn (ProyectoExpediente $e) => [
                'proyecto_id' => $e->proyecto_id,
                'cantidad_expedientes' => (int) $cantidades[$e->id],
                'expediente_id' => $e->id,
                'estado' => $e->estado,
                'fase_sgr' => $e->fase_sgr,
                'configurado' => $e->estaConfigurado(),
                'plantillas' => $plantillas->get($e->id, collect())->pluck('nombre')->values()->all(),
                'tiene_sgr' => $plantillas->get($e->id, collect())->contains('motor', PlantillaChecklist::MOTOR_SGR),
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
                'responsables' => $responsables->get($e->id, collect())->map(fn (object $r) => [
                    'id' => (int) $r->id,
                    'nombre' => (string) $r->nombre,
                    'pendientes' => (int) $r->pendientes,
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Expedientes del proyecto, el más reciente primero, con su cabecera y su avance. Las
     * consultas no crecen con los expedientes: todo se lee junto y las plantillas compatibles
     * se calculan una vez por proyecto.
     */
    public function presentarDeProyecto(Proyecto $proyecto): array
    {
        $expedientes = $proyecto->expedientes()
            ->with(['versionesPlantilla.plantilla', 'usuarioRadico:id,name', 'usuarioCreo:id,name'])
            ->withCount([
                'requisitos as requisitos_aplican' => fn ($q) => $q->cuenta(),
                'requisitos as requisitos_cumplidos' => fn ($q) => $q->cumplidaQueCuenta(),
            ])
            ->orderByDesc('id')
            ->get();
        $versiones = $expedientes->flatMap(fn (ProyectoExpediente $e) => $e->versionesPlantilla);
        $compatibles = $versiones->isNotEmpty() ? $this->plantillas->idsCompatibles($proyecto) : [];
        $nuevas = $this->plantillas->versionesNuevas($versiones);

        return $expedientes->map(fn (ProyectoExpediente $e) => [
            ...$this->presentarCabecera($e->setRelation('proyecto', $proyecto), $this->plantillas->ordenar($e->versionesPlantilla), $compatibles, $nuevas),
            'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
        ])->values()->all();
    }

    /**
     * @param ?Collection<int, CatalogoVersion> $versiones las de versionesDe(), si ya se leyeron
     * @param ?list<int> $compatibles las de idsCompatibles() del proyecto, si ya se calcularon
     * @param ?array<int, CatalogoVersion> $nuevas las de PlantillasExpediente::versionesNuevas(), si ya se calcularon
     */
    public function presentarCabecera(ProyectoExpediente $expediente, ?Collection $versiones = null, ?array $compatibles = null, ?array $nuevas = null): array
    {
        $expediente->loadMissing(['proyecto:id,nombre,sector', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);
        $versiones ??= $this->plantillas->versionesDe($expediente);
        $compatibles ??= $versiones->isEmpty() ? [] : $this->plantillas->idsCompatibles($expediente->proyecto);
        $nuevas ??= $this->plantillas->versionesNuevas($versiones);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'plantillas' => $versiones->map(fn (CatalogoVersion $v) => [
                'plantilla_id' => $v->plantilla_id,
                'nombre' => $v->plantilla->nombre,
                'motor' => $v->plantilla->motor,
                'es_principal' => (bool) $v->pivot->es_principal,
                // Cambiar las fuentes o el sector del proyecto no altera el expediente: solo se avisa.
                'compatible' => in_array($v->plantilla_id, $compatibles, true),
                'version' => ['id' => $v->id, 'numero' => $v->numero],
                // Solo se ofrece: el expediente la aplica de forma explícita (VersionesExpediente).
                'version_nueva' => isset($nuevas[$v->id]) ? ['id' => $nuevas[$v->id]->id, 'numero' => $nuevas[$v->id]->numero] : null,
            ])->values()->all(),
            'tiene_sgr' => $this->plantillas->tieneSgr($versiones),
            'fase_sgr' => $expediente->fase_sgr,
            'configurado' => $expediente->estaConfigurado(),
            'estado' => $expediente->estado,
            'fecha_radicado' => $expediente->fecha_radicado?->toIso8601String(),
            'usuario_radico' => $expediente->usuarioRadico?->name,
            'usuario_creo' => $expediente->usuarioCreo?->name,
            'creado' => $expediente->created_at?->toIso8601String(),
            'actualizado' => $expediente->updated_at?->toIso8601String(),
        ];
    }

    public function progresoDe(ProyectoExpediente $expediente): array
    {
        return $this->progreso(
            $expediente->requisitos()->cuenta()->count(),
            $expediente->requisitos()->cumplidaQueCuenta()->count()
        );
    }

    /**
     * Se muestra el requisito del primer origen (la principal primero); sin orígenes, el ancla.
     * Un requisito propio no tiene requisito de catálogo: trae sus textos en `propio`.
     *
     * @param ?array<int, int> $posiciones plantilla_id => posición en el expediente, si ya se leyeron
     */
    public function presentarFila(ProyectoRequisito $fila, ?array $posiciones = null): array
    {
        $fila->loadMissing(self::RELACIONES_FILA);
        $posiciones ??= $this->posiciones($this->plantillas->versionesDe($fila->expediente));
        $origenes = $this->origenesOrdenados($fila, $posiciones);
        $r = $origenes->first() ?? $fila->requisito;
        $plantilla = $r?->version->plantilla;

        return [
            'id' => $fila->id,
            'estado' => $fila->estadoVisible(),
            'caracter' => $fila->caracter,
            'cuenta' => $fila->cuenta(),
            // Solo mientras hay un "no aplica" marcado, pendiente o aprobado; nunca en por definir.
            'no_aplica' => ! in_array($fila->estado_aplicabilidad, [ProyectoRequisito::NO_APLICA_PENDIENTE, ProyectoRequisito::NO_APLICA], true) ? null : [
                'justificacion' => $fila->justificacion_no_aplica,
                'usuario_id' => $fila->usuario_no_aplica_id,
                'usuario' => $fila->usuarioNoAplica?->name,
                'aprobado_por' => $fila->usuarioAprobo?->name,
                'fecha_aprobacion' => $fila->fecha_aprobacion?->toIso8601String(),
            ],
            'responsable' => $fila->responsable
                ? ['id' => $fila->responsable->id, 'nombre' => $fila->responsable->nombre, 'cargo' => $fila->responsable->cargo]
                : null,
            'cumplido' => $fila->cumplido,
            'fecha_cumplido' => $fila->fecha_cumplido?->toIso8601String(),
            'usuario_cumplido' => $fila->usuarioCumplido?->name,
            'observacion' => $fila->observacion,
            'soporte' => $fila->soporte_archivo ? ['nombre' => $fila->soporte_nombre] : null,
            // La de la fila, que reúne todos sus orígenes; la de `requisito` es solo la del mostrado.
            'requiere_aprobacion_no_aplica' => $fila->requiereAprobacionNoAplica(),
            'requisito' => $r ? [
                'id' => $r->id,
                'clave' => $r->clave,
                'seccion' => $r->seccion,
                'fuente' => $r->fuente,
                'sector' => $r->sector ? ['id' => $r->sector->id, 'clave' => $r->sector->clave, 'nombre' => $r->sector->nombre, 'fuente' => $r->sector->fuente] : null,
                'tipo' => $r->tipo ? ['id' => $r->tipo->id, 'clave' => $r->tipo->clave, 'nombre' => $r->tipo->nombre] : null,
                'subtitulo' => $r->subtitulo,
                'codigo' => $r->codigo,
                'texto' => $r->texto,
                'nota' => $r->nota,
                'descripcion' => $r->descripcion,
                // En palabras, cuándo aplica un condicionado: lo lee quien decide si aplica.
                'condicion_descripcion' => $r->condicion_descripcion,
                'soporte_requerido' => $r->soporte_requerido,
                'cargo_cumplimiento' => $r->cargo_cumplimiento,
                'cargo_revision' => $r->cargo_revision,
                'requiere_aprobacion_no_aplica' => $r->requiere_aprobacion_no_aplica,
            ] : null,
            'plantilla' => $plantilla ? ['id' => $plantilla->id, 'nombre' => $plantilla->nombre, 'motor' => $plantilla->motor] : null,
            'origenes' => $origenes->map(fn (RequisitoCatalogo $o) => [
                'plantilla_id' => $o->version->plantilla_id,
                'nombre' => $o->version->plantilla->nombre,
            ])->values()->all(),
            'propio' => $fila->esPropio() ? [
                'titulo' => $fila->titulo_propio,
                'descripcion' => $fila->descripcion_propia,
                'categoria' => $fila->categoria_propia,
            ] : null,
            'categoria' => $fila->esPropio() ? $fila->categoria_propia : $r->categoria,
        ];
    }

    /**
     * Una página del historial completo del expediente (ver HistorialChecklist::paginaDeExpediente()).
     * Cada entrada de una fila que todavía existe trae en `requisito` lo que muestra la fila; si
     * la fila ya no existe, null (un propio eliminado guarda su título en `antes`).
     *
     * @return array{entradas: list<array>, pagina: int, por_pagina: int, total: int}
     */
    public function historialDe(ProyectoExpediente $expediente, int $pagina): array
    {
        $historial = $this->historial->paginaDeExpediente($expediente, $pagina);
        $deFila = fn (array $entrada) => $entrada['entidad'] === HistorialChecklist::REQUISITO;
        $filas = $this->resumenFilasHistorial($expediente, collect($historial['entradas'])->filter($deFila)->pluck('entidad_id'));

        return [
            // La fila se identifica por `requisito`; el id de la entidad no sale.
            'entradas' => array_map(fn (array $entrada) => [
                ...Arr::except($entrada, 'entidad_id'),
                'requisito' => $deFila($entrada) ? ($filas[$entrada['entidad_id']] ?? null) : null,
            ], $historial['entradas']),
            'pagina' => $pagina,
            'por_pagina' => $historial['por_pagina'],
            'total' => $historial['total'],
        ];
    }

    /**
     * Resumen de las filas dadas que todavía existen, leídas juntas: el requisito que muestra la
     * fila, como presentarFila(), o el título de un propio, sin código ni texto.
     *
     * @param Collection<int, int> $ids
     * @return array<int, array{id: int, codigo: ?string, texto: ?string, titulo: ?string}> id de la fila => resumen
     */
    private function resumenFilasHistorial(ProyectoExpediente $expediente, Collection $ids): array
    {
        $filas = $ids->isEmpty() ? collect() : $expediente->requisitos()
            ->whereIn('id', $ids->unique()->values())
            ->with(['requisito', 'origenes.version:id,plantilla_id'])
            ->get();
        if ($filas->isEmpty()) {
            return [];
        }
        $posiciones = $this->posiciones($this->plantillas->versionesDe($expediente));

        return $filas->mapWithKeys(function (ProyectoRequisito $fila) use ($posiciones) {
            $mostrado = $this->requisitoMostrado($fila, $posiciones);

            return [$fila->id => [
                'id' => $fila->id,
                'codigo' => $mostrado?->codigo,
                'texto' => $mostrado?->texto,
                'titulo' => $fila->esPropio() ? $fila->titulo_propio : null,
            ]];
        })->all();
    }

    /**
     * @param Collection<int, CatalogoVersion> $versiones las de versionesDe()
     * @return array<int, int> plantilla_id => posición (0 es la principal)
     */
    public function posiciones(Collection $versiones): array
    {
        return $versiones->values()->pluck('plantilla_id')->flip()->map(fn ($posicion) => (int) $posicion)->all();
    }

    /**
     * Requisito que muestra la fila, el mismo de presentarFila(): el del primer origen y, sin
     * orígenes, el ancla. Null en un propio. Espera cargados `requisito` y `origenes.version`.
     *
     * @param array<int, int> $posiciones las de posiciones()
     */
    public function requisitoMostrado(ProyectoRequisito $fila, array $posiciones): ?RequisitoCatalogo
    {
        return $this->origenesOrdenados($fila, $posiciones)->first() ?? $fila->requisito;
    }

    /** Orígenes de la fila en el orden de las plantillas del expediente, la principal primero. */
    private function origenesOrdenados(ProyectoRequisito $fila, array $posiciones): Collection
    {
        return $fila->origenes
            ->sortBy(fn (RequisitoCatalogo $o) => [$posiciones[$o->version->plantilla_id] ?? PHP_INT_MAX, $o->id])
            ->values();
    }

    /**
     * Pares de filas de plantilla, de plantillas distintas y sin llave compartida, cuyos textos
     * se parecen: Jaccard de sus palabras normalizadas de 0,8 o más. Solo es un aviso: nunca
     * se fusionan. Las filas comparten plantilla si alguna de sus plantillas de origen coincide.
     *
     * - Entran las filas que aplican y siguen en juego: pendientes, cumplidas, por definir o
     *   con un "no aplica" por aprobar. Un "no aplica" ya decidido no avisa; un propio tampoco.
     * - Devuelve los MAX_AVISOS más parecidos, de mayor a menor similitud (a igual similitud,
     *   en el orden del checklist), y el total de pares encontrados.
     *
     * @return array{avisos: list<array{filas: array{int, int}, textos: array{string, string}, plantillas: array{string, string}, similitud: float}>, total: int}
     */
    private function avisosDuplicados(Collection $filas, array $posiciones): array
    {
        $candidatas = $filas
            ->reject(fn (ProyectoRequisito $f) => $f->esPropio() || $f->estado_aplicabilidad === ProyectoRequisito::NO_APLICA)
            ->map(function (ProyectoRequisito $f) use ($posiciones) {
                $origenes = $this->origenesOrdenados($f, $posiciones);
                $mostrado = $origenes->first() ?? $f->requisito;

                return [
                    'id' => $f->id,
                    'texto' => $mostrado->texto,
                    'plantilla' => $mostrado->version->plantilla->nombre,
                    'plantillas' => $origenes->map(fn (RequisitoCatalogo $o) => $o->version->plantilla_id)->unique()->all(),
                    'palabras' => $this->palabras($mostrado->texto),
                ];
            })
            ->filter(fn (array $c) => $c['palabras'] !== [])
            ->values()
            ->all();

        $avisos = [];
        foreach ($candidatas as $i => $a) {
            for ($j = $i + 1; $j < count($candidatas); $j++) {
                $b = $candidatas[$j];
                if (array_intersect($a['plantillas'], $b['plantillas'])) {
                    continue;
                }
                $comunes = count(array_intersect_key($a['palabras'], $b['palabras']));
                $todas = count($a['palabras'] + $b['palabras']);
                // comunes / todas >= 0,8, sin redondeo de coma flotante.
                if ($comunes * 5 >= $todas * 4) {
                    $avisos[] = [
                        'parecido' => $comunes / $todas,
                        'filas' => [$a['id'], $b['id']],
                        'textos' => [$a['texto'], $b['texto']],
                        'plantillas' => [$a['plantilla'], $b['plantilla']],
                        'similitud' => round($comunes / $todas, 2),
                    ];
                }
            }
        }

        // usort es estable: a igual parecido se conserva el orden del checklist.
        usort($avisos, fn (array $x, array $y) => $y['parecido'] <=> $x['parecido']);

        return [
            'avisos' => array_map(fn (array $a) => array_diff_key($a, ['parecido' => true]), array_slice($avisos, 0, self::MAX_AVISOS)),
            'total' => count($avisos),
        ];
    }

    /**
     * Palabras de más de 3 letras y todos los números, en minúsculas y sin tildes ni signos.
     * Los números distinguen "Anexo 1" de "Anexo 2".
     *
     * @return array<string, true>
     */
    private function palabras(?string $texto): array
    {
        $palabras = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $texto)), -1, PREG_SPLIT_NO_EMPTY);

        return array_fill_keys(array_filter($palabras, fn (string $p) => strlen($p) > 3 || ctype_digit($p)), true);
    }

    // ------------------------------------------------------------ internos

    /**
     * Comprueba ids contra el catálogo. Acepta elementos inactivos solo si el
     * expediente ya los tenía (para no forzar a quitarlos al reconfigurar).
     */
    private function validarSeleccion(array $datos, ?ProyectoExpediente $expediente): array
    {
        $errores = [];
        $resultado = [];

        $grupos = [
            'sector_ids' => [RequisitoSector::class, 'sectores', 'sector'],
            'tipo_ids' => [RequisitoTipo::class, 'tipos', 'tipo de proyecto'],
            'componente_ids' => [RequisitoComponente::class, 'componentes', 'componente'],
            'tipologia_ids' => [RequisitoTipologia::class, 'tipologias', 'tipología'],
        ];

        foreach ($grupos as $campo => [$modelo, $relacion, $etiqueta]) {
            $ids = array_values(array_unique(array_map('intval', $datos[$campo] ?? [])));
            $previos = $expediente
                ? array_map('intval', $expediente->{$relacion}()->pluck($this->tablaDe($modelo) . '.id')->all())
                : [];

            $encontrados = $modelo::query()->whereIn('id', $ids)->get()->keyBy('id');
            foreach ($ids as $id) {
                $item = $encontrados->get($id);
                if (! $item) {
                    $errores[] = "El {$etiqueta} con id {$id} no existe.";
                } elseif (! $item->activo && ! in_array($id, $previos, true)) {
                    $errores[] = "El {$etiqueta} \"{$item->nombre}\" está inactivo.";
                }
            }
            $resultado[$campo] = $ids;
            $resultado['_' . $campo] = $encontrados;
        }

        // Un tipo marcado debe pertenecer a un sector marcado.
        foreach ($resultado['_tipo_ids'] as $tipo) {
            if (! in_array($tipo->sector_id, $resultado['sector_ids'], true)) {
                $errores[] = "El tipo \"{$tipo->nombre}\" pertenece a un sector que no está seleccionado.";
            }
        }

        if ($errores) {
            throw new ReglaNegocioException('La configuración del expediente no es válida.', $errores);
        }

        return array_filter($resultado, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
    }

    private function tablaDe(string $modelo): string
    {
        return (new $modelo())->getTable();
    }

    /**
     * Plantillas, fase e ids ordenados de cada selección, para comparar y registrar la
     * configuración. La fase solo cuenta con una plantilla de motor sgr: sin ella no tiene
     * efecto, y cambiarla no debe registrar un cambio de configuración.
     */
    private function configuracionRegistrable(ProyectoExpediente $expediente): array
    {
        $ids = fn (string $relacion, string $tabla) => $expediente->{$relacion}()
            ->orderBy("{$tabla}.id")->pluck("{$tabla}.id")->map(fn ($id) => (int) $id)->all();
        $versiones = $this->plantillas->versionesDe($expediente);

        return [
            'plantillas' => $this->plantillasRegistrables($versiones),
            ...($this->plantillas->tieneSgr($versiones) ? ['fase_sgr' => (int) $expediente->fase_sgr] : []),
            'sector_ids' => $ids('sectores', 'requisitos_sectores'),
            'tipo_ids' => $ids('tipos', 'requisitos_tipos'),
            'componente_ids' => $ids('componentes', 'requisitos_componentes'),
            'tipologia_ids' => $ids('tipologias', 'requisitos_tipologias'),
        ];
    }

    /**
     * @param Collection<int, CatalogoVersion> $versiones las de versionesDe()
     * @return list<array{plantilla_id: int, catalogo_version_id: int, es_principal: bool}>
     */
    private function plantillasRegistrables(Collection $versiones): array
    {
        return $versiones
            ->map(fn (CatalogoVersion $v) => [
                'plantilla_id' => $v->plantilla_id,
                'catalogo_version_id' => $v->id,
                'es_principal' => (bool) $v->pivot->es_principal,
            ])
            ->values()
            ->all();
    }

    /** La fase solo se pide si el expediente tiene una plantilla con motor sgr. */
    private function exigirFaseSgr(array $datos, bool $tieneSgr): void
    {
        if ($tieneSgr && ! isset($datos['fase_sgr'])) {
            throw ValidationException::withMessages(['fase_sgr' => 'Elige la fase SGR del expediente.']);
        }
    }

    private function guardarSeleccion(ProyectoExpediente $expediente, array $seleccion): void
    {
        $expediente->sectores()->sync($seleccion['sector_ids']);
        $expediente->tipos()->sync($seleccion['tipo_ids']);
        $expediente->componentes()->sync($seleccion['componente_ids']);
        $expediente->tipologias()->sync($seleccion['tipologia_ids']);
        $expediente->unsetRelation('sectores')->unsetRelation('tipos')
            ->unsetRelation('componentes')->unsetRelation('tipologias');
    }

    /**
     * Bloquea la fila del expediente y deja el modelo con lo que dice esa lectura: las reglas
     * se deciden sobre ella y no sobre lo que leyó la petición. Va dentro de una transacción.
     *
     * Orden único de bloqueo: primero el expediente, después sus filas y orígenes. Toda
     * escritura del checklist lo sigue (marcar, soportes, "no aplica", propios, sincronizar,
     * radicar): así ninguna espera una fila que otra tiene mientras esta espera el expediente,
     * el interbloqueo 1213 de MariaDB. SQLite no lo puede reproducir en las pruebas.
     *
     * Equivale a refresh(), pero copia la fila leída con FOR UPDATE: en REPEATABLE READ, un
     * SELECT normal en una transacción que ya leyó algo puede devolver la foto de entonces.
     * Las relaciones se descartan por la misma razón y se vuelven a leer al usarlas.
     */
    public function bloquear(ProyectoExpediente $expediente): void
    {
        $actual = ProyectoExpediente::query()->whereKey($expediente->id)->lockForUpdate()->firstOrFail();
        $expediente->setRawAttributes($actual->getAttributes(), true);
        $expediente->setRelations([]);
    }

    /** Bloquea el expediente y exige, sobre esa lectura, que su checklist admita cambios. */
    public function bloquearChecklistEditable(ProyectoExpediente $expediente): void
    {
        $this->bloquear($expediente);
        $this->exigirChecklistEditable($expediente);
    }

    public function exigirBorrador(ProyectoExpediente $expediente): void
    {
        if (! $expediente->esBorrador()) {
            throw new ReglaNegocioException('El expediente está radicado: su checklist está congelado y no admite cambios.');
        }
    }

    private function exigirConfigurado(ProyectoExpediente $expediente): void
    {
        if (! $expediente->estaConfigurado()) {
            throw new ReglaNegocioException('Confirma la configuración del expediente antes de chequear sus requisitos.');
        }
    }

    private function exigirSinBorrador(Proyecto $proyecto): void
    {
        $borrador = $proyecto->expedientes()->where('estado', ProyectoExpediente::BORRADOR)->value('id');
        if ($borrador) {
            throw new ReglaNegocioException("El proyecto ya tiene el expediente #{$borrador} en borrador. Radícalo o elimínalo antes de crear otro.");
        }
    }

    private function esFormulacion(mixed $faseId): bool
    {
        if (! $faseId) {
            return false;
        }

        return $this->normalizar(Fase::query()->whereKey($faseId)->value('nombre')) === 'formulacion';
    }

    private function normalizar(?string $texto): string
    {
        return Str::lower(Str::ascii(trim((string) $texto)));
    }

    /** Registra lo que cambió en la fila antes de guardarla. */
    private function registrarCambios(ProyectoRequisito $fila, ?User $usuario): void
    {
        if ($fila->isDirty('cumplido')) {
            $this->historial->registrarFila(
                $fila,
                $fila->cumplido ? 'cumplido_marcado' : 'cumplido_desmarcado',
                [
                    'cumplido' => (bool) $fila->getOriginal('cumplido'),
                    // Al desmarcar, la fila borra quién y cuándo: queda aquí.
                    'fecha_cumplido' => $fila->getOriginal('fecha_cumplido')?->toIso8601String(),
                    'usuario_cumplido' => $fila->getOriginal('usuario_cumplido_id')
                        ? User::whereKey($fila->getOriginal('usuario_cumplido_id'))->value('name')
                        : null,
                ],
                ['cumplido' => $fila->cumplido],
                $usuario
            );
        }
        if ($fila->isDirty('observacion')) {
            $this->historial->registrarFila(
                $fila,
                'observacion_cambiada',
                ['observacion' => $fila->getOriginal('observacion')],
                ['observacion' => $fila->observacion],
                $usuario
            );
        }
        if ($fila->isDirty('responsable_id')) {
            // Se guarda el nombre: el historial debe leerse aunque el responsable se elimine.
            $nombre = fn ($id) => $id ? Responsable::whereKey($id)->value('nombre') : null;
            $this->historial->registrarFila(
                $fila,
                'responsable_cambiado',
                ['responsable_id' => $fila->getOriginal('responsable_id'), 'responsable' => $nombre($fila->getOriginal('responsable_id'))],
                ['responsable_id' => $fila->responsable_id, 'responsable' => $nombre($fila->responsable_id)],
                $usuario
            );
        }
    }

    /** Acepta un responsable inactivo solo si la fila ya lo tenía. */
    private function responsableValido(mixed $id, ProyectoRequisito $fila): ?int
    {
        if ($id === null) {
            return null;
        }

        $responsable = Responsable::find((int) $id)
            ?? throw new ReglaNegocioException('El responsable no existe.');
        if (! $responsable->activo && $responsable->id !== $fila->responsable_id) {
            throw new ReglaNegocioException("El responsable \"{$responsable->nombre}\" está inactivo.");
        }

        return $responsable->id;
    }

    /** El checklist admite cambios: el expediente está en borrador y con la configuración confirmada. */
    public function exigirChecklistEditable(ProyectoExpediente $expediente): void
    {
        $this->exigirBorrador($expediente);
        $this->exigirConfigurado($expediente);
    }

    public function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
    {
        $this->exigirChecklistEditable($expediente);

        if ($fila->expediente_id !== $expediente->id) {
            throw new ReglaNegocioException('El requisito no pertenece a este expediente.');
        }
        $this->exigirQueAplique($fila);
    }

    /** También se llama con la fila ya bloqueada: la configuración pudo retirarla después de la ruta. */
    public function exigirQueAplique(ProyectoRequisito $fila): void
    {
        if (! $fila->aplica) {
            throw new ReglaNegocioException('El requisito no aplica con la configuración actual del expediente.');
        }
    }

    private function progreso(int $total, int $cumplidos): array
    {
        return [
            'total' => $total,
            'cumplidos' => $cumplidos,
            'porcentaje' => $total > 0 ? (int) round($cumplidos / $total * 100) : 0,
        ];
    }

    private function opciones($coleccion): array
    {
        return $coleccion->map(fn ($i) => ['id' => $i->id, 'clave' => $i->clave, 'nombre' => $i->nombre])->values()->all();
    }
}
