<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cementerio o crematorio frecuente, para elegirlo de una lista en la solicitud. */
class LugarDestino extends Model
{
    protected $table = 'lugares_destino';

    public const TIPOS = ['cementerio' => 'Cementerio', 'crematorio' => 'Crematorio'];

    /** Qué tipo de lugar corresponde a cada destino final de la solicitud. */
    public const POR_DESTINO = ['sepultura' => 'cementerio', 'cremacion' => 'crematorio'];

    protected $fillable = ['tipo', 'nombre', 'direccion', 'comuna', 'telefono', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function scopeActivos($q)
    {
        return $q->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }

    /** Texto que se guarda en la solicitud (sirve también para el mapa de la ruta). */
    public function texto(): string
    {
        return $this->nombre.($this->direccion ? ', '.$this->direccion : '').', '.$this->comuna;
    }
}
