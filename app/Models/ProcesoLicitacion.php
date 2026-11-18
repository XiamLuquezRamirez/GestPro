<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoLicitacion extends Model
{
    public const SECOP_II = 'secop_ii';
    public const SECOP_I = 'secop_i';
    public const TVEC = 'tvec';
    public const NINGUNA = 'ninguna';
    public const PLATAFORMAS = [self::SECOP_II, self::SECOP_I, self::TVEC, self::NINGUNA];

    public const PREPARACION = 'preparacion';
    public const PUBLICADO = 'publicado';
    public const EVALUACION = 'evaluacion';
    public const ADJUDICADO = 'adjudicado';
    public const DESIERTO = 'desierto';
    public const CELEBRADO = 'celebrado';
    public const CANCELADO = 'cancelado';
    public const REVOCADO = 'revocado';
    public const ESTADOS = [
        self::PREPARACION, self::PUBLICADO, self::EVALUACION, self::ADJUDICADO,
        self::DESIERTO, self::CELEBRADO, self::CANCELADO, self::REVOCADO,
    ];

    /** Estados en los que se puede registrar un contrato del proceso. */
    public const ADMITEN_CONTRATOS = [self::ADJUDICADO, self::CELEBRADO];

    protected $table = 'procesos_licitacion';
    protected $fillable = [
        'proyecto', 'objeto', 'codigo_proceso', 'tipo_proceso', 'modalidad', 'plataforma', 'url_proceso',
        'estado', 'entidad_contratante', 'tipo_proponente', 'entidad_proponente', 'monto', 'usuario_creo_id',
        'fuente_id', 'fecha_apertura', 'fecha_cierre', 'responsable_id', 'observaciones',
    ];
    protected $attributes = ['estado' => self::PREPARACION];
    protected $casts = [
        'proyecto' => 'integer',
        'modalidad' => 'integer',
        'entidad_contratante' => 'integer',
        'monto' => 'decimal:2',
        'usuario_creo_id' => 'integer',
        'fuente_id' => 'integer',
        'responsable_id' => 'integer',
        'fecha_apertura' => 'date',
        'fecha_cierre' => 'date',
    ];

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

    public function usuarioCreo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creo_id');
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
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
