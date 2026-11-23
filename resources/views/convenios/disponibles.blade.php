@extends('layouts.app')
@section('titulo', 'Convenios disponibles')
@section('subtitulo', 'Instituciones, beneficios y documentos habituales')
@section('acciones')
    <a href="{{ route('clientes.create') }}" class="btn btn-outline">← Registro de clientes</a>
@endsection

@section('contenido')
<div class="grid-2">
    @foreach ($convenios as $c)
        <div class="card">
            <div class="card-header">
                <div class="flex"><span class="logo-convenio" style="width:64px;height:40px;font-size:12px;background:{{ $c->color }}">@if ($c->logoUrl())<img src="{{ $c->logoUrl() }}" alt="">@else{{ $c->sigla() }}@endif</span>
                    <div><h2>{{ $c->institucion }}</h2><div class="xs muted">{{ etiqueta('tipos_convenio', $c->tipo) }}</div></div></div>
                <a href="{{ route('clientes.create', ['convenio' => $c->id]) }}" class="btn btn-primary btn-sm">Elegir</a>
            </div>
            <div class="card-body small">
                <p class="mt-0"><strong>Beneficio:</strong> {{ $c->beneficio }} @if ($c->descuento_pct)· <span class="badge b-ok">{{ $c->descuento_pct }}% de descuento</span>@endif</p>
                @if ($c->requisitos)<p><strong>Requisitos:</strong> {{ $c->requisitos }}</p>@endif
                @if ($c->documentos)<p class="mb-0"><strong>Documentos habituales:</strong> {{ $c->documentos }}</p>@endif
            </div>
        </div>
    @endforeach
</div>
@endsection
