<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de acceso por módulo según los permisos del rol.
 * Uso: ->middleware('permiso:solicitudes')  o  'permiso:stock,urnas' (cualquiera de ellos).
 */
class EnsurePermiso
{
    public function handle(Request $request, Closure $next, string ...$modulos): Response
    {
        $user = $request->user();

        if (! $user || ! $user->puede(...$modulos)) {
            abort(403, 'Su perfil no tiene acceso a esta sección.');
        }

        return $next($request);
    }
}
