@extends('layouts.app')
@section('titulo', $urna->codigo.' · '.$urna->modelo)
@section('subtitulo', 'Ficha del modelo, fotos, unidades en bodega y movimientos')
@section('acciones')
    <a href="{{ route('urnas.index') }}" class="btn btn-outline">Volver</a>
    <a href="{{ route('unidades.etiquetas', ['urna' => $urna->id]) }}" target="_blank" class="btn btn-outline">Imprimir etiquetas</a>
    <a href="{{ route('urnas.fotos', $urna) }}" class="btn btn-outline">Administrar fotos</a>
    <a href="{{ route('urnas.edit', $urna) }}" class="btn btn-primary">Editar</a>
@endsection

@section('contenido')
<div class="grid-1-2">
    <div>
        @include('urnas.partials.galeria', ['urna' => $urna, 'id' => 'galeria-modelo'])
        <div class="flex between mt-1"><span class="small muted">{{ $urna->imagenes->count() }} foto(s)</span><a href="{{ route('urnas.fotos', $urna) }}" class="small">Agregar, cambiar o eliminar fotos →</a></div>
        <div class="card mt-2">
            <div class="card-body">
                <div class="flex between"><span class="small muted">{{ etiqueta('categorias_urna', $urna->categoria) }}</span><x-estado :valor="$urna->estado" /></div>
                <div style="font-family:var(--serif);font-size:26px;font-weight:600;margin:4px 0">{{ clp($urna->precio) }}</div>
                <table class="tabla">
                    <tr><td class="muted">Medidas (largo × ancho × alto)</td><td class="strong">{{ $urna->medidasTexto() ?? '—' }}</td></tr>
                    <tr><td class="muted">Material</td><td>{{ $urna->material ?: '—' }}</td></tr>
                    <tr><td class="muted">Color</td><td>{{ $urna->color ?: '—' }}</td></tr>
                    <tr><td class="muted">Acabado</td><td>{{ $urna->acabado ? etiqueta('acabados_urna', $urna->acabado) : '—' }}</td></tr>
                    <tr><td class="muted">Manillas</td><td>{{ $urna->manillas ?: '—' }}</td></tr>
                    <tr><td class="muted">Proveedor</td><td>{{ $urna->proveedor?->nombre ?? '—' }}</td></tr>
                    <tr><td class="muted">Catálogo público</td><td>{{ $urna->publica ? 'Visible' : 'Oculta' }}{{ $urna->bajo_pedido ? ' · fabricación a pedido' : '' }}</td></tr>
                </table>
                @if ($urna->descripcion)<p class="small mb-0">{{ $urna->descripcion }}</p>@endif
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><h2>Unidades de este modelo</h2><span class="meta">{{ $unidades->where('estado', 'disponible')->count() }} disponibles · {{ $unidades->where('estado', 'reservada')->count() }} reservadas · {{ $unidades->where('estado', 'vendida')->count() }} vendidas</span></div>
            <div class="card-body p0 tabla-wrap">
                <table class="tabla">
                    <thead><tr><th>Código</th><th>Ingreso</th><th>Estado</th><th>Familia</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($unidades as $un)
                        <tr>
                            <td><a href="{{ route('unidades.show', $un) }}" class="strong" style="font-family:monospace">{{ $un->codigo }}</a></td>
                            <td class="small">{{ fecha($un->fecha_ingreso) }}</td>
                            <td><x-estado :valor="$un->estado" /></td>
                            <td>@if ($un->solicitud)<x-familia :cliente="$un->solicitud->cliente" :url="route('solicitudes.show', $un->solicitud)" />@else<span class="muted">—</span>@endif</td>
                            <td class="nowrap"><a href="{{ route('unidades.show', $un) }}" class="btn btn-outline btn-xs">{{ $un->estado === 'disponible' ? 'Reservar' : 'Ver' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted text-center">Sin unidades. Regístrelas en Reposición.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Movimientos de bodega</h2></div>
            <div class="card-body p0 tabla-wrap">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Cant.</th><th>Detalle</th><th>Usuario</th></tr></thead>
                    <tbody>
                    @forelse ($urna->movimientos->take(30) as $m)
                        <tr><td class="nowrap small">{{ fecha($m->created_at, 'd M y, H:i') }}</td><td><span class="badge b-neutral">{{ $m->tipo }}</span></td><td>{{ $m->cantidad > 0 ? '+' : '' }}{{ $m->cantidad }}</td>
                            <td class="small">{{ $m->referencia }} {{ $m->proveedor ? '· '.$m->proveedor->nombre : '' }} {{ $m->costo_total ? '· '.clp($m->costo_total) : '' }}</td><td class="small">{{ $m->usuario?->nombre }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="muted text-center">Sin movimientos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
