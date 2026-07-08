<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoUbicacion extends Model
{
    public $timestamps = false;

    protected $fillable = ['proyecto', 'lat', 'lng'];
    protected $casts = ['lat' => 'decimal:7', 'lng' => 'decimal:7'];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see
    // PresupuestoProyecto::proyectoRel() for the same pattern in this project).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
