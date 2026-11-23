<?php

namespace App\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Ejecuta una operación de negocio y convierte las reglas incumplidas
     * (DomainException) en un mensaje de error en la misma pantalla.
     */
    protected function intentar(callable $operacion, string $exito, string|\Closure|null $redireccion = null): RedirectResponse
    {
        try {
            $resultado = $operacion();
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $destino = $redireccion instanceof \Closure ? $redireccion($resultado) : $redireccion;

        return ($destino ? redirect($destino) : back())->with('ok', $exito);
    }

    /**
     * Descarga CSV con BOM y separador ";" para que Excel lo abra con tildes.
     * @param  array  $encabezados
     * @param  iterable  $filas  arreglos de valores
     */
    protected function csv(string $nombre, array $encabezados, iterable $filas): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $encabezados, ';');
            foreach ($filas as $fila) {
                fputcsv($out, $fila, ';');
            }
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Mes "AAAA-MM" válido desde la URL, o null. */
    protected function mesValido(?string $mes): ?string
    {
        return $mes && preg_match('/^\d{4}-\d{2}$/', $mes) ? $mes : null;
    }
}
