<?php

namespace App\Models;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificacionBeneficio extends Model
{
    protected $table = 'verificaciones_beneficio';

    public const INSTITUCIONES = [
        'afp' => 'AFP',
        'ips' => 'IPS',
        'dipreca' => 'DIPRECA',
        'capredena' => 'CAPREDENA',
    ];

    public const ESTADOS = [
        'pendiente' => 'Pendiente (fuera de horario)',
        'por_verificar' => 'Por verificar',
        'con_beneficio' => 'Con beneficio',
        'sin_beneficio' => 'Sin beneficio',
    ];

    protected $fillable = [
        'usuario_id', 'solicitud_id', 'rut_fallecido', 'institucion', 'estado',
        'monto_beneficio', 'nota', 'consultar_desde',
    ];

    protected function casts(): array
    {
        return [
            'rut_fallecido' => 'encrypted',
            'consultar_desde' => 'datetime',
            'monto_beneficio' => 'integer',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class)->withTrashed();
    }

    public function rutFormateado(): string
    {
        return Rut::formatear($this->rut_fallecido);
    }
}
