<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = ['nombre', 'ciudad', 'modalidad', 'periodicidad', 'telefono', 'estado'];

    public function urnas(): HasMany
    {
        return $this->hasMany(Urna::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoUrna::class);
    }
}
