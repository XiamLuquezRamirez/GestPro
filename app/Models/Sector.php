<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    public $timestamps = false;

    protected $table = 'sectores';
    protected $fillable = ['nombre', 'activo', 'requisitos_sector_id'];
    protected $casts = ['activo' => 'boolean'];

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'sector');
    }

    /** Sector SGR equivalente; solo se usa para preseleccionar al crear un expediente. */
    public function requisitosSector(): BelongsTo
    {
        return $this->belongsTo(RequisitoSector::class, 'requisitos_sector_id');
    }
}
