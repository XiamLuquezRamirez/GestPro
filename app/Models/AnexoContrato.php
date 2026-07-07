<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnexoContrato extends Model
{
    // Explicit override: Eloquent's default convention singularizes "Anexo" and pluralizes
    // "Contrato" independently, producing "anexo_contratos" instead of the actual table name.
    protected $table = 'anexos_contratos';
    protected $fillable = ['contrato_id', 'descripcion', 'nombre_archivo', 'ruta_archivo', 'fecha'];
    protected $casts = ['fecha' => 'date'];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
