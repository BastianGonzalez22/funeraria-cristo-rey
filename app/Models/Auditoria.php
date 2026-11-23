<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inalterable (solo inserción). Cualquier intento de modificar o
 * eliminar un evento lanza una excepción (Historia 10).
 */
class Auditoria extends Model
{
    protected $table = 'auditorias';

    public const UPDATED_AT = null;

    protected $fillable = ['evento', 'usuario_id', 'auditable_type', 'auditable_id', 'descripcion', 'detalle', 'ip'];

    protected function casts(): array
    {
        return ['detalle' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La auditoría es de solo inserción.'));
        static::deleting(fn () => throw new \LogicException('La auditoría es de solo inserción.'));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }
}
