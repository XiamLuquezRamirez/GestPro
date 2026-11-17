<?php

namespace App\Services\Contratacion;

use App\Exceptions\EdicionDesactualizadaException;
use App\Exceptions\ReglaNegocioException;
use App\Models\Entidad;
use App\Models\Modalidad;
use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Viabilizacion\HistorialChecklist;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Procesos de contratación de un proyecto (la obra, la interventoría...).
 *
 * Reglas:
 * - El objeto, la modalidad y la plataforma son obligatorios; el número, el enlace, la entidad
 *   contratante y el valor, opcionales.
 * - La modalidad y la entidad deben existir y estar activas. Al editar se acepta la que el
 *   proceso ya tiene aunque se haya desactivado: los catálogos se desactivan, no se eliminan.
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
    /** Campo de la API => columna del proceso. */
    private const CAMPOS = [
        'objeto' => 'objeto',
        'modalidad_id' => 'modalidad',
        'plataforma' => 'plataforma',
        'codigo_proceso' => 'codigo_proceso',
        'url_proceso' => 'url_proceso',
        'entidad_contratante_id' => 'entidad_contratante',
        'monto' => 'monto',
    ];

    private const TEXTOS = ['objeto', 'codigo_proceso', 'url_proceso'];

    private const OBLIGATORIOS = [
        'objeto' => 'Escribe el objeto del proceso.',
        'modalidad_id' => 'Elige la modalidad del proceso.',
        'plataforma' => 'Elige la plataforma del proceso.',
    ];

    public const DESACTUALIZADO = 'Otra persona cambió este proceso mientras lo editabas. Ciérralo y vuelve a abrirlo para ver lo último.';

    private const RELACIONES = ['modalidadRel:id,nombre,clave,activo', 'entidadContratante:id,nombre', 'usuarioCreo:id,name'];

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

    /** @param array{objeto: string, modalidad_id: int, plataforma: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null} $datos */
    public function crear(Proyecto $proyecto, array $datos, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores([...array_fill_keys(array_keys(self::CAMPOS), null), ...$datos]);

        return DB::transaction(function () use ($proyecto, $valores, $usuario) {
            $this->exigirCatalogos($valores, null);
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
     * @param array{objeto?: string, modalidad_id?: int, plataforma?: string, codigo_proceso?: ?string, url_proceso?: ?string, entidad_contratante_id?: ?int, monto?: int|float|string|null} $datos
     */
    public function editar(ProcesoLicitacion $proceso, array $datos, ?string $actualizado, ?User $usuario): ProcesoLicitacion
    {
        $valores = $this->valores($datos);

        return DB::transaction(function () use ($proceso, $valores, $actualizado, $usuario) {
            // Compara con la fila bloqueada: así también se decide sobre la modalidad y la
            // entidad que el proceso tiene ahora, no las de cuando llegó el pedido.
            $actual = $this->bloquearVigente($proceso, $actualizado);
            $this->exigirCatalogos($valores, $actual);
            $antes = $this->valoresDe($actual);
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
            $actual->load(['modalidadRel:id,nombre', 'entidadContratante:id,nombre']);
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
                'estado' => $actual->estado,
            ], [], $usuario);
            $actual->delete();
        });
    }

    private function bloquear(ProcesoLicitacion $proceso): ProcesoLicitacion
    {
        return ProcesoLicitacion::query()->whereKey($proceso->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * La fila bloqueada, si sigue siendo la versión que tenía el formulario: `$actualizado` cae en
     * el mismo segundo que updated_at, o ambos son null (un proceso anterior a las fechas).
     */
    private function bloquearVigente(ProcesoLicitacion $proceso, ?string $actualizado): ProcesoLicitacion
    {
        $actual = $this->bloquear($proceso);
        $esperado = $actualizado === null ? null : Carbon::parse($actualizado)->getTimestamp();
        if ($esperado !== $actual->updated_at?->getTimestamp()) {
            throw new EdicionDesactualizadaException(self::DESACTUALIZADO);
        }

        return $actual;
    }

    /**
     * updated_at se guarda sin fracciones de segundo: si un cambio cae en el mismo segundo que el
     * anterior, se adelanta uno para que `actualizado` cambie siempre y el control de versión no
     * deje pasar un formulario viejo.
     */
    private function adelantarActualizado(ProcesoLicitacion $proceso): void
    {
        $ahora = Carbon::now();
        $siguiente = $proceso->updated_at?->copy()->startOfSecond()->addSecond();
        $proceso->updated_at = $siguiente !== null && $siguiente->greaterThan($ahora) ? $siguiente : $ahora;
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

    /** La modalidad y la entidad del pedido existen y están activas, o son las que el proceso ya tiene. */
    private function exigirCatalogos(array $valores, ?ProcesoLicitacion $actual): void
    {
        $errores = array_filter([
            'modalidad_id' => $this->errorCatalogo(Modalidad::class, $valores['modalidad_id'] ?? null, $actual?->modalidad, 'modalidad'),
            'entidad_contratante_id' => $this->errorCatalogo(Entidad::class, $valores['entidad_contratante_id'] ?? null, $actual?->entidad_contratante, 'entidad'),
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** @param class-string<Model> $modelo */
    private function errorCatalogo(string $modelo, ?int $id, ?int $actual, string $nombre): ?string
    {
        if ($id === null) {
            return null;
        }
        $fila = $modelo::query()->find($id, ['id', 'activo']);
        if ($fila === null) {
            return "Elige una {$nombre} de la lista.";
        }

        return $fila->activo || $id === $actual ? null : "La {$nombre} elegida está inactiva: elige otra.";
    }

    /** @return array<string, mixed> los campos de la API con los valores guardados */
    private function valoresDe(ProcesoLicitacion $proceso): array
    {
        return array_map(fn (string $columna) => $proceso->{$columna}, self::CAMPOS);
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
            'estado' => $proceso->estado,
            'contratos' => (int) $proceso->contratos_count,
            'creado' => $proceso->created_at?->toIso8601String(),
            'actualizado' => $proceso->updated_at?->toIso8601String(),
            'usuario_creo' => $proceso->usuarioCreo?->name,
        ];
    }
}
