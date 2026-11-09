<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProyectoExpediente extends Model
{
    public const BORRADOR = 'borrador';
    public const RADICADO = 'radicado';

    protected $table = 'proyectos_expedientes';
    protected $fillable = [
        'proyecto_id', 'fase_sgr', 'fecha_configuracion', 'estado',
        'fecha_radicado', 'usuario_radicado_id', 'usuario_creo_id',
    ];
    protected $casts = [
        'proyecto_id' => 'integer',
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
