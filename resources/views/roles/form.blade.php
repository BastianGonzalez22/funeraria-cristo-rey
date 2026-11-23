@extends('layouts.app')
@php $editando = $rol->exists; $marcados = old('permisos', $rol->permisos ?? []); $esAdmin = $rol->nombre === 'administrador'; @endphp
@section('titulo', $editando ? 'Editar rol: '.ucfirst($rol->nombre) : 'Nuevo rol')
@section('subtitulo', 'Marque los módulos a los que tendrá acceso')
@section('acciones')
    <a href="{{ route('roles.index') }}" class="btn btn-outline">Volver</a>
@endsection

@section('contenido')
<form method="POST" action="{{ $editando ? route('roles.update', $rol) : route('roles.store') }}">
    @csrf
    @if ($editando) @method('PUT') @endif
    <div class="card">
        <div class="card-body">
            <div class="form-grid cols-3">
                <div class="campo"><label>Nombre del rol *</label><input class="input" name="nombre" value="{{ old('nombre', $rol->nombre) }}" required maxlength="40" @readonly($esAdmin || $rol->nombre === 'solicitante') placeholder="ej: secretaria"></div>
                <div class="campo"><label>Descripción</label><input class="input" name="descripcion" value="{{ old('descripcion', $rol->descripcion) }}" maxlength="255"></div>
                <div class="campo"><label>Estado</label>
                    <select class="input" name="estado" @disabled($esAdmin)>
                        <option value="activo" @selected(old('estado', $rol->estado ?? 'activo') === 'activo')>Activo</option>
                        <option value="inactivo" @selected(old('estado', $rol->estado) === 'inactivo')>Inactivo (sus usuarios no pueden ingresar)</option>
                    </select>
                    @if ($esAdmin)<input type="hidden" name="estado" value="activo">@endif</div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Permisos por módulo</h2>@if ($esAdmin)<span class="badge b-dark">El administrador siempre tiene acceso total</span>@endif</div>
        <div class="card-body">
            <div class="permisos-grid">
                @foreach (config('funeraria.modulos') as $clave => [$titulo, $desc])
                    <label class="check"><input type="checkbox" name="permisos[]" value="{{ $clave }}" @checked($esAdmin || in_array($clave, $marcados)) @disabled($esAdmin)>
                        <span><strong>{{ $titulo }}</strong><small>{{ $desc }}</small></span></label>
                @endforeach
            </div>
            <p class="small muted mb-0 mt-2"><strong>Finanzas</strong> da acceso a montos, abonos y contratos. Un chofer normalmente solo necesita <em>Rutas e itinerarios</em> y <em>Bodega</em>.</p>
        </div>
    </div>
    <div class="form-acciones"><button class="btn btn-primary">Guardar rol</button></div>
</form>
@endsection
