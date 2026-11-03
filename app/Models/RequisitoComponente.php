<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitoComponente extends Model
{
    public $timestamps = false;

    protected $table = 'requisitos_componentes';
    protected $fillable = ['clave', 'nombre', 'orden', 'activo'];
    protected $casts = ['orden' => 'integer', 'activo' => 'boolean'];
}
