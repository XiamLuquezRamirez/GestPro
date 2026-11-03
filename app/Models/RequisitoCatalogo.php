<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoCatalogo extends Model
{
    public $timestamps = false;

    protected $table = 'requisitos_catalogo';
    protected $fillable = [
        'catalogo_version_id', 'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id',
        'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
    ];
    protected $casts = [
        'catalogo_version_id' => 'integer',
        'sector_id' => 'integer',
        'tipo_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CatalogoVersion::class, 'catalogo_version_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(RequisitoSector::class, 'sector_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(RequisitoTipo::class, 'tipo_id');
    }
}
