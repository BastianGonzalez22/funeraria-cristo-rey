<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['nombre', 'descripcion', 'permisos', 'sistema', 'estado'];

    protected $attributes = ['estado' => 'activo', 'sistema' => false];

    protected function casts(): array
    {
        return ['permisos' => 'array', 'sistema' => 'boolean'];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'rol_id');
    }

    public static function idDe(string $nombre): int
    {
        return (int) static::where('nombre', $nombre)->value('id');
    }

    public function tienePermiso(string $modulo): bool
    {
        if ($this->nombre === 'administrador') {
            return $modulo !== 'portal'; // el portal es solo para las familias
        }

        return in_array($modulo, $this->permisos ?? [], true);
    }

    /** Roles que el sistema necesita para funcionar y no se pueden eliminar. */
    public function esProtegido(): bool
    {
        return in_array($this->nombre, ['administrador', 'solicitante'], true);
    }

    public function etiquetasPermisos(): array
    {
        if ($this->nombre === 'administrador') {
            return ['Acceso total'];
        }

        return collect($this->permisos ?? [])->map(fn ($m) => config("funeraria.modulos.{$m}.0", $m))->all();
    }
}
