<?php

namespace App\Services\Viabilizacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\CatalogoVersion;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
use App\Models\RequisitoSector;
use App\Models\RequisitoTipo;
use App\Models\RequisitoTipologia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Administración del catálogo maestro (rol Administrador).
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

    /** Catálogo vigente para armar la configuración de un expediente. */
    public function vigente(bool $incluirInactivos = false): array
    {
        $version = CatalogoVersion::vigente()->first()
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

    public function crearBorrador(?string $descripcion): CatalogoVersion
    {
        if (CatalogoVersion::where('estado', CatalogoVersion::BORRADOR)->exists()) {
            throw new ReglaNegocioException('Ya existe una versión en borrador; publíquela o elimínela antes de crear otra.');
        }
        $vigente = CatalogoVersion::vigente()->first()
            ?? throw new ReglaNegocioException('No hay una versión vigente que copiar.');

        return DB::transaction(function () use ($vigente, $descripcion) {
            $borrador = CatalogoVersion::create([
                'numero' => (int) CatalogoVersion::max('numero') + 1,
                'estado' => CatalogoVersion::BORRADOR,
                'descripcion' => $descripcion,
                'vigente' => false,
            ]);

            $columnas = ['clave', 'seccion', 'fuente', 'sector_id', 'tipo_id', 'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo'];
            $vigente->requisitos()->orderBy('id')->select($columnas)->get()
                ->map(fn ($r) => ['catalogo_version_id' => $borrador->id, ...$r->only($columnas)])
                ->chunk(100)
                ->each(fn ($lote) => DB::table('requisitos_catalogo')->insert($lote->values()->all()));

            return $borrador;
        });
    }

    public function publicar(CatalogoVersion $version): CatalogoVersion
    {
        $this->exigirBorrador($version);

        $errores = [];
        $componentes = RequisitoComponente::pluck('clave')->all();
        $tipologias = RequisitoTipologia::pluck('clave')->all();
        foreach ($version->requisitos()->where('activo', true)->whereIn('seccion', ['A', 'B', 'D'])->get() as $r) {
            foreach ($this->evaluador->validarCondicion($r->condicion, $componentes, $tipologias) as $error) {
                $errores[] = "{$r->clave}: {$error}";
            }
        }
        if ($errores) {
            throw new ReglaNegocioException('La versión tiene condiciones inválidas y no puede publicarse.', $errores);
        }

        return DB::transaction(function () use ($version) {
            CatalogoVersion::where('id', '!=', $version->id)->update(['vigente' => false]);
            $version->update(['estado' => CatalogoVersion::PUBLICADA, 'fecha_publicacion' => now(), 'vigente' => true]);

            return $version;
        });
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
            $datos['condicion'] = null; // en C aplica por sector/tipo, no por condición
        } else {
            $datos['sector_id'] = null;
            $datos['tipo_id'] = null;
            foreach ($this->evaluador->validarCondicion($datos['condicion'] ?? null,
                RequisitoComponente::pluck('clave')->all(), RequisitoTipologia::pluck('clave')->all()) as $error) {
                $errores[] = $error;
            }
            $datos['condicion'] = trim((string) ($datos['condicion'] ?? ''));
        }

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
        ];

        if ($requisito) {
            $requisito->update($valores);

            return $requisito;
        }

        return RequisitoCatalogo::create($valores);
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
