<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModificacionContrato extends Model
{
    /** Tipo de las suspensiones (fase 7B): las registra «Suspender», no el formulario de modificaciones. */
    public const SUSPENSION = 'suspension';

    public $timestamps = false;

    protected $table = 'modificaciones_contrato';

    protected $fillable = [
        'contrato_id', 'numero_otrosi', 'tipo', 'valor_adicion',
        'dias_prorroga', 'fecha_modificacion', 'justificacion',
        'fecha_reinicio', 'fecha_reinicio_prevista',
    ];

    protected $casts = [
        'valor_adicion' => 'decimal:2',
        'dias_prorroga' => 'integer',
        'fecha_modificacion' => 'date',
        'fecha_reinicio' => 'date',
        'fecha_reinicio_prevista' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    /** Suspensiones abiertas: sin fecha de reinicio. */
    public function scopeSuspensionesAbiertas(Builder $consulta): Builder
    {
        return $consulta->where('tipo', self::SUSPENSION)->whereNull('fecha_reinicio');
    }
}
