<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoExpediente extends Model
{
    // De viabilización, por proyecto: borrador -> radicado. De contratación, uno por proceso:
    // borrador -> cerrado. El cierre guarda su fecha y su usuario en fecha_radicado y
    // usuario_radicado_id, los del acto que termina el trámite.
    public const TIPO_VIABILIZACION = 'viabilizacion';
    public const TIPO_CONTRATACION = 'contratacion';
    public const TIPOS = [self::TIPO_VIABILIZACION, self::TIPO_CONTRATACION];

    public const BORRADOR = 'borrador';
    public const RADICADO = 'radicado';
    public const CERRADO = 'cerrado';

    protected $table = 'proyectos_expedientes';
    protected $fillable = [
        'proyecto_id', 'tipo', 'proceso_id', 'fase_sgr', 'fecha_configuracion', 'estado',
        'fecha_radicado', 'usuario_radicado_id', 'usuario_creo_id',
    ];
    // El mismo valor por defecto de la columna, para que un expediente recién creado lo tenga
    // sin volver a leerlo.
    protected $attributes = ['tipo' => self::TIPO_VIABILIZACION];
    protected $casts = [
        'proyecto_id' => 'integer',
        'proceso_id' => 'integer',
        'fase_sgr' => 'integer',
        'fecha_configuracion' => 'datetime',
        'fecha_radicado' => 'datetime',
    ];

    public function estaConfigurado(): bool
    {
        return $this->fecha_configuracion !== null;
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /** Proceso de contratación del expediente; null en los de viabilización. */
    public function proceso(): BelongsTo
    {
        return $this->belongsTo(ProcesoLicitacion::class, 'proceso_id');
    }

    public function scopeViabilizacion(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('tipo'), self::TIPO_VIABILIZACION);
    }

    public function scopeContratacion(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('tipo'), self::TIPO_CONTRATACION);
    }

    public function esViabilizacion(): bool
    {
        return $this->tipo === self::TIPO_VIABILIZACION;
    }

    public function esContratacion(): bool
    {
        return $this->tipo === self::TIPO_CONTRATACION;
    }

    /** Versiones de plantilla que usa el expediente. La etapa 5 permite más de una. */
    public function versionesPlantilla(): BelongsToMany
    {
        return $this->belongsToMany(CatalogoVersion::class, 'expediente_plantillas', 'expediente_id', 'catalogo_version_id')
            ->withPivot('es_principal');
    }

    public function sectores(): BelongsToMany
    {
        return $this->belongsToMany(RequisitoSector::class, 'expediente_sectores', 'expediente_id', 'sector_id');
    }

    public function tipos(): BelongsToMany
    {
        return $this->belongsToMany(RequisitoTipo::class, 'expediente_tipos', 'expediente_id', 'tipo_id');
    }

    public function componentes(): BelongsToMany
    {
        return $this->belongsToMany(RequisitoComponente::class, 'expediente_componentes', 'expediente_id', 'componente_id');
    }

    public function tipologias(): BelongsToMany
    {
        return $this->belongsToMany(RequisitoTipologia::class, 'expediente_tipologias', 'expediente_id', 'tipologia_id');
    }

    /** Todas las filas del checklist, incluidas las que dejaron de aplicar. */
    public function requisitos(): HasMany
    {
        return $this->hasMany(ProyectoRequisito::class, 'expediente_id');
    }

    public function usuarioRadico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_radicado_id');
    }

    public function usuarioCreo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creo_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::BORRADOR;
    }
}
