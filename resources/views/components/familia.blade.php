@props(['cliente', 'url' => null])
{{-- Nombre de la familia y, abajo en chico, el nombre del contratante --}}
@if ($cliente)
    @if ($url)
        <a href="{{ $url }}" {{ $attributes }}><span class="familia-n">{{ $cliente->nombre_familia }}</span><span class="familia-c">{{ $cliente->nombre_contratante }}</span></a>
    @else
        <span {{ $attributes }}><span class="familia-n">{{ $cliente->nombre_familia }}</span><span class="familia-c">{{ $cliente->nombre_contratante }}</span></span>
    @endif
@else
    <span class="muted">—</span>
@endif
