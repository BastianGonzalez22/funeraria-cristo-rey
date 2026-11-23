{{-- Carrusel de fotos de una urna. Variables: $urna, $id (único en la página) --}}
@php $fotos = $urna->fotos(); $gid = $id ?? 'galeria-'.$urna->id; $titulos = $urna->imagenes->pluck('titulo')->all(); @endphp
<div class="galeria" id="{{ $gid }}" data-galeria>
    <div class="pista">
        @forelse ($fotos as $k => $f)
            <div class="slide" style="background-image:url('{{ $f }}')">@if (! empty($titulos[$k]))<span class="cap">{{ $titulos[$k] }}</span>@endif</div>
        @empty
            <div class="slide" style="background:{{ $urna->colorMuestra() }};background-image:linear-gradient(135deg,rgba(255,255,255,.25),rgba(0,0,0,.18))"><span class="cap">Sin fotos aún</span></div>
        @endforelse
    </div>
    @if (count($fotos) > 1)
        <button type="button" class="nav prev" aria-label="Anterior">‹</button>
        <button type="button" class="nav next" aria-label="Siguiente">›</button>
        <div class="puntos">@foreach ($fotos as $f)<button type="button" aria-label="Foto {{ $loop->iteration }}"></button>@endforeach</div>
    @endif
</div>
@if (count($fotos) > 1)
    <div class="miniaturas" data-galeria-mini="{{ $gid }}">
        @foreach ($fotos as $f)<button type="button" style="background-image:url('{{ $f }}')" aria-label="Ver foto {{ $loop->iteration }}"></button>@endforeach
    </div>
@endif
