<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contrato extends Model
{
    public const CELEBRADO = 'celebrado';
    public const EN_EJECUCION = 'en_ejecucion';
    public const SUSPENDIDO = 'suspendido';
    public const TERMINADO = 'terminado';
    public const LIQUIDADO = 'liquidado';
    public const ANULADO = 'anulado';
    public const ESTADOS = [
        self::CELEBRADO, self::EN_EJECUCION, self::SUSPENDIDO, self::TERMINADO, self::LIQUIDADO, self::ANULADO,
    ];

    // Los contratos antiguos tienen las marcas de tiempo nulas. El resto del código los escribe
    // con DB::table, que no las llena; solo el modelo y el servicio de contratos las mantienen.
    public $timestamps = true;

    protected $fillable = [
        'proyecto', 'n_contrato', 'objeto', 'contratante', 'contratista', 'valor',
        'fecha_inicio', 'fecha_fin', 'interventoria', 'avance_fisico', 'avance_financiero',
        'estado', 'anticipo', 'porcentaje_anticipo', 'proceso_licitacion',
        'valor_inicial', 'fecha_fin_inicial',
        'tipo_contrato_id', 'contratista_identificacion', 'fecha_suscripcion', 'supervisor',
        'numero_rp', 'fecha_rp', 'fecha_acta_inicio', 'usuario_creo_id',
        'contratante_nit', 'secop_id_contrato', 'secop_proceso', 'url_secop', 'secop_consultado_at',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'valor_inicial' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'fecha_fin_inicial' => 'date',
        'fecha_suscripcion' => 'date',
        'fecha_rp' => 'date',
        'fecha_acta_inicio' => 'date',
        'secop_consultado_at' => 'datetime',
        'anticipo' => 'boolean',
        'tipo_contrato_id' => 'integer',
        'usuario_creo_id' => 'integer',
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

    public function tipoContrato(): BelongsTo
    {
        return $this->belongsTo(TipoProceso::class, 'tipo_contrato_id');
    }

    public function usuarioCreo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creo_id');
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

    public function modificaciones(): HasMany
    {
        return $this->hasMany(ModificacionContrato::class, 'contrato_id');
    }
}
