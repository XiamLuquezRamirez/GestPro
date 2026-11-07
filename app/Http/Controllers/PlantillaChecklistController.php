<?php

namespace App\Http\Controllers;

use App\Models\PlantillaChecklist;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\PlantillaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Plantillas de checklist. Rutas con can:gestionar-catalogos (rol Administrador). */
class PlantillaChecklistController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'boolean' => 'Revisa :attribute: debe ser sí o no.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'array' => 'Revisa :attribute: debe ser una lista.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'nombre.required' => 'Escribe el nombre de la plantilla.',
        'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        'alcance.in' => 'Elige el alcance: general o específica.',
        'motor.in' => 'El motor debe ser sgr o simple.',
    ];

    private const ATRIBUTOS = [
        'nombre' => 'el nombre',
        'descripcion' => 'la descripción',
        'alcance' => 'el alcance',
        'todas_fuentes' => 'la opción "Todas las fuentes"',
        'todos_sectores' => 'la opción "Todos los sectores"',
        'fuente_ids' => 'las fuentes',
        'fuente_ids.*' => 'cada fuente',
        'sector_ids' => 'los sectores',
        'sector_ids.*' => 'cada sector',
        'activo' => 'el estado activo',
        'motor' => 'el motor',
        'requisito_ids' => 'los requisitos',
        'requisito_ids.*' => 'cada requisito',
        'buscar' => 'la búsqueda',
    ];

    public function __construct(
        private readonly PlantillaService $plantillas,
        private readonly CatalogoService $catalogo,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate(['buscar' => ['nullable', 'string', 'max:199']], self::MENSAJES, self::ATRIBUTOS);

        return response()->json($this->plantillas->listar($datos['buscar'] ?? null));
    }

    public function show(PlantillaChecklist $plantilla): JsonResponse
    {
        return response()->json($this->plantillas->presentar($plantilla));
    }

    public function store(Request $request): JsonResponse
    {
        $plantilla = $this->plantillas->crear($request->validate($this->reglas(), self::MENSAJES, self::ATRIBUTOS), $request->user());

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    public function update(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $this->plantillas->actualizar($plantilla, $request->validate($this->reglas(), self::MENSAJES, self::ATRIBUTOS));

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }

    public function activar(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']], self::MENSAJES, self::ATRIBUTOS);
        $this->plantillas->activar($plantilla, (bool) $datos['activo']);

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }

    public function duplicar(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:199'],
            'motor' => ['sometimes', Rule::in([PlantillaChecklist::MOTOR_SGR, PlantillaChecklist::MOTOR_SIMPLE])],
            'requisito_ids' => ['sometimes', 'array'],
            'requisito_ids.*' => ['integer'],
        ], self::MENSAJES, self::ATRIBUTOS);
        $copia = $this->plantillas->duplicar($plantilla, $datos, $request->user());

        return response()->json($this->plantillas->presentar($copia), 201);
    }

    public function crearVersion(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate(['descripcion' => ['nullable', 'string', 'max:255']], self::MENSAJES, self::ATRIBUTOS);
        $this->catalogo->crearBorrador($datos['descripcion'] ?? null, $plantilla);

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    private function reglas(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:199'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'alcance' => ['nullable', Rule::in(PlantillaService::ALCANCES)],
            'todas_fuentes' => ['sometimes', 'boolean'],
            'todos_sectores' => ['sometimes', 'boolean'],
            'fuente_ids' => ['sometimes', 'array'],
            'fuente_ids.*' => ['integer'],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer'],
        ];
    }
}
