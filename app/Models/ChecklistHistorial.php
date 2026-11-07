<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistHistorial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'checklist_historial';
    protected $fillable = ['entidad_tipo', 'entidad_id', 'expediente_id', 'evento', 'antes', 'despues', 'usuario_id'];
    protected $casts = [
        'entidad_id' => 'integer',
        'expediente_id' => 'integer',
        'antes' => 'array',
        'despues' => 'array',
        'usuario_id' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
