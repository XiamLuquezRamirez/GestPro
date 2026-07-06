<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEvento extends Model
{
    public $timestamps = false;

    protected $fillable = ['nombre', 'icono', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'tipo_eventos');
    }
}
