<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionFlota extends Model
{
    protected $table = 'asignaciones_flota';

    protected $fillable = [
        'solicitud_id', 'vehiculo_id', 'chofer_id', 'inicio', 'fin', 'origen', 'destino',
        'hoja_ruta', 'notas', 'apoyo_externo', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fin' => 'datetime',
            'hoja_ruta' => 'array',
            'apoyo_externo' => 'boolean',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(Solicitud::class)->withTrashed();
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class)->withTrashed();
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class)->withTrashed();
    }

    public function scopeActivas($q)
    {
        return $q->whereIn('estado', ['programada', 'en_curso']);
    }

    /** Enlace a Google Maps con toda la ruta (salida → paradas → destino). */
    public function rutaUrl(): string
    {
        $paradas = collect($this->hoja_ruta ?? [])->pluck('direccion')->filter()->all();

        return ruta_url($this->origen, $this->destino, $paradas);
    }

    /** Texto listo para enviar por WhatsApp a los choferes / familia. */
    public function textoWhatsapp(): string
    {
        $s = $this->solicitud;
        $lineas = [
            '*'.config('funeraria.nombre').'* — Hoja de ruta',
            'Servicio: '.$s->cliente->nombre_familia.' ('.$s->nombreServicio().')',
            'Vehículo: '.$this->vehiculo->nombre.' · Chofer: '.$this->chofer->nombre,
            'Salida: '.$this->inicio->format('d/m H:i').' desde '.($this->origen ?: '—'),
        ];
        foreach ($this->hoja_ruta ?? [] as $p) {
            $lineas[] = '• '.($p['hora'] ?? '').' '.($p['lugar'] ?? '').(! empty($p['direccion']) ? ' — '.$p['direccion'].' '.mapa_url($p['direccion']) : '');
        }
        $lineas[] = 'Destino: '.($this->destino ?: '—');
        $lineas[] = 'Ruta completa: '.$this->rutaUrl();

        return implode("\n", $lineas);
    }
}
