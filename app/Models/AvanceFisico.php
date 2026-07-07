<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceFisico extends Model
{
    public $timestamps = false;

    protected $table = 'avance_fisico';
    protected $fillable = ['contrato_id', 'descripcion_avance_fisico', 'fecha_avance_fisico', 'valor_avance_fisico'];
    protected $casts = ['fecha_avance_fisico' => 'date'];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }
}
