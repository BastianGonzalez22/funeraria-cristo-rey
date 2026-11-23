<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Condolencia extends Model
{
    protected $table = 'condolencias';

    protected $fillable = ['solicitud_id', 'nombre', 'mensaje', 'visible'];

    protected function casts(): array
    {
        return ['visible' => 'boolean'];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class)->withTrashed();
    }
}
