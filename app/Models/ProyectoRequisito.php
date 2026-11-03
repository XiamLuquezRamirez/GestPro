<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProyectoRequisito extends Model
{
    public $timestamps = false;

    protected $table = 'proyectos_requisitos';
    protected $fillable = [
        'expediente_id', 'requisito_catalogo_id', 'aplica', 'cumplido', 'fecha_cumplido',
        'usuario_cumplido_id', 'observacion', 'soporte_archivo', 'soporte_nombre', 'orden',
    ];
    protected $casts = [
        'expediente_id' => 'integer',
        'requisito_catalogo_id' => 'integer',
        'aplica' => 'boolean',
        'cumplido' => 'boolean',
        'fecha_cumplido' => 'datetime',
        'orden' => 'integer',
    ];

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
}
