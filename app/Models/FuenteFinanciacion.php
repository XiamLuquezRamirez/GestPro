<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FuenteFinanciacion extends Model
{
    public const SGR = 'sgr';
    public const RECURSOS_PROPIOS = 'recursos_propios';

    public $timestamps = false;

    protected $table = 'fuentes_financiacion';
    protected $fillable = ['clave', 'nombre', 'descripcion', 'orden', 'activo'];
    protected $casts = ['activo' => 'boolean', 'orden' => 'integer'];

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'proyecto_fuentes', 'fuente_id', 'proyecto_id');
    }
}
