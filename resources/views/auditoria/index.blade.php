@extends('layouts.app')
@section('titulo', 'Auditoría')
@section('subtitulo', 'Registro inalterable (solo inserción) de acciones sobre datos personales, contratos, pagos y stock')

@section('contenido')
<div class="card">
    <div class="card-header">
        <h2>Eventos</h2>
        <form method="GET" class="filtros">
            <select class="input" name="evento" style="width:200px"><option value="">Todos los eventos</option>
                @foreach ($eventos as $e)<option value="{{ $e }}" @selected(request('evento') === $e)>{{ $e }}</option>@endforeach</select>
            <select class="input" name="usuario" style="width:170px"><option value="">Todos los usuarios</option>
                @foreach ($usuarios as $u)<option value="{{ $u->id }}" @selected(request('usuario') == $u->id)>{{ $u->nombre }}</option>@endforeach</select>
            <input class="input" type="date" name="desde" value="{{ request('desde') }}" style="width:150px">
            <input class="input" type="date" name="hasta" value="{{ request('hasta') }}" style="width:150px">
            <button class="btn btn-outline btn-sm">Filtrar</button>
        </form>
    </div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Descripción</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($registros as $r)
                <tr>
                    <td class="nowrap small">{{ $r->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="small">{{ $r->usuario?->nombre ?? 'Sistema / público' }}</td>
                    <td><span class="badge b-neutral">{{ $r->evento }}</span></td>
                    <td class="small">{{ $r->descripcion }}</td>
                    <td class="xs muted" style="max-width:280px">
                        @if ($r->detalle)
                            @foreach ($r->detalle as $k => $v){{ $k }}: {{ is_array($v) ? (($v['antes'] ?? '').' → '.($v['despues'] ?? json_encode($v, JSON_UNESCAPED_UNICODE))) : $v }}@if (! $loop->last) · @endif @endforeach
                        @endif
                    </td>
                    <td class="xs muted">{{ $r->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted text-center">Sin eventos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $registros->links() }}
</div>
@endsection
