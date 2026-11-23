<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Registro de auditoría (sección 5.5/5.6 del informe).
 * La escritura es independiente de la operación de negocio: si falla, se
 * registra en el log y la operación en la base relacional NO se revierte.
 * Para usar MongoDB basta con reemplazar la persistencia de este servicio
 * por el driver mongodb/laravel-mongodb manteniendo la misma firma.
 */
class AuditService
{
    public static function registrar(string $evento, string $descripcion, ?Model $modelo = null, array $detalle = []): void
    {
        try {
            Auditoria::create([
                'evento' => $evento,
                'usuario_id' => Auth::id(),
                'auditable_type' => $modelo ? get_class($modelo) : null,
                'auditable_id' => $modelo?->getKey(),
                'descripcion' => mb_substr($descripcion, 0, 255),
                'detalle' => $detalle ?: null,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar auditoría: '.$e->getMessage(), compact('evento', 'descripcion'));
        }
    }

    /** Diferencias legibles entre los valores originales y los nuevos de un modelo. */
    public static function cambios(Model $modelo, array $ocultos = []): array
    {
        $cambios = [];
        foreach ($modelo->getDirty() as $campo => $nuevo) {
            if (in_array($campo, ['updated_at', ...$ocultos], true)) {
                continue;
            }
            $anterior = $modelo->getOriginal($campo);
            $cambios[$campo] = [
                'antes' => self::texto($anterior),
                'despues' => self::texto($modelo->{$campo}),
            ];
        }

        return $cambios;
    }

    private static function texto($v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d/m/Y H:i');
        }
        if (is_bool($v)) {
            return $v ? 'sí' : 'no';
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }

        return (string) $v;
    }
}
