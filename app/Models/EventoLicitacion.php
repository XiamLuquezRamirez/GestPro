<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoLicitacion extends Model
{
    public $timestamps = false;

    protected $table = 'eventos_licitacion';
    protected $fillable = ['proyecto', 'proceso', 'descripcion', 'fecha', 'cumplido'];
    protected $casts = [
        'fecha' => 'date',
        'cumplido' => 'boolean',
    ];

    // proyectoRel()/procesoRel(): both FK columns are single words that would collide with the
    // natural relation name (see Task 4's note on Estado::faseRel()).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function procesoRel(): BelongsTo
    {
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso');
    }
}
