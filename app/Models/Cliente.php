<?php

namespace App\Models;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $attributes = ['estado' => 'activo', 'medio_contacto' => 'telefono'];

    protected $fillable = [
        'user_id', 'nombre_familia', 'nombre_contratante', 'rut_contratante', 'telefono',
        'email', 'direccion', 'comuna', 'medio_contacto', 'convenio_id', 'urna_preferida_id',
        'documentos', 'notas', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'rut_contratante' => 'encrypted',
            'documentos' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Cliente $c) {
            if ($c->isDirty('rut_contratante')) {
                $c->rut_hash = Rut::hash($c->rut_contratante);
            }
        });
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class);
    }

    public function urnaPreferida(): BelongsTo
    {
        return $this->belongsTo(Urna::class, 'urna_preferida_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class);
    }

    public function contratos(): HasManyThrough
    {
        return $this->hasManyThrough(Contrato::class, Solicitud::class);
    }

    public function rutFormateado(): string
    {
        return Rut::formatear($this->rut_contratante);
    }

    public function rutEnmascarado(): string
    {
        return Rut::enmascarar($this->rut_contratante);
    }

    /** Búsqueda por apellido, contratante, teléfono, RUT o número de contrato. */
    public function scopeBuscar($q, ?string $termino)
    {
        $t = trim((string) $termino);
        if ($t === '') {
            return $q;
        }

        return $q->where(function ($w) use ($t) {
            $w->where('nombre_familia', 'like', "%{$t}%")
                ->orWhere('nombre_contratante', 'like', "%{$t}%")
                ->orWhere('telefono', 'like', "%{$t}%")
                ->orWhereHas('contratos', fn ($c) => $c->where('numero', 'like', "%{$t}%"));
            if (Rut::valido($t)) {
                $w->orWhere('rut_hash', Rut::hash($t));
            }
        });
    }

    /** Filtros separados del buscador de familias: nombre y RUT. */
    public function scopeFiltrarNombreRut($q, ?string $nombre, ?string $rut)
    {
        $n = trim((string) $nombre);
        $r = trim((string) $rut);

        return $q
            ->when($n !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre_familia', 'like', "%{$n}%")->orWhere('nombre_contratante', 'like', "%{$n}%")))
            ->when($r !== '', fn ($w) => Rut::valido($r) ? $w->where('rut_hash', Rut::hash($r)) : $w->whereRaw('1 = 0'));
    }

    /** Resumen financiero de la familia (todas sus solicitudes). */
    public function resumenPagos(): array
    {
        $contratos = $this->contratos()->get();
        $total = (int) $contratos->sum('monto_total');
        $saldo = (int) $contratos->sum('saldo_pendiente');
        $atrasado = $contratos->contains(fn (Contrato $c) => $c->estaAtrasado());

        return [
            'total' => $total,
            'abonado' => $total - $saldo,
            'saldo' => $saldo,
            'estado' => $contratos->isEmpty() ? 'sin_contrato' : ($saldo === 0 ? 'pagado' : ($atrasado ? 'atrasado' : 'pendiente')),
        ];
    }
}
