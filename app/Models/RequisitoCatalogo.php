<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoCatalogo extends Model
{
    public const OBLIGATORIO = 'obligatorio';
    public const OPCIONAL = 'opcional';
    public const CONDICIONADO = 'condicionado';
    public const CARACTERES = [self::OBLIGATORIO, self::OPCIONAL, self::CONDICIONADO];

    // Valores que pide la propuesta; las etiquetas con tilde las pone la pantalla.
    public const CATEGORIAS = ['tecnico', 'juridico', 'financiero', 'ambiental', 'social', 'administrativo', 'otro'];
    public const TIPOS = ['documental', 'validacion', 'condicion'];

    public $timestamps = false;

    protected $table = 'requisitos_catalogo';
    protected $fillable = [
        'catalogo_version_id', 'clave', 'seccion', 'fuente', 'sector_id', 'tipo_id',
        'subtitulo', 'codigo', 'texto', 'nota', 'condicion', 'orden', 'activo',
        'descripcion', 'categoria', 'tipo_requisito', 'caracter', 'cargo_cumplimiento', 'cargo_revision',
        'soporte_requerido', 'referencia_normativa', 'condicion_descripcion',
        'requiere_aprobacion_no_aplica', 'codigo_comun',
    ];
    protected $casts = [
        'catalogo_version_id' => 'integer',
        'sector_id' => 'integer',
        'tipo_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
        'requiere_aprobacion_no_aplica' => 'boolean',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CatalogoVersion::class, 'catalogo_version_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(RequisitoSector::class, 'sector_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(RequisitoTipo::class, 'tipo_id');
    }
}
