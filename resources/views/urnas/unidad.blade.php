@extends('layouts.app')
@section('titulo', 'Urna '.$u->codigo)
@section('subtitulo', $u->urna->modelo.' · '.$u->nombreEstado())
@section('acciones')
    <a href="{{ route('urnas.index') }}#unidades" class="btn btn-outline">Volver</a>
    <a href="{{ route('unidades.etiquetas', ['ids' => $u->id]) }}" target="_blank" class="btn btn-outline">Imprimir etiqueta</a>
@endsection

@section('contenido')
@php $gestion = auth()->user()->puede('urnas'); $urna = $u->urna; @endphp
<div class="grid-2">
    <div>
        @include('urnas.partials.galeria', ['urna' => $urna, 'id' => 'galeria-unidad'])

        <div class="etiqueta mt-2">
            <div class="marca"><span>{{ config('funeraria.nombre') }}</span><span>{{ fecha($u->fecha_ingreso, 'd/m/Y') }}</span></div>
            <div class="cod">{{ $u->codigo }}</div>
            <div class="barras"></div>
            <div class="mod">{{ $urna->modelo }}</div>
            <div class="det"><b>{{ $urna->acabado ? etiqueta('acabados_urna', $urna->acabado) : etiqueta('categorias_urna', $urna->categoria) }}</b> · {{ $urna->material }} · {{ $urna->color }}</div>
            <div class="det">Medidas: <b>{{ $urna->medidasTexto() ?? '—' }}</b> · {{ etiqueta('categorias_urna', $urna->categoria) }}</div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><h2>Estado de esta urna</h2><x-estado :valor="$u->estado" /></div>
            <div class="card-body">
                <div class="ficha" style="grid-template-columns:1fr 1fr">
                    <div class="dato"><div class="k">Modelo</div><div class="v">{{ $urna->codigo }} · {{ $urna->modelo }}</div></div>
                    @if ($gestion)<div class="dato"><div class="k">Precio de venta</div><div class="v">{{ clp($urna->precio) }}</div></div>@endif
                    <div class="dato"><div class="k">Ingresó a bodega</div><div class="v">{{ fecha($u->fecha_ingreso) }}{{ $u->ingresadaPor ? ' · '.$u->ingresadaPor->nombre : '' }}</div></div>
                    <div class="dato"><div class="k">Proveedor</div><div class="v">{{ $u->proveedor?->nombre ?? '—' }}</div></div>
                    <div class="dato"><div class="k">Ubicación</div><div class="v">{{ $u->ubicacion }}</div></div>
                    @if ($u->reservada_at)<div class="dato"><div class="k">Reservada el</div><div class="v">{{ fecha_hora($u->reservada_at) }}</div></div>@endif
                    @if ($u->vendida_at)<div class="dato"><div class="k">Vendida el</div><div class="v">{{ fecha_hora($u->vendida_at) }}</div></div>@endif
                </div>

                @if ($u->solicitud)
                    @php $s = $u->solicitud; $c = $s->contrato; @endphp
                    <div class="aviso aviso-info mt-2 mb-0">
                        Asociada a <x-familia :cliente="$s->cliente" :url="route('solicitudes.show', $s)" />
                        <div class="small mt-1">{{ $s->codigo() }} · {{ $s->nombreServicio() }} · {{ fecha_hora($s->fecha_hora) }}
                            @if ($c && auth()->user()->puede('finanzas')) · contrato {{ $c->numero }}: abonado {{ clp($c->totalAbonado()) }} de {{ clp($c->monto_total) }} @endif</div>
                    </div>
                @endif
                @if ($u->observacion)<p class="small muted mt-2 mb-0">{{ $u->observacion }}</p>@endif
            </div>
        </div>

        @if ($gestion)
            @if ($u->estado === 'disponible')
                <div class="card">
                    <div class="card-header"><h2>Reservar para una familia</h2><span class="meta">Se reserva con el abono al aprobar</span></div>
                    <div class="card-body">
                        <form method="GET" action="{{ route('unidades.reservar', $u) }}">
                            <div class="campo mb-2">
                                <label>Solicitud de la familia</label>
                                <select class="input" name="solicitud_id" required>
                                    <option value="">Seleccione…</option>
                                    @if ($pendientes->isNotEmpty())
                                        <optgroup label="En revisión (se aprobará con abono)">
                                            @foreach ($pendientes as $p)<option value="{{ $p->id }}">{{ $p->cliente->nombre_familia }} ({{ $p->cliente->nombre_contratante }}) — {{ fecha($p->fecha_hora, 'd M H:i') }}</option>@endforeach
                                        </optgroup>
                                    @endif
                                    @if ($aprobadas->isNotEmpty())
                                        <optgroup label="Aprobadas (cambiar su urna por esta)">
                                            @foreach ($aprobadas as $p)<option value="{{ $p->id }}">{{ $p->cliente->nombre_familia }} ({{ $p->cliente->nombre_contratante }}) — actual: {{ $p->unidad?->codigo ?? 'a pedido' }}</option>@endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <button class="btn btn-primary">Reservar esta urna</button>
                        </form>
                        <p class="small muted mb-0 mt-2">Para una solicitud en revisión se abre la aprobación con esta urna elegida: al registrar el abono mínimo del {{ config('funeraria.abono_minimo_pct') }}% queda reservada y se bloquea.</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2>Dar de baja</h2></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('unidades.baja', $u) }}" class="flex" data-confirmar="¿Dar de baja esta urna? Dejará de estar disponible.">
                            @csrf
                            <input class="input" name="motivo" placeholder="Motivo (dañada, devuelta al proveedor…)" required>
                            <button class="btn btn-danger btn-sm">Dar de baja</button>
                        </form>
                    </div>
                </div>
            @elseif ($u->estado === 'reservada')
                <div class="card">
                    <div class="card-header"><h2>Acciones de la reserva</h2></div>
                    <div class="card-body">
                        <div class="flex flex-wrap">
                            <form method="POST" action="{{ route('unidades.comprar', $u) }}" data-confirmar="¿Confirmar la compra? La urna quedará vendida a esta familia.">
                                @csrf <button class="btn btn-ok">Comprar (confirmar venta)</button>
                            </form>
                            @if ($u->solicitud)<a href="{{ route('solicitudes.show', $u->solicitud) }}#cambiar-urna" class="btn btn-outline">Cambiar por otra urna</a>@endif
                        </div>
                        <p class="small muted mb-0 mt-2">Para confirmar la compra la familia debe tener abonado al menos el {{ config('funeraria.abono_minimo_pct') }}% (o la cuota mortuoria en trámite). Si la familia quiere otra urna, al cambiarla esta vuelve a quedar disponible.</p>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
