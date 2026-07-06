<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    public $timestamps = false;

    protected $table = 'sectores';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class, 'sector');
    }
}
