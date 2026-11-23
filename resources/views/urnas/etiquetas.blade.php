@extends('layouts.app')
@section('titulo', 'Etiquetas de urnas')
@section('subtitulo', $unidades->count().' etiqueta(s) · pegue cada una en su urna')
@section('acciones')
    <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
@endsection

@section('contenido')
@if ($unidades->isEmpty())
    <div class="aviso aviso-info">No hay unidades para imprimir.</div>
@endif
<div class="etiquetas">
    @foreach ($unidades as $u)
        @php $urna = $u->urna; @endphp
        <div class="etiqueta">
            <div class="marca"><span>{{ config('funeraria.nombre') }}</span><span>{{ fecha($u->fecha_ingreso, 'd/m/Y') }}</span></div>
            <div class="cod">{{ $u->codigo }}</div>
            <div class="barras"></div>
            <div class="mod">{{ $urna->modelo }}</div>
            <div class="det"><b>{{ $urna->acabado ? etiqueta('acabados_urna', $urna->acabado) : etiqueta('categorias_urna', $urna->categoria) }}</b> · {{ $urna->material }} · {{ $urna->color }}</div>
            <div class="det">Medidas: <b>{{ $urna->medidasTexto() ?? '—' }}</b> · {{ etiqueta('categorias_urna', $urna->categoria) }}</div>
            @if ($u->solicitud)<div class="det mt-1">Reservada: <b>{{ $u->solicitud->cliente->nombre_familia }}</b> ({{ $u->solicitud->cliente->nombre_contratante }})</div>@endif
        </div>
    @endforeach
</div>
@endsection
