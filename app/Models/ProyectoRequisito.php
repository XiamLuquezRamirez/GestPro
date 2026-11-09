<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'titulo_propio', 'descripcion_propia', 'categoria_propia',
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

    /**
     * Entre las que cuentan, las que faltan: el complemento de cumplidaQueCuenta, así que el
     * total menos los cumplidos son estas. Un "no aplica" por aprobar o un "por definir" faltan.
     */
    public function scopePendienteQueCuenta(Builder $query): Builder
    {
        return $query->cuenta()->where(fn (Builder $q) => $q->where('estado_aplicabilidad', '!=', self::APLICA)
            ->orWhere('cumplido', false));
    }

    /**
     * Filas con trabajo hecho: cumplidas, con observación, soporte o responsable, o con un
     * "no aplica" marcado. Es lo que conserva una fila retirada y la única definición: la
     * usan los retenidos del expediente, la vista previa de una versión nueva y el desempate
     * de filas con la misma llave al sincronizar.
     */
    public function scopeConTrabajo(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('cumplido', true)
            ->orWhereNotNull('observacion')
            ->orWhereNotNull('soporte_archivo')
            ->orWhereNotIn('estado_aplicabilidad', [self::APLICA, self::POR_DEFINIR])
            ->orWhereNotNull('responsable_id'));
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

    /**
     * Requisito que creó la fila (su ancla): no cambia aunque cambien las plantillas.
     * Null en un requisito propio del proyecto.
     */
    public function requisito(): BelongsTo
    {
        return $this->belongsTo(RequisitoCatalogo::class, 'requisito_catalogo_id');
    }

    /** Requisitos de las plantillas del expediente que aplican con la llave de esta fila. */
    public function origenes(): BelongsToMany
    {
        return $this->belongsToMany(RequisitoCatalogo::class, 'proyecto_requisito_origenes', 'proyecto_requisito_id', 'requisito_catalogo_id');
    }

    /** Requisito agregado en el expediente, con sus textos en la fila y sin plantilla. */
    public function esPropio(): bool
    {
        return $this->requisito_catalogo_id === null;
    }

    /**
     * Si un "no aplica" de esta fila necesita aprobación: la exige si alguno de sus orígenes
     * la exige. Sin orígenes (fila retirada) decide su ancla; un propio nunca la exige.
     * Es la única definición: la usan el "no aplica" y la presentación de la fila.
     */
    public function requiereAprobacionNoAplica(): bool
    {
        if ($this->esPropio()) {
            return false;
        }

        return $this->origenes->isNotEmpty()
            ? $this->origenes->contains(fn (RequisitoCatalogo $r) => $r->requiere_aprobacion_no_aplica)
            : (bool) $this->requisito?->requiere_aprobacion_no_aplica;
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
