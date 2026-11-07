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
            throw new ReglaNegocioException('Ya existe una versión en borrador; publíquela o elimínela antes de crear otra.');
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
        $this->exigirBorrador($version);
        $plantilla = $version->plantilla;

        if (! $version->requisitos()->where('activo', true)->exists()) {
            throw new ReglaNegocioException('La versión no tiene requisitos activos y no puede publicarse.');
        }
        $sinFuentes = ! $plantilla->todas_fuentes && ! $plantilla->fuentes()->exists();
        $sinSectores = ! $plantilla->todos_sectores && ! $plantilla->sectores()->exists();
        if ($sinFuentes || $sinSectores) {
            throw new ReglaNegocioException('Asocia la plantilla a sus fuentes y sectores antes de publicar.');
        }

        [$errores, $mensaje] = $plantilla->motor === PlantillaChecklist::MOTOR_SGR
            ? [$this->erroresDeCondiciones($version), 'La versión tiene condiciones inválidas y no puede publicarse.']
            : [$this->erroresDeCondicionados($version), 'La versión tiene requisitos condicionados sin describir y no puede publicarse.'];
        if ($errores) {
            throw new ReglaNegocioException($mensaje, $errores);
        }

        return DB::transaction(function () use ($version, $usuario) {
            CatalogoVersion::where('plantilla_id', $version->plantilla_id)
                ->where('id', '!=', $version->id)
                ->update(['vigente' => false]);
            $version->update([
                'estado' => CatalogoVersion::PUBLICADA,
                'fecha_publicacion' => now(),
                'usuario_publico_id' => $usuario?->id,
                'vigente' => true,
            ]);

            return $version;
        });
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
        return $version->requisitos()->where('activo', true)
            ->where('caracter', RequisitoCatalogo::CONDICIONADO)
            ->where(fn ($q) => $q->whereNull('condicion_descripcion')->orWhere('condicion_descripcion', ''))
            ->orderBy('orden')
            ->get()
            ->map(fn (RequisitoCatalogo $r) => "{$r->texto}: describe cuándo aplica este requisito condicionado.")
            ->all();
    }

    public function eliminarBorrador(CatalogoVersion $version): void
    {
        $this->exigirBorrador($version);
        $version->delete(); // sus requisitos caen en cascada; ningún expediente usa un borrador
    }

    // ------------------------------------------------------------ requisitos

    public function guardarRequisito(CatalogoVersion $version, array $datos, ?RequisitoCatalogo $requisito = null): RequisitoCatalogo
    {
        $this->exigirBorrador($version);
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
            'subtitulo' => $datos['subtitulo'] ?? null,
            'codigo' => $datos['codigo'],
            'texto' => $datos['texto'],
            'nota' => $datos['nota'] ?? null,
            'condicion' => $datos['condicion'],
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => $datos['activo'] ?? true,
            ...$this->camposDeSeguimiento($datos, $requisito, sinCaracter: true),
        ];
        if ($version->plantilla->clave === PlantillaChecklist::CLAVE_SGR) {
            // En la plantilla SGR el código común es la clave, también al editarla, y todo
            // "no aplica" se aprueba (decisión del usuario).
            $valores['codigo_comun'] = $datos['clave'];
            $valores['requiere_aprobacion_no_aplica'] = true;
        } else {
            // Otra plantilla con motor sgr (una copia): el código común lo define el administrador.
            $valores['codigo_comun'] = array_key_exists('codigo_comun', $datos)
                ? (trim((string) $datos['codigo_comun']) ?: null)
                : $requisito?->codigo_comun;
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
            ? (trim((string) $datos['codigo_comun']) ?: null)
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
            'subtitulo' => $datos['subtitulo'] ?? $requisito?->subtitulo,
            'codigo' => $datos['codigo'] ?? $requisito?->codigo,
            'texto' => $datos['texto'],
            'nota' => $datos['nota'] ?? null,
            'orden' => $datos['orden'] ?? ($requisito?->orden ?? (int) $version->requisitos()->max('orden') + 1),
            'activo' => $datos['activo'] ?? ($requisito?->activo ?? true),
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

    private function claveLibre(CatalogoVersion $version): string
    {
        $numero = $version->requisitos()->count() + 1;
        while ($version->requisitos()->where('clave', "r{$numero}")->exists()) {
            $numero++;
        }

        return "r{$numero}";
    }

    /** Orden de presentación: los ids en el orden en que deben mostrarse. */
    public function ordenarRequisitos(CatalogoVersion $version, array $ids): void
    {
        $this->exigirBorrador($version);

        $propios = $version->requisitos()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $pedidos = collect($ids)->map(fn ($id) => (int) $id);
        if ($pedidos->sort()->values()->all() !== $propios) {
            throw new ReglaNegocioException('El orden debe incluir todos los requisitos de la versión, una vez cada uno.');
        }

        DB::transaction(function () use ($pedidos) {
            foreach ($pedidos->values() as $posicion => $id) {
                RequisitoCatalogo::whereKey($id)->update(['orden' => $posicion + 1]);
            }
        });
    }

    public function eliminarRequisito(CatalogoVersion $version, RequisitoCatalogo $requisito): void
    {
        $this->exigirBorrador($version);
        if ($requisito->catalogo_version_id !== $version->id) {
            throw new ReglaNegocioException('El requisito no pertenece a esta versión.');
        }
        $requisito->delete();
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

    private function exigirBorrador(CatalogoVersion $version): void
    {
        if (! $version->esBorrador()) {
            throw new ReglaNegocioException('Esta versión del catálogo ya fue publicada y es inmutable. Cree un borrador para hacer cambios.');
        }
    }

    private function listar($query): array
    {
        return $query->orderBy('orden')->get()
            ->map(fn ($i) => ['id' => $i->id, 'clave' => $i->clave, 'nombre' => $i->nombre, 'activo' => $i->activo])
            ->values()->all();
    }
}
