@extends('layouts.app')
@section('titulo', 'Contrato '.$contrato->numero)
@section('subtitulo', $contrato->solicitud->cliente->nombre_familia.' ('.$contrato->solicitud->cliente->nombre_contratante.') · '.$contrato->solicitud->nombreServicio())
@section('acciones')
    <a href="{{ route('abonos.index') }}" class="btn btn-outline">Volver</a>
    <a href="{{ route('contratos.pdf', $contrato) }}" target="_blank" class="btn btn-outline">Contrato PDF</a>
    @if ($contrato->estado === 'liquidado')
        <a href="{{ route('contratos.finiquito', $contrato) }}" target="_blank" class="btn btn-primary">Finiquito PDF</a>
    @elseif ($contrato->saldo_pendiente === 0)
        <form method="POST" action="{{ route('contratos.liquidar', $contrato) }}" data-confirmar="¿Liquidar el contrato? Se emitirá el comprobante de finiquito.">@csrf<button class="btn btn-ok">Liquidar contrato</button></form>
    @endif
@endsection

@section('contenido')
@php $c = $contrato; @endphp
<div class="grid-2-1">
    <div>
        <div class="card">
            <div class="card-header"><h2>Montos</h2>@if ($c->estado === 'liquidado')<x-estado valor="liquidado" />@else<x-estado :valor="$c->estadoPago()" />@endif</div>
            <div class="card-body p0">
                <table class="tabla">
                    <tr><td>Servicio ({{ $c->solicitud->nombreServicio() }})</td><td class="text-right">{{ clp($c->monto_servicio) }}</td></tr>
                    <tr><td>Urna {{ $c->solicitud->urna?->codigo }} {{ $c->solicitud->urna?->modelo }}</td><td class="text-right">{{ clp($c->monto_urna) }}</td></tr>
                    <tr><td>Descuento {{ $c->convenio ? '('.$c->convenio->institucion.')' : '' }}</td><td class="text-right">- {{ clp($c->descuento) }}</td></tr>
                    <tr><td class="strong">Total</td><td class="text-right strong">{{ clp($c->monto_total) }}</td></tr>
                    <tr><td>Abonado</td><td class="text-right text-ok">{{ clp($c->totalAbonado()) }}</td></tr>
                    <tr><td class="strong">Saldo pendiente</td><td class="text-right strong {{ $c->saldo_pendiente ? 'text-bad' : '' }}">{{ clp($c->saldo_pendiente) }}</td></tr>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Abonos</h2><a href="{{ route('abonos.index', ['contrato' => $c->id]) }}#registrar" class="btn btn-primary btn-xs">+ Abono</a></div>
            <div class="card-body p0 tabla-wrap">
                <table class="tabla">
                    <thead><tr><th>Comprobante</th><th>Fecha</th><th>Medio</th><th>Registrado por</th><th class="text-right">Monto</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($c->abonos as $a)
                        <tr>
                            <td>@if ($a->numero_comprobante)<a href="{{ route('abonos.comprobante', $a) }}" target="_blank">{{ $a->numero_comprobante }}</a>@else<span class="muted">—</span>@endif
                                @if ($a->adjunto_path)<div class="xs"><a href="{{ route('abonos.adjunto', $a) }}" target="_blank">ver adjunto</a></div>@endif</td>
                            <td>{{ fecha($a->fecha) }}</td>
                            <td>{{ $a->nombreMedio() }}<div class="xs muted">{{ $a->referencia }}</div></td>
                            <td class="small">{{ $a->usuario->nombre }}</td>
                            <td class="text-right">{{ clp($a->monto) }}</td>
                            <td>@if ($a->estado === 'pendiente')<x-estado valor="pendiente" texto="Por confirmar" /><div class="xs"><a href="{{ route('abonos.index') }}#por-confirmar">Revisar</a></div>@else<x-estado :valor="$a->estado" />@endif @if ($a->motivo_anulacion)<div class="xs muted">{{ $a->motivo_anulacion }}</div>@endif</td>
                            <td>
                                @if ($a->estado === 'registrado' && $c->estado === 'vigente')
                                    <form method="POST" action="{{ route('abonos.anular', $a) }}">@csrf<input type="hidden" name="motivo">
                                        <button class="btn btn-danger btn-xs" data-motivo="motivo" data-pregunta="Motivo de la anulación (queda en auditoría):">Anular</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted text-center">Sin abonos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><h2>Plan de pago</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('contratos.update', $c) }}">
                    @csrf @method('PUT')
                    <div class="campo mb-2"><label>Cuotas</label><input class="input" type="number" name="cuotas" min="1" max="24" value="{{ $c->cuotas }}"></div>
                    <div class="campo mb-2"><label>Vencimiento del saldo</label><input class="input" type="date" name="fecha_vencimiento" value="{{ $c->fecha_vencimiento->format('Y-m-d') }}"></div>
                    <label class="check"><input type="checkbox" name="cuota_mortuoria_en_tramite" value="1" @checked($c->cuota_mortuoria_en_tramite)> Cuota mortuoria AFP/IPS en trámite</label>
                    <div class="campo mt-2"><label>Cláusulas adicionales</label><textarea class="input" name="clausulas_adicionales">{{ $c->clausulas_adicionales }}</textarea></div>
                    <p class="small muted">Valor de cada cuota: {{ clp($c->valorCuota()) }}</p>
                    <button class="btn btn-outline" @disabled($c->estado === 'liquidado')>Guardar plan</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Historial del contrato</h2></div>
            <div class="card-body">
                <ul class="timeline">
                    @forelse ($modificaciones as $m)
                        <li><strong>{{ fecha($m->created_at, 'd M, H:i') }}</strong> · {{ $m->usuario?->nombre ?? 'Sistema' }}<br>{{ $m->descripcion }}</li>
                    @empty
                        <li class="muted">Sin registros.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
