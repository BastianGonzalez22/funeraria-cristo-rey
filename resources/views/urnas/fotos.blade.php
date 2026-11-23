@extends('layouts.app')
@section('titulo', 'Fotos de '.$urna->codigo.' · '.$urna->modelo)
@section('subtitulo', 'Agregue, elimine o cambie las fotos que se ven en el catálogo y en el carrusel')
@section('acciones')
    <a href="{{ route('urnas.show', $urna) }}" class="btn btn-outline">Volver a la urna</a>
    <a href="{{ route('publico.urna', $urna->codigo) }}" target="_blank" class="btn btn-outline">Ver en el sitio</a>
@endsection

@section('contenido')
<div class="grid-2-1">
    <div>
        <div class="card">
            <div class="card-header"><h2>Fotos actuales</h2><span class="meta">{{ $urna->imagenes->count() }} foto(s) · la primera es la principal</span></div>
            <div class="card-body">
                @if ($urna->imagenes->isEmpty())
                    <p class="muted mb-0">Esta urna aún no tiene fotos. Agréguelas con el formulario de la derecha.</p>
                @else
                    <div class="grid-3" style="gap:14px">
                        @foreach ($urna->imagenes as $img)
                            <div class="urna-card">
                                <div class="foto" style="background-image:url('{{ $img->url() }}');height:150px">
                                    @if ($loop->first)<span class="mini-cont" style="left:8px;right:auto;background:var(--accent)">Principal</span>@endif
                                </div>
                                <div class="cuerpo" style="gap:6px">
                                    <form method="POST" action="{{ route('urnas.imagen.actualizar', $img) }}" class="flex" style="gap:6px">
                                        @csrf @method('PUT')
                                        <input class="input" name="titulo" value="{{ $img->titulo }}" placeholder="Título (ej: Vista lateral)" maxlength="80" style="padding:5px 8px;font-size:12px">
                                        <button class="btn btn-outline btn-xs" title="Guardar título">✓</button>
                                    </form>
                                    <div class="flex" style="gap:6px">
                                        @unless ($loop->first)
                                            <form method="POST" action="{{ route('urnas.imagen.principal', $img) }}" class="grow">@csrf<button class="btn btn-outline btn-xs" style="width:100%;justify-content:center">Hacer principal</button></form>
                                        @endunless
                                        <form method="POST" action="{{ route('urnas.imagen.eliminar', $img) }}" class="grow" data-confirmar="¿Eliminar esta foto? No se puede deshacer.">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-xs" style="width:100%;justify-content:center">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><h2>Agregar fotos</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('urnas.fotos.subir', $urna) }}" enctype="multipart/form-data">
                    @csrf
                    <input class="input" type="file" name="imagenes[]" accept="image/*" multiple required data-vista-previa="#previa-nuevas">
                    <div class="ayuda">Puede elegir varias a la vez (frente, lateral, interior…). JPG, PNG o WEBP de hasta 4 MB cada una.</div>
                    <div id="previa-nuevas" class="miniaturas"></div>
                    <button class="btn btn-primary mt-2" style="width:100%;justify-content:center">Subir fotos</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Vista previa</h2></div>
            <div class="card-body">@include('urnas.partials.galeria', ['urna' => $urna, 'id' => 'galeria-previa'])</div>
        </div>
    </div>
</div>
@endsection
