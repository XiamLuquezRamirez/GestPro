<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActividadAvance extends Model
{
    public $timestamps = false;

    protected $table = 'actividad_avances';
    protected $fillable = ['actividad_id', 'fecha', 'porcentaje_ejecucion'];
    protected $casts = ['fecha' => 'date'];

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ActividadContrato::class, 'actividad_id');
    }
}
