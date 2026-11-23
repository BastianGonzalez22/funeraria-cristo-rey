@extends('layouts.app')
@section('titulo', 'Dashboard')
@section('subtitulo', 'Resumen de '.now()->translatedFormat('F Y').' · '.config('funeraria.nombre'))
@section('acciones')
    @if (auth()->user()->puede('calendario'))<a href="{{ route('calendario.index') }}" class="btn btn-outline">Ver calendario</a>@endif
    @if (auth()->user()->puede('solicitudes'))<a href="{{ route('solicitudes.create') }}" class="btn btn-primary">+ Nueva solicitud</a>@endif
@endsection

@section('contenido')
@php $esAdmin = auth()->user()->puede('finanzas'); $prom = config('funeraria.promedio_servicios_mes'); @endphp

<div class="grid-2-1 mb-1">
    <div class="card">
        <div class="card-header"><h2>Calendario de servicios</h2><a href="{{ route('calendario.index') }}" class="small">Abrir calendario →</a></div>
        <div class="card-body">@include('partials.mini-calendario', ['rutaMes' => 'dashboard'])</div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Urnas en bodega</h2>@if (auth()->user()->puede('reposicion'))<a href="{{ route('reposicion.index') }}" class="small">Reposición →</a>@endif</div>
        <div class="card-body">
            <div class="totales mt-0" style="grid-template-columns:1fr 1fr">
                <div><div class="k">En bodega</div><div class="v">{{ $stockInd['en_bodega'] }}<span class="small muted"> / {{ $stockInd['capacidad'] }}</span></div></div>
                <div><div class="k">Disponibles</div><div class="v text-ok">{{ $stockInd['disponibles'] }}</div></div>
                <div><div class="k">Reservadas</div><div class="v text-warn">{{ $stockInd['reservadas'] }}</div></div>
                <div><div class="k">Personalizadas</div><div class="v">{{ $stockInd['personalizadas'] }}</div></div>
            </div>
            <p class="small muted mb-0 mt-1">{{ $stockInd['vendidas_mes'] }} vendida(s) este mes · {{ $stockInd['libre'] }} espacio(s) libre(s)</p>
        </div>
    </div>
</div>

<div class="grid-4 mb-3">
    <div class="kpi">
        <div class="lbl">Servicios del mes</div>
        <div class="val">{{ $kpis['servicios_mes'] }}</div>
        <div class="det {{ $kpis['servicios_mes'] >= $prom ? 'text-bad' : 'text-ok' }}">Promedio mensual: {{ $prom }} · mes anterior: {{ $kpis['servicios_mes_anterior'] }}</div>
    </div>
    <div class="kpi">
        <div class="lbl">Solicitudes en revisión</div>
        <div class="val">{{ $kpis['en_revision'] }}</div>
        <div class="det text-warn">{{ $kpis['en_revision'] ? 'Requieren aprobación' : 'Todo al día' }}</div>
    </div>
    @if ($esAdmin)
        <div class="kpi">
            <div class="lbl">Abonos del mes</div>
            <div class="val">{{ clp($kpis['abonos_mes']) }}</div>
            <div class="det {{ ($kpis['abonos_variacion'] ?? 0) >= 0 ? 'text-ok' : 'text-bad' }}">
                @if ($kpis['abonos_variacion'] !== null)
                    {{ $kpis['abonos_variacion'] >= 0 ? '▲' : '▼' }} {{ abs($kpis['abonos_variacion']) }}% vs. mes anterior
                @else
                    Sin datos del mes anterior
                @endif
            </div>
        </div>
        <div class="kpi">
            <div class="lbl">Saldos por cobrar</div>
            <div class="val">{{ clp($kpis['saldos']) }}</div>
            <div class="det text-bad">{{ $kpis['familias_con_saldo'] }} familia(s) con saldo pendiente</div>
        </div>
    @else
        <div class="kpi">
            <div class="lbl">Servicios hoy</div>
            <div class="val">{{ $kpis['hoy'] }}</div>
            <div class="det muted">Programados para {{ now()->translatedFormat('l d') }}</div>
        </div>
        <div class="kpi">
            <div class="lbl">Alertas de stock</div>
            <div class="val">{{ $stock->count() }}</div>
            <div class="det {{ $stock->count() ? 'text-bad' : 'text-ok' }}">{{ $stock->count() ? 'Modelos por reponer' : 'Bodega en orden' }}</div>
        </div>
    @endif
</div>

@if ($esAdmin && $atrasados->isNotEmpty())
    <div class="aviso aviso-error">
        <strong>Pagos atrasados:</strong>
        @foreach ($atrasados as $c)
            <a href="{{ route('abonos.index', ['contrato' => $c->id]) }}">{{ $c->solicitud->cliente->nombre_familia }} — {{ $c->solicitud->cliente->nombre_contratante }} ({{ clp($c->saldo_pendiente) }})</a>@if (! $loop->last), @endif
        @endforeach
    </div>
@endif

<div class="grid-2-1">
    <div>
        <div class="card">
            <div class="card-header">
                <h2>Próximos servicios</h2>
                <a href="{{ route('solicitudes.index') }}" class="btn btn-outline btn-sm">Ver todas</a>
            </div>
            <div class="card-body p0 tabla-wrap">
                <table class="tabla">
                    <thead><tr><th>Familia</th><th>Servicio</th><th>Fecha / hora</th><th>Flota</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($proximos as $s)
                        <tr>
                            <td><x-familia :cliente="$s->cliente" :url="route('solicitudes.show', $s)" /></td>
                            <td>{{ $s->nombreServicio() }}</td>
                            <td class="nowrap">{{ fecha($s->fecha_hora, 'd M, H:i') }}</td>
                            <td>{{ $s->asignacion?->vehiculo?->nombre ?? '—' }}</td>
                            <td><x-estado :valor="$s->estado" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted text-center">No hay servicios programados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Alertas</h2><span class="meta">{{ count($alertas) }} activas</span></div>
            <div class="card-body">
                @if (empty($alertas))
                    <p class="muted mb-0">Sin alertas pendientes.</p>
                @else
                    <ul class="alertas">
                        @foreach ($alertas as $a)
                            <li><span class="punto {{ $a['tipo'] }}"></span><a href="{{ $a['url'] }}" class="grow" style="color:inherit">{{ $a['texto'] }}</a><span class="badge b-neutral">{{ $a['grupo'] }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><h2>Alertas de stock</h2>@if (auth()->user()->puede('stock', 'urnas', 'reposicion'))<a href="{{ route('urnas.index') }}" class="small">Bodega →</a>@endif</div>
            <div class="card-body">
                @forelse ($stock as $u)
                    <div class="alerta-stock">
                        <div class="t"><span>{{ $u->codigo }} {{ $u->modelo }}</span><span class="muted">{{ $u->disponible() }} (mín. {{ $u->stock_minimo }})</span></div>
                        <div class="barra {{ $u->estado === 'reponer' ? 'bad' : 'warn' }}"><span style="width: {{ max(4, $u->porcentajeStock()) }}%"></span></div>
                        <div class="d {{ $u->estado === 'reponer' ? 'text-bad' : 'text-warn' }}">
                            {{ $u->estado === 'reponer' ? 'Bajo el mínimo — reponer' : (in_array($u->categoria, ['infantil', 'sobredimensionada']) ? 'En el umbral — mantener stock de tamaños especiales (SEREMI)' : 'En el umbral — solicitar reposición') }}
                        </div>
                    </div>
                @empty
                    <p class="muted mb-0">Todos los modelos están sobre el mínimo.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Accesos rápidos</h2></div>
            <div class="card-body flex flex-wrap">
                <a class="btn btn-outline btn-sm" href="{{ route('clientes.create') }}">Registrar familia</a>
                @if (auth()->user()->puede('flota'))<a class="btn btn-outline btn-sm" href="{{ route('flota.index') }}">Asignar carroza</a>@endif
                <a class="btn btn-outline btn-sm" href="{{ route('convenios.index') }}#verificaciones">Verificar AFP/IPS</a>
                <a class="btn btn-outline btn-sm" href="{{ route('usuarios.index') }}">Buscar familia</a>
                @if ($esAdmin)
                    <a class="btn btn-outline btn-sm" href="{{ route('abonos.index') }}">Registrar abono</a>
                    <a class="btn btn-outline btn-sm" href="{{ route('reportes.index') }}">Reportes</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
