<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceFinanciero extends Model
{
    public $timestamps = false;

    protected $table = 'avance_financiero';
    protected $fillable = [
        'contrato_id', 'descripcion', 'fecha_acta', 'valor_facturado',
        'amortizacion_50', 'valor_presente_acta', 'porcentaje_ejecutado', 'anexo',
    ];

    protected $casts = [
        'fecha_acta' => 'date',
        'valor_facturado' => 'decimal:2',
        'amortizacion_50' => 'decimal:2',
        'valor_presente_acta' => 'decimal:2',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
