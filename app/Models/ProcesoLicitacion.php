<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoLicitacion extends Model
{
    public $timestamps = false;

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'codigo_proceso', 'tipo_proceso', 'modalidad',
        'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto',
    ];
    protected $casts = ['monto' => 'decimal:2'];

    // proyectoRel()/modalidadRel(): both FK columns (proyecto, modalidad) are single words that
    // would collide with the natural relation name (see Task 4's note on Estado::faseRel()).
    // tipoProceso()/entidadContratante() don't need the suffix: their columns are snake_case
    // (tipo_proceso/entidad_contratante), which never matches the camelCase method name.
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_proceso');
    }

    public function modalidadRel(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class, 'modalidad');
    }

    public function entidadContratante(): BelongsTo
    {
        return $this->belongsTo(Entidad::class, 'entidad_contratante');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoLicitacion::class, 'proceso');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'proceso_licitacion');
    }
}
