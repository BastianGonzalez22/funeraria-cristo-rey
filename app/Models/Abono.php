<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abono extends Model
{
    protected $table = 'abonos';

    protected $fillable = [
        'contrato_id', 'usuario_id', 'numero_comprobante', 'monto', 'medio_pago', 'fecha',
        'referencia', 'adjunto_path', 'estado', 'motivo_anulacion', 'confirmado_por', 'confirmado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'integer',
            'confirmado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Los abonos nunca se eliminan: se anulan (queda en auditoría).
        static::deleting(fn () => throw new \LogicException('Los abonos no se eliminan; deben anularse.'));
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por')->withTrashed();
    }

    public function esDeclaradoPorFamilia(): bool
    {
        return (bool) $this->usuario?->puede('portal') && ! $this->usuario?->puede('finanzas');
    }

    public function nombreMedio(): string
    {
        return etiqueta('medios_pago', $this->medio_pago);
    }
}
