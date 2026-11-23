<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Ajustes simples del sitio (clave/valor): imagen del login, imagen de portada… */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $fillable = ['clave', 'valor'];

    public static function valor(string $clave, $defecto = null)
    {
        try {
            $todos = Cache::rememberForever('ajustes', fn () => Schema::hasTable('ajustes') ? static::pluck('valor', 'clave')->all() : []);
        } catch (\Throwable) {
            return $defecto;
        }

        return $todos[$clave] ?? $defecto;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget('ajustes');
    }

    /** URL pública de una imagen guardada como ajuste, o null. */
    public static function imagen(string $clave): ?string
    {
        $path = static::valor($clave);

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
    }
}
