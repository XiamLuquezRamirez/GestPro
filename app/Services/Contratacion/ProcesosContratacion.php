<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\Contrato;
use App\Models\Entidad;
use App\Models\FuenteFinanciacion;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\Responsable;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría...).
 *
 * Reglas:
 * - El objeto, la modalidad y la plataforma son obligatorios; el número, el enlace, la entidad
 *   contratante y el valor, opcionales.
 * - Opcionales: la fuente de financiación, el responsable, las fechas de apertura y de cierre y
 *   las observaciones. El cierre no puede ser anterior a la apertura (se compara con lo que
 *   quedaría guardado, no solo con lo que trae el pedido).
 * - La modalidad, la entidad, la fuente y el responsable deben existir y estar activos. Al editar
 *   se acepta el que el proceso ya tiene aunque se haya desactivado: los catálogos se desactivan,
 *   no se eliminan.
 * - Un proceso nuevo empieza en preparación. El estado solo cambia con cambiarEstado(): lo
 *   cambia una persona, sin transiciones prohibidas (el checklist solo avisa), pero no al mismo
 *   estado.
 * - No se elimina un proceso con contratos vinculados (contratos.proceso_licitacion).
 * - Todo queda en checklist_historial (entidad_tipo = proceso, sin expediente): al editar, solo
 *   lo que cambió; al eliminar, los datos para saber qué era.
 * - Los cambios sobre un proceso bloquean su fila: dos ediciones no se pisan en el historial.
 * - Editar y cambiar el estado traen `actualizado`, el de presentar() cuando se abrió el
 *   formulario: si ya no es el de la fila, otra persona lo cambió entretanto y se rechaza (409)
 *   en vez de deshacer en silencio lo suyo. Cada cambio adelanta updated_at al menos un segundo.
 */
final class ProcesosContratacion
{
    use ControlDeVersion;

    /** Campo de la API => columna del proceso. */
    private const CAMPOS = [
        'objeto' => 'objeto',
        'modalidad_id' => 'modalidad',
        'plataforma' => 'plataforma',
        'codigo_proceso' => 'codigo_proceso',
        'url_proceso' => 'url_proceso',
        'entidad_contratante_id' => 'entidad_contratante',
        'monto' => 'monto',
        'fuente_id' => 'fuente_id',
        'responsable_id' => 'responsable_id',
        'fecha_apertura' => 'fecha_apertura',
        'fecha_cierre' => 'fecha_cierre',
        'observaciones' => 'observaciones',
    ];

    private const TEXTOS = ['objeto', 'codigo_proceso', 'url_proceso', 'observaciones'];

    private const OBLIGATORIOS = [
        'objeto' => 'Escribe el objeto del proceso.',
        'modalidad_id' => 'Elige la modalidad del proceso.',
        'plataforma' => 'Elige la plataforma del proceso.',
    ];

    public const CIERRE_ANTERIOR = 'La fecha de cierre no puede ser anterior a la de apertura.';

    public const DESACTUALIZADO = 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre', 'usuarioCreo:id,name'];

    public function __construct(private readonly HistorialChecklist $historial)
    {
    }

    /** Los procesos del proyecto en el orden en que se crearon, con la forma de presentar(). */
    public function deProyecto(Proyecto $proyecto): array
    {
        return ProcesoLicitacion::query()
            ->where('proyecto', $proyecto->id)
            ->with(self::RELACIONES)
            ->withCount('contratos')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcesoLicitacion $proceso) => $this->forma($proceso))
            ->all();
    }

    public function presentar(ProcesoLicitacion $proceso): array
    {
        return $this->forma($proceso->load(self::RELACIONES)->loadCount('contratos'));
    }

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), ...$datos]);

        return DB::transaction(function () use ($proyecto, $valores, $usuario) {
            $this->exigirCatalogos($valores, null);
            $this->exigirFechas($valores);
            $proceso = ProcesoLicitacion::create([
                ...$this->columnas($valores),
                'proyecto' => $proyecto->id,
                'estado' => ProcesoLicitacion::PREPARACION,
                'usuario_creo_id' => $usuario?->id,
            ]);
            $this->historial->registrarProceso($proceso, 'proceso_creado', [], [
                'proyecto_id' => $proyecto->id,
                ...$this->valoresDe($proceso),
                'estado' => $proceso->estado,
            ], $usuario);

            return $proceso;
        });
    }

    /**
     * Cambia los campos que trae el pedido; los demás no cambian. El estado no se edita aquí.
     * `$actualizado` es el `actualizado` del proceso que tenía el formulario.
     *
     * @param array{objeto?: string, modalidad_id?: int, plataforma?: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null, fuente_id?: ?int, responsable_id?: ?int, fecha_apertura?: ?string, fecha_cierre?: ?string, observaciones?: ?string} $datos
     */
    public function editar(ProcesoLicitacion $proceso, array $datos, ?string $actualizado, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($proceso, $valores, $actualizado, $usuario) {
            // Compara con la fila bloqueada: así también se decide sobre los catálogos y las
            // fechas que el proceso tiene ahora, no los de cuando llegó el pedido.
            $actual = $this->bloquearVigente($proceso, $actualizado);
            $this->exigirCatalogos($valores, $actual);
            $antes = $this->valoresDe($actual);
            $this->exigirFechas([...$antes, ...$valores]);
            $cambios = array_filter($valores, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios !== []) {
                $this->adelantarActualizado($actual);
                $actual->update($this->columnas($cambios));
                $this->historial->registrarProceso($actual, 'proceso_editado', array_intersect_key($antes, $cambios), $cambios, $usuario);
            }

            return $actual;
        });
    }

    /** `$actualizado` es el `actualizado` del proceso que tenía el formulario. */
    public function cambiarEstado(ProcesoLicitacion $proceso, string $estado, ?string $observacion, ?string $actualizado, ?User $usuario): ProcesoLicitacion
    {
        $observacion = trim((string) $observacion);
        $observacion = $observacion !== '' ? $observacion : null;

        return DB::transaction(function () use ($proceso, $estado, $observacion, $actualizado, $usuario) {
            // Primero la versión: con un formulario viejo, "ya está en ese estado" engañaría.
            $actual = $this->bloquearVigente($proceso, $actualizado);
            $anterior = $actual->estado;
            if ($anterior === $estado) {
                throw new ReglaNegocioException('El proceso ya está en ese estado.');
            }

            $this->adelantarActualizado($actual);
            $actual->update(['estado' => $estado]);
            $this->historial->registrarProceso($actual, 'proceso_estado_cambiado', ['estado' => $anterior], [
                'estado' => $estado,
                'observacion' => $observacion,
            ], $usuario);

            return $actual;
        });
    }

    public function eliminar(ProcesoLicitacion $proceso, ?User $usuario): void
    {
        DB::transaction(function () use ($proceso, $usuario) {
            // Con la fila bloqueada, un contrato que se vincule a la vez espera y luego no
            // encuentra el proceso, o termina antes y aquí se cuenta.
            $actual = $this->bloquear($proceso);
            $contratos = $actual->contratos()->count();
            if ($contratos > 0) {
                throw new ReglaNegocioException($contratos === 1
                    ? 'El proceso tiene 1 contrato vinculado: no se puede eliminar.'
                    : "El proceso tiene {$contratos} contratos vinculados: no se puede eliminar.");
            }

            // El registro sobrevive al proceso: con el proyecto y los nombres de los catálogos.
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre', 'fuente:id,nombre', 'responsable:id,nombre']);
            $datos = $this->valoresDe($actual);
            $this->historial->registrarProceso($actual, 'proceso_eliminado', [
                'proyecto_id' => $actual->proyecto,
                'objeto' => $datos['objeto'],
                'modalidad_id' => $datos['modalidad_id'],
                'modalidad' => $actual->modalidadRel?->nombre,
                'plataforma' => $datos['plataforma'],
                'codigo_proceso' => $datos['codigo_proceso'],
                'url_proceso' => $datos['url_proceso'],
                'entidad_contratante_id' => $datos['entidad_contratante_id'],
                'entidad_contratante' => $actual->entidadContratante?->nombre,
                'monto' => $datos['monto'],
                'fuente_id' => $datos['fuente_id'],
                'fuente' => $actual->fuente?->nombre,
                'responsable_id' => $datos['responsable_id'],
                'responsable' => $actual->responsable?->nombre,
                'fecha_apertura' => $datos['fecha_apertura'],
                'fecha_cierre' => $datos['fecha_cierre'],
                'observaciones' => $datos['observaciones'],
                'estado' => $actual->estado,
            ], [], $usuario);
            $actual->delete();
        });
    }

    /**
     * Lo que se sabe de un proceso para precargar el formulario de un contrato nuevo: el proceso
     * resumido, si admite contratos y los datos sugeridos. El valor sugerido es el monto menos lo
     * que ya suman los contratos vigentes del proceso (los anulados no cuentan), nunca negativo;
     * sin monto no hay sugerencia.
     *
     * @return array{proceso: array, admite_contratos: bool, precarga: array}
     */
    public function precargaContrato(ProcesoLicitacion $proceso): array
    {
        $proceso->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre']);
        $modalidad = $proceso->modalidadRel;

        return [
            'proceso' => [
                'id' => $proceso->id,
                'codigo_proceso' => $proceso->codigo_proceso,
                'objeto' => $proceso->objeto,
                'estado' => $proceso->estado,
                'modalidad' => $modalidad ? ['id' => $modalidad->id, 'nombre' => $modalidad->nombre] : null,
                'plataforma' => $proceso->plataforma,
                'url_proceso' => $proceso->url_proceso,
                'monto' => $proceso->monto,
            ],
            'admite_contratos' => in_array($proceso->estado, ProcesoLicitacion::ADMITEN_CONTRATOS, true),
            'precarga' => [
                'objeto' => $proceso->objeto,
                'contratante' => $proceso->entidadContratante?->nombre,
                'valor_inicial' => $this->valorSugerido($proceso),
            ],
        ];
    }

    private function valorSugerido(ProcesoLicitacion $proceso): ?string
    {
        if ($proceso->monto === null) {
            return null;
        }
        $restante = BigDecimal::of($proceso->monto);
        $proceso->contratos()
            ->where(fn ($q) => $q->whereNull('estado')->orWhere('estado', '<>', Contrato::ANULADO))
            ->pluck('valor_inicial')
            ->each(function ($valor) use (&$restante) {
                $restante = $restante->minus(BigDecimal::of((string) ($valor ?? 0)));
            });

        return (string) ($restante->isNegative() ? BigDecimal::zero() : $restante)->toScale(2);
    }

    private function bloquear(ProcesoLicitacion $proceso): ProcesoLicitacion
    {
        return ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
    }

    /** La fila bloqueada, si sigue siendo la versión que tenía el formulario (ControlDeVersion). */
    private function bloquearVigente(ProcesoLicitacion $proceso, ?string $actualizado): ProcesoLicitacion
    {
        $actual = $this->bloquear($proceso);
        $this->exigirVersion($actual, $actualizado, self::DESACTUALIZADO);

        return $actual;
    }

    /**
     * Valores del pedido en el orden de CAMPOS: textos recortados y vacíos como null, y el valor
     * con dos decimales, como lo devuelve el modelo, para comparar con lo guardado.
     */
    private function valores(array $datos): array
    {
        $valores = array_intersect_key(array_replace(self::CAMPOS, $datos), $datos, self::CAMPOS);
        foreach (self::TEXTOS as $campo) {
            if (array_key_exists($campo, $valores) && $valores[$campo] !== null) {
                $texto = trim((string) $valores[$campo]);
                $valores[$campo] = $texto !== '' ? $texto : null;
            }
        }
        if (isset($valores['monto'])) {
            $valores['monto'] = (string) BigDecimal::of((string) $valores['monto'])->toScale(2);
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

    /** Los catálogos del pedido existen y están activos, o son los que el proceso ya tiene. */
    private function exigirCatalogos(array $valores, ?ProcesoLicitacion $actual): void
    {
        $errores = array_filter([
            'modalidad_id' => $this->errorCatalogo(Modalidad::class, $valores['modalidad_id'] ?? null, $actual?->modalidad, 'modalidad'),
            'entidad_contratante_id' => $this->errorCatalogo(Entidad::class, $valores['entidad_contratante_id'] ?? null, $actual?->entidad_contratante, 'entidad'),
            'fuente_id' => $this->errorCatalogo(FuenteFinanciacion::class, $valores['fuente_id'] ?? null, $actual?->fuente_id, 'fuente'),
            'responsable_id' => $this->errorCatalogo(Responsable::class, $valores['responsable_id'] ?? null, $actual?->responsable_id, 'responsable', false),
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** @param class-string<Model> $modelo */
    private function errorCatalogo(string $modelo, ?int $id, ?int $actual, string $nombre, bool $femenino = true): ?string
    {
        if ($id === null) {
            return null;
        }
        $fila = $modelo::query()->find($id, ['id', 'activo']);
        if ($fila === null) {
            return ($femenino ? 'Elige una' : 'Elige un') . " {$nombre} de la lista.";
        }
        if ($fila->activo || $id === $actual) {
            return null;
        }

        return $femenino ? "La {$nombre} elegida está inactiva: elige otra." : "El {$nombre} elegido está inactivo: elige otro.";
    }

    /** El cierre no es anterior a la apertura; `$valores` trae las dos fechas, como quedarían. */
    private function exigirFechas(array $valores): void
    {
        $apertura = $valores['fecha_apertura'] ?? null;
        $cierre = $valores['fecha_cierre'] ?? null;
        if ($apertura !== null && $cierre !== null && $cierre < $apertura) {
            throw new ReglaNegocioException(self::CIERRE_ANTERIOR, [self::CIERRE_ANTERIOR], ['fecha_cierre' => [self::CIERRE_ANTERIOR]]);
        }
    }

    /** @return array<string, mixed> los campos de la API con los valores guardados (las fechas como año-mes-día) */
    private function valoresDe(ProcesoLicitacion $proceso): array
    {
        return array_map(function (string $columna) use ($proceso) {
            $valor = $proceso->{$columna};

            return $valor instanceof DateTimeInterface ? $valor->format('Y-m-d') : $valor;
        }, self::CAMPOS);
    }

    private function columnas(array $valores): array
    {
        $columnas = [];
        foreach ($valores as $campo => $valor) {
            $columnas[self::CAMPOS[$campo]] = $valor;
        }

        return $columnas;
    }

    private function forma(ProcesoLicitacion $proceso): array
    {
        $modalidad = $proceso->modalidadRel;
        $entidad = $proceso->entidadContratante;
        $fuente = $proceso->fuente;
        $responsable = $proceso->responsable;

        return [
            'id' => $proceso->id,
            'objeto' => $proceso->objeto,
            'modalidad' => $modalidad ? [
                'id' => $modalidad->id,
                'nombre' => $modalidad->nombre,
                'clave' => $modalidad->clave,
                'activo' => $modalidad->activo,
            ] : null,
            'plataforma' => $proceso->plataforma,
            'codigo_proceso' => $proceso->codigo_proceso,
            'url_proceso' => $proceso->url_proceso,
            'entidad_contratante' => $entidad ? ['id' => $entidad->id, 'nombre' => $entidad->nombre] : null,
            'monto' => $proceso->monto,
            'fuente' => $fuente ? ['id' => $fuente->id, 'nombre' => $fuente->nombre] : null,
            'responsable' => $responsable ? ['id' => $responsable->id, 'nombre' => $responsable->nombre] : null,
            'fecha_apertura' => $proceso->fecha_apertura?->format('Y-m-d'),
            'fecha_cierre' => $proceso->fecha_cierre?->format('Y-m-d'),
            'observaciones' => $proceso->observaciones,
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
        ];
    }
}
