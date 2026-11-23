@extends('layouts.publico')
@section('titulo', 'Contacto')
@section('contenido')
<section class="seccion-pub">
    <div class="grid-2">
        <div>
            <h2>Contáctenos</h2>
            <p class="muted">Déjenos sus datos y le llamaremos o escribiremos por el medio que prefiera. Si es urgente, llame al <strong>{{ config('funeraria.telefono') }}</strong>.</p>
            <div class="card mt-2"><div class="card-body">
                <form method="POST" action="{{ route('publico.contacto.enviar') }}">
                    @csrf
                    <input type="text" name="sitio_web" class="hidden" tabindex="-1" autocomplete="off">
                    @if ($urna)<input type="hidden" name="urna_id" value="{{ $urna->id }}"><div class="aviso aviso-info small">Consulta por la urna <strong>{{ $urna->codigo }} {{ $urna->modelo }}</strong></div>@endif
                    <div class="form-grid">
                        <div class="campo"><label>Nombre *</label><input class="input" name="nombre" value="{{ old('nombre') }}" required></div>
                        <div class="campo"><label>Teléfono *</label><input class="input" name="telefono" value="{{ old('telefono') }}" required placeholder="+56 9 1234 5678"></div>
                        <div class="campo"><label>Correo</label><input class="input" type="email" name="email" value="{{ old('email') }}"></div>
                        <div class="campo"><label>Prefiere que lo contactemos por</label>
                            <select class="input" name="medio_contacto">@foreach (config('funeraria.medios_contacto') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                        <div class="campo full"><label>Mensaje *</label><textarea class="input" name="mensaje" required>{{ old('mensaje') }}</textarea></div>
                    </div>
                    <div class="form-acciones"><button class="btn btn-primary">Enviar</button></div>
                </form>
            </div></div>
        </div>
        <div>
            <div class="card"><div class="card-body">
                <h3>Atención 24 horas</h3>
                <p>Teléfono: <a href="tel:{{ preg_replace('/\s/', '', config('funeraria.telefono')) }}">{{ config('funeraria.telefono') }}</a><br>
                    WhatsApp: <a href="{{ whatsapp_url(null) }}" target="_blank">enviar mensaje</a><br>
                    Correo: <a href="mailto:{{ config('funeraria.email') }}">{{ config('funeraria.email') }}</a></p>
                <p>{{ config('funeraria.direccion') }}</p>
                <a class="btn btn-outline btn-sm" target="_blank" href="{{ mapa_url(config('funeraria.nombre').' '.config('funeraria.direccion')) }}">Ver en el mapa</a>
            </div></div>
            <div class="card"><div class="card-body">
                <h3>¿Prefiere hacerlo en línea?</h3>
                <p class="small">Cree una cuenta para ingresar la solicitud, adjuntar el certificado de defunción y revisar sus pagos.</p>
                <a href="{{ route('registro') }}" class="btn btn-primary btn-sm">Crear cuenta</a>
            </div></div>
        </div>
    </div>
</section>
@endsection
