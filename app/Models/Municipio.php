<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipio extends Model
{
    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo', 'departamento', 'imagen'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function departamentoRel(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento', 'codigo');
    }

    // References codigo, not id: proyectos.municipio holds the municipio's codigo
    // (see Proyecto::municipioRel() and the FK comment in the proyectos migration).
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'municipio', 'codigo');
    }
}
