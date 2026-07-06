<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoProceso extends Model
{
    public $timestamps = false;

    protected $table = 'tipos_procesos';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'tipo_proceso');
    }
}
