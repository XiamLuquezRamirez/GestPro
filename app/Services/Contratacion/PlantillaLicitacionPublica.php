<?php

namespace App\Services\Contratacion;

use App\Models\EtapaContratacion;
use App\Models\Modalidad;
use App\Models\PlantillaChecklist;
use App\Models\RequisitoCatalogo;
use App\Services\Viabilizacion\CatalogoService;
use App\Services\Viabilizacion\PlantillaService;
use Illuminate\Support\Facades\DB;

/**
 * Borrador de la plantilla de contratación "Licitación pública – SECOP II", armado a partir de
 * la guía CCE-SEC-G1-18 de Colombia Compra (sección 4 del diseño de contratación). Lo usa el
 * comando plantillas:sembrar-contratacion.
 *
 * Se crea con los mismos servicios que la pantalla de plantillas, para que valide igual, y queda
 * en borrador: no se ofrece a ningún proceso hasta que la entidad la valide y un administrador
 * la publique. Todos los ítems son obligatorios salvo los condicionados, que dicen cuándo aplican.
 */
final class PlantillaLicitacionPublica
{
    public const CLAVE = 'licitacion_publica_secop_ii';
    public const NOMBRE = 'Licitación pública – SECOP II';
    public const MODALIDAD = 'licitacion_publica';
    public const REFERENCIA = 'Guía CCE-SEC-G1-18 (SECOP II, licitación pública)';

    private const DESCRIPCION = 'Borrador a partir de la guía CCE-SEC-G1-18 de Colombia Compra (SECOP II, licitación pública). '
        . 'La entidad (jurídica o contratación) debe validarlo antes de publicarlo.';

    /**
     * Requisitos en el orden en que se siembran: [clave de la etapa, texto, cuándo aplica si es
     * condicionado, observaciones].
     *
     * @var list<array{0: string, 1: string, 2?: ?string, 3?: string}>
     */
    private const REQUISITOS = [
        ['planeacion', 'Estudios previos'],
        ['planeacion', 'Certificado de disponibilidad presupuestal (CDP)'],
        ['planeacion', 'Vínculo con el Plan Anual de Adquisiciones o su justificación'],
        ['planeacion', 'Código BPIN', 'Si el gasto es de inversión.'],
        ['pliego_borrador', 'Proceso creado en SECOP II con su número'],
        ['pliego_borrador', 'Cronograma con todos los hitos, incluidas las audiencias de riesgos y de adjudicación'],
        ['pliego_borrador', 'Cuestionario y lista de precios configurados'],
        ['pliego_borrador', 'Modelo de evaluación y criterios de desempate'],
        ['pliego_borrador', 'Garantías definidas'],
        // La guía lo trae al configurar el proceso: el pliego fija el porcentaje.
        ['pliego_borrador', 'Porcentaje mínimo para pequeños productores (Decreto 248 de 2021)', 'Si se compran alimentos.'],
        ['publicacion_borrador', 'Aviso de convocatoria publicado con la misma fecha que los estudios previos'],
        ['publicacion_borrador', 'Proyecto de pliego publicado'],
        ['observaciones_borrador', 'Todas las observaciones respondidas y publicadas'],
        ['pliego_definitivo', 'Acto administrativo de apertura'],
        ['pliego_definitivo', 'Pliego definitivo publicado'],
        ['adendas', 'Adendas publicadas a más tardar 3 días hábiles antes del cierre', 'Si hubo adendas.'],
        ['cierre', 'Lista de oferentes o acta de cierre publicada'],
        ['evaluacion', 'Informe de evaluación publicado con su plazo de observaciones'],
        ['evaluacion', 'Subsanaciones solicitadas e incorporadas', 'Si hubo subsanaciones.'],
        ['observaciones_informe', 'Respuestas a las observaciones al informe publicadas'],
        ['observaciones_informe', 'Nuevo informe de evaluación', 'Si cambió la evaluación.'],
        ['adjudicacion', 'Acto de adjudicación o de declaratoria de desierto, en firme'],
        ['adjudicacion', 'Informe de selección publicado'],
        ['contrato', 'Contrato electrónico aceptado por el contratista'],
        ['contrato', 'Garantías aprobadas', null, 'A validar por la entidad: la guía no lo detalla.'],
    ];

    public function __construct(
        private readonly PlantillaService $plantillas,
        private readonly CatalogoService $catalogo,
    ) {
    }

    public function existente(): ?PlantillaChecklist
    {
        return PlantillaChecklist::where('clave', self::CLAVE)->first();
    }

    /**
     * Lo que impide sembrar: otra plantilla ya tiene el nombre (sin la clave, que existente()
     * descarta antes; PlantillaService lo rechazaría igual), o la modalidad o alguna etapa no
     * existen o están inactivas. Así --simular dice lo mismo que la ejecución real.
     *
     * @return list<string>
     */
    public function impedimentos(): array
    {
        $errores = [];

        if ($repetido = $this->plantillas->nombreEnUso(self::NOMBRE)) {
            $errores[] = "Ya existe una plantilla llamada \"{$repetido}\". Cámbiele el nombre en Plantillas de checklist para poder sembrar esta.";
        }

        $modalidad = Modalidad::where('clave', self::MODALIDAD)->first();
        if (! $modalidad) {
            $errores[] = 'Falta la modalidad sembrada ' . self::MODALIDAD . '. Ejecute las migraciones.';
        } elseif (! $modalidad->activo) {
            $errores[] = "La modalidad \"{$modalidad->nombre}\" está inactiva. Actívela en Parámetros → Modalidades.";
        }

        $claves = $this->clavesDeEtapas();
        $etapas = EtapaContratacion::whereIn('clave', $claves)->get()->keyBy('clave');
        $faltan = array_values(array_filter($claves, fn (string $clave) => ! $etapas->has($clave)));
        $inactivas = $etapas->reject->activo->keys()->all();
        if ($faltan) {
            $errores[] = 'Faltan etapas de contratación sembradas: ' . implode(', ', $faltan) . '. Ejecute las migraciones.';
        }
        if ($inactivas) {
            $errores[] = 'Hay etapas de contratación inactivas: ' . implode(', ', $inactivas) . '. Actívelas en Parámetros → Etapas de contratación.';
        }

        return $errores;
    }

    /**
     * Los requisitos que se sembrarían, con el nombre de su etapa.
     *
     * @return list<array{etapa: string, texto: string, condicion: ?string}>
     */
    public function vistaPrevia(): array
    {
        $nombres = EtapaContratacion::whereIn('clave', $this->clavesDeEtapas())->pluck('nombre', 'clave');

        return array_map(fn (array $r) => [
            'etapa' => $nombres[$r[0]] ?? $r[0],
            'texto' => $r[1],
            'condicion' => $r[2] ?? null,
        ], self::REQUISITOS);
    }

    public function cantidad(): int
    {
        return count(self::REQUISITOS);
    }

    /**
     * Crea la plantilla con su versión en borrador y los requisitos, todo o nada. Quien llama
     * comprueba antes existente() e impedimentos(); los servicios vuelven a validarlo todo.
     */
    public function sembrar(): PlantillaChecklist
    {
        return DB::transaction(function () {
            $plantilla = $this->plantillas->crear([
                'nombre' => self::NOMBRE,
                'descripcion' => self::DESCRIPCION,
                'uso' => PlantillaChecklist::USO_CONTRATACION,
                'todas_fuentes' => true,
                'todos_sectores' => true,
                'modalidad_ids' => [Modalidad::where('clave', self::MODALIDAD)->value('id')],
            ], null);
            $plantilla->update(['clave' => self::CLAVE]);

            $borrador = $plantilla->versiones()->sole();
            $etapas = EtapaContratacion::whereIn('clave', $this->clavesDeEtapas())->pluck('id', 'clave');
            foreach (self::REQUISITOS as $r) {
                $condicion = $r[2] ?? null;
                $this->catalogo->guardarRequisito($borrador, [
                    'texto' => $r[1],
                    'etapa_id' => (int) $etapas[$r[0]],
                    'caracter' => $condicion ? RequisitoCatalogo::CONDICIONADO : RequisitoCatalogo::OBLIGATORIO,
                    'condicion_descripcion' => $condicion,
                    'referencia_normativa' => self::REFERENCIA,
                    'nota' => $r[3] ?? null,
                ]);
            }

            return $plantilla;
        });
    }

    /** @return list<string> */
    private function clavesDeEtapas(): array
    {
        return array_values(array_unique(array_column(self::REQUISITOS, 0)));
    }
}
