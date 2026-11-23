@extends('layouts.publico')
@section('titulo', $urna->modelo)
@section('contenido')
<section class="seccion-pub">
    <a href="{{ route('publico.catalogo') }}" class="small">← Volver al catálogo</a>
    <div class="grid-2 mt-2">
        <div>
            @include('urnas.partials.galeria', ['urna' => $urna, 'id' => 'galeria-publica'])
        </div>
        <div>
            <div class="small muted">{{ $urna->codigo }} · {{ etiqueta('categorias_urna', $urna->categoria) }}</div>
            <h1 class="mt-1">{{ $urna->modelo }}</h1>
            <div style="font-family:var(--serif);font-size:30px;font-weight:600;margin:10px 0">{{ clp($urna->precio) }}</div>
            <table class="tabla">
                <tr><td>Material</td><td>{{ $urna->material ?: '—' }}</td></tr>
                <tr><td>Color</td><td>{{ $urna->color ?: '—' }}</td></tr>
                <tr><td>Acabado</td><td>{{ $urna->acabado ? etiqueta('acabados_urna', $urna->acabado) : '—' }}</td></tr>
                @if ($urna->medidasTexto())<tr><td>Medidas (largo × ancho × alto)</td><td>{{ $urna->medidasTexto() }}</td></tr>@endif
                @if ($urna->manillas)<tr><td>Manillas</td><td>{{ $urna->manillas }}</td></tr>@endif
                <tr><td>Disponibilidad</td><td>{{ $urna->disponible() > 0 ? 'Disponible' : ($urna->bajo_pedido ? 'Se fabrica a pedido' : 'Sin unidades por ahora — consúltenos') }}</td></tr>
            </table>
            @if ($urna->descripcion)<p class="small mt-2">{{ $urna->descripcion }}</p>@endif
            <div class="flex flex-wrap mt-3">
                <a class="btn btn-whatsapp" target="_blank" rel="noopener" href="{{ whatsapp_url(null, 'Hola, quisiera información de la urna '.$urna->codigo.' '.$urna->modelo.'.') }}">Consultar por WhatsApp</a>
                <a class="btn btn-outline" href="{{ route('publico.contacto', ['urna' => $urna->codigo]) }}">Formulario de contacto</a>
                <a class="btn btn-primary" href="{{ auth()->check() && auth()->user()->puede('portal') ? route('portal.create', ['urna' => $urna->id]) : route('registro') }}">Solicitar servicio con esta urna</a>
            </div>
        </div>
    </div>
</section>
@endsection
