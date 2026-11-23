<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        setlocale(LC_TIME, 'es_CL.UTF-8', 'es_ES.UTF-8', 'es');

        Paginator::defaultView('partials.paginacion');
        Paginator::defaultSimpleView('partials.paginacion');

        // RBAC por módulos (capítulo 6 del informe): los permisos se administran por rol.
        Gate::define('finanzas', fn (User $u) => $u->puede('finanzas'));
        Gate::define('gestion', fn (User $u) => $u->puede('urnas'));
        Gate::define('administrar-usuarios', fn (User $u) => $u->puede('roles'));
        Gate::define('inventario', fn (User $u) => $u->puede('stock', 'urnas', 'reposicion'));
        Gate::before(fn (User $u, string $habilidad) => str_starts_with($habilidad, 'modulo.') ? $u->puede(substr($habilidad, 7)) : null);

        View::share('funeraria', config('funeraria'));
    }
}
