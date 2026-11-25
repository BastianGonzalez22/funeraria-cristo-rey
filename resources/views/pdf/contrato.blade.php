<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">@include('pdf._estilos')</head>
<body>
@php $s = $c->solicitud; $cl = $s->cliente; @endphp
@include('pdf._cabecera', ['titulo' => 'Contrato de servicio funerario', 'numero' => $c->numero])

<h3>Contratante</h3>
<table>
    <tr><td class="k">Nombre</td><td>{{ $cl->nombre_contratante }}</td><td class="k">RUT</td><td>{{ $cl->rutFormateado() }}</td></tr>
    <tr><td class="k">Domicilio</td><td>{{ $cl->direccion }} {{ $cl->comuna }}</td><td class="k">Teléfono</td><td>{{ $cl->telefono }}</td></tr>
</table>

<h3>Fallecido</h3>
<table>
    <tr><td class="k">Nombre</td><td>{{ $s->nombre_fallecido }}</td><td class="k">RUT</td><td>{{ $s->rutFallecidoFormateado() }}</td></tr>
    <tr><td class="k">Fecha defunción</td><td>{{ $s->fecha_defuncion?->format('d/m/Y') ?? '—' }}</td><td class="k">Destino final</td><td>{{ etiqueta('destinos', $s->destino_final) }}</td></tr>
</table>

<h3>Servicio contratado</h3>
<table>
    <tr><td class="k">Tipo de servicio</td><td>{{ $s->nombreServicio() }}</td><td class="k">Fecha y hora</td><td>{{ $s->fecha_hora->format('d/m/Y H:i') }}</td></tr>
    <tr><td class="k">Velatorio</td><td>{{ $s->lugar_velatorio ?: '—' }}</td><td class="k">Sepultura / cremación</td><td>{{ $s->cementerio_crematorio ?: '—' }}</td></tr>
    <tr><td class="k">Urna</td><td>{{ $s->urna?->codigo }} {{ $s->urna?->modelo }}</td><td class="k">Sucursal</td><td>{{ $s->sucursal->nombre }}</td></tr>
    <tr><td class="k">Incluye</td><td colspan="3">{{ implode(', ', array_slice(config('funeraria.elementos'), 0, 4)) }}.</td></tr>
</table>

<h3>Valores</h3>
<table class="grid">
    <tr><td>Servicio {{ $s->nombreServicio() }}</td><td class="r">{{ clp($c->monto_servicio) }}</td></tr>
    <tr><td>Urna {{ $s->urna?->modelo }}</td><td class="r">{{ clp($c->monto_urna) }}</td></tr>
    @if ($c->descuento)<tr><td>Descuento {{ $c->convenio ? 'convenio '.$c->convenio->institucion : '' }}</td><td class="r">- {{ clp($c->descuento) }}</td></tr>@endif
    <tr class="tot"><td>Total</td><td class="r">{{ clp($c->monto_total) }}</td></tr>
    <tr><td>Abonado a la fecha</td><td class="r">{{ clp($c->totalAbonado()) }}</td></tr>
    <tr class="tot"><td>Saldo pendiente</td><td class="r">{{ clp($c->saldo_pendiente) }}</td></tr>
</table>

<h3>Condiciones</h3>
<p>1. Para aceptar la solicitud el contratante abona como mínimo el {{ config('funeraria.abono_minimo_pct') }}% del valor total ({{ clp($c->abono_minimo) }}).
    El saldo se pagará en {{ $c->cuotas }} cuota(s){{ $c->cuotas > 1 ? ' de '.clp($c->valorCuota()) : '' }} con vencimiento el {{ $c->fecha_vencimiento->format('d/m/Y') }}.
    @if ($c->cuota_mortuoria_en_tramite) Parte del pago se realizará mediante la cuota mortuoria de AFP/IPS, actualmente en trámite; la diferencia es de cargo del contratante. @endif</p>
<p>2. Cada pago se respalda con un comprobante digital numerado. El contratante puede consultar su saldo en el portal web de la funeraria.</p>
<p>3. Las modificaciones de horario, recorrido u otros datos solicitadas por la familia quedan registradas en el sistema con fecha y responsable.</p>
<p>4. Los datos personales y el certificado de defunción se utilizan exclusivamente para la prestación del servicio y los trámites ante SEREMI de Salud, Registro Civil e instituciones previsionales.</p>
@if ($c->clausulas_adicionales)<p>5. {{ $c->clausulas_adicionales }}</p>@endif

<table class="firma">
    <tr><td><div class="linea"></div>{{ $cl->nombre_contratante }}<br>Contratante</td><td>@include('pdf._firma', ['firmante' => 'Representante'])</td></tr>
</table>
</body></html>
