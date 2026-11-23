<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una urna física de la bodega, con su propio código y etiqueta.
 * Estados: disponible → reservada (asociada a una familia con abono, bloqueada)
 *          → vendida (compra confirmada / servicio realizado). También: baja.
 */
class UrnaUnidad extends Model
{
    protected $table = 'urna_unidades';

    public const ESTADOS = [
        'disponible' => 'Disponible',
        'reservada' => 'Reservada',
        'vendida' => 'Vendida',
        'baja' => 'De baja',
    ];

    protected $fillable = [
        'urna_id', 'codigo', 'estado', 'solicitud_id', 'proveedor_id', 'ingresada_por', 'costo',
        'ubicacion', 'fecha_ingreso', 'reservada_at', 'vendida_at', 'observacion',
    ];

    protected $attributes = ['estado' => 'disponible', 'ubicacion' => 'Bodega principal', 'costo' => 0];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'reservada_at' => 'datetime',
            'vendida_at' => 'datetime',
            'costo' => 'integer',
        ];
    }

    public function urna(): BelongsTo
    {
        return $this->belongsTo(Urna::class)->withTrashed();
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class)->withTrashed();
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function ingresadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ingresada_por')->withTrashed();
    }

    public function scopeDisponibles($q)
    {
        return $q->where('estado', 'disponible');
    }

    public function scopeEnBodega($q)
    {
        return $q->whereIn('estado', ['disponible', 'reservada']);
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Siguiente código correlativo para un modelo: U-102-0008 */
    public static function siguienteCodigo(Urna $urna): string
    {
        $ultimo = static::where('urna_id', $urna->id)->where('codigo', 'like', $urna->codigo.'-%')->orderByDesc('codigo')->value('codigo');
        $n = $ultimo ? ((int) substr($ultimo, strrpos($ultimo, '-') + 1)) + 1 : 1;

        return sprintf('%s-%04d', $urna->codigo, $n);
    }
}
