{{-- Cementerio o crematorio: lista según el destino final (más cercanos primero) + texto libre --}}
@php
    try {
        $lugaresDestino = \App\Models\LugarDestino::activos()->get();
    } catch (\Throwable) {
        $lugaresDestino = collect();
    }
    $valorDestino = old('cementerio_crematorio', $solicitud->cementerio_crematorio);
@endphp
<select class="input" data-lugar-destino aria-label="Elegir cementerio o crematorio">
    <option value="">Elegir de la lista…</option>
    @foreach ($lugaresDestino->groupBy('comuna') as $comuna => $lugares)
        <optgroup label="{{ $comuna }}">
            @foreach ($lugares as $l)
                <option value="{{ $l->texto() }}" data-tipo="{{ $l->tipo }}" data-comuna="{{ $l->comuna }}" @selected($valorDestino === $l->texto())>{{ $l->nombre }}{{ $l->direccion ? ' — '.$l->direccion : '' }}</option>
            @endforeach
        </optgroup>
    @endforeach
    <option value="__otro">Otro (escribir abajo)</option>
</select>
<input class="input mt-1" name="cementerio_crematorio" value="{{ $valorDestino }}" maxlength="150" placeholder="Nombre y dirección del cementerio o crematorio">
<div class="ayuda" data-lugar-ayuda>Se muestran los más cercanos a Temuco primero, según el destino final elegido.</div>
