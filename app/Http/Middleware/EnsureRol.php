<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso: ->middleware('rol:administrador,encargado')
 */
class EnsureRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tieneRol(...$roles)) {
            abort(403, 'Su perfil no tiene acceso a esta sección.');
        }

        return $next($request);
    }
}
