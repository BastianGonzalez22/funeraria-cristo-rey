<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingreso · {{ config('funeraria.nombre') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php
    // Imagen de la derecha: se cambia en Configuración (o dejando un archivo en public/img/login.jpg)
    $imagen = \App\Models\Ajuste::imagen('login_imagen') ?? (file_exists(public_path('img/login.jpg')) ? asset('img/login.jpg') : null);
    $frase = \App\Models\Ajuste::valor('login_texto') ?: config('funeraria.lema');
@endphp
<div class="login-v2">
    <div class="lado-form">
        <div class="arriba">
            <a href="{{ route('inicio') }}" class="marca">{{ config('funeraria.nombre') }}</a>
            <a href="{{ route('inicio') }}" class="btn btn-outline btn-sm">← Volver al inicio</a>
        </div>
        <div class="centro">
            <div class="caja">
                <h1 class="mb-1">Ingreso al sistema</h1>
                <p class="muted mt-0 mb-3">El sistema identifica su perfil automáticamente al ingresar.</p>
                @include('partials.avisos')
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="campo mb-2">
                        <label for="email">Correo electrónico</label>
                        <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>
                    <div class="campo mb-2">
                        <label for="password">Contraseña</label>
                        <input id="password" class="input" type="password" name="password" required autocomplete="current-password">
                    </div>
                    <label class="check mb-2"><input type="checkbox" name="recordar" value="1"> Mantener sesión iniciada</label>
                    <button class="btn btn-primary" style="width:100%;justify-content:center;padding:11px" type="submit">Ingresar</button>
                </form>
                <p class="small mt-3">¿Es una familia y desea solicitar un servicio? <a href="{{ route('registro') }}" class="strong">Cree su cuenta</a></p>
                <div class="roles-demo">Tras 5 intentos fallidos la cuenta se bloquea y el administrador debe desbloquearla.</div>
            </div>
        </div>
        <div class="small muted">Atención 24 horas · {{ config('funeraria.telefono') }}</div>
    </div>
    <div class="lado-img {{ $imagen ? '' : 'sin-foto' }}" @if ($imagen) style="background-image:url('{{ $imagen }}')" @endif>
        <div class="texto">
            <blockquote>“{{ $frase }}.”</blockquote>
            <div class="small" style="color:#d9cfc2">{{ config('funeraria.direccion') }}</div>
        </div>
    </div>
</div>
</body>
</html>
