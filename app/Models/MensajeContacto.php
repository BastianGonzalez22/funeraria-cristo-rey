<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeContacto extends Model
{
    protected $table = 'mensajes_contacto';

    protected $fillable = ['nombre', 'telefono', 'email', 'medio_contacto', 'urna_id', 'mensaje', 'atendido'];

    protected function casts(): array
    {
        return ['atendido' => 'boolean'];
    }

    public function urna(): BelongsTo
    {
        return $this->belongsTo(Urna::class)->withTrashed();
    }
}
