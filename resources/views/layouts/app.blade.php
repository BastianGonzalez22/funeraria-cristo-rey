<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ config('funeraria.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
@php
    $u = auth()->user();
    $rol = $u->nombreRol();
    $nav = function (string $ruta, string $texto, string $ico, $cuenta = null, ?string $patron = null) {
        $activo = request()->routeIs(...explode('|', $patron ?? $ruta)) ? 'activo' : '';
        $badge = $cuenta ? '<span class="cuenta">'.$cuenta.'</span>' : '';
        return '<a href="'.route($ruta).'" class="'.$activo.'"><span class="ico">'.$ico.'</span>'.e($texto).$badge.'</a>';
    };
    $enRevision = $u->puede('solicitudes') ? \App\Models\Solicitud::whereIn('estado', ['en_revision', 'con_observaciones'])->count() : 0;
    $abonosPendientes = $u->puede('finanzas') ? \App\Models\Abono::where('estado', 'pendiente')->count() : 0;
    $stockBajo = $u->puede('reposicion') ? \App\Models\Urna::where('estado', 'reponer')->count() : 0;
@endphp
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="nombre">{{ config('funeraria.nombre') }}</div>
            <div class="sub">{{ $u->puede('portal') ? 'Portal de familias' : 'Sistema de gestión integral' }}</div>
        </div>
        <nav>
            @if ($u->puede('portal'))
                <div class="seccion">Mis trámites</div>
                {!! $nav('portal.create', 'Nueva solicitud', '+') !!}
                {!! $nav('portal.index', 'Mis solicitudes', '▤', null, 'portal.index|portal.show|portal.edit') !!}
                {!! $nav('portal.pagos', 'Mis pagos', '●') !!}
            @endif

            @if ($u->puede('dashboard', 'solicitudes', 'clientes', 'stock', 'urnas', 'reposicion', 'flota', 'calendario', 'finanzas', 'itinerarios'))
                <div class="seccion">Principal</div>
                @if ($u->puede('dashboard')) {!! $nav('dashboard', 'Dashboard', '■') !!} @endif
                @if ($u->puede('solicitudes')) {!! $nav('solicitudes.index', 'Solicitudes', '▤', $enRevision, 'solicitudes.*') !!} @endif
                @if ($u->puede('clientes')) {!! $nav('clientes.create', 'Registro de clientes', '✎', null, 'clientes.*') !!} @endif
                @if ($u->puede('stock', 'urnas', 'reposicion')) {!! $nav('urnas.index', $u->puede('urnas') ? 'Stock de Urnas' : 'Bodega de urnas', '◆', null, 'urnas.*|unidades.*') !!} @endif
                @if ($u->puede('reposicion')) {!! $nav('reposicion.index', 'Reposición', '⇪', $stockBajo ?: null) !!} @endif
                @if ($u->puede('flota')) {!! $nav('flota.index', 'Flota y Cortejos', '▶', null, 'flota.*') !!} @endif
                @if ($u->puede('itinerarios')) {!! $nav('itinerario.index', $u->puede('flota') ? 'Rutas de choferes' : 'Mis rutas', '➜', null, 'itinerario.*') !!} @endif
                @if ($u->puede('calendario')) {!! $nav('calendario.index', 'Calendario', '▦') !!} @endif
                @if ($u->puede('finanzas')) {!! $nav('abonos.index', 'Abonos y Pagos', '●', $abonosPendientes ?: null, 'abonos.*|contratos.*') !!} @endif
            @endif

            @if ($u->puede('usuarios', 'roles', 'convenios', 'personal', 'web', 'reportes', 'auditoria', 'configuracion'))
                <div class="seccion">Administración</div>
                @if ($u->puede('usuarios', 'roles')) {!! $nav('usuarios.index', 'Usuarios y Roles', '◉', null, 'usuarios.*|roles.*') !!} @endif
                @if ($u->puede('convenios')) {!! $nav('convenios.index', 'Convenios', '▭', null, 'convenios.*') !!} @endif
                @if ($u->puede('personal')) {!! $nav('personal.index', 'Personal y vehículos', '☰', null, 'personal.*') !!} @endif
                @if ($u->puede('web')) {!! $nav('web.index', 'Sitio web y opiniones', '✉', null, 'web.*') !!} @endif
                @if ($u->puede('reportes')) {!! $nav('reportes.index', 'Reportes', '▨') !!} @endif
                @if ($u->puede('auditoria')) {!! $nav('auditoria.index', 'Auditoría', '⚑') !!} @endif
                @if ($u->puede('configuracion')) {!! $nav('configuracion.index', 'Configuración', '⚙') !!} @endif
            @endif

            <div class="seccion">Funeraria</div>
            <a href="{{ route('inicio') }}" target="_blank"><span class="ico">⌂</span>Ver sitio público</a>
            @if ($u->puede('portal'))
                <a href="{{ whatsapp_url(null, 'Hola, necesito información') }}" target="_blank" rel="noopener"><span class="ico">✆</span>WhatsApp</a>
            @endif
        </nav>
        <div class="usuario">
            <div class="avatar">{{ $u->iniciales() }}</div>
            <div class="grow">
                <div class="n">{{ $u->nombre }}</div>
                <div class="r">{{ ucfirst($rol) }}</div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Cerrar sesión</button></form>
            </div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="flex">
                <button class="menu-movil" type="button" data-toggle-menu aria-label="Menú">☰</button>
                <div class="titulo">
                    <h1>@yield('titulo')</h1>
                    @hasSection('subtitulo')<p>@yield('subtitulo')</p>@endif
                </div>
            </div>
            <div class="acciones flex flex-wrap">@yield('acciones')</div>
        </header>
        <main class="contenido">
            @include('partials.avisos')
            @yield('contenido')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
