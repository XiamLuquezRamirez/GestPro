<?php

namespace App\Services\Contratacion;

use App\Exceptions\ReglaNegocioException;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fechas programada y real de una etapa de un expediente de contratación, puestas por una persona.
 *
 * Reglas:
 * - Solo en un expediente de contratación en borrador, y en una etapa que tiene requisitos en él
 *   (una de su avance por etapa).
 * - Lo que no llega en el pedido no cambia; null borra la fecha. La fecha real no puede ser
 *   posterior a hoy; la programada puede ser cualquiera.
 * - Poner la fecha real a mano, aunque sea la misma que sugirió el sistema, la deja como de una
 *   persona (`fecha_real_sugerida` = false): ya no se borra si la etapa deja de estar completa.
 * - Vaciarla con la etapa completa la deja descartada (ExpedienteEtapa::descartada()): no se
 *   vuelve a sugerir hasta que la etapa deje de estar completa y se complete otra vez. La
 *   sugerencia nace solo cuando una etapa pasa a estar completa.
 * - La fila de la etapa nace con la primera fecha. Lo que cambia queda en el historial del
 *   expediente (`etapa_fechas`, con la etapa y solo los campos que cambiaron).
 * - Sobre el expediente bloqueado, como las acciones del checklist.
 */
final class CronogramaExpediente
{
    public const NO_ES_CONTRATACION = 'El expediente no es de contratación.';
    public const SIN_REQUISITOS = 'La etapa no tiene requisitos en este expediente.';
    public const REAL_FUTURA = 'La fecha real no puede ser posterior a hoy.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /**
     * @param array{fecha_programada?: ?string, fecha_real?: ?string} $datos fechas año-mes-día
     * @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente
     */
    public function guardar(ProyectoExpediente $expediente, EtapaContratacion $etapa, array $datos, ?User $usuario): array
    {
        if (! $expediente->esContratacion()) {
            throw new ReglaNegocioException(self::NO_ES_CONTRATACION);
        }
        $real = $datos['fecha_real'] ?? null;
        if ($real !== null && $real > $this->avisos->hoy()->toDateString()) {
            throw new ReglaNegocioException(self::REAL_FUTURA, [self::REAL_FUTURA], ['fecha_real' => [self::REAL_FUTURA]]);
        }

        DB::transaction(function () use ($expediente, $etapa, $datos, $usuario) {
            $this->expedientes->bloquear($expediente);
            $this->expedientes->exigirBorrador($expediente);
            $avance = collect($this->expedientes->avancePorEtapaDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(self::SIN_REQUISITOS);
            }

            $fila = ExpedienteEtapa::query()
                ->where('expediente_id', $expediente->id)
                ->where('etapa_id', $etapa->id)
                ->first() ?? new ExpedienteEtapa(['expediente_id' => $expediente->id, 'etapa_id' => $etapa->id]);
            $antes = $this->fechas($fila);
            if (array_key_exists('fecha_programada', $datos)) {
                $fila->fecha_programada = $datos['fecha_programada'];
            }
            if (array_key_exists('fecha_real', $datos)) {
                $fila->fecha_real = $datos['fecha_real'];
                $fila->fecha_real_sugerida = $datos['fecha_real'] === null && $this->avisos->completa($avance);
            }
            $despues = $this->fechas($fila);
            $cambios = array_filter($despues, fn ($valor, string $campo) => $valor !== $antes[$campo], ARRAY_FILTER_USE_BOTH);
            if ($cambios === []) {
                return;
            }

            $fila->usuario_actualizo_id = $usuario?->id;
            $fila->save();
            $cual = ['etapa_id' => $etapa->id, 'etapa' => $etapa->nombre];
            $this->historial->registrarExpediente($expediente, 'etapa_fechas', [...$cual, ...array_intersect_key($antes, $cambios)], [...$cual, ...$cambios], $usuario);
            $expediente->touch();
        });

        $cronograma = $this->expedientes->cronogramaDeContratacion($expediente);

        return [
            'etapa' => collect($cronograma['avance_por_etapa'])->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id),
            'avisos' => $cronograma['avisos'],
        ];
    }

    /** @return array{fecha_programada: ?string, fecha_real: ?string, fecha_real_sugerida: bool} */
    private function fechas(ExpedienteEtapa $fila): array
    {
        $fecha = fn (?DateTimeInterface $valor) => $valor?->format('Y-m-d');

        return [
            'fecha_programada' => $fecha($fila->fecha_programada),
            'fecha_real' => $fecha($fila->fecha_real),
            'fecha_real_sugerida' => $fila->sugerida(),
        ];
    }
}
