<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cronograma de una etapa en un expediente de contratación: su fecha programada y su fecha real.
 * La fila nace al guardar la primera fecha de la etapa o al sugerir la real.
 *
 * `fecha_real_sugerida` dice que el sistema ya atendió la fecha real de la vez actual en que la
 * etapa está completa:
 * - con fecha real: la puso el sistema al completarse la etapa y nadie la ha tocado (sugerida());
 * - sin fecha real: una persona la vació estando la etapa completa (descartada()), y no se vuelve a
 *   sugerir hasta que la etapa deje de estar completa y se complete otra vez.
 * Falso con una fecha real: la puso una persona. Falso sin ella: nadie la ha atendido; si la etapa
 * se completa, se sugiere. La API muestra `fecha_real_sugerida` solo en el primer caso (sugerida()).
 *
 * Revisión (R15, solo avisa): `estado_revision` es sin_revisar, aprobada o devuelta, con quién la
 * revisó (`usuario_reviso_id`), cuándo (`fecha_revision`), la observación y, al aprobarla, cuántos
 * requisitos de la etapa faltaban (`pendientes_al_aprobar`). El estado que ve la pantalla
 * (pendiente, lista, aprobada, aprobada con cambios, devuelta) se calcula con el checklist:
 * AvisosContratacion::revision().
 */
class ExpedienteEtapa extends Model
{
    protected $table = 'expediente_etapas';
    public const SIN_REVISAR = 'sin_revisar';
    public const APROBADA = 'aprobada';
    public const DEVUELTA = 'devuelta';

    protected $fillable = [
        'expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id',
        'estado_revision', 'usuario_reviso_id', 'fecha_revision', 'observacion_revision', 'pendientes_al_aprobar',
    ];
    protected $attributes = ['fecha_real_sugerida' => false, 'estado_revision' => self::SIN_REVISAR];
    protected $casts = [
        'expediente_id' => 'integer',
        'etapa_id' => 'integer',
        'fecha_programada' => 'date',
        'fecha_real' => 'date',
        'fecha_real_sugerida' => 'boolean',
        'usuario_actualizo_id' => 'integer',
        'usuario_reviso_id' => 'integer',
        'fecha_revision' => 'datetime',
        'pendientes_al_aprobar' => 'integer',
    ];

    /** La fecha real la puso el sistema y nadie la ha tocado. */
    public function sugerida(): bool
    {
        return $this->fecha_real_sugerida && $this->fecha_real !== null;
    }

    /** Una persona vació la fecha real estando la etapa completa: no se vuelve a sugerir mientras lo siga. */
    public function descartada(): bool
    {
        return $this->fecha_real_sugerida && $this->fecha_real === null;
    }

    public function aprobada(): bool
    {
        return $this->estado_revision === self::APROBADA;
    }

    public function devuelta(): bool
    {
        return $this->estado_revision === self::DEVUELTA;
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_id');
    }

    public function usuarioReviso(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_reviso_id');
    }
}
