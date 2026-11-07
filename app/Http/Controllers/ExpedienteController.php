<?php

namespace App\Http\Controllers;

use App\Models\ChecklistHistorial;
use App\Models\Proyecto;
use App\Models\ProyectoExpediente;
use App\Models\ProyectoRequisito;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expedientes de viabilización SGR de un proyecto y su checklist.
 * Lectura: cualquier usuario autenticado. Escritura: can:editar-datos.
 */
class ExpedienteController extends Controller
{
    private const REGLAS_CONFIGURACION = [
        'fase_sgr' => ['required', 'integer', 'in:1,2,3'],
        'sector_ids' => ['sometimes', 'array'],
        'sector_ids.*' => ['integer'],
        'tipo_ids' => ['sometimes', 'array'],
        'tipo_ids.*' => ['integer'],
        'componente_ids' => ['sometimes', 'array'],
        'componente_ids.*' => ['integer'],
        'tipologia_ids' => ['sometimes', 'array'],
        'tipologia_ids.*' => ['integer'],
    ];

    // Documentos oficiales y sus escaneos.
    private const MIMES_SOPORTE = 'pdf,jpg,jpeg,png,doc,docx,xls,xlsx';
    private const MAX_SOPORTE_KB = 20480;

    public function __construct(
        private readonly ExpedienteService $servicio,
        private readonly HistorialChecklist $historial,
    ) {
    }

    /** Vista consolidada del Dashboard: el expediente más reciente de cada proyecto. */
    public function resumen(): JsonResponse
    {
        return response()->json($this->servicio->resumenPorProyecto());
    }

    public function index(Proyecto $proyecto): JsonResponse
    {
        $expedientes = $proyecto->expedientes()->orderByDesc('id')->get()
            ->map(fn (ProyectoExpediente $e) => [
                ...$this->servicio->presentarCabecera($e),
                'progreso' => $this->servicio->progresoDe($e),
            ]);

        $proyecto->loadMissing('sectorRel');

        return response()->json([
            'proyecto' => ['id' => $proyecto->id, 'nombre' => $proyecto->nombre],
            // Preselección sugerida a partir del sector de GestPro (mapeo opcional).
            'sector_sugerido_id' => $proyecto->sectorRel?->requisitos_sector_id,
            'expedientes' => $expedientes,
        ]);
    }

    public function store(Request $request, Proyecto $proyecto): JsonResponse
    {
        $datos = $request->validate(self::REGLAS_CONFIGURACION);
        $expediente = $this->servicio->crear($proyecto, $datos, $request->user());

        return response()->json($this->servicio->presentar($expediente), 201);
    }

    public function show(ProyectoExpediente $expediente): JsonResponse
    {
        return response()->json($this->servicio->presentar($expediente));
    }

    public function updateConfiguracion(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $datos = $request->validate(self::REGLAS_CONFIGURACION);
        $this->servicio->actualizarConfiguracion($expediente, $datos, $request->user());

        return response()->json($this->servicio->presentar($expediente->refresh()));
    }

    public function confirmarConfiguracion(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $datos = $request->validate(self::REGLAS_CONFIGURACION);
        $this->servicio->confirmarConfiguracion($expediente, $datos, $request->user());

        return response()->json($this->servicio->presentar($expediente->refresh()));
    }

    public function updateRequisito(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $datos = $request->validate([
            'cumplido' => ['sometimes', 'boolean'],
            'observacion' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'responsable_id' => ['sometimes', 'nullable', 'integer'],
        ], ['responsable_id.integer' => 'El responsable no es válido.']);
        $fila = $this->servicio->marcar($expediente, $requisito, $datos, $request->user());

        return response()->json([
            'requisito' => $this->servicio->presentarFila($fila->refresh()),
            'progreso' => $this->servicio->progresoDe($expediente),
        ]);
    }

    public function subirSoporte(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        // Ambas reglas hacen falta: mimes mira el contenido y extensions el nombre. Solo
        // con mimes, un PDF renombrado virus.exe pasaría y se descargaría como .exe.
        $request->validate([
            'archivo' => [
                'required', 'file',
                'mimes:' . self::MIMES_SOPORTE,
                'extensions:' . self::MIMES_SOPORTE,
                'max:' . self::MAX_SOPORTE_KB,
            ],
        ]);
        $fila = $this->servicio->guardarSoporte($expediente, $requisito, $request->file('archivo'), $request->user());

        return response()->json(['requisito' => $this->servicio->presentarFila($fila->refresh())]);
    }

    public function descargarSoporte(ProyectoExpediente $expediente, ProyectoRequisito $requisito): StreamedResponse|JsonResponse
    {
        $disco = Storage::disk(ExpedienteService::DISCO);
        if (! $requisito->soporte_archivo || ! $disco->exists($requisito->soporte_archivo)) {
            return response()->json(['mensaje' => 'El requisito no tiene soporte adjunto.'], 404);
        }

        return $disco->download($requisito->soporte_archivo, $requisito->soporte_nombre);
    }

    public function eliminarSoporte(Request $request, ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        $fila = $this->servicio->eliminarSoporte($expediente, $requisito, $request->user());

        return response()->json(['requisito' => $this->servicio->presentarFila($fila->refresh())]);
    }

    public function radicar(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $this->servicio->radicar($expediente, $request->user());

        return response()->json($this->servicio->presentar($expediente->refresh()));
    }

    public function historialRequisito(ProyectoExpediente $expediente, ProyectoRequisito $requisito): JsonResponse
    {
        return response()->json($this->historial->deFila($requisito));
    }

    /** Soporte reemplazado o retirado, conservado en el historial. */
    public function descargarSoporteHistorial(ProyectoExpediente $expediente, ChecklistHistorial $entrada): StreamedResponse|JsonResponse
    {
        $ruta = $entrada->expediente_id === $expediente->id ? ($entrada->antes['soporte_archivo'] ?? null) : null;
        if ($ruta && dirname($ruta) !== "expedientes/{$expediente->id}") {
            $ruta = null; // solo se sirven archivos de la carpeta del expediente
        }
        $disco = Storage::disk(ExpedienteService::DISCO);
        if (! $ruta || ! $disco->exists($ruta)) {
            return response()->json(['mensaje' => 'Ese registro no tiene un soporte disponible.'], 404);
        }

        return $disco->download($ruta, $entrada->antes['soporte_nombre'] ?? 'soporte');
    }

    public function destroy(Request $request, ProyectoExpediente $expediente): JsonResponse
    {
        $this->servicio->eliminar($expediente, $request->user());

        return response()->json(['mensaje' => 'Expediente eliminado.']);
    }
}
