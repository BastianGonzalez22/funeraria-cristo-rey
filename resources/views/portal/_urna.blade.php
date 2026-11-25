{{-- Urna de la solicitud vista por la familia: modelo, código y si está reservada o ya comprada --}}
@php $u = $s->unidad; @endphp
{{ $s->urna?->modelo ?? 'Por definir' }}
@if ($u)
    <div class="small muted">Código {{ $u->codigo }}</div>
    @if ($u->estado === 'vendida')
        <x-estado valor="vendida" texto="Compra confirmada" />
        @if (! ($corto ?? false) && $u->vendida_at)<span class="small muted">el {{ fecha($u->vendida_at) }}</span>@endif
    @elseif ($u->estado === 'reservada')
        <x-estado valor="reservada" texto="Reservada para su familia" />
        @if (! ($corto ?? false) && $u->reservada_at)<span class="small muted">desde el {{ fecha($u->reservada_at) }}</span>@endif
    @endif
@elseif ($s->urna?->bajo_pedido && $s->contrato)
    <div class="small muted">Se fabrica a pedido</div>
@endif
