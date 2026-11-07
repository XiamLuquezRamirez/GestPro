<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoRequisito extends Model
{
    // Lo decide una persona. Es independiente de `aplica`, que decide el sistema al configurar.
    public const APLICA = 'aplica';
    public const POR_DEFINIR = 'por_definir';
    public const NO_APLICA_PENDIENTE = 'no_aplica_pendiente';
    public const NO_APLICA = 'no_aplica';

    public $timestamps = false;

    protected $table = 'proyectos_requisitos';
    protected $fillable = [
        'expediente_id', 'requisito_catalogo_id', 'aplica', 'cumplido', 'fecha_cumplido',
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
        'estado_aplicabilidad', 'justificacion_no_aplica', 'usuario_no_aplica_id', 'usuario_aprobo_id',
        'fecha_aprobacion', 'caracter', 'responsable_id',
    ];
    protected $attributes = [
        'estado_aplicabilidad' => self::APLICA,
        'caracter' => RequisitoCatalogo::OBLIGATORIO,
    ];
    protected $casts = [
        'expediente_id' => 'integer',
        'requisito_catalogo_id' => 'integer',
        'aplica' => 'boolean',
        'cumplido' => 'boolean',
        'fecha_cumplido' => 'datetime',
        'orden' => 'integer',
        'usuario_no_aplica_id' => 'integer',
        'usuario_aprobo_id' => 'integer',
        'fecha_aprobacion' => 'datetime',
        'responsable_id' => 'integer',
    ];

    /**
     * Filas que cuentan en el avance y deciden la radicación. Es la única definición:
     * la usan el detalle, el resumen del tablero y la radicación.
     */
    public function scopeCuenta(Builder $query): Builder
    {
        return $query->where('aplica', true)
            ->where('caracter', '!=', RequisitoCatalogo::OPCIONAL)
            ->where('estado_aplicabilidad', '!=', self::NO_APLICA);
    }

    /** Entre las que cuentan, las cumplidas. Un "no aplica" por aprobar no está cumplido. */
    public function scopeCumplidaQueCuenta(Builder $query): Builder
    {
        return $query->cuenta()->where('estado_aplicabilidad', self::APLICA)->where('cumplido', true);
    }

    public function cuenta(): bool
    {
        return $this->aplica
            && $this->caracter !== RequisitoCatalogo::OPCIONAL
            && $this->estado_aplicabilidad !== self::NO_APLICA;
    }

    /** Estado que ve el usuario: por_definir, pendiente, cumplido, no_aplica_pendiente o no_aplica. */
    public function estadoVisible(): string
    {
        if ($this->estado_aplicabilidad !== self::APLICA) {
            return $this->estado_aplicabilidad;
        }

        return $this->cumplido ? 'cumplido' : 'pendiente';
    }

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(ProyectoExpediente::class, 'expediente_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoCatalogo::class, 'requisito_catalogo_id');
    }

    public function usuarioCumplido(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cumplido_id');
    }

    public function usuarioNoAplica(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_no_aplica_id');
    }

    public function usuarioAprobo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_aprobo_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }
}
