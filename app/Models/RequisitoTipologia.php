<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitoTipologia extends Model
{
    public $timestamps = false;

    protected $table = 'requisitos_tipologias';
    protected $fillable = ['clave', 'nombre', 'orden', 'activo'];
    protected $casts = ['orden' => 'integer', 'activo' => 'boolean'];
}
