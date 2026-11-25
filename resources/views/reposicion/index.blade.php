@extends('layouts.app')
@section('titulo', 'Reposición de urnas')
@section('subtitulo', 'Qué falta en bodega, llegada del proveedor y etiquetas de cada urna')
@section('acciones')
    <a href="{{ route('urnas.index') }}" class="btn btn-outline">Ver bodega</a>
@endsection

@section('contenido')
@if (request('nuevas'))
    <div class="aviso aviso-ok">Las urnas nuevas ya tienen su código.
        <a href="{{ route('unidades.etiquetas', ['ids' => request('nuevas')]) }}" target="_blank" class="strong">Imprimir sus etiquetas →</a></div>
@endif

<div class="grid-4 mb-3">
    <div class="kpi"><div class="lbl">En bodega</div><div class="val">{{ $ind['en_bodega'] }}<span class="small muted"> / {{ $ind['capacidad'] }}</span></div><div class="det muted">{{ $ind['libre'] }} espacio(s) libre(s)</div></div>
    <div class="kpi"><div class="lbl">Disponibles</div><div class="val text-ok">{{ $ind['disponibles'] }}</div></div>
    <div class="kpi"><div class="lbl">Modelos por reponer</div><div class="val {{ $porReponer->where('estado', 'reponer')->count() ? 'text-bad' : '' }}">{{ $porReponer->where('estado', 'reponer')->count() }}</div><div class="det muted">{{ $porReponer->where('estado', 'en_umbral')->count() }} en el umbral</div></div>
    <div class="kpi"><div class="lbl">Personalizadas</div><div class="val">{{ $ind['personalizadas'] }}</div><div class="det muted">{{ $ind['modelos_pedido'] }} modelo(s) a pedido</div></div>
</div>

<div class="grid-2-1">
    <div class="card">
        <div class="card-header"><h2>Pendiente de reponer</h2><span class="meta">Sugerido: completar hasta el doble del mínimo</span></div>
        <div class="card-body p0 tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Modelo</th><th>Disponibles</th><th>Mínimo</th><th>Sugerido</th><th>Proveedor</th><th></th></tr></thead>
                <tbody>
                @forelse ($porReponer as $u)
                    <tr>
                        <td><span class="strong">{{ $u->codigo }} {{ $u->modelo }}</span><div class="xs muted">{{ etiqueta('categorias_urna', $u->categoria) }} · {{ $u->medidasTexto() }}</div></td>
                        <td><span class="{{ $u->estado === 'reponer' ? 'text-bad' : 'text-warn' }} strong">{{ $u->disponible() }}</span></td>
                        <td>{{ $u->stock_minimo }}</td>
                        <td class="strong">{{ $u->sugerido }}</td>
                        <td class="small">{{ $u->proveedor?->nombre ?? '—' }}<div class="xs muted">{{ $u->proveedor?->periodicidad }}</div></td>
                        <td><a href="#registrar" class="btn btn-outline btn-xs" onclick="document.querySelector('[name=urna_id]').value='{{ $u->id }}';document.querySelector('[name=cantidad]').value='{{ min($u->sugerido, max(1, $ind['libre'])) }}';@if($u->proveedor_id)document.querySelector('[name=proveedor_id]').value='{{ $u->proveedor_id }}';@endif">Reponer</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted text-center">Todos los modelos están sobre el mínimo. 👍</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" id="registrar">
        <div class="card-header"><h2>Registrar llegada</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('urnas.compra') }}">
                @csrf
                <div class="campo mb-2"><label>Modelo *</label>
                    <select class="input" name="urna_id" required>
                        @foreach ($urnas as $u)<option value="{{ $u->id }}" @selected(old('urna_id', request('urna')) == $u->id)>{{ $u->codigo }} {{ $u->modelo }} ({{ $u->disponible() }} disp.{{ $u->bajo_pedido ? ' · personalizada / a pedido' : '' }})</option>@endforeach
                    </select></div>
                <div class="campo mb-2"><label>Proveedor</label>
                    <select class="input" name="proveedor_id"><option value="">—</option>
                        @foreach ($proveedores as $p)<option value="{{ $p->id }}" @selected(old('proveedor_id') == $p->id)>{{ $p->nombre }} — {{ $p->modalidad }}</option>@endforeach
                    </select></div>
                <div class="campo mb-2"><label>Cantidad que llegó *</label>
                    <input class="input" type="number" name="cantidad" min="1" max="{{ max(1, $ind['libre']) }}" value="{{ old('cantidad', min(5, max(1, $ind['libre']))) }}" required>
                    <div class="ayuda">Máximo según espacio libre: {{ $ind['libre'] }}.</div></div>
                @if ($verPrecios)
                    <div class="campo mb-2"><label>Costo total (opcional)</label><input class="input" type="number" name="costo_total" min="0" step="1000" value="{{ old('costo_total') }}"></div>
                @endif
                <button class="btn btn-primary" style="width:100%;justify-content:center" @disabled($ind['libre'] <= 0)>Registrar y generar códigos</button>
            </form>
            <p class="small muted mt-2 mb-0">Cada urna recibe un código único y su etiqueta (código, modelo, acabado y medidas). Imprímalas y péguelas antes de guardarlas.</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Últimas reposiciones</h2></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Modelo</th><th>Cantidad</th><th>Códigos</th><th>Proveedor</th><th>Registró</th></tr></thead>
            <tbody>
            @forelse ($historial as $m)
                <tr><td class="nowrap small">{{ fecha($m->created_at, 'd M y, H:i') }}</td><td>{{ $m->urna->codigo }} {{ $m->urna->modelo }}</td><td>+{{ $m->cantidad }}</td>
                    <td class="small" style="font-family:monospace">{{ str_replace('Reposición: ', '', $m->referencia) }}</td><td class="small">{{ $m->proveedor?->nombre ?? '—' }}</td><td class="small">{{ $m->usuario?->nombre }}</td></tr>
            @empty
                <tr><td colspan="6" class="muted text-center">Aún no hay reposiciones.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
