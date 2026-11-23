<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">@include('pdf._estilos')</head>
<body>
@php $s = $c->solicitud; @endphp
@include('pdf._cabecera', ['titulo' => 'Comprobante de finiquito', 'numero' => $c->numero])

<p style="text-align:center;margin:20px 0"><span class="sello">Contrato liquidado</span></p>

<p>{{ config('funeraria.nombre') }} certifica que el contrato <strong>{{ $c->numero }}</strong>, suscrito por
    <strong>{{ $s->cliente->nombre_contratante }}</strong>, RUT {{ $s->cliente->rutFormateado() }}, por el servicio funerario de
    <strong>{{ $s->nombre_fallecido }}</strong> realizado el {{ $s->fecha_hora->format('d/m/Y') }}, por un total de
    <strong>{{ clp($c->monto_total) }}</strong>, se encuentra íntegramente pagado, sin saldo pendiente, a la fecha {{ $c->liquidado_at?->format('d/m/Y') }}.</p>

<h3>Detalle de pagos</h3>
<table class="grid">
    <tr><th>Comprobante</th><th>Fecha</th><th>Medio</th><th class="r">Monto</th></tr>
    @foreach ($c->abonos->where('estado', 'registrado') as $a)
        <tr><td>{{ $a->numero_comprobante }}</td><td>{{ $a->fecha->format('d/m/Y') }}</td><td>{{ $a->nombreMedio() }}</td><td class="r">{{ clp($a->monto) }}</td></tr>
    @endforeach
    <tr class="tot"><td colspan="3">Total pagado</td><td class="r">{{ clp($c->totalAbonado()) }}</td></tr>
</table>

<table class="firma">
    <tr><td><div class="linea"></div>{{ $s->cliente->nombre_contratante }}</td><td><div class="linea"></div>{{ config('funeraria.nombre') }}</td></tr>
</table>
</body></html>
