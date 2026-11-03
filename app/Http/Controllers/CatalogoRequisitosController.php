<?php

namespace App\Http\Controllers;

use App\Models\CatalogoVersion;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
use App\Models\RequisitoTipologia;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\EvaluadorRequisitos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo maestro de requisitos de viabilización.
 * Lectura del vigente: cualquier usuario autenticado. Administración:
 * can:gestionar-catalogos (rol Administrador).
 */
class CatalogoRequisitosController extends Controller
{
    private const CLAVE = ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'];

    public function __construct(private readonly CatalogoService $servicio)
    {
    }

    public function vigente(Request $request): JsonResponse
    {
        $inactivos = $request->boolean('incluir_inactivos') && $request->user()->can('gestionar-catalogos');

        return response()->json($this->servicio->vigente($inactivos));
    }

    // ------------------------------------------------------------- versiones

    public function versiones(): JsonResponse
    {
        return response()->json(
            CatalogoVersion::query()->withCount(['requisitos', 'expedientes'])->orderByDesc('numero')->get()
                ->map(fn (CatalogoVersion $v) => [
                    'id' => $v->id,
                    'numero' => $v->numero,
                    'estado' => $v->estado,
                    'descripcion' => $v->descripcion,
                    'vigente' => $v->vigente,
                    'fecha_publicacion' => $v->fecha_publicacion?->toIso8601String(),
                    'requisitos' => $v->requisitos_count,
                    'expedientes' => $v->expedientes_count,
                ])
        );
    }

    public function crearVersion(Request $request): JsonResponse
    {
        $datos = $request->validate(['descripcion' => ['nullable', 'string', 'max:255']]);

        return response()->json($this->servicio->crearBorrador($datos['descripcion'] ?? null), 201);
    }

    public function publicarVersion(CatalogoVersion $version): JsonResponse
    {
        return response()->json($this->servicio->publicar($version));
    }

    public function eliminarVersion(CatalogoVersion $version): JsonResponse
    {
        $this->servicio->eliminarBorrador($version);

        return response()->json(['mensaje' => 'Borrador eliminado.']);
    }

    // ------------------------------------------------------------ requisitos

    public function requisitos(CatalogoVersion $version): JsonResponse
    {
        return response()->json(
            $version->requisitos()->with(['sector:id,clave,nombre', 'tipo:id,clave,nombre'])->orderBy('orden')->get()
        );
    }

    public function crearRequisito(Request $request, CatalogoVersion $version): JsonResponse
    {
        $requisito = $this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito()));

        return response()->json($requisito, 201);
    }

    public function actualizarRequisito(Request $request, CatalogoVersion $version, RequisitoCatalogo $requisito): JsonResponse
    {
        return response()->json($this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito()), $requisito));
    }

    public function eliminarRequisito(CatalogoVersion $version, RequisitoCatalogo $requisito): JsonResponse
    {
        $this->servicio->eliminarRequisito($version, $requisito);

        return response()->json(['mensaje' => 'Requisito eliminado.']);
    }

    /** Valida una condición del DSL sin guardar nada (ayuda para el editor). */
    public function validarCondicion(Request $request, EvaluadorRequisitos $evaluador): JsonResponse
    {
        $datos = $request->validate(['condicion' => ['nullable', 'string', 'max:255']]);
        $errores = $evaluador->validarCondicion(
            $datos['condicion'] ?? null,
            RequisitoComponente::pluck('clave')->all(),
            RequisitoTipologia::pluck('clave')->all()
        );

        return response()->json(['valida' => $errores === [], 'errores' => $errores]);
    }

    // ------------------------------------- sectores, tipos, componentes...

    public function crearEntidad(Request $request, string $entidad): JsonResponse
    {
        $reglas = [
            'clave' => self::CLAVE,
            'nombre' => ['required', 'string', 'max:500'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
        if ($entidad === 'sectores') {
            $reglas['fuente'] = ['nullable', 'string', 'max:199'];
        }
        if ($entidad === 'tipos') {
            $reglas['sector_id'] = ['required', 'integer', 'exists:requisitos_sectores,id'];
        }

        return response()->json($this->servicio->guardarEntidad($entidad, $request->validate($reglas)), 201);
    }

    public function actualizarEntidad(Request $request, string $entidad, int $id): JsonResponse
    {
        $modelo = CatalogoService::ENTIDADES[$entidad];
        $item = $modelo::findOrFail($id);

        $reglas = [
            'nombre' => ['sometimes', 'string', 'max:500'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
        if ($entidad === 'sectores') {
            $reglas['fuente'] = ['sometimes', 'nullable', 'string', 'max:199'];
        }

        return response()->json($this->servicio->guardarEntidad($entidad, $request->validate($reglas), $item));
    }

    private function reglasRequisito(): array
    {
        return [
            'clave' => self::CLAVE,
            'seccion' => ['required', 'in:A,B,C,D'],
            'fuente' => ['required', 'in:a12,ot'],
            'sector_id' => ['nullable', 'integer'],
            'tipo_id' => ['nullable', 'integer'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:60'],
            'texto' => ['required', 'string'],
            'nota' => ['nullable', 'string'],
            'condicion' => ['nullable', 'string', 'max:255'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
