<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitoSector extends Model
{
    public $timestamps = false;

    protected $table = 'requisitos_sectores';
    protected $fillable = ['clave', 'nombre', 'fuente', 'orden', 'activo'];
    protected $casts = ['orden' => 'integer', 'activo' => 'boolean'];

    public function tipos(): HasMany
    {
        return $this->hasMany(RequisitoTipo::class, 'sector_id');
    }
}
