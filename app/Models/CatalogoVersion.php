<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoVersion extends Model
{
    public const BORRADOR = 'borrador';
    public const PUBLICADA = 'publicada';

    protected $table = 'catalogo_versiones';
    protected $fillable = ['numero', 'estado', 'descripcion', 'fecha_publicacion', 'vigente'];
    protected $casts = [
        'numero' => 'integer',
        'vigente' => 'boolean',
        'fecha_publicacion' => 'datetime',
    ];

    public function requisitos(): HasMany
    {
        return $this->hasMany(RequisitoCatalogo::class, 'catalogo_version_id');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(ProyectoExpediente::class, 'catalogo_version_id');
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('vigente', true);
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }
}
