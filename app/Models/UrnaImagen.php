<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UrnaImagen extends Model
{
    protected $table = 'urna_imagenes';

    protected $fillable = ['urna_id', 'path', 'titulo', 'orden'];

    public function urna(): BelongsTo
    {
        return $this->belongsTo(Urna::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
