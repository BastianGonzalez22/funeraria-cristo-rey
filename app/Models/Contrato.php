<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contratos y abonos no usan borrado lógico: toda corrección es una
 * operación autorizada y auditada (sección 5.4 del informe).
 */
class Contrato extends Model
{
    protected $table = 'contratos';

    protected $fillable = [
        'numero', 'solicitud_id', 'convenio_id', 'monto_servicio', 'monto_urna', 'descuento',
        'monto_total', 'abono_minimo', 'saldo_pendiente', 'cuotas', 'fecha_vencimiento',
        'cuota_mortuoria_en_tramite', 'clausulas_adicionales', 'estado', 'liquidado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'liquidado_at' => 'datetime',
            'cuota_mortuoria_en_tramite' => 'boolean',
            'monto_servicio' => 'integer',
            'monto_urna' => 'integer',
            'descuento' => 'integer',
            'monto_total' => 'integer',
            'abono_minimo' => 'integer',
            'saldo_pendiente' => 'integer',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class)->withTrashed();
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class)->withTrashed();
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(Abono::class)->orderBy('fecha')->orderBy('id');
    }

    public function abonosValidos(): HasMany
    {
        return $this->hasMany(Abono::class)->where('estado', 'registrado');
    }

    public function totalAbonado(): int
    {
        return $this->monto_total - $this->saldo_pendiente;
    }

    public function cumpleAbonoMinimo(): bool
    {
        return $this->totalAbonado() >= $this->abono_minimo;
    }

    public function faltaParaMinimo(): int
    {
        return max(0, $this->abono_minimo - $this->totalAbonado());
    }

    public function estaAtrasado(): bool
    {
        return $this->estado === 'vigente'
            && $this->saldo_pendiente > 0
            && $this->fecha_vencimiento
            && $this->fecha_vencimiento->endOfDay()->isPast();
    }

    public function valorCuota(): int
    {
        return (int) ceil($this->saldo_pendiente / max(1, $this->cuotas));
    }

    public function estadoPago(): string
    {
        if ($this->saldo_pendiente === 0) {
            return 'pagado';
        }

        return $this->estaAtrasado() ? 'atrasado' : 'pendiente';
    }

    public static function siguienteNumero(): string
    {
        $anio = now()->year;
        $ultimo = static::where('numero', 'like', "CT-{$anio}-%")->orderByDesc('numero')->value('numero');
        $n = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('CT-%d-%04d', $anio, $n);
    }

    public function scopeConSaldo($q)
    {
        return $q->where('estado', 'vigente')->where('saldo_pendiente', '>', 0);
    }

    public function scopeAtrasados($q)
    {
        return $q->conSaldo()->whereDate('fecha_vencimiento', '<', now()->toDateString());
    }
}
