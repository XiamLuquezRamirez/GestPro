<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Services\Contratacion\ContratosService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Guardar y eliminar contratos (can:editar-datos). La lectura sigue en ProyectoController
 * (GET /proyectos y GET /listarContratos).
 */
class ContratoController extends Controller
{
    private const MENSAJES = [
        'required' => 'Completa :attribute.',
        'string' => 'Revisa :attribute: debe ser un texto.',
        'integer' => 'Revisa :attribute: debe ser un número entero.',
        'numeric' => 'Revisa :attribute: debe ser un número.',
        'boolean' => 'Revisa :attribute: debe ser sí o no.',
        'array' => 'Revisa :attribute: no es una lista válida.',
        'max' => 'En :attribute caben hasta :max caracteres.',
        'date_format' => 'Escribe :attribute como año-mes-día.',
        'n_contrato.required' => 'Escribe el número del contrato.',
        'n_contrato.max' => 'El número del contrato admite hasta 100 caracteres.',
        'objeto.required' => 'Escribe el objeto del contrato.',
        'objeto.max' => 'El objeto admite hasta 5000 caracteres.',
        'valor_inicial.required' => 'Escribe el valor inicial del contrato.',
        'valor_inicial.numeric' => 'Escribe el valor inicial como un número.',
        'valor_inicial.min' => 'El valor inicial no puede ser negativo.',
        'valor_inicial.decimal' => 'El valor inicial admite hasta 2 decimales.',
        'estado.required' => 'Elige el estado del contrato.',
        'estado.in' => 'Elige un estado de la lista.',
        'porcentaje_anticipo.min' => 'El porcentaje de anticipo va de 0 a 50.',
        'porcentaje_anticipo.max' => 'El porcentaje de anticipo va de 0 a 50.',
        'avance_fisico.min' => 'El avance físico va de 0 a 100.',
        'avance_fisico.max' => 'El avance físico va de 0 a 100.',
        'avance_financiero.min' => 'El avance financiero va de 0 a 100.',
        'avance_financiero.max' => 'El avance financiero va de 0 a 100.',
        'actualizado.present' => 'Falta la versión del contrato que estabas editando. Ciérralo y vuelve a abrirlo.',
        'actualizado.date' => 'La versión del contrato no es válida. Ciérralo y vuelve a abrirlo.',
        'anexos.*.descripcion.required' => 'Escribe la descripción del anexo.',
        'anexos.*.descripcion.max' => 'La descripción del anexo admite hasta 255 caracteres.',
        'anexos.*.nombreArchivo.required' => 'Falta el nombre del archivo del anexo: vuelve a subirlo.',
        'anexos.*.nombreArchivo.max' => 'El nombre del archivo del anexo admite hasta 255 caracteres.',
        'anexos.*.rutaArchivo.required' => 'El anexo no es válido: vuelve a subirlo.',
        'anexos.*.fecha.required' => 'Falta la fecha del anexo.',
        'id.required' => 'Falta el contrato que quieres eliminar.',
    ];

    private const ATRIBUTOS = [
        'id' => 'el contrato',
        'proyecto' => 'el proyecto',
        'proceso_id' => 'el proceso',
        'tipo_contrato_id' => 'el tipo de contrato',
        'n_contrato' => 'el número del contrato',
        'objeto' => 'el objeto',
        'contratante' => 'el contratante',
        'contratante_nit' => 'el NIT del contratante',
        'contratista' => 'el contratista',
        'contratista_identificacion' => 'la identificación del contratista',
        'supervisor' => 'el supervisor',
        'interventoria' => 'la interventoría',
        'numero_rp' => 'el número del RP',
        'valor_inicial' => 'el valor inicial',
        'fecha_inicio' => 'la fecha de inicio',
        'fecha_fin_inicial' => 'la fecha de fin',
        'fecha_suscripcion' => 'la fecha de suscripción',
        'fecha_rp' => 'la fecha del RP',
        'fecha_acta_inicio' => 'la fecha del acta de inicio',
        'estado' => 'el estado',
        'anticipo' => 'el anticipo',
        'porcentaje_anticipo' => 'el porcentaje de anticipo',
        'avance_fisico' => 'el avance físico',
        'avance_financiero' => 'el avance financiero',
        'actualizado' => 'la versión del contrato',
        'anexos' => 'los anexos',
        'anexos.*.descripcion' => 'la descripción del anexo',
        'anexos.*.nombreArchivo' => 'el nombre del archivo del anexo',
        'anexos.*.rutaArchivo' => 'el archivo del anexo',
        'anexos.*.fecha' => 'la fecha del anexo',
    ];

    // Las columnas de valor son decimal(15, 2): 13 cifras enteras y 2 decimales.
    private const CIFRAS_ENTERAS = 13;

    // Lo que sube subirAnexo: un archivo directo en public/anexos_contratos. Otra ruta se rechaza:
    // al eliminar el contrato se borra el archivo.
    private const RUTA_ANEXO = '#^anexos_contratos/[^/\\\\]+$#';

    public function __construct(private readonly ContratosService $contratos)
    {
    }

    /**
     * Crea (sin `formContrato.id`) o edita un contrato. El pedido es `formContrato` con los campos
     * y `anexos`; al editar, los anexos que trae reemplazan a los que había. Los campos que no se
     * escriben desde aquí (el valor y la fecha fin vigentes, la referencia de SECOP, lo que la
     * pantalla manda de más) se ignoran.
     */
    public function guardar(Request $request): JsonResponse
    {
        $form = $request->input('formContrato');
        $form = is_array($form) ? $form : [];
        $editar = ! empty($form['id']);

        $validador = Validator::make($form, $this->reglas($editar), self::MENSAJES, self::ATRIBUTOS);
        $validadorAnexos = Validator::make($request->only('anexos'), $this->reglasAnexos(), self::MENSAJES, self::ATRIBUTOS);
        // Los dos a la vez: así la pantalla muestra todos los errores de una vez.
        $fallaForm = $validador->fails();
        $fallanAnexos = $validadorAnexos->fails();
        if ($fallaForm || $fallanAnexos) {
            throw ValidationException::withMessages([...$validador->errors()->toArray(), ...$validadorAnexos->errors()->toArray()]);
        }
        $datos = $validador->validated();
        // `anexos: null` es como no enviarlos: se conservan.
        $anexos = $request->input('anexos') !== null ? $validadorAnexos->validated()['anexos'] : null;
        unset($datos['id'], $datos['actualizado']);

        if ($editar) {
            $contrato = Contrato::find($form['id']);
            if ($contrato === null) {
                return response()->json(['mensaje' => 'El contrato no existe.'], 404);
            }
            $guardado = $this->contratos->editar($contrato, $datos, $anexos, $form['actualizado'], $request->user());
        } else {
            $guardado = $this->contratos->crear($datos, $anexos ?? [], $request->user());
        }

        return response()->json([
            'success' => true,
            'contrato' => $this->contratos->presentar($guardado->contrato),
            'avisos' => $guardado->avisos,
            'proponer_celebrado' => $guardado->proponerCelebrado,
        ]);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $datos = $request->validate(['id' => ['bail', 'required', $this->entero()]], self::MENSAJES, self::ATRIBUTOS);
        $contrato = Contrato::find($datos['id']);
        if ($contrato === null) {
            return response()->json(['mensaje' => 'El contrato no existe.'], 404);
        }
        $this->contratos->eliminar($contrato, $request->user());

        return response()->json(['success' => true, 'mensaje' => 'Contrato eliminado.']);
    }

    /**
     * Al editar, los opcionales que no vienen en el pedido no cambian y con null se borran; los
     * obligatorios siempre se envían, y también `actualizado` (el updated_at del contrato que
     * tenía el formulario; null para un contrato antiguo, sin fechas).
     */
    private function reglas(bool $editar): array
    {
        $cifras = fn (string $atributo, mixed $valor, Closure $falla) => strlen(ltrim(explode('.', ltrim((string) $valor, '+-'))[0], '0')) <= self::CIFRAS_ENTERAS
            || $falla('El valor inicial admite hasta ' . self::CIFRAS_ENTERAS . ' cifras enteras y 2 decimales.');
        $opcional = fn (mixed ...$reglas) => ['sometimes', 'nullable', 'bail', ...$reglas];
        $fecha = $opcional('string', 'date_format:Y-m-d');

        return [
            'id' => $opcional($this->entero()),
            'proyecto' => $opcional($this->entero()),
            'proceso_id' => $opcional($this->entero()),
            'tipo_contrato_id' => $opcional($this->entero()),
            'n_contrato' => ['bail', 'required', 'string', 'max:100'],
            'objeto' => ['bail', 'required', 'string', 'max:5000'],
            'contratante' => $opcional('string', 'max:5000'),
            'contratante_nit' => $opcional('string', 'max:30'),
            'contratista' => $opcional('string', 'max:5000'),
            'contratista_identificacion' => $opcional('string', 'max:30'),
            'supervisor' => $opcional('string', 'max:255'),
            'interventoria' => $opcional('string', 'max:5000'),
            'numero_rp' => $opcional('string', 'max:40'),
            'valor_inicial' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,2', $cifras],
            'fecha_inicio' => $fecha,
            'fecha_fin_inicial' => $fecha,
            'fecha_suscripcion' => $fecha,
            'fecha_rp' => $fecha,
            'fecha_acta_inicio' => $fecha,
            'estado' => ['bail', 'required', 'string', Rule::in(Contrato::ESTADOS)],
            'anticipo' => $opcional('boolean'),
            'porcentaje_anticipo' => $opcional('integer', 'min:0', 'max:50'),
            // Los calcula la pantalla y pueden traer decimales: el servicio los redondea.
            'avance_fisico' => $opcional('numeric', 'min:0', 'max:100'),
            'avance_financiero' => $opcional('numeric', 'min:0', 'max:100'),
            ...($editar ? ['actualizado' => ['present', 'nullable', 'bail', 'string', 'date']] : []),
        ];
    }

    private function reglasAnexos(): array
    {
        $ruta = fn (string $atributo, mixed $valor, Closure $falla) => (preg_match(self::RUTA_ANEXO, (string) $valor) === 1
                && ! in_array(basename((string) $valor), ['.', '..'], true))
            || $falla('El anexo no es válido: vuelve a subirlo.');

        return [
            'anexos' => ['sometimes', 'nullable', 'array'],
            'anexos.*' => ['array'],
            'anexos.*.descripcion' => ['bail', 'required', 'string', 'max:255'],
            'anexos.*.nombreArchivo' => ['bail', 'required', 'string', 'max:255'],
            'anexos.*.rutaArchivo' => ['bail', 'required', 'string', $ruta],
            'anexos.*.fecha' => ['bail', 'required', 'string', 'date_format:Y-m-d'],
        ];
    }

    /** Enteros de verdad: un número o un texto de solo cifras (los select envían textos), no true. */
    private function entero(): Closure
    {
        return fn (string $atributo, mixed $valor, Closure $falla) => is_int($valor)
            || (is_string($valor) && preg_match('/^\d+$/', $valor) === 1)
            || $falla('Revisa :attribute: debe ser un número entero.');
    }
}
