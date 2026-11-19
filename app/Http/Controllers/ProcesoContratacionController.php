<?php

namespace App\Http\Controllers;

use App\Models\ProcesoLicitacion;
use App\Models\Proyecto;
use App\Services\Contratacion\ExpedientesContratacion;
use App\Services\Contratacion\ProcesosContratacion;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\PlantillasExpediente;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Procesos de contratación de un proyecto.
 * Lectura: cualquier usuario autenticado. Escritura: can:editar-datos.
 */
class ProcesoContratacionController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'in' => 'Revisa :attribute: no es un valor válido.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'objeto.required' => 'Escribe el objeto del proceso.',
        'objeto.max' => 'El objeto admite hasta 5000 caracteres.',
        'modalidad_id.required' => 'Elige la modalidad del proceso.',
        'plataforma.required' => 'Elige la plataforma del proceso.',
        'plataforma.in' => 'Elige una plataforma de la lista.',
        'codigo_proceso.max' => 'El número del proceso admite hasta 100 caracteres.',
        'url_proceso.url' => 'Escribe un enlace completo que empiece por http:// o https://.',
        'url_proceso.max' => 'El enlace admite hasta 500 caracteres.',
        'monto.numeric' => 'Escribe el valor como un número.',
        'monto.min' => 'El valor no puede ser negativo.',
        'monto.decimal' => 'El valor admite hasta 2 decimales.',
        'fecha_apertura.date_format' => 'Escribe :attribute como año-mes-día.',
        'fecha_cierre.date_format' => 'Escribe :attribute como año-mes-día.',
        'observaciones.max' => 'Las observaciones admiten hasta 5000 caracteres.',
        'estado.required' => 'Elige el estado del proceso.',
        'estado.in' => 'Elige un estado de la lista.',
        'observacion.max' => 'La observación admite hasta 2000 caracteres.',
        'actualizado.present' => 'Falta la versión del proceso que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del proceso no es válida. Ciérralo y vuelve a abrirlo.',
        'plantillas.required' => 'Elige al menos una plantilla para el expediente.',
        'plantillas.array' => 'Elige al menos una plantilla para el expediente.',
        'plantillas.max' => 'Un expediente admite como máximo :max plantillas.',
    ];

    private const ATRIBUTOS = [
        'objeto' => 'el objeto',
        'modalidad_id' => 'la modalidad',
        'plataforma' => 'la plataforma',
        'codigo_proceso' => 'el número del proceso',
        'url_proceso' => 'el enlace',
        'entidad_contratante_id' => 'la entidad contratante',
        'monto' => 'el valor',
        'fuente_id' => 'la fuente de financiación',
        'responsable_id' => 'el responsable',
        'fecha_apertura' => 'la fecha de apertura',
        'fecha_cierre' => 'la fecha de cierre',
        'observaciones' => 'las observaciones',
        'estado' => 'el estado',
        'observacion' => 'la observación',
        'actualizado' => 'la versión del proceso',
        'plantillas' => 'las plantillas',
        'plantillas.*' => 'cada plantilla',
    ];

    // La columna es decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // El `actualizado` del proceso que tenía el formulario: siempre presente; null solo para un
    // proceso sin fechas, anterior a ellas. Con otro valor, el servicio responde 409.
    private const VERSION = ['present', 'nullable', 'bail', 'string', 'date'];

    public function __construct(
        private readonly ProcesosContratacion $procesos,
        private readonly ExpedientesContratacion $expedientes,
        private readonly ExpedienteService $expediente,
    ) {
    }

    public function index(Proyecto $proyecto): JsonResponse
    {
        return response()->json([
            'proyecto' => ['id' => $proyecto->id, 'nombre' => $proyecto->nombre],
            'procesos' => $this->procesos->deProyecto($proyecto),
        ]);
    }

    /** Lo que precarga el formulario de un contrato nuevo del proceso. */
    public function precargaContrato(ProcesoLicitacion $proceso): JsonResponse
    {
        return response()->json($this->procesos->precargaContrato($proceso));
    }

    /** Las plantillas de contratación con que se puede crear el expediente del proceso. */
    public function plantillasCompatibles(ProcesoLicitacion $proceso): JsonResponse
    {
        return response()->json($this->expedientes->disponibles($proceso));
    }

    /** Crea el expediente del proceso con las plantillas elegidas; la primera es la principal. */
    public function crearExpediente(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $entero = fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');
        $datos = $request->validate([
            'plantillas' => ['bail', 'required', 'array', 'max:' . PlantillasExpediente::MAX_PLANTILLAS],
            'plantillas.*' => ['bail', 'integer', $entero],
        ], self::MENSAJES, self::ATRIBUTOS);
        $creado = $this->expedientes->crear($proceso, $datos['plantillas'], $request->user());

        return response()->json($this->expediente->presentar($creado), 201);
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $proceso = $this->procesos->crear($proyecto, $this->validar($request), $request->user());

        return response()->json($this->procesos->presentar($proceso), 201);
    }

    public function update(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $datos = $this->validar($request, ['actualizado' => self::VERSION]);
        $proceso = $this->procesos->editar($proceso, $datos, $datos['actualizado'], $request->user());

        return response()->json($this->procesos->presentar($proceso));
    }

    public function cambiarEstado(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['bail', 'required', 'string', Rule::in(ProcesoLicitacion::ESTADOS)],
            'observacion' => ['sometimes', 'nullable', 'bail', 'string', 'max:2000'],
            'actualizado' => self::VERSION,
        ], self::MENSAJES, self::ATRIBUTOS);
        $proceso = $this->procesos->cambiarEstado(
            $proceso, $datos['estado'], $datos['observacion'] ?? null, $datos['actualizado'], $request->user()
        );

        return response()->json($this->procesos->presentar($proceso));
    }

    public function destroy(Request $request, ProcesoLicitacion $proceso): JsonResponse
    {
        $this->procesos->eliminar($proceso, $request->user());

        return response()->json(['mensaje' => 'Proceso eliminado.']);
    }

    /**
     * Al editar, los opcionales que no vienen en el pedido no cambian y con null se borran; los
     * obligatorios siempre se envían. El estado no se recibe aquí: tiene su propia ruta. `$mas`
     * agrega reglas, como la versión al editar.
     */
    private function validar(Request $request, array $mas = []): array
    {
        // Enteros de verdad: `integer` admitiría true como 1 o "1".
        $entero = fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || $falla('Revisa :attribute: debe ser un número entero.');
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');

        return $request->validate([
            'objeto' => ['bail', 'required', 'string', 'max:5000'],
            'modalidad_id' => ['bail', 'required', 'integer', $entero],
            'plataforma' => ['bail', 'required', 'string', Rule::in(ProcesoLicitacion::PLATAFORMAS)],
            'codigo_proceso' => ['sometimes', 'nullable', 'bail', 'string', 'max:100'],
            'url_proceso' => ['sometimes', 'nullable', 'bail', 'string', 'url:http,https', 'max:500'],
            'entidad_contratante_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'monto' => ['sometimes', 'nullable', 'bail', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            'fuente_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'responsable_id' => ['sometimes', 'nullable', 'bail', 'integer', $entero],
            'fecha_apertura' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'fecha_cierre' => ['sometimes', 'nullable', 'bail', 'string', 'date_format:Y-m-d'],
            'observaciones' => ['sometimes', 'nullable', 'bail', 'string', 'max:5000'],
            ...$mas,
        ], self::MENSAJES, self::ATRIBUTOS);
    }
}
