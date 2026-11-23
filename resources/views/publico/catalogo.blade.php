@extends('layouts.publico')
@section('titulo', 'Catálogo de urnas')
@section('contenido')
<section class="seccion-pub">
    <h2>Catálogo de urnas</h2>
    <p class="muted">Contamos con modelos estándar, infantiles, sobredimensionados, de cremación y personalizados (institucionales y deportivos). Consulte disponibilidad por WhatsApp enviando el código del modelo.</p>
    <form method="GET" class="filtros card card-body mt-2">
        <div class="campo"><label>Tipo</label>
            <select class="input" name="categoria"><option value="">Todos</option>
                @foreach (config('funeraria.categorias_urna') as $k => $v)<option value="{{ $k }}" @selected(($filtros['categoria'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="campo"><label>Color</label>
            <select class="input" name="color"><option value="">Todos</option>
                @foreach ($colores as $c)<option value="{{ $c }}" @selected(($filtros['color'] ?? '') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="campo"><label>Acabado</label>
            <select class="input" name="acabado"><option value="">Todos</option>
                @foreach (config('funeraria.acabados_urna') as $k => $v)<option value="{{ $k }}" @selected(($filtros['acabado'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <button class="btn btn-primary">Filtrar</button>
    </form>
    <div class="catalogo">
        @forelse ($urnas as $u)
            @include('urnas.partials.tarjeta', ['u' => $u, 'accion' => route('publico.urna', $u->codigo), 'textoAccion' => 'Ver detalle'])
        @empty
            <p class="muted">No hay modelos con ese filtro.</p>
        @endforelse
    </div>
</section>
@endsection
