@extends('layouts.app')
@php $editando = $usuario->exists; @endphp
@section('titulo', $editando ? 'Editar usuario' : 'Nuevo usuario')
@section('subtitulo', 'El administrador puede dar o quitar permisos asignando el perfil')
@section('acciones')
    <a href="{{ route('usuarios.index') }}" class="btn btn-outline">Volver</a>
@endsection

@section('contenido')
<div class="grid-2-1">
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ $editando ? route('usuarios.update', $usuario) : route('usuarios.store') }}">
                @csrf
                @if ($editando) @method('PUT') @endif
                <div class="form-grid">
                    <div class="campo"><label>Nombre *</label><input class="input" name="nombre" value="{{ old('nombre', $usuario->nombre) }}" required></div>
                    <div class="campo"><label>Correo *</label><input class="input" type="email" name="email" value="{{ old('email', $usuario->email) }}" required></div>
                    <div class="campo"><label>Teléfono</label><input class="input" name="telefono" value="{{ old('telefono', $usuario->telefono) }}"></div>
                    <div class="campo"><label>Perfil *</label>
                        <select class="input" name="rol_id" required>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}" @selected(old('rol_id', $usuario->rol_id) == $r->id)>{{ ucfirst($r->nombre) }} — {{ $r->descripcion }}</option>
                            @endforeach
                        </select></div>
                    <div class="campo"><label>{{ $editando ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}</label><input class="input" type="password" name="password" {{ $editando ? '' : 'required' }} autocomplete="new-password"></div>
                    <div class="campo"><label>Repetir contraseña</label><input class="input" type="password" name="password_confirmation" autocomplete="new-password"></div>
                    <div class="campo"><label>Estado</label>
                        <select class="input" name="estado">
                            @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'bloqueado' => 'Bloqueado'] as $k => $v)
                                <option value="{{ $k }}" @selected(old('estado', $usuario->estado) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                        @if ($usuario->estado === 'bloqueado')<div class="ayuda">Bloqueado por intentos fallidos. Cambie a “Activo” para desbloquear.</div>@endif</div>
                </div>
                <div class="form-acciones"><button class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Perfiles</h2></div>
        <div class="card-body">
            <ul class="reglas">
                @foreach ($roles as $r)
                    <li><strong>{{ ucfirst($r->nombre) }}:</strong> {{ $r->descripcion }}<div class="chips mt-1">@foreach (array_slice($r->etiquetasPermisos(), 0, 6) as $p)<span>{{ $p }}</span>@endforeach</div></li>
                @endforeach
            </ul>
            <p class="small muted">Los perfiles con “Rutas e itinerarios” (sin administrar la flota) se vinculan automáticamente a una ficha de chofer. Los permisos se cambian en <a href="{{ route('roles.index') }}">Roles y permisos</a>.</p>
        </div>
    </div>
</div>
@endsection
