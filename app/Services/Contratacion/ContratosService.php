<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\AnexoContrato;
use App\Models\AvanceFinanciero;
use App\Models\AvanceFisico;
use App\Models\Contrato;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contratos: crear, editar, eliminar, recalcular sus valores vigentes y presentarlos.
 *
 * Reglas:
 * - El número, el objeto, el valor inicial y el estado (uno de Contrato::ESTADOS) son
 *   obligatorios; lo demás, opcional.
 * - El proyecto es opcional: la empresa también ejecuta contratos de proyectos que no formuló. Al
 *   editar se puede asignar o cambiar; si viene, debe existir. Un contrato que ya tiene proyecto no
 *   se queda sin él al editar.
 * - El proceso (columna proceso_licitacion) debe ser del mismo proyecto que el contrato (R3). Un
 *   contrato sin proyecto toma el del proceso. Vincular un proceso nuevo, al crear o al cambiarlo,
 *   exige que esté adjudicado o celebrado; el que ya tiene se conserva aunque haya cambiado de
 *   estado. Desvincular se permite.
 * - El tipo de contrato debe existir y estar activo, o ser el que el contrato ya tiene.
 * - La fecha fin inicial no es anterior a la de inicio (con lo que quedaría guardado).
 * - El pedido escribe el valor inicial y la fecha fin inicial, y la referencia de SECOP II (ID en
 *   mayúsculas, proceso, enlace y fecha de la última consulta). El valor y la fecha fin vigentes
 *   los deriva recalcular(): inicial más adiciones y prórrogas. Al crear son los iniciales.
 * - Avisos que no impiden guardar: los contratos del proceso que no están anulados suman más que
 *   su monto (R9). Al crear el primer contrato de un proceso adjudicado se propone pasarlo a
 *   celebrado.
 * - Editar trae `actualizado`, el updated_at que tenía el formulario: si ya no es el de la fila,
 *   se rechaza (409). Cambiar los anexos también cambia la versión. Registrar o quitar una modificación recalcula sin cambiar la versión: no
 *   toca lo que edita el formulario.
 * - No se elimina un contrato con actas (financieras o físicas), actividades o modificaciones: se
 *   anula. Al eliminar se borran sus anexos, las filas y los archivos.
 * - Todo queda en checklist_historial (entidad_tipo = contrato, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - La búsqueda, el detalle y el listado por proyecto traen `aviso_proceso`: lo que avisa el
 *   proceso del contrato (etapas vencidas, cierre vencido o sin actualización), o null si no
 *   tiene proceso o este no avisa nada (ExpedientesContratacion::resumenDeProcesos).
 */
final class ContratosService
{
    use ControlDeVersion;

    /** Campo de la API => columna del contrato, en el orden del historial. */
    private const CAMPOS = [
        'proyecto' => 'proyecto',
        'proceso_id' => 'proceso_licitacion',
        'tipo_contrato_id' => 'tipo_contrato_id',
        'n_contrato' => 'n_contrato',
        'objeto' => 'objeto',
        'contratante' => 'contratante',
        'contratante_nit' => 'contratante_nit',
        'contratista' => 'contratista',
        'contratista_identificacion' => 'contratista_identificacion',
        'supervisor' => 'supervisor',
        'interventoria' => 'interventoria',
        'numero_rp' => 'numero_rp',
        'valor_inicial' => 'valor_inicial',
        'fecha_inicio' => 'fecha_inicio',
        'fecha_fin_inicial' => 'fecha_fin_inicial',
        'fecha_suscripcion' => 'fecha_suscripcion',
        'fecha_rp' => 'fecha_rp',
        'fecha_acta_inicio' => 'fecha_acta_inicio',
        'estado' => 'estado',
        'anticipo' => 'anticipo',
        'porcentaje_anticipo' => 'porcentaje_anticipo',
        'avance_fisico' => 'avance_fisico',
        'avance_financiero' => 'avance_financiero',
        'secop_id_contrato' => 'secop_id_contrato',
        'secop_proceso' => 'secop_proceso',
        'url_secop' => 'url_secop',
        'secop_consultado_at' => 'secop_consultado_at',
    ];

    private const TEXTOS = [
        'n_contrato', 'objeto', 'contratante', 'contratante_nit', 'contratista',
        'contratista_identificacion', 'supervisor', 'interventoria', 'numero_rp',
        'secop_id_contrato', 'secop_proceso', 'url_secop',
    ];

    private const ENTEROS = ['proyecto', 'proceso_id', 'tipo_contrato_id', 'porcentaje_anticipo'];

    // Los avances los calcula la pantalla y pueden traer decimales: se guardan redondeados.
    private const AVANCES = ['avance_fisico', 'avance_financiero'];

    private const OBLIGATORIOS = [
        'n_contrato' => 'Escribe el número del contrato.',
        'objeto' => 'Escribe el objeto del contrato.',
        'valor_inicial' => 'Escribe el valor inicial del contrato.',
        'estado' => 'Elige el estado del contrato.',
    ];

    public const DESACTUALIZADO = 'Otra persona cambió este contrato mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    public const CON_REGISTROS = 'Este contrato tiene actas, actividades o modificaciones: anúlalo en vez de eliminarlo.';

    public const FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    public const SIN_PROYECTO = 'El contrato debe seguir en un proyecto.';

    private const CARPETA_ANEXOS = 'anexos_contratos';

    public function __construct(
        private readonly HistorialChecklist $historial,
        private readonly ExpedientesContratacion $expedientes,
    ) {
    }

    /**
     * Crea el contrato con sus anexos. Los campos que no vienen quedan vacíos; el anticipo, en no.
     *
     * @param array<string, mixed> $datos campos de CAMPOS
     * @param list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos
     */
    public function crear(array $datos, array $anexos, ?User $usuario): ContratoGuardado
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), 'anticipo' => false, ...$datos]);

        return DB::transaction(function () use ($valores, $anexos, $usuario) {
            $this->exigirTipo($valores, null);
            [$valores, $proceso] = $this->exigirVinculos($valores, null);
            $this->exigirFechas($valores);

            $contrato = Contrato::create([
                ...$this->columnas($valores),
                'valor' => $valores['valor_inicial'],
                'fecha_fin' => $valores['fecha_fin_inicial'],
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->guardarAnexos($contrato, $anexos);
            $this->historial->registrarContrato($contrato, 'contrato_creado', [], $this->valoresDe($contrato), $usuario);

            // El primero: el proceso no tenía otros contratos, ni siquiera anulados.
            $proponer = $proceso?->estado === ProcesoLicitacion::ADJUDICADO
                && $proceso->contratos()->count() === 1;

            return new ContratoGuardado($contrato, $this->avisos($contrato), $proponer);
        });
    }

    /**
     * Cambia los campos que trae el pedido; los demás no cambian. Con `$anexos` (aunque sea una
     * lista vacía) los anexos se reemplazan; con null se conservan. `$actualizado` es el
     * updated_at que tenía el formulario. Si cambia algún campo o los anexos, cambia la versión y
     * queda en el historial. Después recalcula el valor y la fecha fin vigentes.
     *
     * @param array<string, mixed> $datos campos de CAMPOS
     * @param ?list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos
     */
    public function editar(Contrato $contrato, array $datos, ?array $anexos, ?string $actualizado, ?User $usuario): ContratoGuardado
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($contrato, $valores, $anexos, $actualizado, $usuario) {
            // Se decide con la fila bloqueada: el tipo, el proceso y las fechas que tiene ahora.
            $actual = $this->bloquear($contrato);
            $this->exigirVersion($actual, $actualizado, self::DESACTUALIZADO);
            $this->exigirTipo($valores, $actual);
            [$valores] = $this->exigirVinculos($valores, $actual);
            // Sin proceso que lo dé, un proyecto null quitaría el que tiene.
            if ($actual->proyecto !== null && array_key_exists('proyecto', $valores) && $valores['proyecto'] === null) {
                throw ValidationException::withMessages(['proyecto' => self::SIN_PROYECTO]);
            }
            $antes = $this->valoresDe($actual);
            $this->exigirFechas([...$antes, ...$valores]);

            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            $anexosAntes = $this->anexosDe($actual);
            $anexosDespues = $anexos === null ? $anexosAntes : array_map(fn (array $a) => [
                'descripcion' => $a['descripcion'],
                'nombre_archivo' => $a['nombreArchivo'],
                'ruta_archivo' => $a['rutaArchivo'],
                'fecha' => $a['fecha'],
            ], array_values($anexos));
            $cambianAnexos = $anexosDespues !== $anexosAntes;

            if ($cambios !== [] || $cambianAnexos) {
                $this->adelantarActualizado($actual);
                $actual->fill($this->columnas($cambios))->save();
                if ($cambianAnexos) {
                    $actual->anexos()->delete();
                    $this->guardarAnexos($actual, $anexos);
                }
                $this->historial->registrarContrato(
                    $actual,
                    'contrato_editado',
                    [...array_intersect_key($antes, $cambios), ...($cambianAnexos ? ['anexos' => $anexosAntes] : [])],
                    [...$cambios, ...($cambianAnexos ? ['anexos' => $anexosDespues] : [])],
                    $usuario
                );
            }
            $this->recalcular($actual);
            $actual->refresh();

            return new ContratoGuardado($actual, $this->avisos($actual), false);
        });
    }

    public function eliminar(Contrato $contrato, ?User $usuario): void
    {
        $rutas = DB::transaction(function () use ($contrato, $usuario) {
            // Con la fila bloqueada, un acta o una modificación que se registre a la vez espera y
            // luego no encuentra el contrato, o termina antes y aquí se cuenta.
            $actual = $this->bloquear($contrato);
            $conRegistros = AvanceFinanciero::query()->where('contrato_id', $actual->id)->exists()
                || AvanceFisico::query()->where('contrato_id', $actual->id)->exists()
                || $actual->actividades()->exists()
                || $actual->modificaciones()->exists();
            if ($conRegistros) {
                throw new ReglaNegocioException(self::CON_REGISTROS);
            }

            $anexos = $this->anexosDe($actual);
            // El registro sobrevive al contrato: con los vigentes y los anexos que tenía.
            $this->historial->registrarContrato($actual, 'contrato_eliminado', [
                ...$this->valoresDe($actual),
                'valor' => $actual->valor,
                'fecha_fin' => $actual->fecha_fin?->format('Y-m-d'),
                'anexos' => $anexos,
            ], [], $usuario);
            $actual->anexos()->delete();
            $actual->delete();

            return array_unique(array_column($anexos, 'ruta_archivo'));
        });

        // Los archivos, solo cuando las filas ya no están: si algo fallara antes, no se pierden. Un
        // archivo que otro anexo (de cualquier contrato) también usa se conserva.
        foreach ($rutas as $ruta) {
            if (! AnexoContrato::query()->where('ruta_archivo', $ruta)->exists()) {
                $this->borrarArchivo($ruta);
            }
        }
    }

    /**
     * Deriva el valor y la fecha fin vigentes desde el valor inicial y la fecha fin inicial más
     * todas las modificaciones. Devuelve el resumen: vigentes, % adicionado y si supera el 50 %.
     * No cambia la versión del contrato (updated_at): no toca lo que edita el formulario.
     *
     * @return array{valor_inicial: float, valor_vigente: float, fecha_fin_inicial: ?string, fecha_fin_vigente: ?string, porcentaje_adicionado: float, supera_limite: bool}
     */
    public function recalcular(Contrato $contrato): array
    {
        $fila = DB::table('contratos')->where('id', $contrato->id)->first();

        $valorInicial = (float) ($fila->valor_inicial ?? $fila->valor ?? 0);
        $fechaFinInicial = $fila->fecha_fin_inicial ?? $fila->fecha_fin;
        $fechaFinInicial = $fechaFinInicial !== null ? Carbon::parse($fechaFinInicial)->toDateString() : null;

        $modificaciones = DB::table('modificaciones_contrato')->where('contrato_id', $contrato->id);
        $sumaAdiciones = (float) (clone $modificaciones)->sum('valor_adicion');
        $sumaDias = (int) (clone $modificaciones)->sum('dias_prorroga');

        $valorVigente = $valorInicial + $sumaAdiciones;
        $fechaFinVigente = $fechaFinInicial !== null
            ? Carbon::parse($fechaFinInicial)->addDays($sumaDias)->toDateString()
            : null;

        // Con el constructor de consultas, no con el modelo: así no cambia updated_at.
        DB::table('contratos')->where('id', $contrato->id)->update([
            'valor' => $valorVigente,
            'fecha_fin' => $fechaFinVigente,
        ]);

        $porcentaje = $valorInicial > 0 ? ($sumaAdiciones / $valorInicial) * 100 : 0.0;

        return [
            'valor_inicial' => $valorInicial,
            'valor_vigente' => $valorVigente,
            'fecha_fin_inicial' => $fechaFinInicial,
            'fecha_fin_vigente' => $fechaFinVigente,
            'porcentaje_adicionado' => round($porcentaje, 2),
            'supera_limite' => $porcentaje > 50,
        ];
    }

    public function presentar(Contrato $contrato): array
    {
        $contrato->load(['tipoContrato', 'procesoLicitacion.modalidadRel:id,nombre', 'usuarioCreo:id,name', 'anexos' => fn ($q) => $q->orderBy('id')]);
        $tipo = $contrato->tipoContrato;
        $fecha = fn (?DateTimeInterface $valor) => $valor?->format('Y-m-d');
        $momento = fn (?DateTimeInterface $valor) => $valor?->format('Y-m-d H:i:s');

        return [
            'id' => $contrato->id,
            'proyecto' => $this->entero($contrato->proyecto),
            'n_contrato' => $contrato->n_contrato,
            'objeto' => $contrato->objeto,
            'contratante' => $contrato->contratante,
            'contratante_nit' => $contrato->contratante_nit,
            'contratista' => $contrato->contratista,
            'contratista_identificacion' => $contrato->contratista_identificacion,
            'tipo_contrato_id' => $contrato->tipo_contrato_id,
            'tipo_contrato' => $tipo ? ['id' => $tipo->id, 'nombre' => $tipo->nombre, 'activo' => $tipo->activo] : null,
            'valor_inicial' => $contrato->valor_inicial,
            'valor' => $contrato->valor,
            'fecha_inicio' => $fecha($contrato->fecha_inicio),
            'fecha_fin_inicial' => $fecha($contrato->fecha_fin_inicial),
            'fecha_fin' => $fecha($contrato->fecha_fin),
            'fecha_suscripcion' => $fecha($contrato->fecha_suscripcion),
            'numero_rp' => $contrato->numero_rp,
            'fecha_rp' => $fecha($contrato->fecha_rp),
            'fecha_acta_inicio' => $fecha($contrato->fecha_acta_inicio),
            'supervisor' => $contrato->supervisor,
            'interventoria' => $contrato->interventoria,
            'estado' => $contrato->estado,
            'estado_original' => $contrato->estado_original,
            'anticipo' => (bool) $contrato->anticipo,
            'porcentaje_anticipo' => $this->entero($contrato->porcentaje_anticipo),
            'avance_fisico' => $this->entero($contrato->avance_fisico),
            'avance_financiero' => $this->entero($contrato->avance_financiero),
            'proceso_id' => $this->entero($contrato->proceso_licitacion),
            'proceso' => $contrato->procesoLicitacion ? $this->resumenProceso($contrato->procesoLicitacion) : null,
            'secop_id_contrato' => $contrato->secop_id_contrato,
            'secop_proceso' => $contrato->secop_proceso,
            'url_secop' => $contrato->url_secop,
            'secop_consultado_at' => $momento($contrato->secop_consultado_at),
            'anexos' => $contrato->anexos->map(fn (AnexoContrato $a) => [
                'id' => $a->id,
                'descripcion' => $a->descripcion,
                'nombre_archivo' => $a->nombre_archivo,
                'ruta_archivo' => $a->ruta_archivo,
                'fecha' => $fecha($a->fecha),
            ])->all(),
            'usuario_creo' => $contrato->usuarioCreo?->name,
            'created_at' => $momento($contrato->created_at),
            'updated_at' => $momento($contrato->updated_at),
        ];
    }

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table) con `proceso_id`
     * y `proceso` resumido, o null, en una sola consulta de procesos. Con `$conAviso`, también con
     * `aviso_proceso` (avisoProceso()), con consultas que no crecen con los procesos.
     *
     * @param iterable<object> $filas
     * @param array<int, array{avisos: array}> $resumenes los de ExpedientesContratacion::resumenDeProcesos()
     *        que quien llama ya tiene (proceso_id => resumen): solo se calculan los que faltan
     */
    public function agregarProcesos(iterable $filas, bool $conAviso = false, array $resumenes = []): void
    {
        $filas = Collection::make($filas);
        $procesos = ProcesoLicitacion::query()
            ->whereIn('id', $filas->pluck('proceso_licitacion')->filter()->unique()->values())
            ->with('modalidadRel:id,nombre')
            ->get()
            ->keyBy('id');
        if ($conAviso) {
            $faltan = $procesos->reject(fn (ProcesoLicitacion $p) => isset($resumenes[$p->id]))->values();
            $resumenes += $faltan->isEmpty() ? [] : $this->expedientes->resumenDeProcesos($faltan);
        }

        foreach ($filas as $fila) {
            $proceso = $fila->proceso_licitacion !== null ? $procesos->get($fila->proceso_licitacion) : null;
            $fila->proceso_id = $this->entero($fila->proceso_licitacion);
            $fila->proceso = $proceso ? $this->resumenProceso($proceso) : null;
            if ($conAviso) {
                $fila->aviso_proceso = $proceso ? $this->avisoProceso($proceso->id, $resumenes[$proceso->id]['avisos']) : null;
            }
        }
    }

    /**
     * Lo que avisa el proceso de un contrato en el módulo Contratos: null si no tiene etapas
     * vencidas, ni el cierre vencido, ni está sin actualización.
     *
     * @param array $avisos los de AvisosContratacion::resumen()
     * @return ?array{proceso_id: int, etapas_vencidas: int, cierre_vencido: bool, sin_actualizacion: bool, dias_sin_actualizacion: ?int}
     */
    private function avisoProceso(int $procesoId, array $avisos): ?array
    {
        if ($avisos['etapas_vencidas'] === 0 && ! $avisos['cierre_vencido'] && ! $avisos['sin_actualizacion']) {
            return null;
        }

        return [
            'proceso_id' => $procesoId,
            'etapas_vencidas' => $avisos['etapas_vencidas'],
            'cierre_vencido' => $avisos['cierre_vencido'],
            'sin_actualizacion' => $avisos['sin_actualizacion'],
            'dias_sin_actualizacion' => $avisos['dias_sin_actualizacion'],
        ];
    }

    /**
     * Completa las filas de contratos de los listados (consultas con DB::table) con sus anexos, actas
     * financieras, modificaciones y actividades (cada una con su último avance y su historial).
     *
     * @param iterable<object> $filas
     */
    public function agregarDetalle(iterable $filas): void
    {
        foreach ($filas as $contrato) {
            $contrato->anexos = DB::table('anexos_contratos')->where('contrato_id', $contrato->id)->get();

            $contrato->avancesFinancieros = DB::table('avance_financiero')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_acta', 'desc')
                ->get();

            $contrato->modificaciones = DB::table('modificaciones_contrato')
                ->where('contrato_id', $contrato->id)
                ->orderBy('fecha_modificacion', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $actividades = DB::table('actividades_contrato')->where('contrato_id', $contrato->id)->get();
            foreach ($actividades as $actividad) {
                $ultimoAvance = DB::table('actividad_avances')
                    ->where('actividad_id', $actividad->id)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();
                $actividad->ultimo_avance = $ultimoAvance->porcentaje_ejecucion ?? null;
                $actividad->fecha_ultimo_avance = $ultimoAvance->fecha ?? null;
                $actividad->avances = DB::table('actividad_avances')
                    ->select('id', 'fecha', 'porcentaje_ejecucion')
                    ->where('actividad_id', $actividad->id)
                    ->orderBy('fecha', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
            }
            $contrato->actividades = $actividades;
        }
    }

    /**
     * Todos los contratos, con o sin proyecto, del más reciente al más antiguo. Filtros opcionales:
     * `q` (texto en número, objeto, contratista, contratante, NIT e identificación), `estado`,
     * `proyecto` (id o 'sin') y `proceso` (id).
     *
     * @param array{q?: ?string, estado?: ?string, proyecto?: int|string|null, proceso?: int|string|null} $filtros
     * @param array<int, array{avisos: array}> $resumenes los resúmenes de procesos que quien llama ya tiene (agregarProcesos())
     * @return list<array<string, mixed>>
     */
    public function buscar(array $filtros, array $resumenes = []): array
    {
        $consulta = DB::table('contratos')
            ->leftJoin('proyectos', 'proyectos.id', '=', 'contratos.proyecto')
            ->leftJoin('tipos_procesos', 'tipos_procesos.id', '=', 'contratos.tipo_contrato_id')
            ->select([
                'contratos.id', 'contratos.n_contrato', 'contratos.objeto', 'contratos.contratista', 'contratos.contratante',
                'contratos.estado', 'contratos.valor', 'contratos.valor_inicial', 'contratos.fecha_inicio', 'contratos.fecha_fin',
                'contratos.avance_fisico', 'contratos.avance_financiero', 'contratos.secop_id_contrato', 'contratos.updated_at',
                'contratos.proceso_licitacion',
                'contratos.proyecto as proyecto_id', 'proyectos.nombre as proyecto_nombre',
                'contratos.tipo_contrato_id', 'tipos_procesos.nombre as tipo_nombre',
            ])
            ->orderByDesc('contratos.id');

        $texto = trim((string) ($filtros['q'] ?? ''));
        if ($texto !== '') {
            // Sin comodines: el % y el _ que escribe la persona se buscan tal cual.
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texto) . '%';
            $consulta->where(function ($q) use ($patron) {
                foreach (['n_contrato', 'objeto', 'contratista', 'contratante', 'contratante_nit', 'contratista_identificacion'] as $columna) {
                    $q->orWhereRaw("contratos.{$columna} LIKE ? ESCAPE '!'", [$patron]);
                }
            });
        }
        if (($filtros['estado'] ?? null) !== null) {
            $consulta->where('contratos.estado', $filtros['estado']);
        }
        if (($filtros['proyecto'] ?? null) !== null) {
            $filtros['proyecto'] === 'sin'
                ? $consulta->whereNull('contratos.proyecto')
                : $consulta->where('contratos.proyecto', (int) $filtros['proyecto']);
        }
        if (($filtros['proceso'] ?? null) !== null) {
            $consulta->where('contratos.proceso_licitacion', (int) $filtros['proceso']);
        }

        $filas = $consulta->get();
        $this->agregarProcesos($filas, true, $resumenes);

        return $filas->map(fn ($f) => [
            'id' => (int) $f->id,
            'n_contrato' => $f->n_contrato,
            'objeto' => $f->objeto,
            'contratista' => $f->contratista,
            'contratante' => $f->contratante,
            'estado' => $f->estado,
            'valor' => $f->valor,
            'valor_inicial' => $f->valor_inicial,
            'fecha_inicio' => $f->fecha_inicio,
            'fecha_fin' => $f->fecha_fin,
            'avance_fisico' => $this->entero($f->avance_fisico),
            'avance_financiero' => $this->entero($f->avance_financiero),
            'tipo_contrato' => $f->tipo_contrato_id !== null ? ['id' => (int) $f->tipo_contrato_id, 'nombre' => $f->tipo_nombre] : null,
            'proyecto' => $f->proyecto_id !== null ? ['id' => (int) $f->proyecto_id, 'nombre' => $f->proyecto_nombre] : null,
            'proceso' => $f->proceso,
            'aviso_proceso' => $f->aviso_proceso,
            'secop_id_contrato' => $f->secop_id_contrato,
            'updated_at' => $f->updated_at,
        ])->all();
    }

    /**
     * El contrato con la misma forma que un elemento de GET /listarContratos (con `aviso_proceso`),
     * más `proyecto_nombre`, o null si no existe.
     */
    public function detalle(int $id): ?object
    {
        $fila = DB::table('contratos')
            ->leftJoin('proyectos', 'proyectos.id', '=', 'contratos.proyecto')
            ->where('contratos.id', $id)
            ->select(['contratos.*', 'proyectos.nombre as proyecto_nombre'])
            ->first();
        if ($fila === null) {
            return null;
        }
        $filas = [$fila];
        $this->agregarProcesos($filas, true);
        $this->agregarDetalle($filas);

        return $fila;
    }

    /** @return array{id: int, codigo_proceso: ?string, objeto: ?string, estado: string, modalidad: ?array, plataforma: ?string, url_proceso: ?string} */
    private function resumenProceso(ProcesoLicitacion $proceso): array
    {
        $modalidad = $proceso->modalidadRel;

        return [
            'id' => $proceso->id,
            'codigo_proceso' => $proceso->codigo_proceso,
            'objeto' => $proceso->objeto,
            'estado' => $proceso->estado,
            'modalidad' => $modalidad ? ['id' => $modalidad->id, 'nombre' => $modalidad->nombre] : null,
            'plataforma' => $proceso->plataforma,
            'url_proceso' => $proceso->url_proceso,
        ];
    }

    /** @return list<string> */
    private function avisos(Contrato $contrato): array
    {
        $proceso = $contrato->proceso_licitacion !== null ? ProcesoLicitacion::find($contrato->proceso_licitacion) : null;
        if ($proceso?->monto === null) {
            return [];
        }

        $suma = BigDecimal::zero();
        $proceso->contratos()
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', '<>', Contrato::ANULADO))
            ->pluck('valor_inicial')
            ->each(function ($valor) use (&$suma) {
                $suma = $suma->plus(BigDecimal::of((string) ($valor ?? 0)));
            });
        $monto = BigDecimal::of($proceso->monto);

        return $suma->isGreaterThan($monto)
            ? ["Los contratos del proceso suman {$this->pesos($suma)}, más que su valor de {$this->pesos($monto)}."]
            : [];
    }

    /** $1.234.567,89 */
    private function pesos(BigDecimal $valor): string
    {
        [$entero, $decimales] = explode('.', (string) $valor->toScale(2, RoundingMode::HALF_UP));

        return '$' . number_format((int) $entero, 0, ',', '.') . ',' . $decimales;
    }

    private function bloquear(Contrato $contrato): Contrato
    {
        return Contrato::query()->whereKey($contrato->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Valores del pedido en el orden de CAMPOS, como quedarían guardados: textos recortados y
     * vacíos como null, enteros como enteros, avances redondeados, el valor con dos decimales.
     */
    private function valores(array $datos): array
    {
        $valores = array_intersect_key(array_replace(self::CAMPOS, $datos), $datos, self::CAMPOS);
        if (array_key_exists('anticipo', $valores)) {
            $valores['anticipo'] = filter_var($valores['anticipo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
        foreach ($valores as $campo => $valor) {
            if ($valor === null) {
                continue;
            }
            $texto = trim((string) $valor);
            $valores[$campo] = match (true) {
                $campo === 'secop_id_contrato' => $texto !== '' ? strtoupper($texto) : null,
                in_array($campo, self::TEXTOS, true) => $texto !== '' ? $texto : null,
                in_array($campo, self::ENTEROS, true) => (int) $valor,
                in_array($campo, self::AVANCES, true) => (int) round((float) $valor),
                $campo === 'valor_inicial' => (string) BigDecimal::of((string) $valor)->toScale(2),
                default => $valor,
            };
        }

        $faltan = array_filter(
            self::OBLIGATORIOS,
            fn (string $campo) => array_key_exists($campo, $valores) && $valores[$campo] === null,
            ARRAY_FILTER_USE_KEY
        );
        if ($faltan !== []) {
            throw ValidationException::withMessages($faltan);
        }

        return $valores;
    }

    /** El tipo del pedido existe y está activo, o es el que el contrato ya tiene. */
    private function exigirTipo(array $valores, ?Contrato $actual): void
    {
        $id = $valores['tipo_contrato_id'] ?? null;
        if ($id === null) {
            return;
        }
        $tipo = TipoProceso::query()->find($id, ['id', 'activo']);
        if ($tipo === null) {
            throw ValidationException::withMessages(['tipo_contrato_id' => 'Elige un tipo de contrato de la lista.']);
        }
        if (! $tipo->activo && $id !== $actual?->tipo_contrato_id) {
            throw ValidationException::withMessages(['tipo_contrato_id' => 'El tipo de contrato elegido está inactivo: elige otro.']);
        }
    }

    /**
     * El proyecto existe y el proceso es suyo (R3) y admite contratos si es un vínculo nuevo. Sin
     * proyecto, el contrato toma el del proceso. El proceso queda bloqueado: así no se elimina ni
     * se pierde de vista mientras se le vincula el contrato.
     *
     * @return array{0: array, 1: ?ProcesoLicitacion} los valores (con el proyecto que corresponda) y el proceso
     */
    private function exigirVinculos(array $valores, ?Contrato $actual): array
    {
        $proyectoId = array_key_exists('proyecto', $valores) ? $valores['proyecto'] : $this->entero($actual?->proyecto);
        $procesoActual = $this->entero($actual?->proceso_licitacion);
        $procesoId = array_key_exists('proceso_id', $valores) ? $valores['proceso_id'] : $procesoActual;

        if ($proyectoId !== null && ! Proyecto::query()->whereKey($proyectoId)->exists()) {
            throw ValidationException::withMessages(['proyecto' => 'Elige un proyecto de la lista.']);
        }
        if ($procesoId === null) {
            return [$valores, null];
        }

        $proceso = ProcesoLicitacion::query()->whereKey($procesoId)->lockForUpdate()->first();
        if ($proceso === null) {
            throw ValidationException::withMessages(['proceso_id' => 'Elige un proceso de la lista.']);
        }
        if ($procesoId !== $procesoActual && ! in_array($proceso->estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true)) {
            throw ValidationException::withMessages(['proceso_id' => 'Solo se puede vincular un contrato a un proceso adjudicado o celebrado.']);
        }
        if ($proyectoId === null) {
            $valores['proyecto'] = $proceso->proyecto;
        } elseif ($proyectoId !== $proceso->proyecto) {
            throw ValidationException::withMessages(['proceso_id' => 'El proceso elegido es de otro proyecto.']);
        }

        return [$valores, $proceso];
    }

    /** La fecha fin inicial no es anterior a la de inicio; `$valores` trae las dos, como quedarían. */
    private function exigirFechas(array $valores): void
    {
        $inicio = $valores['fecha_inicio'] ?? null;
        $fin = $valores['fecha_fin_inicial'] ?? null;
        if ($inicio !== null && $fin !== null && $fin < $inicio) {
            throw ValidationException::withMessages(['fecha_fin_inicial' => self::FIN_ANTERIOR]);
        }
    }

    /** @param list<array{descripcion: string, nombreArchivo: string, rutaArchivo: string, fecha: string}> $anexos */
    private function guardarAnexos(Contrato $contrato, array $anexos): void
    {
        foreach ($anexos as $anexo) {
            $contrato->anexos()->create([
                'descripcion' => $anexo['descripcion'],
                'nombre_archivo' => $anexo['nombreArchivo'],
                'ruta_archivo' => $anexo['rutaArchivo'],
                'fecha' => $anexo['fecha'],
            ]);
        }
    }

    /** @return list<array{descripcion: ?string, nombre_archivo: ?string, ruta_archivo: ?string, fecha: ?string}> los anexos guardados, en orden */
    private function anexosDe(Contrato $contrato): array
    {
        return $contrato->anexos()->orderBy('id')->get()->map(fn (AnexoContrato $a) => [
            'descripcion' => $a->descripcion,
            'nombre_archivo' => $a->nombre_archivo,
            'ruta_archivo' => $a->ruta_archivo,
            'fecha' => $a->fecha?->format('Y-m-d'),
        ])->all();
    }

    /** Borra el archivo de un anexo si existe y está dentro de public/anexos_contratos. */
    private function borrarArchivo(?string $ruta): void
    {
        $carpeta = realpath(public_path(self::CARPETA_ANEXOS));
        $archivo = $ruta !== null ? realpath(public_path($ruta)) : false;
        if ($carpeta !== false && $archivo !== false && is_file($archivo)
            && str_starts_with($archivo, $carpeta . DIRECTORY_SEPARATOR)) {
            unlink($archivo);
        }
    }

    /** @return array<string, mixed> los campos de la API con los valores guardados, normalizados como valores() */
    private function valoresDe(Contrato $contrato): array
    {
        $valores = [];
        foreach (self::CAMPOS as $campo => $columna) {
            $valor = $contrato->{$columna};
            $valores[$campo] = match (true) {
                $valor === null => null,
                $campo === 'secop_consultado_at' => $valor->format('Y-m-d H:i:s'),
                $valor instanceof DateTimeInterface => $valor->format('Y-m-d'),
                in_array($campo, self::ENTEROS, true), in_array($campo, self::AVANCES, true) => (int) $valor,
                $campo === 'valor_inicial' => (string) BigDecimal::of((string) $valor)->toScale(2),
                $campo === 'anticipo' => (bool) $valor,
                default => $valor,
            };
        }

        return $valores;
    }

    private function columnas(array $valores): array
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS[$campo]] = $valor;
        }

        return $columnas;
    }

    private function entero(mixed $valor): ?int
    {
        return $valor === null ? null : (int) $valor;
    }
}
