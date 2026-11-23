@extends('layouts.publico')
@section('titulo', 'Convenios')
@section('contenido')
<section class="seccion-pub">
    <h2>Convenios y cuota mortuoria</h2>
    <p class="muted">Trabajamos con AFP, IPS, cajas de previsión y empresas. Gestionamos el cobro de la cuota mortuoria por usted: con el RUT del fallecido revisamos sus beneficios en pocos minutos.</p>
    <div class="grid-2 mt-2">
        @foreach ($convenios as $c)
            <div class="card">
                <div class="card-body flex" style="align-items:flex-start">
                    <div class="logo-convenio" style="background:{{ $c->color }};flex:none">@if ($c->logoUrl())<img src="{{ $c->logoUrl() }}" alt="{{ $c->institucion }}">@else{{ $c->sigla() }}@endif</div>
                    <div>
                        <h3>{{ $c->institucion }}</h3>
                        <div class="xs muted mb-1">{{ etiqueta('tipos_convenio', $c->tipo) }}</div>
                        <p class="small mt-0 mb-1">{{ $c->beneficio }} @if ($c->descuento_pct)<span class="badge b-ok">{{ $c->descuento_pct }}% dcto.</span>@endif</p>
                        @if ($c->documentos)<p class="xs muted mb-0">Documentos: {{ $c->documentos }}</p>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="aviso aviso-info mt-2">Los menores de 18 años no tienen cuota mortuoria. Los jubilados cuentan con {{ config('funeraria.cuota_mortuoria_uf') }} UF. Si el fallecimiento ocurre después de las 17:00, la verificación se realiza el día hábil siguiente.</div>
</section>
@endsection
