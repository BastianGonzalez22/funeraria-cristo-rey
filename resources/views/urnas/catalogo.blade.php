@extends('layouts.app')
@section('titulo', 'Catálogo de urnas')
@section('subtitulo', 'Modelos con precio, disponibilidad y filtros por tipo, color y acabado')
@section('acciones')
    <a href="{{ route('clientes.create') }}" class="btn btn-outline">← Registro</a>
    <a href="{{ route('publico.catalogo') }}" target="_blank" class="btn btn-outline">Vista pública</a>
@endsection

@section('contenido')
<div class="aviso aviso-info small">Al seleccionar una urna se abre el registro con esa opción ya elegida. Las urnas <strong>reservadas o vendidas no aparecen</strong>: solo se muestran los modelos con unidades disponibles (o personalizadas a pedido).
    <a href="{{ route('urnas.catalogo', array_merge(request()->query(), ['todas' => $todas ? null : 1])) }}">{{ $todas ? 'Ocultar agotadas' : 'Ver también los modelos agotados' }}</a></div>
<div class="card">
    <div class="card-body">
        <form method="GET" class="filtros">
            <input type="hidden" name="destino" value="{{ $destino }}">
            @if ($todas)<input type="hidden" name="todas" value="1">@endif
            <div class="campo"><label>Buscar modelo</label><input class="input" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Ej: roble, infantil"></div>
            <div class="campo"><label>Tipo</label>
                <select class="input" name="categoria"><option value="">Todos</option>
                    @foreach (config('funeraria.categorias_urna') as $k => $v)<option value="{{ $k }}" @selected(($filtros['categoria'] ?? '') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="campo"><label>Color</label>
                <select class="input" name="color"><option value="">Todos</option>
                    @foreach ($colores as $c)<option value="{{ $c }}" @selected(($filtros['color'] ?? '') === $c)>{{ $c }}</option>@endforeach
                </select></div>
            <div class="campo"><label>Acabado</label>
                <select class="input" name="acabado"><option value="">Todos</option>
                    @foreach (config('funeraria.acabados_urna') as $k => $v)<option value="{{ $k }}" @selected(($filtros['acabado'] ?? '') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <button class="btn btn-primary">Filtrar</button>
        </form>
        <p class="small muted mb-0 mt-1">{{ $urnas->count() }} modelo(s)</p>
    </div>
</div>

<div class="catalogo">
    @foreach ($urnas as $u)
        @include('urnas.partials.tarjeta', [
            'u' => $u,
            'mostrarStock' => true,
            'accion' => $u->puedeAsociarse()
                ? ($destino === 'solicitud' ? route('solicitudes.create', ['urna' => $u->id]) : route('clientes.create', ['urna' => $u->id]))
                : null,
            'textoAccion' => 'Elegir esta urna',
        ])
    @endforeach
</div>
@endsection
