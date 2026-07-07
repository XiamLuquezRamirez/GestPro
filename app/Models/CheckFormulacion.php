<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckFormulacion extends Model
{
    public $timestamps = false;

    protected $table = 'check_formulacion';
    protected $fillable = ['proyecto', 'checklist'];
    protected $casts = ['checklist' => 'array'];

    // proyectoRel() not proyecto(): the FK column is also called `proyecto` (see Task 4's note
    // on Estado::faseRel()).
    public function proyectoRel(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto');
    }
}
