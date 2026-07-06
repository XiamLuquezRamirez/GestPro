<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prioridad extends Model
{
    public $timestamps = false;

    protected $table = 'prioridades';
    protected $fillable = ['nombre', 'color', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'prioridad');
    }
}
