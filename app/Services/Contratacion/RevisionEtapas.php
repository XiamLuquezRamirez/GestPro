<?php

namespace App\Services\Contratacion;

use App\Exceptions\AprobacionConPendientesException;
use App\Exceptions\ReglaNegocioException;
use App\Models\ChecklistHistorial;
use App\Models\EtapaContratacion;
use App\Models\ExpedienteEtapa;
use App\Models\ProyectoExpediente;
use App\Models\User;
use App\Services\Viabilizacion\ExpedienteService;
use App\Services\Viabilizacion\HistorialChecklist;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Revisión por etapa de un expediente de contratación (R15): aprobar y devolver una etapa. Solo
 * avisa: la revisión nunca bloquea marcar requisitos, cambiar fechas ni cerrar.
 *
 * Reglas:
 * - Solo en un expediente de contratación en borrador, y en una etapa que tiene requisitos en él
 *   (una de su avance por etapa). La fila de la etapa nace si no existe, como con las fechas.
 * - Aprobar: con requisitos que cuentan sin cumplir, sin `confirmar` responde cuántos faltan
 *   (AprobacionConPendientesException) y no aprueba; confirmada, aprueba y guarda cuántos
 *   faltaban. Una etapa ya aprobada no se aprueba otra vez. La fecha real, si está vacía o es la
 *   sugerida, pasa a ser la de hoy (la de la aprobación) y deja de ser sugerida; la que escribió
 *   una persona se respeta. Sigue siendo corregible.
 * - Devolver: con una observación (obligatoria); una devuelta se puede devolver otra vez. Si la
 *   etapa estaba aprobada y su fecha real es la que puso la aprobación (nadie la cambió
 *   después), vuelve a la regla de la sugerida: sugerida si la etapa está completa, vacía si no.
 * - Queda en el historial del expediente (`etapa_aprobada`, `etapa_devuelta`) con la etapa, el
 *   estado de revisión de antes y de después, la observación y la fecha real si cambió.
 * - Sobre el expediente bloqueado, como las acciones del checklist y las fechas de una etapa.
 */
final class RevisionEtapas
{
    public const YA_APROBADA = 'La etapa ya está aprobada.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /** @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente */
    public function aprobar(ProyectoExpediente $expediente, EtapaContratacion $etapa, bool $confirmar, ?string $observacion, ?User $usuario): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance) use ($confirmar, $observacion, $usuario) {
            if ($fila->aprobada()) {
                throw new ReglaNegocioException(self::YA_APROBADA);
            }
            $pendientes = $avance['total'] - $avance['cumplidos'];
            if ($pendientes > 0 && ! $confirmar) {
                $falta = $pendientes === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes} requisitos";
                throw new AprobacionConPendientesException("{$falta} de esta etapa. Confirma para aprobarla de todos modos.", $pendientes);
            }

            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $fijaFecha = $fila->fecha_real === null || $fila->sugerida();
            if ($fijaFecha) {
                $fila->fecha_real = $this->avisos->hoy()->toDateString();
                $fila->fecha_real_sugerida = false;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::APROBADA, $observacion, $pendientes, $usuario);

            return ['etapa_aprobada', [...$antes, ...($fijaFecha ? $fechaAntes : [])], [
                'estado_revision' => ExpedienteEtapa::APROBADA,
                ...($observacion !== null ? ['observacion' => $observacion] : []),
                'pendientes_al_aprobar' => $pendientes,
                ...($pendientes > 0 ? ['con_pendientes' => true] : []),
                ...($fijaFecha ? $this->fecha($fila) : []),
            ]];
        });
    }

    /** @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente */
    public function devolver(ProyectoExpediente $expediente, EtapaContratacion $etapa, string $observacion, ?User $usuario): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance, ProyectoExpediente $expediente) use ($etapa, $observacion, $usuario) {
            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            $vuelve = $fila->aprobada() && $this->fechaDeLaAprobacion($expediente, $etapa, $fila);
            if ($vuelve) {
                $completa = $this->avisos->completa($avance);
                $fila->fecha_real = $completa ? $fila->fecha_real : null;
                $fila->fecha_real_sugerida = $completa;
            }
            $this->guardarRevision($fila, ExpedienteEtapa::DEVUELTA, $observacion, null, $usuario);

            return ['etapa_devuelta', [...$antes, ...($vuelve ? $fechaAntes : [])], [
                'estado_revision' => ExpedienteEtapa::DEVUELTA,
                'observacion' => $observacion,
                ...($vuelve ? $this->fecha($fila) : []),
            ]];
        });
    }

    /**
     * Lo común de aprobar y devolver: las reglas del expediente y la etapa, la fila de la etapa
     * (nace si no existe), el historial y la respuesta.
     *
     * @param \Closure(ExpedienteEtapa, array, ProyectoExpediente): array{0: string, 1: array, 2: array} $accion
     *        cambia la fila (sin guardar lo que no sea la revisión) y da el evento, el antes y el después
     */
    private function revisar(ProyectoExpediente $expediente, EtapaContratacion $etapa, ?User $usuario, \Closure $accion): array
    {
        if (! $expediente->esContratacion()) {
            throw new ReglaNegocioException(CronogramaExpediente::NO_ES_CONTRATACION);
        }

        DB::transaction(function () use ($expediente, $etapa, $usuario, $accion) {
            $this->expedientes->bloquear($expediente);
            $this->expedientes->exigirBorrador($expediente);
            $avance = collect($this->expedientes->avancePorEtapaDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(CronogramaExpediente::SIN_REQUISITOS);
            }

            $fila = ExpedienteEtapa::query()
                ->where('expediente_id', $expediente->id)
                ->where('etapa_id', $etapa->id)
                ->first() ?? new ExpedienteEtapa(['expediente_id' => $expediente->id, 'etapa_id' => $etapa->id]);
            [$evento, $antes, $despues] = $accion($fila, $avance, $expediente);

            $cual = ['etapa_id' => $etapa->id, 'etapa' => $etapa->nombre];
            $this->historial->registrarExpediente($expediente, $evento, [...$cual, ...$antes], [...$cual, ...$despues], $usuario);
            $expediente->touch();
        });

        $cronograma = $this->expedientes->cronogramaDeContratacion($expediente);

        return [
            'etapa' => collect($cronograma['avance_por_etapa'])->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id),
            'avisos' => $cronograma['avisos'],
        ];
    }

    private function guardarRevision(ExpedienteEtapa $fila, string $estado, ?string $observacion, ?int $pendientes, ?User $usuario): void
    {
        $fila->fill([
            'estado_revision' => $estado,
            'usuario_reviso_id' => $usuario?->id,
            'fecha_revision' => now(),
            'observacion_revision' => $observacion,
            'pendientes_al_aprobar' => $pendientes,
        ])->save();
        $fila->setRelation('usuarioReviso', $usuario);
    }

    /**
     * La fecha real de la etapa es la que puso su aprobación vigente: el último `etapa_aprobada`
     * de la etapa la fijó, es la misma de ahora y ningún `etapa_fechas` posterior la cambió.
     */
    private function fechaDeLaAprobacion(ProyectoExpediente $expediente, EtapaContratacion $etapa, ExpedienteEtapa $fila): bool
    {
        $eventos = ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)
            ->whereIn('evento', ['etapa_aprobada', 'etapa_fechas'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ChecklistHistorial $h) => ($h->despues['etapa_id'] ?? null) === $etapa->id);
        foreach ($eventos as $evento) {
            if ($evento->evento === 'etapa_fechas') {
                if (array_key_exists('fecha_real', $evento->despues)) {
                    return false;
                }
                continue;
            }

            return array_key_exists('fecha_real', $evento->despues)
                && $evento->despues['fecha_real'] === $fila->fecha_real?->format('Y-m-d');
        }

        return false;
    }

    /** @return array{estado_revision: string, observacion?: string} */
    private function revision(ExpedienteEtapa $fila): array
    {
        return [
            'estado_revision' => $fila->estado_revision,
            ...($fila->observacion_revision !== null ? ['observacion' => $fila->observacion_revision] : []),
        ];
    }

    /** @return array{fecha_real: ?string, fecha_real_sugerida: bool} */
    private function fecha(ExpedienteEtapa $fila): array
    {
        return [
            'fecha_real' => $fila->fecha_real instanceof DateTimeInterface ? $fila->fecha_real->format('Y-m-d') : $fila->fecha_real,
            'fecha_real_sugerida' => $fila->sugerida(),
        ];
    }
}
