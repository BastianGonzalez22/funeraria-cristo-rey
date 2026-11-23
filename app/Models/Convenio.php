<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Convenio extends Model
{
    use SoftDeletes;

    protected $table = 'convenios';

    protected $attributes = ['estado' => 'vigente', 'descuento_pct' => 0, 'monto_cobertura' => 0];

    protected $fillable = [
        'institucion', 'tipo', 'beneficio', 'descuento_pct', 'monto_cobertura', 'requisitos',
        'documentos', 'contacto', 'logo', 'color', 'vigencia_desde', 'vigencia_hasta', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_desde' => 'date',
            'vigencia_hasta' => 'date',
            'descuento_pct' => 'integer',
            'monto_cobertura' => 'integer',
        ];
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function estaVigente(): bool
    {
        return $this->estado === 'vigente'
            && (! $this->vigencia_hasta || $this->vigencia_hasta->endOfDay()->isFuture());
    }

    public function scopeVigentes($q)
    {
        return $q->where('estado', 'vigente')
            ->where(fn ($w) => $w->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', now()->toDateString()));
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function sigla(): string
    {
        $palabras = preg_split('/\s+/', $this->institucion);
        if (count($palabras) === 1) {
            return mb_strtoupper(mb_substr($this->institucion, 0, 4));
        }

        return mb_strtoupper(collect($palabras)->take(3)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }

    /** Descuento aplicable a un monto total. */
    public function descuentoPara(int $monto): int
    {
        return (int) round($monto * $this->descuento_pct / 100);
    }
}
