<?php

namespace App\Http\Controllers;

use App\Models\CatalogoVersion;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Models\RequisitoComponente;
use App\Models\RequisitoTipologia;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\EvaluadorRequisitos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            PlantillaChecklist::sgr()->versiones()->withCount(['requisitos', 'expedientes'])->orderByDesc('numero')->get()
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

    public function publicarVersion(Request $request, CatalogoVersion $version): JsonResponse
    {
        return response()->json($this->servicio->publicar($version, $request->user()));
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
        $requisito = $this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito($version), self::MENSAJES, self::ATRIBUTOS));

        return response()->json($requisito, 201);
    }

    public function actualizarRequisito(Request $request, CatalogoVersion $version, RequisitoCatalogo $requisito): JsonResponse
    {
        return response()->json($this->servicio->guardarRequisito($version, $request->validate($this->reglasRequisito($version), self::MENSAJES, self::ATRIBUTOS), $requisito));
    }

    public function ordenarRequisitos(Request $request, CatalogoVersion $version): JsonResponse
    {
        $datos = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']], self::MENSAJES, self::ATRIBUTOS);
        $this->servicio->ordenarRequisitos($version, $datos['ids']);

        return response()->json(['mensaje' => 'Orden guardado.']);
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

    private const ATRIBUTOS = [
        'clave' => 'la clave', 'texto' => 'el nombre del requisito', 'descripcion' => 'la descripción',
        'categoria' => 'la categoría', 'tipo_requisito' => 'el tipo de requisito', 'caracter' => 'el carácter',
        'cargo_cumplimiento' => 'el cargo de cumplimiento', 'cargo_revision' => 'el cargo de revisión',
        'soporte_requerido' => 'el soporte requerido', 'referencia_normativa' => 'la referencia normativa',
        'condicion_descripcion' => 'la descripción de la condición',
        'requiere_aprobacion_no_aplica' => 'la aprobación del "no aplica"', 'codigo_comun' => 'el código común',
        'codigo' => 'el código', 'subtitulo' => 'el subtítulo', 'seccion' => 'la sección',
        'fuente' => 'la fuente normativa', 'sector_id' => 'el sector', 'tipo_id' => 'el tipo de proyecto',
        'condicion' => 'la condición', 'nota' => 'las observaciones', 'orden' => 'el orden',
        'activo' => 'el estado activo', 'ids' => 'el orden de los requisitos',
    ];

    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'boolean' => 'Revisa :attribute: debe ser sí o no.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'array' => 'Revisa :attribute: debe ser una lista.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'codigo_comun.regex' => 'El código común solo admite letras, números, punto, guion y guion bajo.',
        'texto.required' => 'Escribe el nombre del requisito.',
        'clave.regex' => 'La clave solo admite minúsculas, números y guion bajo.',
        'caracter.in' => 'El carácter debe ser obligatorio, opcional o condicionado.',
        'categoria.in' => 'Elige una categoría de la lista.',
        'tipo_requisito.in' => 'Elige un tipo de requisito de la lista.',
    ];

    /** Las reglas dependen del motor de la plantilla de la versión. */
    private function reglasRequisito(CatalogoVersion $version): array
    {
        $seguimiento = [
            'descripcion' => ['nullable', 'string'],
            'categoria' => ['nullable', Rule::in(RequisitoCatalogo::CATEGORIAS)],
            'tipo_requisito' => ['nullable', Rule::in(RequisitoCatalogo::TIPOS)],
            'cargo_cumplimiento' => ['nullable', 'string', 'max:150'],
            'cargo_revision' => ['nullable', 'string', 'max:150'],
            'soporte_requerido' => ['nullable', 'string', 'max:255'],
            'referencia_normativa' => ['nullable', 'string', 'max:255'],
            'requiere_aprobacion_no_aplica' => ['sometimes', 'boolean'],
            'codigo_comun' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'nota' => ['nullable', 'string'],
            'orden' => ['sometimes', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];

        if ($version->plantilla->motor === PlantillaChecklist::MOTOR_SIMPLE) {
            return [
                ...$seguimiento,
                'clave' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
                'texto' => ['required', 'string'],
                'caracter' => ['sometimes', Rule::in(RequisitoCatalogo::CARACTERES)],
                'condicion_descripcion' => ['nullable', 'string'],
                'codigo' => ['nullable', 'string', 'max:60'],
                'subtitulo' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            ...$seguimiento,
            'clave' => self::CLAVE,
            'seccion' => ['required', 'in:A,B,C,D'],
            'fuente' => ['required', 'in:a12,ot'],
            'sector_id' => ['nullable', 'integer'],
            'tipo_id' => ['nullable', 'integer'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:60'],
            'texto' => ['required', 'string'],
            'condicion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
