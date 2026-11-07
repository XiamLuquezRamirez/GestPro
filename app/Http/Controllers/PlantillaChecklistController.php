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
        'nombre.required' => 'Escribe el nombre de la plantilla.',
        'nombre.max' => 'El nombre admite hasta 199 caracteres.',
        'alcance.in' => 'El alcance debe ser general o específico.',
        'motor.in' => 'El motor debe ser sgr o simple.',
    ];

    public function __construct(
        private readonly PlantillaService $plantillas,
        private readonly CatalogoService $catalogo,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->plantillas->listar($request->query('buscar')));
    }

    public function show(PlantillaChecklist $plantilla): JsonResponse
    {
        return response()->json($this->plantillas->presentar($plantilla));
    }

    public function store(Request $request): JsonResponse
    {
        $plantilla = $this->plantillas->crear($request->validate($this->reglas(), self::MENSAJES), $request->user());

        return response()->json($this->plantillas->presentar($plantilla), 201);
    }

    public function update(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $this->plantillas->actualizar($plantilla, $request->validate($this->reglas(), self::MENSAJES));

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }

    public function activar(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $this->plantillas->activar($plantilla, (bool) $datos['activo']);

        return response()->json($this->plantillas->presentar($plantilla->refresh()));
    }

    public function crearVersion(Request $request, PlantillaChecklist $plantilla): JsonResponse
    {
        $datos = $request->validate(['descripcion' => ['nullable', 'string', 'max:255']]);
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
