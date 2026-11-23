<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opinion extends Model
{
    protected $table = 'opiniones';

    protected $fillable = ['nombre', 'calificacion', 'comentario', 'publicada'];

    protected function casts(): array
    {
        return ['publicada' => 'boolean', 'calificacion' => 'integer'];
    }

    public function estrellas(): string
    {
        return str_repeat('★', $this->calificacion).str_repeat('☆', 5 - $this->calificacion);
    }
}
