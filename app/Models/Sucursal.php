<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sucursal extends Model
{
    use SoftDeletes;

    protected $table = 'sucursales';

    protected $fillable = ['nombre', 'direccion', 'telefono', 'estado'];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    public function scopeActivas($q)
    {
        return $q->where('estado', 'activa');
    }
}
