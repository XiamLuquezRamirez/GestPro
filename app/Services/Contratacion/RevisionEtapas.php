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
 *   (una de su avance por etapa) y alguno que cuente (total > 0). La fila de la etapa nace si no
 *   existe, como con las fechas.
 * - Aprobar: con requisitos que cuentan sin cumplir, sin `confirmar` responde cuántos faltan
 *   (AprobacionConPendientesException) y no aprueba; tampoco si confirma habiendo visto menos
 *   pendientes de los que hay ahora (`pendientes_vistos`); confirmada, aprueba y guarda cuántos
 *   faltaban. Una etapa aprobada sin cambios después no se aprueba otra vez; una aprobada con
 *   cambios (estado visible calculado bajo el bloqueo) se reaprueba: quién, cuándo, la observación
 *   si llega otra y los pendientes, con la misma confirmación, sin tocar la fecha real, y el
 *   historial lo marca (`reaprobacion`). La fecha real, si está vacía o es la
 *   sugerida, pasa a ser la de hoy (la de la aprobación) y deja de ser sugerida; la que escribió
 *   una persona se respeta. Sigue siendo corregible.
 * - Devolver: con una observación (obligatoria); una devuelta se puede devolver otra vez. Si la
 *   etapa estaba aprobada y su fecha real es la que puso la aprobación (nadie la cambió
 *   después), vuelve a la regla de la sugerida: si la etapa está completa, la sugerida que tenía
 *   antes de aprobarse (está en el `antes` del `etapa_aprobada`) o, si no tenía fecha, la de hoy;
 *   vacía si no está completa.
 * - Queda en el historial del expediente (`etapa_aprobada`, `etapa_devuelta`) con la etapa, el
 *   estado de revisión de antes y de después, la observación y la fecha real si cambió.
 * - Sobre el expediente bloqueado, como las acciones del checklist y las fechas de una etapa.
 */
final class RevisionEtapas
{
    public const YA_APROBADA = 'La etapa ya está aprobada.';
    public const NADA_QUE_REVISAR = 'La etapa no tiene requisitos que cuenten.';

    public function __construct(
        private readonly ExpedienteService $expedientes,
        private readonly HistorialChecklist $historial,
        private readonly AvisosContratacion $avisos,
    ) {
    }

    /**
     * @param ?int $pendientesVistos los pendientes que vio quien confirma: si ahora faltan más, la
     *        confirmación no vale y se vuelve a pedir con el número nuevo
     * @return array{etapa: array, avisos: array} la etapa como en avance_por_etapa y los avisos del expediente
     */
    public function aprobar(ProyectoExpediente $expediente, EtapaContratacion $etapa, bool $confirmar, ?string $observacion, ?User $usuario, ?int $pendientesVistos = null): array
    {
        return $this->revisar($expediente, $etapa, $usuario, function (ExpedienteEtapa $fila, array $avance) use ($confirmar, $observacion, $usuario, $pendientesVistos) {
            // Aprobada con cambios después: se vuelve a aprobar (reaprobación); sin cambios, no.
            $reaprobacion = $fila->aprobada() && ($avance['revision']['estado'] ?? null) === AvisosContratacion::REVISION_CON_CAMBIOS;
            if ($fila->aprobada() && ! $reaprobacion) {
                throw new ReglaNegocioException(self::YA_APROBADA);
            }
            $pendientes = $avance['total'] - $avance['cumplidos'];
            $confirmada = $confirmar && ($pendientesVistos === null || $pendientes <= $pendientesVistos);
            if ($pendientes > 0 && ! $confirmada) {
                $falta = $pendientes === 1 ? 'Falta 1 requisito' : "Faltan {$pendientes} requisitos";
                throw new AprobacionConPendientesException("{$falta} de esta etapa. Confirma para aprobarla de todos modos.", $pendientes);
            }

            $antes = $this->revision($fila);
            $fechaAntes = $this->fecha($fila);
            // La reaprobación no toca la fecha real: la puso (o la respetó) la primera aprobación.
            $fijaFecha = ! $reaprobacion && ($fila->fecha_real === null || $fila->sugerida());
            if ($fijaFecha) {
                $fila->fecha_real = $this->avisos->hoy()->toDateString();
                $fila->fecha_real_sugerida = false;
            }
            // Al reaprobar sin observación nueva, se conserva la anterior.
            $observacion = $reaprobacion ? $observacion ?? $fila->observacion_revision : $observacion;
            $this->guardarRevision($fila, ExpedienteEtapa::APROBADA, $observacion, $pendientes, $usuario);

            return ['etapa_aprobada', [...$antes, ...($fijaFecha ? $fechaAntes : [])], [
                'estado_revision' => ExpedienteEtapa::APROBADA,
                ...($observacion !== null ? ['observacion' => $observacion] : []),
                'pendientes_al_aprobar' => $pendientes,
                ...($pendientes > 0 ? ['con_pendientes' => true] : []),
                ...($reaprobacion ? ['reaprobacion' => true] : []),
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
            $aprobacion = $fila->aprobada() ? $this->aprobacionQueFijoLaFecha($expediente, $etapa, $fila) : null;
            $vuelve = $aprobacion !== null;
            if ($vuelve) {
                // La de antes de aprobar, si era sugerida; sin fecha antes, la de hoy (como al
                // completarse); sin completar, vacía.
                $previa = $aprobacion->antes ?? [];
                $completa = $this->avisos->completa($avance);
                $sugeridaPrevia = ($previa['fecha_real_sugerida'] ?? false) && ($previa['fecha_real'] ?? null) !== null;
                $fila->fecha_real = ! $completa ? null : ($sugeridaPrevia ? $previa['fecha_real'] : $this->avisos->hoy()->toDateString());
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
            // El estado visible de la revisión, calculado bajo el bloqueo.
            $avance = collect($this->expedientes->avancePresentadoDe($expediente))
                ->first(fn (array $a) => ($a['etapa']['id'] ?? null) === $etapa->id);
            if ($avance === null) {
                throw new ReglaNegocioException(CronogramaExpediente::SIN_REQUISITOS);
            }
            // Todo lo de la etapa es opcional o no aplica: no hay nada que aprobar ni devolver.
            if ($avance['total'] === 0) {
                throw new ReglaNegocioException(self::NADA_QUE_REVISAR);
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
     * El `etapa_aprobada` de la aprobación vigente, si la fecha real de la etapa es la que puso:
     * esa aprobación la fijó, es la misma de ahora y ningún `etapa_fechas` posterior la cambió.
     * Las reaprobaciones no tocan la fecha: se mira la aprobación anterior a ellas. Su `antes`
     * guarda la fecha real que tenía la etapa antes de aprobarse.
     */
    private function aprobacionQueFijoLaFecha(ProyectoExpediente $expediente, EtapaContratacion $etapa, ExpedienteEtapa $fila): ?ChecklistHistorial
    {
        $eventos = ChecklistHistorial::query()
            ->where('expediente_id', $expediente->id)
            ->where('entidad_tipo', HistorialChecklist::EXPEDIENTE)
            ->whereIn('evento', ['etapa_aprobada', 'etapa_fechas'])
            ->where('despues->etapa_id', $etapa->id)
            ->orderByDesc('id')
            ->get();
        foreach ($eventos as $evento) {
            if ($evento->evento === 'etapa_fechas') {
                if (array_key_exists('fecha_real', $evento->despues)) {
                    return null;
                }
                continue;
            }
            if ($evento->despues['reaprobacion'] ?? false) {
                continue;
            }

            return array_key_exists('fecha_real', $evento->despues)
                && $evento->despues['fecha_real'] === $fila->fecha_real?->format('Y-m-d') ? $evento : null;
        }

        return null;
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
