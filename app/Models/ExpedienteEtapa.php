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
 */
class ExpedienteEtapa extends Model
{
    protected $table = 'expediente_etapas';
    protected $fillable = ['expediente_id', 'etapa_id', 'fecha_programada', 'fecha_real', 'fecha_real_sugerida', 'usuario_actualizo_id'];
    protected $attributes = ['fecha_real_sugerida' => false];
    protected $casts = [
        'expediente_id' => 'integer',
        'etapa_id' => 'integer',
        'fecha_programada' => 'date',
        'fecha_real' => 'date',
        'fecha_real_sugerida' => 'boolean',
        'usuario_actualizo_id' => 'integer',
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

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(EtapaContratacion::class, 'etapa_id');
    }
}
