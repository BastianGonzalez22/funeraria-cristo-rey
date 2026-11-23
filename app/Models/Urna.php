<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Urna extends Model
{
    use SoftDeletes;

    protected $table = 'urnas';

    protected $attributes = ['stock_actual' => 0, 'stock_reservado' => 0, 'stock_minimo' => 5, 'bajo_pedido' => false, 'publica' => true];

    protected $fillable = [
        'codigo', 'modelo', 'categoria', 'material', 'color', 'acabado', 'medidas', 'largo_cm', 'ancho_cm', 'alto_cm', 'manillas',
        'descripcion', 'precio', 'stock_actual', 'stock_reservado', 'stock_minimo', 'bajo_pedido',
        'publica', 'imagen', 'proveedor_id', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'bajo_pedido' => 'boolean',
            'publica' => 'boolean',
            'precio' => 'integer',
            'stock_actual' => 'integer',
            'stock_reservado' => 'integer',
            'stock_minimo' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Urna $u) => $u->estado = $u->calcularEstado());
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoUrna::class)->latest();
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(UrnaUnidad::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(UrnaImagen::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * Recalcula los contadores del modelo desde sus unidades físicas.
     * stock_actual = unidades en bodega (disponibles + reservadas); stock_reservado = reservadas.
     */
    public function sincronizarStock(): void
    {
        $this->stock_actual = $this->unidades()->enBodega()->count();
        $this->stock_reservado = $this->unidades()->where('estado', 'reservada')->count();
        $this->save();
    }

    /** Medidas legibles: 198 x 60 x 45 cm (o el texto libre anterior). */
    public function medidasTexto(): ?string
    {
        if ($this->largo_cm && $this->ancho_cm && $this->alto_cm) {
            return "{$this->largo_cm} x {$this->ancho_cm} x {$this->alto_cm} cm";
        }

        return $this->medidas ?: null;
    }

    /** Todas las URLs de fotos (galería + imagen principal). */
    public function fotos(): array
    {
        $fotos = $this->relationLoaded('imagenes') ? $this->imagenes : $this->imagenes()->get();
        $urls = $fotos->map->url()->all();
        if ($this->imagen && ! $fotos->contains('path', $this->imagen)) {
            array_unshift($urls, Storage::disk('public')->url($this->imagen));
        }

        return $urls;
    }

    public function esPersonalizada(): bool
    {
        return $this->categoria === 'personalizada' || $this->bajo_pedido;
    }

    public function disponible(): int
    {
        return max(0, $this->stock_actual - $this->stock_reservado);
    }

    public function calcularEstado(): string
    {
        if ($this->bajo_pedido) {
            return 'bajo_pedido';
        }
        $d = $this->stock_actual - $this->stock_reservado;
        if ($d < $this->stock_minimo) {
            return 'reponer';
        }
        if ($d === $this->stock_minimo) {
            return 'en_umbral';
        }

        return 'normal';
    }

    public function puedeAsociarse(): bool
    {
        return $this->bajo_pedido || $this->disponible() > 0;
    }

    /** Porcentaje para la barra de progreso respecto del mínimo (100% = 2x mínimo). */
    public function porcentajeStock(): int
    {
        $base = max(1, $this->stock_minimo * 2);

        return (int) min(100, round($this->disponible() / $base * 100));
    }

    public function imagenUrl(): ?string
    {
        if ($this->imagen) {
            return Storage::disk('public')->url($this->imagen);
        }
        $primera = $this->relationLoaded('imagenes') ? $this->imagenes->first() : $this->imagenes()->first();

        return $primera?->url();
    }

    /** Color de muestra cuando la urna aún no tiene fotografía. */
    public function colorMuestra(): string
    {
        $c = mb_strtolower((string) $this->color.' '.$this->material);

        return match (true) {
            str_contains($c, 'blanc') => '#ece8e1',
            str_contains($c, 'gris'), str_contains($c, 'metal') => '#9aa0a6',
            str_contains($c, 'ros') => '#e7b9c2',
            str_contains($c, 'verde'), str_contains($c, 'carabin') => '#3f6b4b',
            str_contains($c, 'roj') => '#9e3b32',
            str_contains($c, 'negr') => '#3a3330',
            str_contains($c, 'roble'), str_contains($c, 'miel') => '#b0814f',
            str_contains($c, 'caf'), str_contains($c, 'nogal') => '#6e4a33',
            default => '#c9b79c',
        };
    }

    /** Modelos que se pueden ofrecer: con unidades disponibles o fabricados bajo pedido. */
    public function scopeOfrecibles($q)
    {
        return $q->where(fn ($w) => $w->where('bajo_pedido', true)->orWhereRaw('stock_actual > stock_reservado'));
    }

    public function scopeFiltrar($q, array $f)
    {
        return $q
            ->when($f['q'] ?? null, fn ($w, $t) => $w->where(fn ($x) => $x->where('modelo', 'like', "%{$t}%")->orWhere('codigo', 'like', "%{$t}%")))
            ->when($f['categoria'] ?? null, fn ($w, $v) => $w->where('categoria', $v))
            ->when($f['color'] ?? null, fn ($w, $v) => $w->where('color', 'like', "%{$v}%"))
            ->when($f['acabado'] ?? null, fn ($w, $v) => $w->where('acabado', $v))
            ->when($f['material'] ?? null, fn ($w, $v) => $w->where('material', 'like', "%{$v}%"));
    }
}
