@extends('layouts.app')
@section('titulo', 'Configuración del sitio')
@section('subtitulo', 'Imagen del ingreso al sistema, portada del sitio y textos')

@section('contenido')
<form method="POST" action="{{ route('configuracion.guardar') }}" enctype="multipart/form-data">
    @csrf
    <div class="grid-2">
        @foreach ($imagenes as $clave => $img)
            <div class="card">
                <div class="card-header"><h2>{{ $img['titulo'] }}</h2>
                    @if ($img['url'])<span class="badge b-ok">Imagen personalizada</span>@else<span class="badge b-neutral">{{ $clave === 'firma_imagen' ? 'Sin firma (línea en blanco)' : 'Fondo por defecto' }}</span>@endif</div>
                <div class="card-body">
                    <div id="previa-{{ $clave }}" style="height:180px;border-radius:10px;margin-bottom:10px;background-size:{{ $clave === 'firma_imagen' ? 'contain' : 'cover' }};background-repeat:no-repeat;background-position:center;{{ $clave === 'firma_imagen' ? 'background-color:#fff;border:1px dashed #d9d3cb;' : '' }}{{ $img['url'] ? "background-image:url('{$img['url']}')" : ($clave === 'firma_imagen' ? '' : 'background-image:linear-gradient(135deg,#3a2e24,#1f1b18)') }}"></div>
                    <input class="input" type="file" name="{{ $clave }}" accept="image/*" data-previa-fondo="#previa-{{ $clave }}">
                    <div class="ayuda">@switch($clave)
                        @case('login_imagen') Se muestra a la derecha de la pantalla de ingreso. Recomendado: horizontal, 1600 × 1200 px. @break
                        @case('portada_imagen') Fondo de la portada del sitio público. Recomendado: 1920 × 800 px. @break
                        @default Firma escaneada o timbre de la funeraria; aparece sobre la línea de firma del comprobante, contrato y finiquito. Recomendado: PNG con fondo transparente, máx. 2 MB.
                    @endswitch Para cambiarla, elija otra y presione Guardar.</div>
                    @if ($img['url'])
                        <button type="submit" name="quitar[]" value="{{ $clave }}" class="btn btn-danger btn-sm mt-2" formnovalidate onclick="return confirm('¿Eliminar esta imagen?')">Eliminar imagen</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <div class="card">
        <div class="card-header"><h2>Textos</h2></div>
        <div class="card-body form-grid">
            <div class="campo"><label>Frase sobre la imagen del login</label><input class="input" name="login_texto" value="{{ $login_texto }}" maxlength="200"></div>
            <div class="campo"><label>Título de la portada</label><input class="input" name="portada_titulo" value="{{ $portada_titulo }}" maxlength="150"></div>
        </div>
    </div>
    <div class="form-acciones"><a href="{{ route('login') }}" target="_blank" class="btn btn-outline" onclick="return confirm('Se abrirá el login en otra pestaña; como ya tiene sesión, puede redirigirlo al panel.')">Ver login</a><button class="btn btn-primary">Guardar</button></div>
</form>

<div class="card mt-3" id="lugares">
    <div class="card-header"><h2>Cementerios y crematorios</h2><span class="meta">Se ofrecen en las solicitudes según el destino final</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Tipo</th><th>Nombre</th><th>Dirección</th><th>Comuna</th><th>Cercanía</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($lugares as $l)
                <tr class="{{ $l->activo ? '' : 'muted' }}">
                    <td>{{ \App\Models\LugarDestino::TIPOS[$l->tipo] }}</td>
                    <td class="strong">{{ $l->nombre }}</td>
                    <td>{{ $l->direccion ?: '—' }}</td>
                    <td>{{ $l->comuna }}</td>
                    <td>{{ $l->orden }}</td>
                    <td>@if ($l->activo)<span class="badge b-ok">Visible</span>@else<span class="badge b-neutral">Oculto</span>@endif</td>
                    <td class="nowrap">
                        <form method="POST" action="{{ route('configuracion.lugares.alternar', $l) }}" class="inline">@csrf @method('PATCH')<button class="btn btn-outline btn-xs">{{ $l->activo ? 'Ocultar' : 'Mostrar' }}</button></form>
                        <form method="POST" action="{{ route('configuracion.lugares.eliminar', $l) }}" class="inline" data-confirmar="¿Eliminar {{ $l->nombre }} de la lista?">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">×</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Aún no hay lugares registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <h3 class="mt-0">Agregar</h3>
        <form method="POST" action="{{ route('configuracion.lugares.guardar') }}" class="form-grid cols-3">
            @csrf
            <div class="campo"><label>Tipo *</label><select class="input" name="tipo" required>@foreach (\App\Models\LugarDestino::TIPOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="campo"><label>Nombre *</label><input class="input" name="nombre" required maxlength="120" placeholder="Ej: Crematorio …"></div>
            <div class="campo"><label>Comuna *</label><input class="input" name="comuna" required maxlength="60" value="Temuco"></div>
            <div class="campo"><label>Dirección</label><input class="input" name="direccion" maxlength="120"></div>
            <div class="campo"><label>Teléfono</label><input class="input" name="telefono" maxlength="30"></div>
            <div class="campo"><label>Cercanía (orden)</label><input class="input" type="number" name="orden" min="0" max="999" value="50">
                <div class="ayuda">Número menor = aparece más arriba (más cerca de Temuco).</div></div>
            <div class="form-acciones full"><button class="btn btn-primary">Agregar a la lista</button></div>
        </form>
    </div>
</div>
@endsection
