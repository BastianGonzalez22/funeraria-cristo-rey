<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $attributes = ['estado' => 'activo', 'intentos_fallidos' => 0];

    protected $fillable = [
        'rol_id', 'nombre', 'email', 'telefono', 'password', 'estado',
        'intentos_fallidos', 'ultimo_acceso_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ultimo_acceso_at' => 'datetime',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class, 'user_id');
    }

    public function chofer(): HasOne
    {
        return $this->hasOne(Chofer::class, 'user_id');
    }

    public function nombreRol(): string
    {
        return $this->rol?->nombre ?? '';
    }

    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->nombreRol(), $roles, true);
    }

    /** ¿El rol del usuario incluye este módulo? (el administrador puede todo) */
    public function puede(string ...$modulos): bool
    {
        $rol = $this->rol;
        if (! $rol || $rol->estado !== 'activo') {
            return false;
        }
        foreach ($modulos as $m) {
            if ($rol->tienePermiso($m)) {
                return true;
            }
        }

        return false;
    }

    /** Ruta de inicio según los permisos del rol. */
    public function rutaInicio(): ?string
    {
        foreach ([
            'dashboard' => 'dashboard', 'portal' => 'portal.index', 'itinerarios' => 'itinerario.index',
            'stock' => 'urnas.index', 'solicitudes' => 'solicitudes.index', 'usuarios' => 'usuarios.index',
            'clientes' => 'clientes.create', 'calendario' => 'calendario.index', 'finanzas' => 'abonos.index',
        ] as $modulo => $ruta) {
            if ($this->puede($modulo)) {
                return $ruta;
            }
        }

        return null;
    }

    public function esAdmin(): bool
    {
        return $this->tieneRol('administrador');
    }

    public function esPersonal(): bool
    {
        return $this->tieneRol('administrador', 'encargado', 'chofer');
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim($this->nombre));

        return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }

    public function nivelAcceso(): string
    {
        $etiquetas = $this->rol?->etiquetasPermisos() ?? [];

        return $etiquetas ? implode(', ', array_slice($etiquetas, 0, 4)).(count($etiquetas) > 4 ? '…' : '') : 'Sin permisos';
    }
}
