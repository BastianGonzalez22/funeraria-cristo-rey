@extends('layouts.publico')
@section('titulo', 'Inicio')
@section('contenido')
@php $portada = \App\Models\Ajuste::imagen('portada_imagen'); $tituloPortada = \App\Models\Ajuste::valor('portada_titulo') ?: config('funeraria.lema'); @endphp
<section class="hero {{ $portada ? 'con-foto' : '' }}" @if ($portada) style="background-image:url('{{ $portada }}')" @endif>
    <div class="wrap">
        <p class="small" style="letter-spacing:.15em;text-transform:uppercase;color:#b59a77">Atención 24 horas · Temuco y Padre Las Casas</p>
        <h1>{{ $tituloPortada }}</h1>
        <p>Servicios funerarios para todas las religiones: retiro, velatorio, cortejo, sepultura o cremación. Le orientamos con los trámites, la cuota mortuoria de AFP/IPS y los convenios vigentes.</p>
        <div class="flex flex-wrap mt-3">
            <a href="tel:{{ preg_replace('/\s/', '', config('funeraria.telefono')) }}" class="btn btn-primary">Llamar ahora: {{ config('funeraria.telefono') }}</a>
            <a href="{{ whatsapp_url(null, 'Hola, necesito información sobre un servicio funerario.') }}" class="btn btn-whatsapp" target="_blank" rel="noopener">Escribir por WhatsApp</a>
            <a href="{{ route('registro') }}" class="btn btn-outline">Solicitar servicio en línea</a>
            @guest<a href="{{ route('login') }}" class="btn btn-ingresar">Ingresar al sistema</a>@endguest
        </div>
    </div>
</section>

<section class="seccion-pub">
    <h2>Nuestros servicios</h2>
    <p class="muted">En todos nuestros servicios entregamos cruz, tarjetas de agradecimiento, foto marcada y flores. El valor cambia según la urna elegida.</p>
    <div class="grid-3 mt-2">
        @foreach (config('funeraria.tipos_servicio') as $t)
            <div class="card mb-0"><div class="card-body">
                <h3>{{ $t['nombre'] }}</h3>
                <p class="small muted mb-1">Desde</p>
                <div style="font-family:var(--serif);font-size:22px;font-weight:600">{{ clp($t['precio']) }}</div>
                <p class="xs muted mb-0">+ valor de la urna. Abono inicial del {{ config('funeraria.abono_minimo_pct') }}% y saldo en cuotas.</p>
            </div></div>
        @endforeach
    </div>
</section>

<section class="seccion-pub" style="padding-top:0">
    <div class="flex between"><h2>Catálogo de urnas</h2><a href="{{ route('publico.catalogo') }}">Ver todo el catálogo →</a></div>
    <div class="catalogo mt-2">
        @foreach ($destacadas as $u)
            @include('urnas.partials.tarjeta', ['u' => $u, 'accion' => route('publico.urna', $u->codigo), 'textoAccion' => 'Ver detalle'])
        @endforeach
    </div>
</section>

@if ($convenios->isNotEmpty())
<section class="seccion-pub" style="padding-top:0">
    <h2>Convenios</h2>
    <p class="muted">Trabajamos con instituciones de previsión y empresas para el cobro de la cuota mortuoria y descuentos.</p>
    <div class="logos-convenio mt-2">
        @foreach ($convenios as $c)
            <div class="logo-convenio" style="background:{{ $c->color }}" title="{{ $c->institucion }}">@if ($c->logoUrl())<img src="{{ $c->logoUrl() }}" alt="{{ $c->institucion }}">@else{{ $c->sigla() }}@endif</div>
        @endforeach
    </div>
</section>
@endif

<section class="seccion-pub" style="padding-top:0">
    <h2>Lo que dicen las familias</h2>
    <div class="grid-3 mt-2">
        @forelse ($opiniones as $o)
            <div class="opinion"><div class="estrellas">{{ $o->estrellas() }}</div><p class="small">{{ $o->comentario }}</p><div class="small strong">— {{ $o->nombre }}</div></div>
        @empty
            <p class="muted">Sea el primero en dejar su opinión.</p>
        @endforelse
    </div>
    <div class="card mt-3" style="max-width:640px">
        <div class="card-header"><h2>Deje su opinión</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('publico.opinion') }}">
                @csrf
                <input type="text" name="sitio_web" class="hidden" tabindex="-1" autocomplete="off">
                <div class="form-grid">
                    <div class="campo"><label>Nombre</label><input class="input" name="nombre" required maxlength="120"></div>
                    <div class="campo"><label>Calificación</label><select class="input" name="calificacion">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ str_repeat('★', $i) }}</option>@endfor</select></div>
                    <div class="campo full"><label>Comentario</label><textarea class="input" name="comentario" required maxlength="1000"></textarea></div>
                </div>
                <div class="form-acciones"><button class="btn btn-primary">Enviar opinión</button></div>
            </form>
        </div>
    </div>
</section>
@endsection
