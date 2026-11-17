<?php

namespace App\Models;

use App\Exceptions\ReglaNegocioException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaChecklist extends Model
{
    public const CLAVE_SGR = 'sgr';
    public const MOTOR_SGR = 'sgr';
    public const MOTOR_SIMPLE = 'simple';

    // El uso se elige al crear la plantilla y no cambia: una de contratación nunca se ofrece a un
    // expediente de viabilización.
    public const USO_VIABILIZACION = 'viabilizacion';
    public const USO_CONTRATACION = 'contratacion';
    public const USOS = [self::USO_VIABILIZACION, self::USO_CONTRATACION];

    protected $table = 'plantillas_checklist';
    protected $fillable = [
        'clave', 'nombre', 'descripcion', 'alcance', 'todas_fuentes', 'todos_sectores',
        'motor', 'activo', 'usuario_creo_id', 'uso', 'todas_modalidades',
    ];
    protected $attributes = [
        'uso' => self::USO_VIABILIZACION,
        'todas_modalidades' => false,
    ];
    protected $casts = [
        'todas_fuentes' => 'boolean',
        'todos_sectores' => 'boolean',
        'todas_modalidades' => 'boolean',
        'activo' => 'boolean',
    ];

    /** La plantilla del checklist de regalías. La crea una migración; sin ella, el módulo no funciona. */
    public static function sgr(): self
    {
        return self::where('clave', self::CLAVE_SGR)->first()
            ?? throw new ReglaNegocioException('Falta la plantilla SGR: ejecute las migraciones.');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(CatalogoVersion::class, 'plantilla_id');
    }

    public function versionVigente(): ?CatalogoVersion
    {
        return $this->versiones()->vigente()->first();
    }

    public function fuentes(): BelongsToMany
    {
        return $this->belongsToMany(FuenteFinanciacion::class, 'plantilla_fuentes', 'plantilla_id', 'fuente_id');
    }

    public function sectores(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'plantilla_sectores', 'plantilla_id', 'sector_id');
    }

    /** Modalidades de contratación a las que aplica; solo las tiene una plantilla de contratación. */
    public function modalidades(): BelongsToMany
    {
        return $this->belongsToMany(Modalidad::class, 'plantilla_modalidades', 'plantilla_id', 'modalidad_id');
    }

    public function esContratacion(): bool
    {
        return $this->uso === self::USO_CONTRATACION;
    }
}
