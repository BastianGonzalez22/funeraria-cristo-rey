@extends('layouts.app')
@section('titulo', 'Calendario de servicios')
@section('subtitulo', 'Servicios realizados y programados, mes a mes')
@section('acciones')
    <a href="{{ route('calendario.exportar', ['mes' => $mes->format('Y-m')]) }}" class="btn btn-outline">Exportar mes (Excel)</a>
    @if (auth()->user()->puede('solicitudes'))<a href="{{ route('solicitudes.create') }}" class="btn btn-primary">+ Nueva solicitud</a>@endif
@endsection

@section('contenido')
@php $previo = $mes->copy()->subMonthNoOverflow(); $sig = $mes->copy()->addMonthNoOverflow(); @endphp
<div class="grid-2-1">
    <div class="card">
        <div class="card-header">
            <h2 style="text-transform:capitalize">{{ $mes->translatedFormat('F Y') }}</h2>
            <div class="flex">
                <span class="small muted">{{ $totalMes }} servicios · {{ $realizadosMes }} realizados</span>
                <form method="GET" class="inline"><input class="input" type="month" name="mes" value="{{ $mes->format('Y-m') }}" onchange="this.form.submit()" style="width:150px;padding:4px 8px"></form>
                <a class="btn btn-outline btn-sm" href="{{ route('calendario.index', ['mes' => $previo->format('Y-m')]) }}">‹</a>
                <a class="btn btn-outline btn-sm" href="{{ route('calendario.index') }}">Hoy</a>
                <a class="btn btn-outline btn-sm" href="{{ route('calendario.index', ['mes' => $sig->format('Y-m')]) }}">›</a>
            </div>
        </div>
        <div class="card-body">
            <table class="cal">
                <thead><tr>@foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($semanas as $semana)
                    <tr>
                        @foreach ($semana as $d)
                            @php $lista = $servicios->get($d->toDateString(), collect()); @endphp
                            <td class="{{ $d->month !== $mes->month ? 'fuera' : '' }} {{ $d->isToday() ? 'hoy' : '' }} {{ $d->isSameDay($dia) ? 'sel' : '' }}">
                                <a class="num" href="{{ route('calendario.index', ['mes' => $mes->format('Y-m'), 'dia' => $d->toDateString()]) }}">{{ $d->day }}</a>
                                @foreach ($lista->take(3) as $s)
                                    <a class="evento ev-{{ $s->estado }}" href="{{ route('calendario.index', ['mes' => $mes->format('Y-m'), 'dia' => $d->toDateString()]) }}" title="{{ $s->cliente->nombre_familia }} — {{ $s->cliente->nombre_contratante }}">{{ $s->fecha_hora->format('H:i') }} {{ str_replace('Familia ', '', $s->cliente->nombre_familia) }}</a>
                                @endforeach
                                @if ($lista->count() > 3)<span class="xs muted">+{{ $lista->count() - 3 }} más</span>@endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="xs muted mb-0"><span class="badge b-ok">Aprobado</span> <span class="badge b-warn">En revisión</span> <span class="badge b-neutral">Realizado</span> — Puede navegar a meses anteriores para revisar lo realizado (trazabilidad).</p>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>{{ ucfirst($dia->translatedFormat('l d \d\e F')) }}</h2><span class="meta">{{ $delDia->count() }} servicio(s)</span></div>
        <div class="card-body p0">
            @forelse ($delDia as $s)
                <div class="card-body" style="border-bottom:1px solid var(--border)">
                    <div class="flex between">
                        <a href="{{ auth()->user()->puede('solicitudes') ? route('solicitudes.show', $s) : '#' }}"><span class="familia-n">{{ $s->fecha_hora->format('H:i') }} · {{ $s->cliente->nombre_familia }}</span><span class="familia-c">{{ $s->cliente->nombre_contratante }}</span></a>
                        <x-estado :valor="$s->estado" />
                    </div>
                    <div class="small muted">{{ $s->nombreServicio() }} · {{ $s->nombre_fallecido }}</div>
                    <div class="small">Velatorio: {{ $s->lugar_velatorio ?: '—' }}<br>Destino: {{ $s->cementerio_crematorio ?: etiqueta('destinos', $s->destino_final) }}</div>
                    @if ($s->asignacion)
                        <div class="small mt-1"><span class="badge b-neutral">{{ $s->asignacion->vehiculo->nombre }}</span> <span class="badge b-neutral">{{ $s->asignacion->chofer->nombre }}</span></div>
                    @elseif ($s->estado === 'aprobada' && auth()->user()->puede('flota'))
                        <div class="small mt-1"><a href="{{ route('flota.editar', $s) }}" class="text-bad">Sin vehículo asignado →</a></div>
                    @endif
                </div>
            @empty
                <div class="card-body muted">No hay servicios este día.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
