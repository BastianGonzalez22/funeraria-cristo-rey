@extends('layouts.app')
@section('titulo', $verPrecios ? 'Stock de Urnas' : 'Bodega de urnas')
@section('subtitulo', 'Cada urna tiene su propio código · se reserva con abono y se bloquea')
@section('acciones')
    <a href="{{ route('urnas.exportar') }}" class="btn btn-outline">Exportar (Excel)</a>
    @if (auth()->user()->puede('reposicion'))<a href="{{ route('reposicion.index') }}" class="btn btn-outline">Reposición</a>@endif
    @if ($verPrecios)
        <a href="{{ route('urnas.catalogo') }}" class="btn btn-outline">Catálogo</a>
        <a href="{{ route('urnas.create') }}" class="btn btn-primary">+ Nuevo modelo</a>
    @endif
@endsection

@section('contenido')
<div class="grid-4 mb-3">
    <div class="kpi"><div class="lbl">Urnas en bodega</div><div class="val">{{ $ind['en_bodega'] }}<span class="small muted"> / {{ $ind['capacidad'] }}</span></div>
        <div class="barra {{ $ind['libre'] <= 3 ? 'bad' : '' }} mt-1"><span style="width: {{ min(100, round($ind['en_bodega'] / max(1, $ind['capacidad']) * 100)) }}%"></span></div>
        <div class="det muted">{{ $ind['libre'] }} espacio(s) libre(s)</div></div>
    <div class="kpi"><div class="lbl">Disponibles</div><div class="val text-ok">{{ $ind['disponibles'] }}</div><div class="det muted">Se pueden ofrecer</div></div>
    <div class="kpi"><div class="lbl">Reservadas</div><div class="val text-warn">{{ $ind['reservadas'] }}</div><div class="det muted">Bloqueadas para una familia</div></div>
    <div class="kpi"><div class="lbl">Personalizadas</div><div class="val">{{ $ind['personalizadas'] }}</div><div class="det muted">En bodega · {{ $ind['modelos_pedido'] }} modelo(s) a pedido · {{ $ind['vendidas_mes'] }} vendidas este mes</div></div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Modelos</h2>
        <form method="GET" class="filtros">
            <input class="input" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Código o modelo" style="width:150px">
            <select class="input" name="categoria" style="width:160px">
                <option value="">Todas las categorías</option>
                @foreach (config('funeraria.categorias_urna') as $k => $v)<option value="{{ $k }}" @selected(($filtros['categoria'] ?? '') === $k)>{{ $v }} ({{ $ind['por_categoria'][$k] ?? 0 }})</option>@endforeach
            </select>
            <select class="input" name="acabado" style="width:130px">
                <option value="">Acabado</option>
                @foreach (config('funeraria.acabados_urna') as $k => $v)<option value="{{ $k }}" @selected(($filtros['acabado'] ?? '') === $k)>{{ $v }}</option>@endforeach
            </select>
            <button class="btn btn-outline btn-sm">Filtrar</button>
        </form>
    </div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Código</th><th>Modelo</th><th>Categoría</th><th>Medidas</th>@if ($verPrecios)<th>Precio</th>@endif<th>Disponibles</th><th>Reservadas</th><th>Vendidas</th><th>Mínimo</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($urnas as $u)
                <tr>
                    <td class="small muted">{{ $u->codigo }}</td>
                    <td><span class="strong">@if ($verPrecios)<a href="{{ route('urnas.show', $u) }}">{{ $u->modelo }}</a>@else{{ $u->modelo }}@endif</span>
                        <div class="xs muted">{{ collect([$u->material, $u->color, $u->acabado ? etiqueta('acabados_urna', $u->acabado) : null])->filter()->implode(' · ') }}</div></td>
                    <td>{{ etiqueta('categorias_urna', $u->categoria) }}</td>
                    <td class="small nowrap">{{ $u->medidasTexto() ?? '—' }}</td>
                    @if ($verPrecios)<td class="nowrap">{{ clp($u->precio) }}</td>@endif
                    <td style="min-width:110px">
                        <div class="small strong">{{ $u->disponibles_count }}</div>
                        @unless ($u->bajo_pedido)
                            <div class="barra {{ $u->estado === 'reponer' ? 'bad' : ($u->estado === 'en_umbral' ? 'warn' : '') }}"><span style="width: {{ max(4, $u->porcentajeStock()) }}%"></span></div>
                        @endunless
                    </td>
                    <td>{{ $u->reservadas_count ?: '—' }}</td>
                    <td>{{ $u->vendidas_count ?: '—' }}</td>
                    <td>{{ $u->bajo_pedido ? 'a pedido' : $u->stock_minimo }}</td>
                    <td><x-estado :valor="$u->estado" /></td>
                    <td class="nowrap">
                        <a href="{{ route('unidades.etiquetas', ['urna' => $u->id]) }}" target="_blank" class="btn btn-outline btn-xs" title="Imprimir etiquetas">Etiquetas</a>
                        @if ($verPrecios)<a href="{{ route('urnas.fotos', $u) }}" class="btn btn-outline btn-xs">Fotos ({{ $u->imagenes->count() }})</a> <a href="{{ route('urnas.edit', $u) }}" class="btn btn-outline btn-xs">Editar</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="muted text-center">No hay urnas con ese filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card" id="unidades">
    <div class="tabs">
        @foreach (['' => 'En bodega', 'disponible' => 'Disponibles', 'reservada' => 'Reservadas', 'vendida' => 'Vendidas', 'baja' => 'De baja'] as $k => $v)
            <a href="{{ route('urnas.index', array_merge($filtros, ['estado_unidad' => $k ?: null])) }}#unidades" class="{{ (string) $estadoUnidad === $k ? 'activo' : '' }}">{{ $v }}</a>
        @endforeach
    </div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Código de la urna</th><th>Modelo</th><th>Ingreso</th><th>Ubicación</th><th>Estado</th><th>Familia</th><th></th></tr></thead>
            <tbody>
            @forelse ($unidades as $un)
                <tr>
                    <td><a href="{{ route('unidades.show', $un) }}" class="strong" style="font-family:monospace;font-size:14px">{{ $un->codigo }}</a></td>
                    <td>{{ $un->urna->modelo }}<div class="xs muted">{{ $un->urna->acabado ? etiqueta('acabados_urna', $un->urna->acabado) : '' }} {{ $un->urna->medidasTexto() }}</div></td>
                    <td class="small">{{ fecha($un->fecha_ingreso) }}</td>
                    <td class="small">{{ $un->ubicacion }}</td>
                    <td><x-estado :valor="$un->estado" /></td>
                    <td>@if ($un->solicitud)<x-familia :cliente="$un->solicitud->cliente" />@else<span class="muted">—</span>@endif</td>
                    <td class="nowrap">
                        <a href="{{ route('unidades.show', $un) }}" class="btn btn-outline btn-xs">{{ $verPrecios && $un->estado === 'disponible' ? 'Reservar' : 'Ver' }}</a>
                        <a href="{{ route('unidades.etiquetas', ['ids' => $un->id]) }}" target="_blank" class="btn btn-outline btn-xs">Etiqueta</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted text-center">No hay unidades en este estado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $unidades->fragment('unidades')->links() }}
</div>

<div class="aviso aviso-info small">
    <strong>Cómo funciona:</strong> cada urna que llega a bodega recibe un código único (por ejemplo <code>U-102-0007</code>) y su etiqueta.
    Al aprobar una solicitud con su abono, la urna queda <strong>reservada</strong> para esa familia: se bloquea y deja de aparecer en el catálogo.
    Con <strong>Comprar</strong> la reserva pasa a <strong>vendida</strong>. Si la familia quiere otra, se cambia y la anterior vuelve a estar disponible.
    Las personalizadas (Carabineros, Deportes Temuco) se encargan a pedido si no hay una en bodega.
</div>
@endsection
