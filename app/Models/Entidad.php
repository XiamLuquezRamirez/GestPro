<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entidad extends Model
{
    public $timestamps = false;

    protected $table = 'entidades';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function proyectosQuePresenta(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'entidad_presenta');
    }

    public function proyectosQueFinancia(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'entidad_financia');
    }

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'entidad_contratante');
    }
}
