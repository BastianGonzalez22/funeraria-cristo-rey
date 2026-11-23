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
                    @if ($img['url'])<span class="badge b-ok">Imagen personalizada</span>@else<span class="badge b-neutral">Fondo por defecto</span>@endif</div>
                <div class="card-body">
                    <div id="previa-{{ $clave }}" style="height:180px;border-radius:10px;margin-bottom:10px;background-size:cover;background-position:center;{{ $img['url'] ? "background-image:url('{$img['url']}')" : 'background-image:linear-gradient(135deg,#3a2e24,#1f1b18)' }}"></div>
                    <input class="input" type="file" name="{{ $clave }}" accept="image/*" data-previa-fondo="#previa-{{ $clave }}">
                    <div class="ayuda">{{ $clave === 'login_imagen' ? 'Se muestra a la derecha de la pantalla de ingreso. Recomendado: horizontal, 1600 × 1200 px.' : 'Fondo de la portada del sitio público. Recomendado: 1920 × 800 px.' }} Para cambiarla, elija otra y presione Guardar.</div>
                    @if ($img['url'])
                        <button type="submit" name="quitar[]" value="{{ $clave }}" class="btn btn-danger btn-sm mt-2" formnovalidate onclick="return confirm('¿Eliminar esta imagen? Se volverá a mostrar el fondo oscuro por defecto.')">Eliminar imagen</button>
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
@endsection
