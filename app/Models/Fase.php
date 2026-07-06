<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fase extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'color', 'activo', 'dashboard'];
    protected $casts = [
        'activo' => 'boolean',
        'dashboard' => 'boolean',
    ];

    public function estados(): HasMany
    {
        return $this->hasMany(Estado::class, 'fase');
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'fase');
    }
}
