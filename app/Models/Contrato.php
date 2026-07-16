<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'porcentaje_anticipo', 'proceso_licitacion',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'valor_inicial' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_inicial' => 'date',
        'anticipo' => 'boolean',
    ];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
    // on Estado::faseRel()). procesoLicitacion() doesn't need the suffix: its column is
    // snake_case (proceso_licitacion), which never matches the camelCase method name.
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }

    public function procesoLicitacion(): BelongsTo
    {
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso_licitacion');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(AnexoContrato::class, 'contrato_id');
    }

    public function avancesFinancieros(): HasMany
    {
        return $this->hasMany(AvanceFinanciero::class, 'contrato_id');
    }

    public function avancesFisicos(): HasMany
    {
        return $this->hasMany(AvanceFisico::class, 'contrato_id');
    }

    public function actividades(): HasMany
    {
        return $this->hasMany(ActividadContrato::class, 'contrato_id');
    }
}
