@extends('layouts.app')
@section('titulo', 'Hoja de ruta')
@section('subtitulo', $a->solicitud->cliente->nombre_familia.' ('.$a->solicitud->cliente->nombre_contratante.') · '.fecha_hora($a->inicio))
@section('acciones')
    <a href="{{ route('itinerario.index') }}" class="btn btn-outline">Volver</a>
    <a class="btn btn-primary" href="{{ $a->rutaUrl() }}" target="_blank" rel="noopener">Abrir ruta en Google Maps</a>
    <button class="btn btn-outline" onclick="window.print()">Imprimir</button>
@endsection

@section('contenido')
@php $s = $a->solicitud; @endphp
<div class="card">
    <div class="card-body">
        <div class="ficha">
            <div class="dato"><div class="k">Vehículo</div><div class="v">{{ $a->vehiculo->nombre }} ({{ $a->vehiculo->patente }})</div></div>
            <div class="dato"><div class="k">Chofer</div><div class="v">{{ $a->chofer->nombre }}</div></div>
            <div class="dato"><div class="k">Horario</div><div class="v">{{ $a->inicio->format('H:i') }} – {{ $a->fin->format('H:i') }}</div></div>
            <div class="dato"><div class="k">Fallecido</div><div class="v">{{ $s->nombre_fallecido }}</div></div>
            <div class="dato"><div class="k">Urna</div><div class="v">{{ $s->unidad?->codigo ?? $s->urna?->codigo }} {{ $s->urna?->modelo }}</div></div>
            <div class="dato"><div class="k">Contacto familia</div><div class="v"><a href="tel:{{ preg_replace('/\s/', '', $s->cliente->telefono) }}">{{ $s->cliente->telefono }}</a></div></div>
        </div>
    </div>
    <div class="card-body p0">
        <table class="tabla">
            <thead><tr><th>Hora</th><th>Parada</th><th>Dirección</th></tr></thead>
            <tbody>
            <tr><td>{{ $a->inicio->format('H:i') }}</td><td>Salida</td><td>{{ $a->origen ?: '—' }} @if ($a->origen)<a href="{{ mapa_url($a->origen) }}" target="_blank">Abrir mapa</a>@endif</td></tr>
            @foreach ($a->hoja_ruta ?? [] as $p)
                <tr><td>{{ $p['hora'] ?? '' }}</td><td>{{ $p['lugar'] ?? '' }}</td><td>{{ $p['direccion'] ?? '' }} @if (! empty($p['direccion']))<a href="{{ mapa_url($p['direccion']) }}" target="_blank">Abrir mapa</a>@endif</td></tr>
            @endforeach
            <tr><td>{{ $a->fin->format('H:i') }}</td><td>Destino</td><td>{{ $a->destino ?: '—' }} @if ($a->destino)<a href="{{ mapa_url($a->destino) }}" target="_blank">Abrir mapa</a>@endif</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <iframe class="mapa-embed mb-2" loading="lazy" src="{{ mapa_embed($a->destino ?: $a->origen) }}" title="Mapa del destino"></iframe>
        <h3 class="mb-1">Elementos a llevar</h3>
        <div class="checks-grid">
            @foreach (config('funeraria.elementos') as $k => $v)
                <div class="small">{{ $s->elementoEntregado($k) ? '☑' : '☐' }} {{ $v }}</div>
            @endforeach
        </div>
        @if ($a->notas)<div class="aviso aviso-info mt-2 mb-0">{{ $a->notas }}</div>@endif
    </div>
</div>
@endsection
