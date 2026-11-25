@extends('layouts.app')
@section('titulo', 'Abonos y Pagos')
@section('subtitulo', 'Trazabilidad de pagos por familia')
@section('acciones')
    <a href="{{ route('abonos.exportar', ['mes' => $mes]) }}" class="btn btn-outline">Exportar {{ $mes ? 'mes' : 'todo' }} (Excel)</a>
    <a href="#registrar" class="btn btn-primary">+ Registrar abono</a>
@endsection

@section('contenido')
@if (request('comprobante'))
    <div class="aviso aviso-ok">Comprobante digital emitido: <a href="{{ route('abonos.comprobante', request('comprobante')) }}" target="_blank" class="strong">descargar PDF</a></div>
@endif

<div class="grid-4 mb-3">
    <div class="kpi"><div class="lbl">Abonos del mes</div><div class="val">{{ clp($totales['mes']) }}</div></div>
    <div class="kpi"><div class="lbl">Saldo por cobrar</div><div class="val">{{ clp($totales['por_cobrar']) }}</div></div>
    <div class="kpi"><div class="lbl">Saldo atrasado</div><div class="val text-bad">{{ clp($totales['atrasado']) }}</div></div>
    <div class="kpi"><div class="lbl">Contratos con saldo</div><div class="val">{{ $abiertos->count() }}</div></div>
</div>

@if ($porConfirmar->isNotEmpty())
<div class="card" id="por-confirmar" style="border-color:#f0dca4">
    <div class="card-header" style="background:var(--warn-bg)"><h2>Abonos informados por familias</h2><span class="meta">Verifique la transferencia y confirme: recién ahí se descuenta del saldo</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Familia</th><th>Contrato</th><th>Fecha</th><th>Medio / referencia</th><th class="text-right">Monto</th><th>Comprobante</th><th></th></tr></thead>
            <tbody>
            @foreach ($porConfirmar as $a)
                <tr>
                    <td><x-familia :cliente="$a->contrato->solicitud->cliente" /></td>
                    <td>{{ $a->contrato->numero }}<div class="xs muted">saldo {{ clp($a->contrato->saldo_pendiente) }}</div></td>
                    <td class="nowrap">{{ fecha($a->fecha) }}</td>
                    <td class="small">{{ $a->nombreMedio() }}<div class="xs muted">{{ $a->referencia }}</div></td>
                    <td class="text-right strong">{{ clp($a->monto) }}</td>
                    <td>@if ($a->adjunto_path)<a href="{{ route('abonos.adjunto', $a) }}" target="_blank" class="btn btn-outline btn-xs">Ver comprobante</a>@else<span class="muted xs">sin adjunto</span>@endif</td>
                    <td class="nowrap">
                        <form method="POST" action="{{ route('abonos.confirmar', $a) }}" class="inline" data-confirmar="¿Confirmar el abono de {{ clp($a->monto) }}? Se descontará del saldo.">@csrf<button class="btn btn-ok btn-xs">Confirmar</button></form>
                        <form method="POST" action="{{ route('abonos.rechazar', $a) }}" class="inline">@csrf<input type="hidden" name="motivo"><button class="btn btn-danger btn-xs" data-motivo="motivo" data-pregunta="Motivo del rechazo (la familia lo verá):">Rechazar</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="tabs">
        @foreach (['vigentes' => 'Vigentes', 'pendientes' => 'Saldo pendiente', 'atrasados' => 'Atrasados', 'liquidados' => 'Liquidados', 'todos' => 'Todos'] as $k => $v)
            <a href="{{ route('abonos.index', ['filtro' => $k]) }}" class="{{ $filtro === $k ? 'activo' : '' }}">{{ $v }}@if (isset($conteos[$k])) <span class="badge {{ $k === 'atrasados' && $conteos[$k] ? 'b-bad' : 'b-neutral' }}">{{ $conteos[$k] }}</span>@endif</a>
        @endforeach
        <form method="GET" class="flex" style="margin-left:auto;padding:6px 0">
            <input type="hidden" name="filtro" value="{{ $filtro }}">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Familia, contratante, RUT o contrato" style="width:230px">
            <input class="input" type="month" name="mes" value="{{ $mes }}" onchange="this.form.submit()" style="width:150px" title="Contratos con abonos en este mes">
        </form>
    </div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Familia</th><th>Contrato</th><th>Total servicio</th><th>Abonado</th><th>Saldo</th><th>Último medio</th><th>Abono mínimo (50%)</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($contratos as $c)
                @php $ultimo = $c->abonos->where('estado', 'registrado')->last(); @endphp
                <tr class="{{ (string) $seleccion === (string) $c->id ? 'destacado' : '' }}">
                    <td><x-familia :cliente="$c->solicitud->cliente" :url="route('solicitudes.show', $c->solicitud_id)" /></td>
                    <td><a href="{{ route('contratos.show', $c) }}">{{ $c->numero }}</a></td>
                    <td>{{ clp($c->monto_total) }}</td>
                    <td>{{ clp($c->totalAbonado()) }}</td>
                    <td class="{{ $c->saldo_pendiente ? 'strong' : '' }}">{{ clp($c->saldo_pendiente) }}</td>
                    <td class="small">{{ $ultimo?->nombreMedio() ?? '—' }}</td>
                    <td>@if ($c->cumpleAbonoMinimo())<span class="badge b-ok">Cumplido</span>@else<span class="badge b-bad">Faltan {{ clp($c->faltaParaMinimo()) }}</span>@endif</td>
                    <td>@if ($c->estado === 'liquidado')<x-estado valor="liquidado" />@else<x-estado :valor="$c->estadoPago()" />@endif</td>
                    <td><a class="btn btn-outline btn-xs" href="{{ route('abonos.index', ['contrato' => $c->id, 'filtro' => $filtro]) }}#registrar">Abonar</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted text-center">Sin contratos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $contratos->links() }}
</div>

<div class="grid-2-1">
    <div class="card" id="registrar">
        <div class="card-header"><h2>Registrar nuevo abono</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('abonos.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid">
                    <div class="campo"><label>Contrato *</label>
                        <select class="input" name="contrato_id" required>
                            <option value="">Seleccione…</option>
                            @foreach ($abiertos as $c)
                                <option value="{{ $c->id }}" @selected(old('contrato_id', $seleccion) == $c->id)>{{ $c->numero }} — {{ $c->solicitud->cliente->nombre_familia }} ({{ $c->solicitud->cliente->nombre_contratante }}) · saldo {{ clp($c->saldo_pendiente) }}</option>
                            @endforeach
                        </select></div>
                    <div class="campo"><label>Monto *</label><input class="input" type="number" name="monto" min="1" value="{{ old('monto') }}" required></div>
                    <div class="campo"><label>Medio de pago *</label>
                        <select class="input" name="medio_pago">@foreach (config('funeraria.medios_pago') as $k => $v)<option value="{{ $k }}" @selected(old('medio_pago') === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="campo"><label>Fecha *</label><input class="input" type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required></div>
                    <div class="campo"><label>Referencia</label><input class="input" name="referencia" value="{{ old('referencia') }}" placeholder="N° transferencia, cuota 2/3…"></div>
                    <div class="campo"><label>Comprobante adjunto (opcional)</label><input class="input" type="file" name="adjunto" accept=".pdf,.jpg,.jpeg,.png"></div>
                </div>
                <div class="aviso aviso-warn small mt-2 mb-0">El saldo pendiente del contrato se recalcula automáticamente y se emite un comprobante digital con número correlativo.</div>
                <div class="form-acciones"><button class="btn btn-primary">Registrar abono</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Últimos comprobantes</h2></div>
        <div class="card-body p0">
            <table class="tabla">
                <tbody>
                @foreach ($ultimos as $a)
                    <tr class="{{ $a->estado === 'anulado' ? 'muted' : '' }}">
                        <td class="small">@if ($a->numero_comprobante)<a href="{{ route('abonos.comprobante', $a) }}" target="_blank">{{ $a->numero_comprobante }}</a>@else<span class="muted">Sin comprobante</span>@endif<div class="xs muted">{{ $a->contrato->solicitud->cliente->nombre_familia }} ({{ $a->contrato->solicitud->cliente->nombre_contratante }}) · {{ fecha($a->fecha, 'd M') }}</div></td>
                        <td class="text-right small strong">{{ clp($a->monto) }}@if (in_array($a->estado, ['anulado', 'rechazado']))<div class="xs text-bad">{{ $a->estado }}</div>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
