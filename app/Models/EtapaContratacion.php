<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Etapa del catálogo de contratación. La clave no se edita; el orden lo fija el administrador. */
class EtapaContratacion extends Model
{
    protected $table = 'etapas_contratacion';
    protected $fillable = ['clave', 'nombre', 'descripcion', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];
}
