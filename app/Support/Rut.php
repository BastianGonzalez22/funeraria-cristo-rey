<?php

namespace App\Support;

/**
 * Utilidades para el RUT chileno: validación por dígito verificador (módulo 11),
 * formato, enmascarado y hash para búsquedas sobre datos cifrados.
 */
class Rut
{
    public static function limpiar(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9kK]/', '', (string) $rut));
    }

    public static function dv(string $cuerpo): string
    {
        $suma = 0;
        $multiplo = 2;
        for ($i = strlen($cuerpo) - 1; $i >= 0; $i--) {
            $suma += ((int) $cuerpo[$i]) * $multiplo;
            $multiplo = $multiplo === 7 ? 2 : $multiplo + 1;
        }
        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11 => '0',
            10 => 'K',
            default => (string) $resto,
        };
    }

    public static function valido(?string $rut): bool
    {
        $r = self::limpiar($rut);
        if (strlen($r) < 2 || strlen($r) > 9) {
            return false;
        }
        $cuerpo = substr($r, 0, -1);
        $dv = substr($r, -1);

        return ctype_digit($cuerpo) && self::dv($cuerpo) === $dv;
    }

    public static function formatear(?string $rut): string
    {
        $r = self::limpiar($rut);
        if (strlen($r) < 2) {
            return (string) $rut;
        }
        $cuerpo = substr($r, 0, -1);

        return number_format((int) $cuerpo, 0, '', '.').'-'.substr($r, -1);
    }

    /** 12.345.678-5 -> 12.345.***-* */
    public static function enmascarar(?string $rut): string
    {
        $f = self::formatear($rut);
        if (strlen($f) < 6) {
            return '***';
        }
        $partes = explode('.', $f);
        if (count($partes) >= 3) {
            return $partes[0].'.'.$partes[1].'.***-*';
        }

        return substr($f, 0, 3).'***-*';
    }

    /** Hash determinístico para buscar por RUT sin descifrar toda la tabla. */
    public static function hash(?string $rut): string
    {
        return hash_hmac('sha256', self::limpiar($rut), (string) config('app.key'));
    }
}
