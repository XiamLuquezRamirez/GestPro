<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'municipio', 'nombre', 'descripcion', 'fecha_inicio', 'estado', 'fase',
        'presupuesto', 'entidad_presenta', 'entidad_financia', 'fuente_financiacion', 'progreso', 'sector',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'presupuesto' => 'decimal:2',
        'progreso' => 'integer',
    ];

    // These four are named *Rel() (not municipio()/estado()/fase()/sector()) because each FK
    // column is a single word matching what the relation would naturally be called. Eloquent's
    // getAttribute() always returns the raw column value when a relation method has the exact
    // same name as an existing attribute, so e.g. `$proyecto->municipio` would never resolve to
    // the relation if the method were named municipio(). entidadPresenta()/entidadFinancia() don't
    // need the suffix: their columns are snake_case (entidad_presenta/entidad_financia), which
    // never matches the camelCase method name, so there's no collision.
    public function municipioRel(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio');
    }

    public function estadoRel(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado');
    }

    public function faseRel(): BelongsTo
    {
        return $this->belongsTo(Fase::class, 'fase');
    }

    public function sectorRel(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'sector');
    }

    public function entidadPresenta(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_presenta');
    }

    public function entidadFinancia(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_financia');
    }

    public function presupuestoComponentes(): HasMany
    {
        return $this->hasMany(PresupuestoProyecto::class, 'proyecto');
    }

    public function procesosLicitacion(): HasMany
    {
        return $this->hasMany(ProcesoLicitacion::class, 'proyecto');
    }

    public function eventosLicitacion(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proyecto');
    }

    public function checksFormulacion(): HasMany
    {
        return $this->hasMany(CheckFormulacion::class, 'proyecto');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'proyecto');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'proyecto');
    }
}
