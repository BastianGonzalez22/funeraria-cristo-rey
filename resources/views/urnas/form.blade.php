@extends('layouts.app')
@php $editando = $urna->exists; @endphp
@section('titulo', $editando ? 'Editar urna '.$urna->codigo : 'Nuevo modelo de urna')
@section('subtitulo', 'Mantenedor: medidas, color, tallado, manillas, material y fotos')
@section('acciones')
    <a href="{{ $editando ? route('urnas.show', $urna) : route('urnas.index') }}" class="btn btn-outline">Volver</a>
@endsection

@section('contenido')
<form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('urnas.update', $urna) : route('urnas.store') }}" id="form-urna">
    @csrf
    @if ($editando) @method('PUT') @endif
    <div class="grid-2-1">
        <div>
            <div class="card">
                <div class="card-header"><h2>Datos del modelo</h2></div>
                <div class="card-body">
                    <div class="form-grid cols-3">
                        <div class="campo"><label>Código del modelo *</label><input class="input" name="codigo" value="{{ old('codigo', $urna->codigo) }}" required placeholder="U-111">
                            <div class="ayuda">Cada urna física recibirá un código propio: {{ ($urna->codigo ?: 'U-111') }}-0001, -0002…</div></div>
                        <div class="campo" style="grid-column: span 2"><label>Nombre del modelo *</label><input class="input" name="modelo" value="{{ old('modelo', $urna->modelo) }}" required></div>
                        <div class="campo"><label>Categoría *</label>
                            <select class="input" name="categoria">@foreach (config('funeraria.categorias_urna') as $k => $v)<option value="{{ $k }}" @selected(old('categoria', $urna->categoria) === $k)>{{ $v }}</option>@endforeach</select></div>
                        <div class="campo"><label>Acabado (tallada, lisa…)</label>
                            <select class="input" name="acabado"><option value="">—</option>@foreach (config('funeraria.acabados_urna') as $k => $v)<option value="{{ $k }}" @selected(old('acabado', $urna->acabado) === $k)>{{ $v }}</option>@endforeach</select></div>
                        <div class="campo"><label>Material</label><input class="input" name="material" value="{{ old('material', $urna->material) }}" placeholder="Madera, metal…"></div>
                        <div class="campo"><label>Color</label><input class="input" name="color" value="{{ old('color', $urna->color) }}" placeholder="Café, blanco…"></div>
                        <div class="campo"><label>Manillas</label><input class="input" name="manillas" value="{{ old('manillas', $urna->manillas) }}" placeholder="Bronce, 6 unidades"></div>
                        <div class="campo"><label>Proveedor</label>
                            <select class="input" name="proveedor_id"><option value="">—</option>@foreach ($proveedores as $p)<option value="{{ $p->id }}" @selected(old('proveedor_id', $urna->proveedor_id) == $p->id)>{{ $p->nombre }}</option>@endforeach</select></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Medidas</h2><span class="meta">En centímetros · aparecen en la etiqueta y el catálogo</span></div>
                <div class="card-body">
                    <div class="form-grid cols-3">
                        <div class="campo"><label>Largo (cm)</label><input class="input" type="number" name="largo_cm" min="40" max="260" value="{{ old('largo_cm', $urna->largo_cm) }}" placeholder="198"></div>
                        <div class="campo"><label>Ancho (cm)</label><input class="input" type="number" name="ancho_cm" min="20" max="120" value="{{ old('ancho_cm', $urna->ancho_cm) }}" placeholder="60"></div>
                        <div class="campo"><label>Alto (cm)</label><input class="input" type="number" name="alto_cm" min="15" max="100" value="{{ old('alto_cm', $urna->alto_cm) }}" placeholder="45"></div>
                    </div>
                    <p class="small muted mb-0 mt-1">Referencia: adulto estándar ~195 × 58 × 42 · infantil ~120 × 45 × 35 · sobredimensionada ~215 × 75 × 55.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Precio, stock y descripción</h2></div>
                <div class="card-body">
                    <div class="form-grid cols-3">
                        <div class="campo"><label>Precio de venta *</label><input class="input" type="number" name="precio" min="0" step="1000" value="{{ old('precio', $urna->precio) }}" required></div>
                        <div class="campo"><label>Stock mínimo (alerta) *</label><input class="input" type="number" name="stock_minimo" min="0" max="30" value="{{ old('stock_minimo', $urna->stock_minimo) }}" required></div>
                        @unless ($editando)
                            <div class="campo"><label>Unidades iniciales en bodega</label><input class="input" type="number" name="stock_inicial" min="0" max="{{ $libre }}" value="{{ old('stock_inicial', 0) }}">
                                <div class="ayuda">Se crean con código y etiqueta. Espacio libre: {{ $libre }}.</div></div>
                        @endunless
                        <div class="campo full"><label>Descripción / características</label><textarea class="input" name="descripcion">{{ old('descripcion', $urna->descripcion) }}</textarea></div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h2>Fotos</h2>@if ($editando)<a href="{{ route('urnas.fotos', $urna) }}" class="small">Administrar fotos →</a>@else<span class="meta">Se muestran en un carrusel</span>@endif</div>
                <div class="card-body">
                    @if ($editando && $urna->imagenes->isNotEmpty())
                        <div class="miniaturas mb-2" style="margin-top:0">
                            @foreach ($urna->imagenes as $img)
                                <div style="position:relative">
                                    <div style="width:84px;height:58px;border-radius:6px;background:center/cover url('{{ $img->url() }}')"></div>
                                    <button type="submit" form="borrar-img-{{ $img->id }}" class="btn btn-danger btn-xs" style="position:absolute;top:2px;right:2px;padding:0 6px" title="Eliminar foto">×</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <input class="input" type="file" name="imagenes[]" accept="image/*" multiple data-vista-previa="#previa-fotos">
                    <div class="ayuda">Puede elegir varias a la vez (frente, lateral, interior…). Máx. 4 MB cada una.</div>
                    <div id="previa-fotos" class="miniaturas"></div>
                    <label class="check mt-2"><input type="checkbox" name="publica" value="1" @checked(old('publica', $urna->publica))> Mostrar en el catálogo público</label>
                    <label class="check"><input type="checkbox" name="bajo_pedido" value="1" @checked(old('bajo_pedido', $urna->bajo_pedido))> Personalizada / se fabrica a pedido</label>
                </div>
            </div>
            <button class="btn btn-primary" style="width:100%;justify-content:center">Guardar</button>
        </div>
    </div>
</form>

@if ($editando)
    @foreach ($urna->imagenes as $img)
        <form method="POST" action="{{ route('urnas.imagen.eliminar', $img) }}" id="borrar-img-{{ $img->id }}" class="hidden" data-confirmar="¿Eliminar esta foto?">@csrf @method('DELETE')</form>
    @endforeach
@endif
@endsection
