<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoVersion extends Model
{
    public const BORRADOR = 'borrador';
    public const PUBLICADA = 'publicada';

    protected $table = 'catalogo_versiones';
    protected $fillable = ['plantilla_id', 'numero', 'estado', 'descripcion', 'fecha_publicacion', 'usuario_publico_id', 'vigente'];
    protected $casts = [
        'plantilla_id' => 'integer',
        'numero' => 'integer',
        'usuario_publico_id' => 'integer',
        'vigente' => 'boolean',
        'fecha_publicacion' => 'datetime',
    ];

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaChecklist::class, 'plantilla_id');
    }

    public function usuarioPublico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_publico_id');
    }

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
