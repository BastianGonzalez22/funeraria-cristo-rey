@extends('layouts.app')
@php $editando = $cliente->exists; $docs = old('documentos', array_keys(array_filter($cliente->documentos ?? []))); @endphp
@section('titulo', $editando ? 'Editar familia' : 'Registro de clientes')
@section('subtitulo', 'Alta de la familia con selección de convenio y de urna')
@section('acciones')
    <a href="{{ route('usuarios.index') }}" class="btn btn-outline">Buscar familias</a>
@endsection

@section('contenido')
<form method="POST" action="{{ $editando ? route('clientes.update', $cliente) : route('clientes.store') }}" novalidate>
    @csrf
    @if ($editando) @method('PUT') @endif
    <div class="grid-2-1">
        <div class="card">
            <div class="card-header"><h2>Datos del contratante</h2><span class="meta">* obligatorio</span></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="campo"><label>Nombre de la familia *</label>
                        <input class="input @error('nombre_familia') invalido @enderror" name="nombre_familia" value="{{ old('nombre_familia', $cliente->nombre_familia) }}" placeholder="Familia Contreras" required>
                        @error('nombre_familia')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Nombre completo del contratante *</label>
                        <input class="input @error('nombre_contratante') invalido @enderror" name="nombre_contratante" value="{{ old('nombre_contratante', $cliente->nombre_contratante) }}" required>
                        @error('nombre_contratante')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>RUT *</label>
                        <input class="input @error('rut_contratante') invalido @enderror" name="rut_contratante" value="{{ old('rut_contratante', $cliente->rut_contratante) }}" data-rut placeholder="12.345.678-5" required>
                        @error('rut_contratante')<div class="error">{{ $message }}</div>@enderror
                        <div class="ayuda">Se valida con su dígito verificador y se almacena cifrado.</div></div>
                    <div class="campo"><label>Teléfono *</label>
                        <input class="input @error('telefono') invalido @enderror" name="telefono" value="{{ old('telefono', $cliente->telefono) }}" placeholder="+56 9 1234 5678" required>
                        @error('telefono')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Correo electrónico</label>
                        <input class="input @error('email') invalido @enderror" type="email" name="email" value="{{ old('email', $cliente->email) }}">
                        @error('email')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Medio de contacto preferido *</label>
                        <select class="input" name="medio_contacto">
                            @foreach (config('funeraria.medios_contacto') as $k => $v)
                                <option value="{{ $k }}" @selected(old('medio_contacto', $cliente->medio_contacto) === $k)>{{ $v }}</option>
                            @endforeach
                        </select></div>
                    <div class="campo"><label>Domicilio del contratante *</label>
                        <input class="input @error('direccion') invalido @enderror" name="direccion" value="{{ old('direccion', $cliente->direccion) }}" required>
                        @error('direccion')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Comuna</label>
                        <input class="input" name="comuna" value="{{ old('comuna', $cliente->comuna) }}"></div>
                    <div class="campo"><label>Convenio</label>
                        <select class="input" name="convenio_id">
                            <option value="">Sin convenio</option>
                            @foreach ($convenios as $c)
                                <option value="{{ $c->id }}" @selected(old('convenio_id', $cliente->convenio_id) == $c->id)>{{ $c->institucion }} — {{ $c->beneficio }}</option>
                            @endforeach
                        </select>
                        <div class="ayuda"><a href="{{ route('convenios.disponibles') }}">Ver descripción de convenios disponibles</a></div></div>
                    <div class="campo"><label>Urna</label>
                        <select class="input" name="urna_preferida_id">
                            <option value="">Por definir</option>
                            @foreach ($urnas as $u)
                                <option value="{{ $u->id }}" @selected(old('urna_preferida_id', $cliente->urna_preferida_id) == $u->id)>{{ $u->codigo }} {{ $u->modelo }} — {{ clp($u->precio) }} ({{ $u->bajo_pedido ? 'bajo pedido' : $u->disponible().' disp.' }})</option>
                            @endforeach
                        </select>
                        <div class="ayuda"><a href="{{ route('urnas.catalogo') }}">Ver catálogo de urnas</a></div></div>
                    <div class="campo full"><label>Notas</label>
                        <textarea class="input" name="notas">{{ old('notas', $cliente->notas) }}</textarea></div>
                    @if ($editando)
                        <div class="campo"><label>Estado</label>
                            <select class="input" name="estado">
                                <option value="activo" @selected($cliente->estado === 'activo')>Activo</option>
                                <option value="inactivo" @selected($cliente->estado === 'inactivo')>Inactivo</option>
                            </select></div>
                    @endif
                </div>
            </div>
        </div>
        <div>
            <div class="card">
                <div class="card-header"><h2>Documentos recibidos</h2></div>
                <div class="card-body">
                    @foreach (config('funeraria.documentos') as $k => $v)
                        <label class="check"><input type="checkbox" name="documentos[]" value="{{ $k }}" @checked(in_array($k, $docs))> {{ $v }}</label>
                    @endforeach
                    <p class="small muted mb-0">El certificado escaneado se adjunta en la solicitud del servicio.</p>
                </div>
            </div>
            <div class="aviso aviso-info small">Si el fallecido no es jubilado o el contacto ocurre después de las 17:00, la verificación del beneficio AFP/IPS queda pendiente para el día hábil siguiente.</div>
            <div class="flex flex-wrap">
                @unless ($editando)
                    <button class="btn btn-primary" name="accion" value="solicitud">Guardar y crear solicitud</button>
                @endunless
                <button class="btn btn-outline" name="accion" value="guardar">Guardar</button>
            </div>
        </div>
    </div>
</form>
@endsection
