<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
use App\Models\RequisitoSector;
use App\Models\RequisitoTipo;
use App\Models\RequisitoTipologia;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Administración del catálogo maestro (rol Administrador).
 *
 * Cada plantilla tiene sus versiones, con su propia numeración, un borrador y una
 * vigente como máximo. Sin indicar plantilla, se trabaja sobre la SGR.
 *
 * Una versión publicada es inmutable: de eso depende que los expedientes ya
 * evaluados no cambien. Para modificar requisitos se crea un borrador (copia de
 * la vigente), se edita y se publica; al publicar pasa a ser la vigente y los
 * expedientes nuevos la usan. Los existentes conservan su versión.
 *
 * Sectores, tipos, componentes y tipologías no se versionan: su `clave` es
 * inmutable (la usan el DSL y los requisitos) y en vez de borrarse se desactivan.
 */
final class CatalogoService
{
    public const ENTIDADES = [
        'sectores' => RequisitoSector::class,
        'tipos' => RequisitoTipo::class,
        'componentes' => RequisitoComponente::class,
        'tipologias' => RequisitoTipologia::class,
    ];

    public function __construct(private readonly EvaluadorRequisitos $evaluador)
    {
    }

    // --------------------------------------------------------------- lectura

    /** Catálogo SGR vigente para armar la configuración de un expediente. */
    public function vigente(bool $incluirInactivos = false): array
    {
        $version = PlantillaChecklist::sgr()->versionVigente()
            ?? throw new ReglaNegocioException('No hay una versión vigente del catálogo de requisitos.');

        $filtrar = fn ($q) => $incluirInactivos ? $q : $q->where('activo', true);

        return [
            'version' => ['id' => $version->id, 'numero' => $version->numero, 'fecha_publicacion' => $version->fecha_publicacion?->toIso8601String()],
            'sectores' => $filtrar(RequisitoSector::query())
                ->with(['tipos' => fn ($q) => $filtrar($q)->orderBy('orden')])
                ->orderBy('orden')->get()
                ->map(fn (RequisitoSector $s) => [
                    'id' => $s->id, 'clave' => $s->clave, 'nombre' => $s->nombre, 'fuente' => $s->fuente, 'activo' => $s->activo,
                    'tipos' => $s->tipos->map(fn ($t) => ['id' => $t->id, 'clave' => $t->clave, 'nombre' => $t->nombre, 'activo' => $t->activo])->values(),
                ])->values(),
            'componentes' => $this->listar($filtrar(RequisitoComponente::query())),
            'tipologias' => $this->listar($filtrar(RequisitoTipologia::query())),
        ];
    }

    // ------------------------------------------------------------- versiones

    public function crearBorrador(?string $descripcion, ?PlantillaChecklist $plantilla = null): CatalogoVersion
    {
        $plantilla ??= PlantillaChecklist::sgr();

        if ($plantilla->versiones()->where('estado', CatalogoVersion::BORRADOR)->exists()) {
            throw new ReglaNegocioException('Ya existe una versión en borrador; publícala o elimínala antes de crear otra.');
        }
        $vigente = $plantilla->versionVigente();
        // Sin versiones publicadas, el borrador nace vacío: es la primera versión de la plantilla.
        if (! $vigente && $plantilla->versiones()->where('estado', CatalogoVersion::PUBLICADA)->exists()) {
            throw new ReglaNegocioException('No hay una versión vigente que copiar.');
        }

        return DB::transaction(function () use ($plantilla, $vigente, $descripcion) {
            $borrador = $plantilla->versiones()->create([
                'numero' => (int) $plantilla->versiones()->max('numero') + 1,
                'estado' => CatalogoVersion::BORRADOR,
                'descripcion' => $descripcion,
                'vigente' => false,
            ]);

            $columnas = [
                'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
                'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
                'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
                'requiere_aprobacion_no_aplica', 'codigo_comun',
            ];
            $vigente?->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
                ->chunk(100)
                ->each(fn ($lote) => DB::table('requisitos_catalogo')->insert($lote->values()->all()));

            return $borrador;
        });
    }

    public function publicar(CatalogoVersion $version, ?User $usuario = null): CatalogoVersion
    {
        // Se relee la fila con bloqueo: una edición en curso termina antes, y una que llega después ve la versión publicada.
        $this->enBorrador($version, function (CatalogoVersion $actual) use ($usuario) {
            $plantilla = $actual->plantilla;

            if (! $actual->requisitos()->where('activo', true)->exists()) {
                throw new ReglaNegocioException('La versión no tiene requisitos activos y no puede publicarse.');
            }
            $sinFuentes = ! $plantilla->todas_fuentes && ! $plantilla->fuentes()->exists();
            $sinSectores = ! $plantilla->todos_sectores && ! $plantilla->sectores()->exists();
            if ($sinFuentes || $sinSectores) {
                throw new ReglaNegocioException('Asocia la plantilla a sus fuentes y sectores antes de publicar.');
            }

            [$errores, $mensaje] = $plantilla->motor === PlantillaChecklist::MOTOR_SGR
                ? [$this->erroresDeCondiciones($actual), 'La versión tiene condiciones inválidas y no puede publicarse.']
                : [$this->erroresDeCondicionados($actual), 'La versión tiene requisitos condicionados sin describir y no puede publicarse.'];
            if ($errores) {
                throw new ReglaNegocioException($mensaje, $errores);
            }

            CatalogoVersion::where('plantilla_id', $actual->plantilla_id)
                ->where('id', '!=', $actual->id)
                ->update(['vigente' => false]);
            $actual->update([
                'estado' => CatalogoVersion::PUBLICADA,
                'fecha_publicacion' => now(),
                'usuario_publico_id' => $usuario?->id,
                'vigente' => true,
            ]);
        });

        return $version->refresh();
    }

    /** Motor sgr: cada condición debe ser válida en el DSL. En la sección C es opcional. */
    private function erroresDeCondiciones(CatalogoVersion $version): array
    {
        $errores = [];
        $componentes = RequisitoComponente::pluck('clave')->all();
        $tipologias = RequisitoTipologia::pluck('clave')->all();
        $conCondicion = $version->requisitos()->where('activo', true)
            ->where(fn ($q) => $q->where('seccion', '!=', 'C')->orWhereNotNull('condicion'))
            ->get();
        foreach ($conCondicion as $r) {
            foreach ($this->evaluador->validarCondicion($r->condicion, $componentes, $tipologias) as $error) {
                $errores[] = "{$r->clave}: {$error}";
            }
        }

        return $errores;
    }

    /** Motor simple: un requisito condicionado debe decir cuándo aplica, para que una persona lo decida. */
    private function erroresDeCondicionados(CatalogoVersion $version): array
    {
        // Se compara en PHP, sin mayúsculas ni tildes, para que MariaDB y SQLite decidan igual.
        $normalizar = fn (?string $texto) => Str::lower(Str::ascii((string) $texto));
        $marcador = 'condicion original:';

        return $version->requisitos()->where('activo', true)
            ->where('caracter', RequisitoCatalogo::CONDICIONADO)
            ->orderBy('orden')
            ->get()
            ->map(function (RequisitoCatalogo $r) use ($normalizar, $marcador) {
                if (str_contains($normalizar($r->condicion_descripcion), $marcador)) {
                    return "{$r->texto}: reemplaza la \"Condición original:\" por una descripción en palabras de cuándo aplica.";
                }

                return trim((string) $r->condicion_descripcion) === ''
                    ? "{$r->texto}: describe cuándo aplica este requisito condicionado."
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public function eliminarBorrador(CatalogoVersion $version): void
    {
        $this->enBorrador($version, function (CatalogoVersion $actual) {
            $actual->delete(); // sus requisitos caen en cascada; ningún expediente usa un borrador
        });
    }

    // ------------------------------------------------------------ requisitos

    public function guardarRequisito(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito = null): RequisitoCatalogo
    {
        return $this->enBorrador($version, fn (CatalogoVersion $actual) => $this->guardarRequisitoEnBorrador($actual, $datos, $requisito));
    }

    private function guardarRequisitoEnBorrador(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito): RequisitoCatalogo
    {
        if ($requisito && $requisito->catalogo_version_id !== $version->id) {
            throw new ReglaNegocioException('El requisito no pertenece a esta versión.');
        }

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
            return $this->guardarRequisitoSimple($version, $datos, $requisito);
        }

        $errores = [];
        $seccion = $datos['seccion'];

        $claveRepetida = RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where('clave', $datos['clave'])
            ->when($requisito, fn ($q) => $q->where('id', '!=', $requisito->id))
            ->exists();
        if ($claveRepetida) {
            $errores[] = "Ya existe un requisito con la clave \"{$datos['clave']}\" en esta versión.";
        }

        if ($seccion === 'C') {
            $sector = isset($datos['sector_id']) ? RequisitoSector::find($datos['sector_id']) : null;
            if (! $sector) {
                $errores[] = 'Los requisitos sectoriales (sección C) requieren un sector.';
            }
            if (! empty($datos['tipo_id'])) {
                $tipo = RequisitoTipo::find($datos['tipo_id']);
                if (! $tipo || ($sector && $tipo->sector_id !== $sector->id)) {
                    $errores[] = 'El tipo de proyecto no pertenece al sector elegido.';
                }
            }
        } else {
            $datos['sector_id'] = null;
            $datos['tipo_id'] = null;
        }

        // Obligatoria en A/B/D. En C es opcional: sin ella, aplica en toda fase.
        $condicion = trim((string) ($datos['condicion'] ?? ''));
        if ($seccion !== 'C' || $condicion !== '') {
            foreach ($this->evaluador->validarCondicion($condicion,
                RequisitoComponente::pluck('clave')->all(), RequisitoTipologia::pluck('clave')->all()) as $error) {
                $errores[] = $error;
            }
        }
        $datos['condicion'] = $condicion !== '' ? $condicion : null;

        if ($errores) {
            throw new ReglaNegocioException('El requisito no es válido.', $errores);
        }

        $valores = [
            'catalogo_version_id' => $version->id,
            'clave' => $datos['clave'],
            'seccion' => $seccion,
            'fuente' => $datos['fuente'],
            'sector_id' => ($datos['sector_id'] ?? null) ?: null,
            'tipo_id' => ($datos['tipo_id'] ?? null) ?: null,
            'subtitulo' => array_key_exists('subtitulo', $datos) ? (trim((string) $datos['subtitulo']) ?: null) : $requisito?->subtitulo,
            'codigo' => $datos['codigo'],
            'texto' => $datos['texto'],
            'nota' => array_key_exists('nota', $datos) ? (trim((string) $datos['nota']) ?: null) : $requisito?->nota,
            'condicion' => $datos['condicion'],
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : ($requisito?->activo ?? true),
            ...$this->camposDeSeguimiento($datos, $requisito, sinCaracter: true),
        ];
        if ($version->plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
            // En la plantilla SGR el código común es la clave, también al editarla, y todo
            // "no aplica" se aprueba (decisión del usuario).
            $valores['codigo_comun'] = $datos['clave'];
            $valores['requiere_aprobacion_no_aplica'] = true;
        } else {
            // Otra plantilla con motor sgr (una copia): el código común lo define el administrador.
            $codigoComun = array_key_exists('codigo_comun', $datos)
                ? (mb_strtolower(trim((string) $datos['codigo_comun'])) ?: null)
                : $requisito?->codigo_comun;
            if ($this->codigoComunRepetido($version, $codigoComun, $requisito)) {
                throw new ReglaNegocioException('El requisito no es válido.', ["Ya existe un requisito con el código común \"{$codigoComun}\" en esta versión."]);
            }
            $valores['codigo_comun'] = $codigoComun;
            $valores['requiere_aprobacion_no_aplica'] = (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? true);
        }

        if ($requisito) {
            $requisito->update($valores);

            return $requisito;
        }

        return RequisitoCatalogo::create($valores);
    }

    /** Motor simple: sin sección ni condiciones automáticas; una persona decide si un condicionado aplica. */
    private function guardarRequisitoSimple(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito): RequisitoCatalogo
    {
        $errores = [];
        $clave = trim((string) ($datos['clave'] ?? '')) ?: ($requisito?->clave ?? $this->claveLibre($version));

        $repetida = fn (string $columna, ?string $valor) => $valor !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where($columna, $valor)
            ->when($requisito, fn ($q) => $q->where('id', '!=', $requisito->id))
            ->exists();
        if ($repetida('clave', $clave)) {
            $errores[] = "Ya existe un requisito con la clave \"{$clave}\" en esta versión.";
        }

        $seguimiento = $this->camposDeSeguimiento($datos, $requisito);
        $codigoComun = array_key_exists('codigo_comun', $datos)
            ? (mb_strtolower(trim((string) $datos['codigo_comun'])) ?: null)
            : $requisito?->codigo_comun;
        if ($repetida('codigo_comun', $codigoComun)) {
            $errores[] = "Ya existe un requisito con el código común \"{$codigoComun}\" en esta versión.";
        }
        if ($seguimiento['caracter'] === RequisitoCatalogo::CONDICIONADO && ! $seguimiento['condicion_descripcion']) {
            $errores[] = 'Un requisito condicionado necesita describir cuándo aplica.';
        }

        if ($errores) {
            throw new ReglaNegocioException('El requisito no es válido.', $errores);
        }

        $valores = [
            'catalogo_version_id' => $version->id,
            'clave' => $clave,
            'seccion' => null,
            'fuente' => null,
            'sector_id' => null,
            'tipo_id' => null,
            'condicion' => null,
            'subtitulo' => array_key_exists('subtitulo', $datos) ? (trim((string) $datos['subtitulo']) ?: null) : $requisito?->subtitulo,
            'codigo' => array_key_exists('codigo', $datos) ? (trim((string) $datos['codigo']) ?: null) : $requisito?->codigo,
            'texto' => $datos['texto'],
            'nota' => array_key_exists('nota', $datos) ? (trim((string) $datos['nota']) ?: null) : $requisito?->nota,
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : ($requisito?->activo ?? true),
            'codigo_comun' => $codigoComun,
            'requiere_aprobacion_no_aplica' => (bool) ($datos['requiere_aprobacion_no_aplica'] ?? $requisito?->requiere_aprobacion_no_aplica ?? false),
            ...$seguimiento,
        ];

        if ($requisito) {
            $requisito->update($valores);

            return $requisito;
        }

        return RequisitoCatalogo::create($valores);
    }

    /** Campos que la propuesta pide a todo requisito. Lo que no llega conserva su valor. */
    private function camposDeSeguimiento(array $datos, ?RequisitoCatalogo $requisito, bool $sinCaracter = false): array
    {
        $texto = fn (string $campo) => array_key_exists($campo, $datos)
            ? (trim((string) $datos[$campo]) ?: null)
            : $requisito?->{$campo};

        $campos = [
            'descripcion' => $texto('descripcion'),
            'categoria' => $texto('categoria'),
            'tipo_requisito' => $texto('tipo_requisito'),
            'cargo_cumplimiento' => $texto('cargo_cumplimiento'),
            'cargo_revision' => $texto('cargo_revision'),
            'soporte_requerido' => $texto('soporte_requerido'),
            'referencia_normativa' => $texto('referencia_normativa'),
            'condicion_descripcion' => $texto('condicion_descripcion'),
        ];
        // En el motor sgr el carácter es siempre obligatorio: qué aplica lo decide el evaluador.
        if (! $sinCaracter) {
            $campos['caracter'] = $datos['caracter'] ?? ($requisito?->caracter ?? RequisitoCatalogo::OBLIGATORIO);
        }

        return $campos;
    }

    /** La siguiente rN libre en toda la plantilla: la clave reconoce un requisito de una versión a otra. */
    private function claveLibre(CatalogoVersion $version): string
    {
        $mayor = RequisitoCatalogo::query()
            ->whereIn('catalogo_version_id', $version->plantilla->versiones()->select('id'))
            ->pluck('clave')
            ->map(fn ($clave) => preg_match('/^r(\d+)$/', $clave, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'r' . ($mayor + 1);
    }

    private function codigoComunRepetido(CatalogoVersion $version, ?string $codigo, ?RequisitoCatalogo $requisito): bool
    {
        return $codigo !== null && RequisitoCatalogo::where('catalogo_version_id', $version->id)
            ->where('codigo_comun', $codigo)
            ->when($requisito, fn ($q) => $q->where('id', '!=', $requisito->id))
            ->exists();
    }

    /** Orden de presentación: los ids en el orden en que deben mostrarse. */
    public function ordenarRequisitos(CatalogoVersion $version, array $ids): void
    {
        $this->enBorrador($version, function (CatalogoVersion $actual) use ($ids) {
            $propios = $actual->requisitos()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $pedidos = collect($ids)->map(fn ($id) => (int) $id);
            if ($pedidos->sort()->values()->all() !== $propios) {
                throw new ReglaNegocioException('El orden debe incluir todos los requisitos de la versión, una vez cada uno.');
            }

            foreach ($pedidos->values() as $posicion => $id) {
                RequisitoCatalogo::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });
    }

    public function eliminarRequisito(CatalogoVersion $version, RequisitoCatalogo $requisito): void
    {
        $this->enBorrador($version, function (CatalogoVersion $actual) use ($requisito) {
            if ($requisito->catalogo_version_id !== $actual->id) {
                throw new ReglaNegocioException('El requisito no pertenece a esta versión.');
            }
            $requisito->delete();
        });
    }

    // ------------------------------------- sectores, tipos, componentes...

    public function guardarEntidad(string $entidad, array $datos, ?Model $item = null): Model
    {
        $modelo = self::ENTIDADES[$entidad] ?? throw new ReglaNegocioException("Catálogo desconocido: {$entidad}");

        if ($item) {
            // La clave no cambia: la referencian el DSL y los requisitos.
            unset($datos['clave'], $datos['sector_id']);
            $item->update($datos);

            return $item;
        }

        $existe = $modelo::query()
            ->where('clave', $datos['clave'])
            ->when($entidad === 'tipos', fn ($q) => $q->where('sector_id', $datos['sector_id'] ?? null))
            ->exists();
        if ($existe) {
            throw new ReglaNegocioException("Ya existe un elemento con la clave \"{$datos['clave']}\".");
        }

        $datos['orden'] ??= (int) $modelo::query()
            ->when($entidad === 'tipos', fn ($q) => $q->where('sector_id', $datos['sector_id']))
            ->max('orden') + 1;

        return $modelo::create($datos);
    }

    // ------------------------------------------------------------- internos

    /**
     * Ejecuta una escritura sobre la versión releída con bloqueo (FOR UPDATE): publicar y editar se
     * serializan, y quien llega después de un publicar ve la versión publicada y recibe el error habitual.
     */
    private function enBorrador(CatalogoVersion $version, callable $accion): mixed
    {
        return DB::transaction(function () use ($version, $accion) {
            $actual = CatalogoVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            $this->exigirBorrador($actual);

            return $accion($actual);
        });
    }

    private function exigirBorrador(CatalogoVersion $version): void
    {
        if (! $version->esBorrador()) {
            throw new ReglaNegocioException('Esta versión del catálogo ya fue publicada y es inmutable. Crea un borrador para hacer cambios.');
        }
    }

    private function listar($query): array
    {
        return $query->orderBy('orden')->get()
            ->map(fn ($i) => ['id' => $i->id, 'clave' => $i->clave, 'nombre' => $i->nombre, 'activo' => $i->activo])
            ->values()->all();
    }
}
