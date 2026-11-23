<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">@include('pdf._estilos')</head>
<body>
@include('pdf._cabecera', ['titulo' => 'Convenio de servicios funerarios', 'numero' => 'CONV-'.str_pad($c->id, 3, '0', STR_PAD_LEFT)])

<p>En Temuco, a {{ ($c->vigencia_desde ?? now())->translatedFormat('d \d\e F \d\e Y') }}, entre <strong>{{ config('funeraria.nombre') }}</strong>, en adelante “la Funeraria”,
    y <strong>{{ $c->institucion }}</strong>, en adelante “la Institución”, se acuerda el siguiente convenio:</p>

<p><strong>PRIMERO. Objeto.</strong> La Funeraria prestará servicios funerarios a los afiliados, beneficiarios o trabajadores de la Institución y a sus familias,
    en las condiciones preferentes que se indican.</p>
<p><strong>SEGUNDO. Beneficio.</strong> {{ $c->beneficio }}.@if ($c->descuento_pct) Se aplicará un descuento de {{ $c->descuento_pct }}% sobre el valor total del servicio.@endif
    @if ($c->monto_cobertura) La Institución cubrirá hasta {{ clp($c->monto_cobertura) }} por servicio.@endif</p>
<p><strong>TERCERO. Requisitos.</strong> {{ $c->requisitos ?: 'Acreditar la calidad de afiliado o beneficiario de la Institución.' }}</p>
<p><strong>CUARTO. Documentación.</strong> {{ $c->documentos ?: 'Certificado de defunción, cédula de identidad del fallecido y del contratante.' }}</p>
<p><strong>QUINTO. Pago.</strong> La Funeraria gestionará ante la Institución el cobro del beneficio o cuota mortuoria que corresponda. La diferencia entre el valor del servicio y el beneficio será de cargo de la familia contratante.</p>
<p><strong>SEXTO. Vigencia.</strong> El presente convenio rige desde {{ $c->vigencia_desde?->format('d/m/Y') ?? 'la fecha de su firma' }}
    {{ $c->vigencia_hasta ? 'hasta el '.$c->vigencia_hasta->format('d/m/Y') : 'por tiempo indefinido' }}, pudiendo cualquiera de las partes ponerle término con 30 días de aviso.</p>
<p><strong>SÉPTIMO. Datos personales.</strong> Las partes tratarán los datos personales de los beneficiarios conforme a la Ley N.º 21.719.</p>
@if ($c->contacto)<p><strong>Contacto de la Institución:</strong> {{ $c->contacto }}</p>@endif

<table class="firma">
    <tr><td><div class="linea"></div>{{ $c->institucion }}</td><td><div class="linea"></div>{{ config('funeraria.nombre') }}</td></tr>
</table>
</body></html>
