@extends('layouts.app')
@section('titulo', 'Roles y permisos')
@section('subtitulo', 'El administrador define qué puede hacer cada perfil')
@section('acciones')
    <a href="{{ route('usuarios.index') }}" class="btn btn-outline">Usuarios y familias</a>
    <a href="{{ route('roles.create') }}" class="btn btn-primary">+ Nuevo rol</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Rol</th><th>Permisos</th><th>Usuarios</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach ($roles as $r)
                <tr>
                    <td><span class="strong">{{ ucfirst($r->nombre) }}</span>@if ($r->sistema) <span class="badge b-neutral">del sistema</span>@endif
                        <div class="xs muted">{{ $r->descripcion }}</div></td>
                    <td><div class="chips">@foreach ($r->etiquetasPermisos() as $p)<span>{{ $p }}</span>@endforeach</div></td>
                    <td>{{ $r->usuarios_count }}</td>
                    <td><x-estado :valor="$r->estado" /></td>
                    <td class="nowrap">
                        <a href="{{ route('roles.edit', $r) }}" class="btn btn-outline btn-xs">Editar</a>
                        @unless ($r->esProtegido())
                            <form method="POST" action="{{ route('roles.destroy', $r) }}" class="inline" data-confirmar="¿Eliminar el rol «{{ $r->nombre }}»? Esta acción no se puede deshacer.">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-xs" @disabled($r->usuarios_count > 0) title="{{ $r->usuarios_count ? 'Tiene usuarios asignados' : 'Eliminar' }}">Eliminar</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="aviso aviso-info small">Un rol solo se puede eliminar si no tiene usuarios asignados. Los roles <strong>administrador</strong> (acceso total) y <strong>solicitante</strong> (familias que se registran en el sitio) son necesarios y no se eliminan. Todos los cambios quedan en la auditoría.</div>
@endsection
