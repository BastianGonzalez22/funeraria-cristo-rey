{{-- Firma de la funeraria en los PDF: imagen de firma/timbre (Configuración) o línea en blanco --}}
@php
    $firmaPath = \App\Models\Ajuste::valor('firma_imagen');
    $firmaSrc = null;
    if ($firmaPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($firmaPath)) {
        $disco = \Illuminate\Support\Facades\Storage::disk('public');
        $firmaSrc = 'data:'.$disco->mimeType($firmaPath).';base64,'.base64_encode($disco->get($firmaPath));
    }
@endphp
@if ($firmaSrc)
    <img src="{{ $firmaSrc }}" style="max-height:70px;max-width:200px;display:block;margin:0 auto 2px">
@endif
<div class="linea"></div>
{{ config('funeraria.nombre') }}
@if (! empty($sucursal))<br><span style="font-size:10px">{{ $sucursal }}</span>@endif
@if (! empty($firmante))<br><span style="font-size:10px">{{ $firmante }}</span>@endif
