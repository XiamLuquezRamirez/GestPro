<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoProyecto extends Model
{
    public $timestamps = false;

    protected $table = 'presupuesto_proyecto';
    protected $fillable = ['proyecto', 'componente', 'valor'];
    protected $casts = ['valor' => 'decimal:2'];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
    // on Estado::faseRel() for why this project suffixes colliding relation names with `Rel`).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
