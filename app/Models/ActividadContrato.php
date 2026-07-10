<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadContrato extends Model
{
    public $timestamps = false;

    protected $table = 'actividades_contrato';
    protected $fillable = ['contrato_id', 'nombre', 'peso'];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function avances(): HasMany
    {
        return $this->hasMany(ActividadAvance::class, 'actividad_id');
    }
}
