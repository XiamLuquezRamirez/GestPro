<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estado extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'color', 'activo', 'icono', 'fase'];
    protected $casts = ['activo' => 'boolean'];

    // Named faseRel() (not fase()) because the FK column is also called `fase` — Eloquent's
    // getAttribute() always returns the raw column value when a relation method has the exact
    // same name as an existing attribute, so `$estado->fase` would never resolve to the relation.
    // This project's convention: whenever a belongsTo FK column is a single word (no underscore)
    // and would collide with the natural relation name, suffix the relation method with `Rel`.
    public function faseRel(): BelongsTo
    {
        return $this->belongsTo(Fase::class, 'fase');
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'estado');
    }
}
