<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModificacionContrato extends Model
{
    public $timestamps = false;

    protected $table = 'modificaciones_contrato';

    protected $fillable = [
        'contrato_id', 'numero_otrosi', 'tipo', 'valor_adicion',
        'dias_prorroga', 'fecha_modificacion', 'justificacion',
    ];

    protected $casts = [
        'valor_adicion' => 'decimal:2',
        'dias_prorroga' => 'integer',
        'fecha_modificacion' => 'date',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
