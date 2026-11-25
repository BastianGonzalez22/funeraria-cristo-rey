@extends('layouts.app')
@section('titulo', 'Mis pagos')
@section('subtitulo', 'Cuánto se cobró, cuánto ha abonado y cuánto le falta · puede abonar desde aquí')

@section('contenido')
@if ($resumen)
    <div class="grid-3 mb-3">
        <div class="kpi"><div class="lbl">Total</div><div class="val">{{ clp($resumen['total']) }}</div></div>
        <div class="kpi"><div class="lbl">Abonado</div><div class="val text-ok">{{ clp($resumen['abonado']) }}</div></div>
        <div class="kpi"><div class="lbl">Saldo pendiente</div><div class="val {{ $resumen['saldo'] ? 'text-bad' : '' }}">{{ clp($resumen['saldo']) }}</div></div>
    </div>
@endif

@php $abiertos = $contratos->where('estado', 'vigente')->where('saldo_pendiente', '>', 0); @endphp
@if ($abiertos->isNotEmpty())
    <div class="grid-2-1" id="abonar">
        <div class="card">
            <div class="card-header"><h2>Realizar un abono</h2><span class="meta">Transferencia o depósito</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('portal.abonar') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-grid">
                        <div class="campo full"><label>Contrato *</label>
                            <select class="input" name="contrato_id" required>
                                @foreach ($abiertos as $c)
                                    <option value="{{ $c->id }}" @selected(old('contrato_id') == $c->id)>{{ $c->numero }} · {{ $c->solicitud->nombre_fallecido }} — saldo {{ clp($c->saldo_pendiente) }}{{ $c->cuotas > 1 ? ' (cuota sugerida '.clp($c->valorCuota()).')' : '' }}</option>
                                @endforeach
                            </select></div>
                        <div class="campo"><label>Monto abonado *</label>
                            <input class="input" type="number" name="monto" min="1000" step="1" value="{{ old('monto', $abiertos->first()->cuotas > 1 ? $abiertos->first()->valorCuota() : '') }}" required placeholder="Ej: 150000"></div>
                        <div class="campo"><label>Fecha de la transferencia *</label>
                            <input class="input" type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required></div>
                        <div class="campo"><label>Medio *</label>
                            <select class="input" name="medio_pago">
                                <option value="transferencia">Transferencia bancaria</option>
                                <option value="efectivo">Depósito en efectivo</option>
                                <option value="tarjeta">Pago con tarjeta (voucher)</option>
                            </select></div>
                        <div class="campo"><label>N° de operación (opcional)</label>
                            <input class="input" name="referencia" value="{{ old('referencia') }}" maxlength="120" placeholder="Ej: 4587221">
                            <div class="ayuda">Número que entrega su banco al hacer la transferencia (aparece en el comprobante). No es su número de cuenta.</div></div>
                        <div class="campo full"><label>Comprobante (foto o PDF) *</label>
                            <input class="input" type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="ayuda">Captura de pantalla de la transferencia o foto del depósito. Máx. 5 MB.</div></div>
                    </div>
                    <div class="form-acciones"><button class="btn btn-primary">Informar abono</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Datos para transferir</h2></div>
            <div class="card-body">
                <table class="tabla">
                    <tr><td class="muted">Banco</td><td class="strong">{{ $cuenta['banco'] }}</td></tr>
                    <tr><td class="muted">Tipo</td><td>{{ $cuenta['tipo'] }}</td></tr>
                    <tr><td class="muted">N° de cuenta</td><td class="strong">{{ $cuenta['numero'] }}</td></tr>
                    <tr><td class="muted">Titular</td><td>{{ $cuenta['titular'] }}</td></tr>
                    <tr><td class="muted">RUT</td><td>{{ $cuenta['rut'] }}</td></tr>
                    <tr><td class="muted">Correo</td><td class="small">{{ $cuenta['email'] }}</td></tr>
                </table>
                <button type="button" class="btn btn-outline btn-sm mt-2" data-copiar="{{ $cuenta['banco'] }} · {{ $cuenta['tipo'] }} {{ $cuenta['numero'] }} · {{ $cuenta['titular'] }} · RUT {{ $cuenta['rut'] }} · {{ $cuenta['email'] }}">Copiar datos</button>
                <p class="small muted mb-0 mt-2">La funeraria verifica cada abono y lo descuenta de su saldo. Cuando quede confirmado podrá descargar el comprobante.</p>
            </div>
        </div>
    </div>
@endif

@forelse ($contratos as $c)
    <div class="card">
        <div class="card-header">
            <h2>Contrato {{ $c->numero }} · {{ $c->solicitud->nombre_fallecido }}</h2>
            <div class="flex"><x-estado :valor="$c->estado === 'liquidado' ? 'liquidado' : $c->estadoPago()" />
                <a href="{{ route('portal.contrato.pdf', $c) }}" target="_blank" class="btn btn-outline btn-xs">Contrato PDF</a></div>
        </div>
        <div class="card-body p0">
            <table class="tabla">
                <thead><tr><th>Comprobante</th><th>Fecha</th><th>Medio</th><th class="text-right">Monto</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($c->abonos->where('estado', '!=', 'anulado') as $a)
                    <tr>
                        <td>{{ $a->numero_comprobante ?? '—' }}</td>
                        <td>{{ fecha($a->fecha) }}</td>
                        <td>{{ $a->nombreMedio() }}</td>
                        <td class="text-right">{{ clp($a->monto) }}</td>
                        <td>
                            @if ($a->estado === 'pendiente')
                                <x-estado valor="pendiente" texto="En verificación" />
                            @elseif ($a->estado === 'rechazado')
                                <x-estado valor="rechazado" /><div class="xs muted">{{ $a->motivo_anulacion }}</div>
                            @else
                                <x-estado valor="registrado" texto="Confirmado" />
                            @endif
                        </td>
                        <td>@if ($a->estado === 'registrado')<a href="{{ route('portal.comprobante', $a) }}" target="_blank" class="btn btn-outline btn-xs">Comprobante PDF</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted text-center">Aún no registra abonos.</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><td colspan="3">Saldo pendiente @if ($c->cuotas > 1 && $c->saldo_pendiente)({{ $c->cuotas }} cuotas de {{ clp($c->valorCuota()) }}, vence {{ fecha($c->fecha_vencimiento) }})@endif</td><td class="text-right">{{ clp($c->saldo_pendiente) }}</td><td colspan="2">
                    @if ($c->estado === 'vigente' && $c->saldo_pendiente > 0)<a href="#abonar" class="btn btn-primary btn-xs">Abonar</a>@endif</td></tr></tfoot>
            </table>
        </div>
    </div>
@empty
    <div class="card"><div class="card-body muted">Aún no tiene contratos. El contrato se genera cuando la funeraria aprueba su solicitud.</div></div>
@endforelse
<div class="aviso aviso-info small">También puede pagar en la oficina o mediante la cuota mortuoria de AFP/IPS (la funeraria la gestiona). Cada abono confirmado genera un comprobante digital.</div>
@endsection
