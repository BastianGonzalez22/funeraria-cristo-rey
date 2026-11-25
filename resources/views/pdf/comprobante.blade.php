<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">@include('pdf._estilos')</head>
<body>
@php
    $c = $abono->contrato; $s = $c->solicitud;
    // Si la familia informó el abono desde el portal, quien lo recibe es el funcionario que lo confirmó
    $receptor = $abono->confirmadoPor ?? $abono->usuario;
@endphp
@include('pdf._cabecera', ['titulo' => 'Comprobante de pago', 'numero' => $abono->numero_comprobante])

@if ($abono->estado === 'anulado')
    <p style="color:#b3261e;font-weight:bold;font-size:14px">COMPROBANTE ANULADO — {{ $abono->motivo_anulacion }}</p>
@endif

<table>
    <tr><td class="k">Recibimos de</td><td>{{ $s->cliente->nombre_contratante }} ({{ $s->cliente->nombre_familia }})</td></tr>
    <tr><td class="k">RUT</td><td>{{ $s->cliente->rutFormateado() }}</td></tr>
    <tr><td class="k">La suma de</td><td style="font-size:16px;font-weight:bold">{{ clp($abono->monto) }}</td></tr>
    <tr><td class="k">Por concepto de</td><td>Abono al contrato {{ $c->numero }} — servicio funerario de {{ $s->nombre_fallecido }}</td></tr>
    <tr><td class="k">Medio de pago</td><td>{{ $abono->nombreMedio() }} {{ $abono->referencia ? '· '.$abono->referencia : '' }}</td></tr>
    <tr><td class="k">Fecha de pago</td><td>{{ $abono->fecha->format('d/m/Y') }}</td></tr>
    <tr><td class="k">Recibido por</td><td>{{ $receptor?->nombre }}</td></tr>
</table>

<h3>Estado de la cuenta</h3>
<table class="grid">
    <tr><td>Total del contrato</td><td class="r">{{ clp($c->monto_total) }}</td></tr>
    <tr><td>Total abonado</td><td class="r">{{ clp($c->totalAbonado()) }}</td></tr>
    <tr class="tot"><td>Saldo pendiente</td><td class="r">{{ clp($c->saldo_pendiente) }}</td></tr>
</table>

@if ($abono->confirmado_at && $abono->esDeclaradoPorFamilia())
    <p style="font-size:10px;color:#555;margin-top:18px">Abono informado por la familia desde el portal y verificado por {{ $abono->confirmadoPor?->nombre }} el {{ $abono->confirmado_at->format('d/m/Y H:i') }}.</p>
@endif

<table class="firma">
    <tr><td></td><td>@include('pdf._firma', ['sucursal' => $s->sucursal?->nombre, 'firmante' => $receptor?->nombre])</td></tr>
</table>
</body></html>
