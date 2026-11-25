<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error del sistema · {{ config('funeraria.nombre') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
    <div class="card" style="max-width:460px;text-align:center"><div class="card-body" style="padding:32px">
        <div style="font-family:var(--serif);font-size:48px;color:var(--accent)">500</div>
        <h1 class="mb-1">Error del sistema</h1>
        <p class="muted">Ocurrió un problema inesperado. Intente nuevamente en unos minutos.</p>
        <div class="flex" style="justify-content:center">
            <a href="{{ url()->previous() }}" class="btn btn-outline">Volver</a>
            <a href="{{ auth()->check() ? route('home') : route('inicio') }}" class="btn btn-primary">Ir al inicio</a>
        </div>
    </div></div>
</div>
</body>
</html>
