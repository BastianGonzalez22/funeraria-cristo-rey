<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoUrna extends Model
{
    protected $table = 'movimientos_urna';

    protected $fillable = ['urna_id', 'proveedor_id', 'usuario_id', 'tipo', 'cantidad', 'costo_total', 'referencia'];

    public function urna(): BelongsTo
    {
        return $this->belongsTo(Urna::class)->withTrashed();
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
