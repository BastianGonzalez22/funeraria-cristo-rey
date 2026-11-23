@extends('layouts.app')
@section('titulo', $esChofer ? 'Mis rutas' : 'Rutas de choferes')
@section('subtitulo', 'Dónde se vela, a dónde se traslada y los horarios · sin información contable')

@section('contenido')
@if ($sinFicha)
    <div class="aviso aviso-warn">Su usuario aún no está vinculado a una ficha de chofer. Pida al administrador que lo vincule en “Personal y vehículos”.</div>
@endif

<div class="card">
    <div class="card-header">
        <h2>Semana del {{ $lunes->translatedFormat('d') }} al {{ $lunes->copy()->addDays(6)->translatedFormat('d M Y') }}</h2>
        <div class="flex">
            <a href="{{ route('itinerario.index', ['semana' => $lunes->copy()->subWeek()->toDateString()]) }}" class="btn btn-outline btn-sm">‹ Anterior</a>
            <a href="{{ route('itinerario.index') }}" class="btn btn-outline btn-sm">Hoy</a>
            <a href="{{ route('itinerario.index', ['semana' => $lunes->copy()->addWeek()->toDateString()]) }}" class="btn btn-outline btn-sm">Siguiente ›</a>
        </div>
    </div>
    <div class="card-body">
        <div class="cal-semana">
            @foreach ($dias as $d)
                @php $n = isset($porDia[$d->toDateString()]) ? $porDia[$d->toDateString()]->count() : 0; @endphp
                <a href="{{ route('itinerario.index', ['semana' => $lunes->toDateString(), 'dia' => $d->toDateString()]) }}" class="{{ $d->isSameDay($dia) ? 'sel' : '' }} {{ $d->isToday() ? 'hoy' : '' }}">
                    <div class="w">{{ $d->translatedFormat('D') }}</div>
                    <div class="d">{{ $d->day }}</div>
                    <div class="n {{ $n ? 'text-warn strong' : 'muted' }}">{{ $n ? $n.' ruta'.($n > 1 ? 's' : '') : 'libre' }}</div>
                </a>
            @endforeach
        </div>
    </div>
</div>

<h2 class="mb-1 mt-2" style="text-transform:capitalize">{{ $dia->translatedFormat('l d \d\e F') }}</h2>
@forelse ($delDia as $a)
    @php $s = $a->solicitud; @endphp
    <div class="card">
        <div class="card-header">
            <div><h2>{{ $a->inicio->format('H:i') }} – {{ $a->fin->format('H:i') }} · {{ $s->cliente->nombre_familia }}</h2><div class="small muted">{{ $s->cliente->nombre_contratante }} · {{ $s->nombreServicio() }}</div></div>
            <x-estado :valor="$a->estado" />
        </div>
        <div class="grid-2" style="gap:0">
            <div class="card-body small">
                <p class="mt-0"><strong>{{ $a->vehiculo->nombre }}</strong> · Chofer {{ $a->chofer->nombre }}</p>
                <table class="tabla">
                    <tr><td class="nowrap">{{ $a->inicio->format('H:i') }}</td><td><strong>Salida / velatorio</strong><br>{{ $a->origen ?: $s->lugar_velatorio ?: '—' }}</td></tr>
                    @foreach ($a->hoja_ruta ?? [] as $p)
                        <tr><td class="nowrap">{{ $p['hora'] ?? '' }}</td><td><strong>{{ $p['lugar'] ?? '' }}</strong><br>{{ $p['direccion'] ?? '' }}</td></tr>
                    @endforeach
                    <tr><td class="nowrap">{{ $a->fin->format('H:i') }}</td><td><strong>Destino</strong><br>{{ $a->destino ?: '—' }}</td></tr>
                </table>
                @if ($a->notas)<div class="aviso aviso-info small mt-1 mb-1">{{ $a->notas }}</div>@endif
                <div class="flex flex-wrap mt-1">
                    <a class="btn btn-primary btn-sm" href="{{ $a->rutaUrl() }}" target="_blank" rel="noopener">Abrir ruta en Google Maps</a>
                    <a class="btn btn-outline btn-sm" href="{{ route('itinerario.show', $a) }}">Hoja de ruta</a>
                    @if ($a->estado === 'programada')
                        <form method="POST" action="{{ route('itinerario.estado', $a) }}">@csrf<input type="hidden" name="estado" value="en_curso"><button class="btn btn-outline btn-sm">Iniciar traslado</button></form>
                    @elseif ($a->estado === 'en_curso')
                        <form method="POST" action="{{ route('itinerario.estado', $a) }}">@csrf<input type="hidden" name="estado" value="completada"><button class="btn btn-ok btn-sm">Marcar completado</button></form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <iframe class="mapa-embed" loading="lazy" src="{{ mapa_embed($a->destino ?: $a->origen ?: $s->lugar_velatorio) }}" title="Mapa del destino"></iframe>
            </div>
        </div>
    </div>
@empty
    <div class="card"><div class="card-body muted">No hay rutas este día. Elija otro día en la semana.</div></div>
@endforelse
@endsection
