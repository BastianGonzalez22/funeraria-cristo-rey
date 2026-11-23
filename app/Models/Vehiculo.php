<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehiculo extends Model
{
    use SoftDeletes;

    protected $table = 'vehiculos';

    protected $attributes = ['estado' => 'disponible'];

    public const TIPOS = [
        'carroza' => 'Carroza',
        'instalacion' => 'Vehículo de instalación',
        'externo' => 'Apoyo externo (otra funeraria)',
    ];

    protected $fillable = ['nombre', 'patente', 'tipo', 'observacion', 'estado'];

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionFlota::class);
    }

    public function scopeAsignables($q)
    {
        return $q->where('estado', '!=', 'en_mantencion');
    }
}
