<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoTipo extends Model
{
    public $timestamps = false;

    protected $table = 'requisitos_tipos';
    protected $fillable = ['sector_id', 'clave', 'nombre', 'orden', 'activo'];
    protected $casts = ['sector_id' => 'integer', 'orden' => 'integer', 'activo' => 'boolean'];

    public function sector(): BelongsTo
    {
        return $this->belongsTo(RequisitoSector::class, 'sector_id');
    }
}
