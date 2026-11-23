<?php

use Carbon\Carbon;

if (! function_exists('clp')) {
    /** Formatea un monto en pesos chilenos: $1.250.000 */
    function clp($monto): string
    {
        return '$'.number_format((float) $monto, 0, ',', '.');
    }
}

if (! function_exists('fecha')) {
    /** Fecha en español: "20 oct 2026" */
    function fecha($valor, string $formato = 'd M Y'): string
    {
        if (! $valor) {
            return '—';
        }

        return Carbon::parse($valor)->translatedFormat($formato);
    }
}

if (! function_exists('fecha_hora')) {
    function fecha_hora($valor): string
    {
        return fecha($valor, 'd M Y, H:i');
    }
}

if (! function_exists('etiqueta')) {
    /** Devuelve la etiqueta legible de un valor configurado en config/funeraria.php */
    function etiqueta(string $grupo, ?string $clave): string
    {
        if ($clave === null || $clave === '') {
            return '—';
        }
        $valor = config("funeraria.{$grupo}.{$clave}");
        if (is_array($valor)) {
            return $valor['nombre'] ?? $clave;
        }

        return $valor ?? ucfirst(str_replace('_', ' ', $clave));
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(?string $telefono, string $texto = ''): string
    {
        // Cadena vacía = compartir a cualquier contacto; null = número de la funeraria.
        if ($telefono === '') {
            return 'https://wa.me/?text='.rawurlencode($texto);
        }
        $numero = preg_replace('/\D/', '', $telefono ?: config('funeraria.whatsapp'));

        return 'https://wa.me/'.$numero.($texto ? '?text='.rawurlencode($texto) : '');
    }
}

if (! function_exists('mapa_url')) {
    function mapa_url(?string $direccion): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $direccion);
    }
}

if (! function_exists('ruta_url')) {
    /** Enlace a Google Maps con la ruta completa: salida → paradas → destino. */
    function ruta_url(?string $origen, ?string $destino, array $paradas = []): string
    {
        $paradas = array_values(array_filter($paradas));
        $params = ['api' => 1, 'travelmode' => 'driving'];
        if ($origen) {
            $params['origin'] = $origen;
        }
        $params['destination'] = $destino ?: (end($paradas) ?: $origen);
        if ($paradas) {
            $params['waypoints'] = implode('|', $paradas);
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($params);
    }
}

if (! function_exists('mapa_embed')) {
    /** Mapa incrustable sin clave de API. */
    function mapa_embed(?string $direccion): string
    {
        return 'https://maps.google.com/maps?q='.rawurlencode((string) $direccion).'&z=15&output=embed';
    }
}
