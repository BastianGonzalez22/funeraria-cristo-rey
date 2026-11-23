@extends('layouts.app')
@section('titulo', 'Reportes')
@section('subtitulo', 'Boletas, gastos, servicios más vendidos y seguimiento de cobros · uso exclusivo del administrador')
@section('acciones')
    <form method="GET" class="flex">
        <input class="input" type="month" name="mes" value="{{ $mes->format('Y-m') }}" onchange="this.form.submit()" style="width:160px">
    </form>
    <a href="{{ route('reportes.exportar', ['mes' => $mes->format('Y-m')]) }}" class="btn btn-primary">Exportar (Excel/CSV)</a>
    <a href="{{ route('reportes.seremi', ['mes' => $mes->format('Y-m')]) }}" class="btn btn-outline">Nómina SEREMI</a>
@endsection

@section('contenido')
<div class="grid-4 mb-3">
    <div class="kpi"><div class="lbl">Servicios del mes</div><div class="val">{{ $totalServiciosMes }}</div><div class="det muted">Promedio esperado: {{ config('funeraria.promedio_servicios_mes') }}</div></div>
    <div class="kpi"><div class="lbl">Ingresos (abonos)</div><div class="val">{{ clp($ingresos) }}</div></div>
    <div class="kpi"><div class="lbl">Gastos</div><div class="val">{{ clp($totalGastos) }}</div></div>
    <div class="kpi"><div class="lbl">Resultado</div><div class="val {{ $ingresos - $totalGastos < 0 ? 'text-bad' : 'text-ok' }}">{{ clp($ingresos - $totalGastos) }}</div></div>
</div>

<div class="card">
    <div class="card-header"><h2>Servicios por mes</h2><span class="meta">Últimos 12 meses — indicador principal del negocio</span></div>
    <div class="card-body">
        <div class="grafico">
            @foreach ($porMes as $i => $m)
                <div class="col {{ $loop->last ? 'actual' : '' }}">
                    <div class="n">{{ $m['total'] }}</div>
                    <div class="b" style="height: {{ round($m['total'] / $maxMes * 100) }}%"></div>
                    <div class="m">{{ $m['mes'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Servicios más vendidos</h2><span class="meta" style="text-transform:capitalize">{{ $mes->translatedFormat('F Y') }}</span></div>
        <div class="card-body">
            @php $maxV = max(1, $masVendidos->max() ?? 1); @endphp
            @forelse ($masVendidos as $tipo => $n)
                <div class="barra-h"><span>{{ etiqueta('tipos_servicio', $tipo) }}</span><div class="barra"><span style="width: {{ round($n / $maxV * 100) }}%; background: var(--accent)"></span></div><strong>{{ $n }}</strong></div>
            @empty
                <p class="muted">Sin servicios en el mes.</p>
            @endforelse
            @if ($urnasMasVendidas->isNotEmpty())
                <p class="small muted mb-1 mt-2">Urnas más vendidas:</p>
                @foreach ($urnasMasVendidas as $u => $n)<div class="small">{{ $u }} — <strong>{{ $n }}</strong></div>@endforeach
            @endif
            @if ($convenios->isNotEmpty())
                <p class="small muted mb-1 mt-2">Contratos por convenio:</p>
                @foreach ($convenios as $inst => $n)<div class="small">{{ $inst }} — <strong>{{ $n }}</strong></div>@endforeach
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Control de gastos</h2><span class="meta">Incluye compras de urnas y arriendos</span></div>
        <div class="card-body p0">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th class="text-right">Monto</th><th></th></tr></thead>
                <tbody>
                @forelse ($gastos as $g)
                    <tr><td class="nowrap">{{ fecha($g->fecha, 'd M') }}</td><td>{{ $g->concepto }}</td><td class="small">{{ etiqueta('categorias_gasto', $g->categoria) }}</td><td class="text-right">{{ clp($g->monto) }}</td>
                        <td><form method="POST" action="{{ route('gastos.destroy', $g) }}" data-confirmar="¿Eliminar este gasto?">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">×</button></form></td></tr>
                @empty
                    <tr><td colspan="5" class="muted text-center">Sin gastos registrados en el mes.</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><td colspan="3">Total de gastos</td><td class="text-right">{{ clp($totalGastos) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('gastos.store') }}" class="filtros">@csrf
                <div class="campo"><label>Concepto</label><input class="input" name="concepto" required></div>
                <div class="campo"><label>Categoría</label><select class="input" name="categoria">@foreach (config('funeraria.categorias_gasto') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="campo" style="min-width:110px"><label>Monto</label><input class="input" type="number" name="monto" min="1" required></div>
                <div class="campo" style="min-width:130px"><label>Fecha</label><input class="input" type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required></div>
                <button class="btn btn-primary">Agregar</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Seguimiento de cobros</h2><span class="meta">Contratos con saldo pendiente</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Familia</th><th>Contrato</th><th>Total</th><th>Abonado</th><th>Saldo</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($cobros as $c)
                <tr>
                    <td><x-familia :cliente="$c->solicitud->cliente" /></td><td>{{ $c->numero }}</td><td>{{ clp($c->monto_total) }}</td><td>{{ clp($c->totalAbonado()) }}</td>
                    <td class="strong">{{ clp($c->saldo_pendiente) }}</td><td class="nowrap">{{ fecha($c->fecha_vencimiento) }}</td><td><x-estado :valor="$c->estadoPago()" /></td>
                    <td><a class="btn btn-whatsapp btn-xs" target="_blank" href="{{ whatsapp_url($c->solicitud->cliente->telefono, 'Estimada familia, le recordamos que el contrato '.$c->numero.' mantiene un saldo de '.clp($c->saldo_pendiente).'. Ante cualquier consulta estamos disponibles. '.config('funeraria.nombre')) }}">Recordar</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted text-center">Sin saldos pendientes.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td colspan="4">Total por cobrar</td><td>{{ clp($cobros->sum('saldo_pendiente')) }}</td><td colspan="3"></td></tr></tfoot>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Boletas / comprobantes emitidos</h2><span class="meta">{{ $boletas->count() }} en el mes</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>N°</th><th>Fecha</th><th>Familia</th><th>Contrato</th><th>Medio</th><th class="text-right">Monto</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($boletas as $b)
                <tr><td>@if ($b->numero_comprobante)<a href="{{ route('abonos.comprobante', $b) }}" target="_blank">{{ $b->numero_comprobante }}</a>@else<span class="muted">—</span>@endif</td><td>{{ fecha($b->fecha) }}</td><td><x-familia :cliente="$b->contrato->solicitud->cliente" /></td><td>{{ $b->contrato->numero }}</td><td>{{ $b->nombreMedio() }}</td><td class="text-right">{{ clp($b->monto) }}</td><td>@if ($b->estado === 'pendiente')<x-estado valor="pendiente" texto="Por confirmar" />@else<x-estado :valor="$b->estado" />@endif</td></tr>
            @empty
                <tr><td colspan="7" class="muted text-center">Sin comprobantes en el mes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
