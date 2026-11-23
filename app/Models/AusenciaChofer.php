<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AusenciaChofer extends Model
{
    protected $table = 'ausencias_chofer';

    public const MOTIVOS = [
        'vacaciones' => 'Vacaciones',
        'permiso' => 'Permiso',
        'licencia' => 'Licencia médica',
        'inasistencia' => 'Avisó que no asiste',
    ];

    protected $fillable = ['chofer_id', 'motivo', 'desde', 'hasta', 'detalle', 'estado'];

    protected function casts(): array
    {
        return ['desde' => 'date', 'hasta' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(fn (AusenciaChofer $a) => $a->estado = $a->calcularEstado());
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class)->withTrashed();
    }

    public function calcularEstado(): string
    {
        $hoy = now()->startOfDay();
        if ($this->hasta && $this->hasta->lt($hoy)) {
            return 'finalizada';
        }
        if ($this->desde && $this->desde->lte($hoy)) {
            return 'vigente';
        }

        return 'programada';
    }

    public static function sincronizarEstados(): int
    {
        $n = 0;
        static::where('estado', '!=', 'finalizada')->get()->each(function (AusenciaChofer $a) use (&$n) {
            $nuevo = $a->calcularEstado();
            if ($nuevo !== $a->estado) {
                $a->save();
                $n++;
            }
        });

        return $n;
    }
}
