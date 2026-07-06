<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Responsable extends Model
{
    public $timestamps = false;

    protected $table = 'responsable';
    protected $fillable = ['nombre', 'email', 'cargo', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'responsable');
    }
}
