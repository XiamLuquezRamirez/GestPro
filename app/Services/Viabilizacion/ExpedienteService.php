<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Fase;
use App\Models\FuenteFinanciacion;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ciclo de vida del expediente de viabilización (la instancia por proyecto).
 *
 * Reglas:
 * - Se crea con la versión VIGENTE del catálogo y queda atada a ella.
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

    // Orden de una fila: posición de la plantilla del primer origen, sección (motor sgr) y orden
    // del requisito. La principal tiene posición 0: un expediente solo SGR conserva su orden.
    private const ORDEN_POR_PLANTILLA = 10_000_000;
    private const ORDEN_POR_SECCION = 100_000;

    /** Del más al menos exigente: una fila con varios orígenes toma el primero que aparezca. */
    private const CARACTER_POR_EXIGENCIA = [RequisitoCatalogo::OBLIGATORIO, RequisitoCatalogo::CONDICIONADO, RequisitoCatalogo::OPCIONAL];

    private const RELACIONES_FILA = [
        'requisito.sector:id,clave,nombre,fuente', 'requisito.tipo:id,clave,nombre', 'usuarioCumplido:id,name',
        'usuarioNoAplica:id,name', 'usuarioAprobo:id,name', 'responsable:id,nombre,cargo',
    ];

    public function __construct(
        private readonly EvaluadorRequisitos $evaluador,
        private readonly HistorialChecklist $historial,
        private readonly PlantillasExpediente $plantillas,
    ) {
    }

    // -------------------------------------------------------------- escritura

    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProyectoExpediente
    {
        $version = PlantillaChecklist::sgr()->versionVigente()
            ?? throw new ReglaNegocioException('No hay una versión vigente del catálogo de requisitos.');

        $seleccion = $this->validarSeleccion($datos, null);

        return DB::transaction(function () use ($proyecto, $version, $datos, $seleccion, $usuario) {
            // El bloqueo evita dos borradores por una doble petición simultánea.
            Proyecto::query()->whereKey($proyecto->id)->lockForUpdate()->first();
            $this->exigirSinBorrador($proyecto);

            $expediente = ProyectoExpediente::create([
                'proyecto_id' => $proyecto->id,
                'catalogo_version_id' => $version->id,
                'fase_sgr' => (int) $datos['fase_sgr'],
                'estado' => ProyectoExpediente::BORRADOR,
                'usuario_creo_id' => $usuario?->id,
            ]);

            $expediente->versionesPlantilla()->attach($version->id, ['es_principal' => true]);
            $this->guardarSeleccion($expediente, $seleccion);
            $this->sincronizar($expediente);
            $this->historial->registrarExpediente($expediente, 'expediente_creado', [], ['catalogo_version_id' => $version->id], $usuario);

            return $expediente;
        });
    }

    /**
     * Expediente que nace con el proyecto, si le corresponde. Nunca interrumpe a quien
     * guarda el proyecto: un fallo se registra y el expediente se puede crear a mano.
     */
    public function crearAutomatico(Proyecto $proyecto, ?User $usuario): ?ProyectoExpediente
    {
        if (! $this->correspondeAutomatico($proyecto)) {
            return null;
        }

        $proyecto->loadMissing('sectorRel');
        $sugerido = $proyecto->sectorRel?->requisitos_sector_id;

        try {
            return $this->crear($proyecto, ['fase_sgr' => 3, 'sector_ids' => $sugerido ? [$sugerido] : []], $usuario);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Proyecto en Formulación, con la fuente SGR y todavía sin expediente. */
    public function correspondeAutomatico(Proyecto $proyecto): bool
    {
        return $this->esFormulacion($proyecto->fase)
            && $proyecto->fuentes()->where('clave', FuenteFinanciacion::SGR)->exists()
            && ! $proyecto->expedientes()->exists();
    }

    public function confirmarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        return DB::transaction(function () use ($expediente, $datos, $usuario) {
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
            ? "El proyecto salió de Formulación con el expediente de viabilización #{$borrador->id} sin radicar."
            : null;
    }

    public function actualizarConfiguracion(ProyectoExpediente $expediente, array $datos, ?User $usuario = null): ProyectoExpediente
    {
        $this->exigirBorrador($expediente);
        $seleccion = $this->validarSeleccion($datos, $expediente);

        return DB::transaction(function () use ($expediente, $datos, $seleccion, $usuario) {
            $antes = $this->configuracionRegistrable($expediente);
            $expediente->update(['fase_sgr' => (int) $datos['fase_sgr']]);
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
            // Bloquea la fila y decide sobre su estado actual: un "no aplica" o una marca
            // simultánea no puede quedar pisado por una lectura vieja.
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
            DB::transaction(function () use ($fila, $archivo, $ruta, $usuario) {
                // Bloquea la fila: con dos subidas a la vez, la segunda ve el soporte de la primera.
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

        DB::transaction(function () use ($fila, $usuario) {
            // Bloquea la fila: si otra petición ya quitó el soporte, no hay nada que registrar.
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

        DB::transaction(function () use ($expediente, $usuario, $total) {
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
     *   tenerlos se recupera tal cual.
     * - Carácter: el más exigente de los orígenes. Una fila nueva condicionada nace por definir;
     *   una existente no cambia de estado por recalcular.
     * - Orden: el del primer origen, desplazado por la posición de su plantilla.
     * - Los requisitos propios no se tocan.
     *
     * @return array{aplican:int, retenidos:int}
     */
    public function sincronizar(ProyectoExpediente $expediente): array
    {
        $llaves = $this->origenesPorLlave($expediente);

        $existentes = [];
        $sinLlave = [];
        $filas = $expediente->requisitos()->whereNotNull('requisito_catalogo_id')
            ->with('requisito:id,codigo_comun')->orderBy('id')->get();
        foreach ($filas as $fila) {
            $llave = $this->llave($fila->requisito);
            // Dos filas con la misma llave solo pueden venir de datos anteriores a esta regla:
            // la primera conserva la llave y las demás quedan sin orígenes.
            if (isset($existentes[$llave])) {
                $sinLlave[] = $fila;
            } else {
                $existentes[$llave] = $fila;
            }
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
        foreach ([...array_values($existentes), ...$sinLlave] as $fila) {
            if ($fila->aplica) {
                $fila->update(['aplica' => false]);
            }
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
        foreach ($actuales as $fila => $requisitos) {
            $sobran = array_diff($requisitos, $deseados[$fila] ?? []);
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
            'proyecto:id,nombre', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name',
            'sectores', 'tipos', 'componentes', 'tipologias',
        ]);

        $filas = $expediente->requisitos()
            ->where('aplica', true)
            ->with(self::RELACIONES_FILA)
            ->orderBy('orden')
            ->get();

        return [
            ...$this->presentarCabecera($expediente),
            'configuracion' => [
                'sectores' => $this->opciones($expediente->sectores),
                'tipos' => $expediente->tipos->map(fn ($t) => ['id' => $t->id, 'sector_id' => $t->sector_id, 'clave' => $t->clave, 'nombre' => $t->nombre])->values(),
                'componentes' => $this->opciones($expediente->componentes),
                'tipologias' => $this->opciones($expediente->tipologias),
            ],
            'progreso' => $this->progresoDe($expediente),
            'requisitos' => $filas->map(fn (ProyectoRequisito $f) => $this->presentarFila($f))->values(),
            // Filas que dejaron de aplicar pero guardan trabajo hecho.
            'retenidos' => $expediente->requisitos()->where('aplica', false)
                ->where(fn ($q) => $q->where('cumplido', true)->orWhereNotNull('observacion')->orWhereNotNull('soporte_archivo')
                    ->orWhere('estado_aplicabilidad', '!=', ProyectoRequisito::APLICA)->orWhereNotNull('responsable_id'))
                ->count(),
        ];
    }

    /**
     * Expediente más reciente de cada proyecto que tiene alguno, con su avance.
     * Son dos consultas sin importar cuántos proyectos haya.
     */
    public function resumenPorProyecto(): array
    {
        $cantidades = ProyectoExpediente::query()
            ->selectRaw('MAX(id) AS ultimo_id, COUNT(*) AS cantidad')
            ->groupBy('proyecto_id')
            ->pluck('cantidad', 'ultimo_id');

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
                'fecha_radicado' => $e->fecha_radicado?->toIso8601String(),
                'actualizado' => $e->updated_at?->toIso8601String(),
                'progreso' => $this->progreso((int) $e->requisitos_aplican, (int) $e->requisitos_cumplidos),
            ])
            ->all();
    }

    public function presentarCabecera(ProyectoExpediente $expediente): array
    {
        $expediente->loadMissing(['proyecto:id,nombre', 'version:id,numero', 'usuarioRadico:id,name', 'usuarioCreo:id,name']);

        return [
            'id' => $expediente->id,
            'proyecto' => ['id' => $expediente->proyecto->id, 'nombre' => $expediente->proyecto->nombre],
            'catalogo_version' => ['id' => $expediente->version->id, 'numero' => $expediente->version->numero],
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

    public function presentarFila(ProyectoRequisito $fila): array
    {
        $fila->loadMissing(self::RELACIONES_FILA);
        $r = $fila->requisito;

        return [
            'id' => $fila->id,
            'estado' => $fila->estadoVisible(),
            'caracter' => $fila->caracter,
            'cuenta' => $fila->cuenta(),
            'no_aplica' => $fila->estado_aplicabilidad === ProyectoRequisito::APLICA ? null : [
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
            'requisito' => [
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
                'cargo_cumplimiento' => $r->cargo_cumplimiento,
                'cargo_revision' => $r->cargo_revision,
                'requiere_aprobacion_no_aplica' => $r->requiere_aprobacion_no_aplica,
            ],
        ];
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

    /** Fase e ids ordenados de cada selección, para comparar y registrar la configuración. */
    private function configuracionRegistrable(ProyectoExpediente $expediente): array
    {
        $ids = fn (string $relacion, string $tabla) => $expediente->{$relacion}()
            ->orderBy("{$tabla}.id")->pluck("{$tabla}.id")->map(fn ($id) => (int) $id)->all();

        return [
            'fase_sgr' => (int) $expediente->fase_sgr,
            'sector_ids' => $ids('sectores', 'requisitos_sectores'),
            'tipo_ids' => $ids('tipos', 'requisitos_tipos'),
            'componente_ids' => $ids('componentes', 'requisitos_componentes'),
            'tipologia_ids' => $ids('tipologias', 'requisitos_tipologias'),
        ];
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

    private function exigirBorrador(ProyectoExpediente $expediente): void
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

    public function exigirEditable(ProyectoExpediente $expediente, ProyectoRequisito $fila): void
    {
        $this->exigirBorrador($expediente);
        $this->exigirConfigurado($expediente);

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
