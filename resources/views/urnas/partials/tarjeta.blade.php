@php $img = $u->imagenUrl(); $nFotos = count($u->fotos()); @endphp
<div class="urna-card">
    <div class="foto {{ $img ? '' : 'muestra' }}" style="{{ $img ? "background-image:url('{$img}')" : 'background-color:'.$u->colorMuestra() }}">
        @if ($nFotos > 1)<span class="mini-cont">{{ $nFotos }} fotos</span>@endif
    </div>
    <div class="cuerpo">
        <div class="cod">
            <span>{{ $u->codigo }} · {{ etiqueta('categorias_urna', $u->categoria) }}</span>
            @if ($mostrarStock ?? false)
                @if ($u->disponible() > 0)
                    <span class="badge {{ $u->estado === 'normal' || $u->bajo_pedido ? 'b-ok' : 'b-warn' }}">{{ $u->disponible() }} disp.</span>
                @elseif ($u->bajo_pedido)
                    <span class="badge b-info">A pedido</span>
                @else
                    <span class="badge b-bad">Sin unidades</span>
                @endif
            @endif
        </div>
        <div class="nom">{{ $u->modelo }}</div>
        <div class="atributos">
            @if ($u->acabado)<span>{{ etiqueta('acabados_urna', $u->acabado) }}</span>@endif
            @if ($u->material)<span>{{ $u->material }}</span>@endif
            @if ($u->color)<span>{{ $u->color }}</span>@endif
        </div>
        @if ($u->medidasTexto())<div class="esp">Medidas: {{ $u->medidasTexto() }}</div>@endif
        <div class="precio">{{ clp($u->precio) }}</div>
        @if (! empty($accion))
            <a href="{{ $accion }}" class="btn btn-primary btn-sm">{{ $textoAccion ?? 'Ver' }}</a>
        @elseif (! empty($textoAccion))
            <button class="btn btn-outline btn-sm" disabled>Sin unidades disponibles</button>
        @endif
    </div>
</div>
