<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Inicio') · {{ config('funeraria.nombre') }}</title>
    <meta name="description" content="{{ config('funeraria.nombre') }} — servicios funerarios en Temuco y Padre Las Casas. Atención 24 horas.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="publico">
<div class="pub-top">
    <div class="wrap">
        <span>Atención 24 horas · {{ config('funeraria.direccion') }}</span>
        <span>Teléfono: <a href="tel:{{ preg_replace('/\s/', '', config('funeraria.telefono')) }}"><strong>{{ config('funeraria.telefono') }}</strong></a></span>
    </div>
</div>
<header class="pub-header">
    <div class="wrap">
        <a href="{{ route('inicio') }}" class="logo">{{ config('funeraria.nombre') }}</a>
        <nav>
            <a href="{{ route('inicio') }}">Inicio</a>
            <a href="{{ route('publico.catalogo') }}">Catálogo de urnas</a>
            <a href="{{ route('publico.convenios') }}">Convenios</a>
            <a href="{{ route('publico.faq') }}">Preguntas frecuentes</a>
            <a href="{{ route('publico.contacto') }}">Contacto</a>
            @auth
                <a href="{{ route('home') }}" class="btn btn-ingresar">Mi cuenta →</a>
            @else
                <a href="{{ route('registro') }}" style="color:#e2d6c6">Crear cuenta</a>
                <a href="{{ route('login') }}" class="btn btn-ingresar">Ingresar →</a>
            @endauth
        </nav>
    </div>
</header>

<main class="pub-main">
@if (session('ok') || session('error') || $errors->any())
    <div class="seccion-pub" style="padding-bottom:0">@include('partials.avisos')</div>
@endif

@yield('contenido')
</main>

<footer class="pub-footer">
    <div class="wrap">
        <div>
            <h3>{{ config('funeraria.nombre') }}</h3>
            <p>{{ config('funeraria.lema') }}. Servicios para todas las religiones en Temuco, Padre Las Casas y alrededores.</p>
        </div>
        <div>
            <h3>Contacto</h3>
            <p>Teléfono: {{ config('funeraria.telefono') }}<br>
                Correo: <a href="mailto:{{ config('funeraria.email') }}">{{ config('funeraria.email') }}</a><br>
                {{ config('funeraria.direccion') }}</p>
        </div>
        <div>
            <h3>Enlaces</h3>
            <p><a href="{{ route('publico.catalogo') }}">Catálogo de urnas</a><br>
                <a href="{{ route('publico.convenios') }}">Convenios y cuota mortuoria</a><br>
                <a href="{{ route('registro') }}">Crear cuenta para solicitar un servicio</a></p>
        </div>
    </div>
</footer>
<a class="wa-flotante" href="{{ whatsapp_url(null, 'Hola, necesito información sobre un servicio funerario.') }}" target="_blank" rel="noopener">✆ WhatsApp</a>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
