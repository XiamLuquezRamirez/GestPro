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

    protected $table = 'plantillas_checklist';
    protected $fillable = [
        'clave', 'nombre', 'descripcion', 'alcance', 'todas_fuentes', 'todos_sectores',
        'motor', 'activo', 'usuario_creo_id',
    ];
    protected $casts = [
        'todas_fuentes' => 'boolean',
        'todos_sectores' => 'boolean',
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
}
