<?php

namespace App\Models;

use App\Support\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Solicitud extends Model
{
    use SoftDeletes;

    protected $table = 'solicitudes';

    public const ESTADOS = [
        'en_revision' => 'En revisión',
        'con_observaciones' => 'Con observaciones',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'finalizada' => 'Finalizada',
    ];

    public const ACTIVAS = ['en_revision', 'con_observaciones', 'aprobada'];

    protected $fillable = [
        'cliente_id', 'urna_id', 'urna_unidad_id', 'sucursal_id', 'usuario_solicitante_id', 'convenio_id',
        'tipo_servicio', 'fecha_hora', 'destino_final', 'nombre_fallecido', 'rut_fallecido',
        'fecha_defuncion', 'certificado_path', 'lugar_retiro', 'lugar_velatorio', 'misa_lugar',
        'misa_hora', 'cementerio_crematorio', 'sepultura_hora', 'cementerio_contacto',
        'cementerio_confirmado', 'comuna_destino', 'fuera_comuna', 'caso_especial',
        'requiere_apoyo_externo', 'permiso_traslado', 'permiso_sanitario', 'medio_contacto',
        'mensaje', 'documentos', 'elementos', 'personal', 'observacion', 'token_publico',
        'publicar_servicio', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'misa_hora' => 'datetime',
            'sepultura_hora' => 'datetime',
            'fecha_defuncion' => 'date',
            'rut_fallecido' => 'encrypted',
            'documentos' => 'array',
            'elementos' => 'array',
            'personal' => 'array',
            'cementerio_confirmado' => 'boolean',
            'fuera_comuna' => 'boolean',
            'caso_especial' => 'boolean',
            'requiere_apoyo_externo' => 'boolean',
            'publicar_servicio' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function urna(): BelongsTo
    {
        return $this->belongsTo(Urna::class)->withTrashed();
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UrnaUnidad::class, 'urna_unidad_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class)->withTrashed();
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class)->withTrashed();
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicitante_id')->withTrashed();
    }

    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class);
    }

    public function asignacion(): HasOne
    {
        return $this->hasOne(AsignacionFlota::class);
    }

    public function condolencias(): HasMany
    {
        return $this->hasMany(Condolencia::class)->latest();
    }

    public function modificaciones()
    {
        return Auditoria::where('auditable_type', self::class)->where('auditable_id', $this->id)->latest('created_at');
    }

    public function codigo(): string
    {
        return 'SOL-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function nombreServicio(): string
    {
        return etiqueta('tipos_servicio', $this->tipo_servicio);
    }

    public function precioServicio(): int
    {
        return (int) config("funeraria.tipos_servicio.{$this->tipo_servicio}.precio", 0);
    }

    /** Valor estimado antes de existir contrato (servicio + urna - convenio). */
    public function valorEstimado(): int
    {
        $total = $this->precioServicio() + (int) ($this->urna?->precio ?? 0);
        $desc = $this->convenio ? $this->convenio->descuentoPara($total) : 0;

        return max(0, $total - $desc);
    }

    public function rutFallecidoFormateado(): string
    {
        return $this->rut_fallecido ? Rut::formatear($this->rut_fallecido) : '—';
    }

    public function esEditablePorSolicitante(): bool
    {
        return in_array($this->estado, ['en_revision', 'con_observaciones'], true);
    }

    public function elementoEntregado(string $clave): bool
    {
        return (bool) data_get($this->elementos, $clave, false);
    }

    public function documentoRecibido(string $clave): bool
    {
        return (bool) data_get($this->documentos, $clave, false);
    }

    public function permisosPendientes(): bool
    {
        $pend = fn ($v) => in_array($v, ['pendiente', 'en_tramite'], true);

        return $pend($this->permiso_traslado) || $pend($this->permiso_sanitario);
    }

    /** Filtro por familia: nombre de familia, contratante, fallecido o RUT del contratante. */
    public function scopeBuscarFamilia($q, ?string $termino)
    {
        $t = trim((string) $termino);
        if ($t === '') {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('nombre_fallecido', 'like', "%{$t}%")
            ->orWhereHas('cliente', fn ($c) => $c->buscar($t)));
    }

    public function scopeDelMes($q, ?string $mes)
    {
        if (! $mes || ! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return $q;
        }
        $inicio = \Carbon\Carbon::parse($mes.'-01')->startOfMonth();

        return $q->whereBetween('fecha_hora', [$inicio, $inicio->copy()->endOfMonth()]);
    }

    public function scopeActivas($q)
    {
        return $q->whereIn('estado', self::ACTIVAS);
    }

    public function scopeVisiblesEnCalendario($q)
    {
        return $q->whereIn('estado', ['en_revision', 'con_observaciones', 'aprobada', 'finalizada']);
    }
}
