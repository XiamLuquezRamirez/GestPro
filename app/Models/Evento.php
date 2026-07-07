<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    public $timestamps = false;

    protected $fillable = ['titulo', 'descripcion', 'fecha', 'tipo_eventos', 'prioridad', 'estado_evento', 'proyecto', 'responsable'];
    protected $casts = ['fecha' => 'date'];

    // tipoEvento() doesn't need a suffix: its column is snake_case (tipo_eventos), which never
    // matches the camelCase method name. prioridad()/proyecto()/responsable() would each collide
    // with their own single-word FK column (see Task 4's note on Estado::faseRel()), so they're
    // suffixed with Rel.
    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(TipoEvento::class, 'tipo_eventos');
    }

    public function prioridadRel(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'prioridad');
    }

    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function responsableRel(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable');
    }
}
