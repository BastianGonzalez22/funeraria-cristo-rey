@extends('layouts.app')
@section('titulo', 'Personal y vehículos')
@section('subtitulo', 'Registro de choferes (planta, part-time y externos) y de la flota')
@section('acciones')
    <a href="{{ route('flota.index') }}" class="btn btn-outline">Flota y cortejos</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-header"><h2>Choferes</h2><span class="meta">Servicios del mes en paréntesis</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Teléfono</th><th>Licencia</th><th>Jornada</th><th>Usuario del sistema</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach ($choferes as $ch)
                <tr>
                        <td><input form="ch-{{ $ch->id }}" class="input" name="nombre" value="{{ $ch->nombre }}"><div class="xs muted">{{ $ch->asignaciones_count }} servicio(s) este mes {{ $ch->ausenteEl(now()) ? '· AUSENTE HOY' : '' }}</div></td>
                        <td><input form="ch-{{ $ch->id }}" class="input" name="telefono" value="{{ $ch->telefono }}" style="width:140px"></td>
                        <td><input form="ch-{{ $ch->id }}" class="input" name="licencia" value="{{ $ch->licencia }}" style="width:80px"></td>
                        <td><select form="ch-{{ $ch->id }}" class="input" name="jornada">@foreach (['completa' => 'Completa', 'part_time' => 'Part-time', 'externo' => 'Externo'] as $k => $v)<option value="{{ $k }}" @selected($ch->jornada === $k)>{{ $v }}</option>@endforeach</select></td>
                        <td><select form="ch-{{ $ch->id }}" class="input" name="user_id"><option value="">—</option>
                                @if ($ch->usuario)<option value="{{ $ch->user_id }}" selected>{{ $ch->usuario->email }}</option>@endif
                                @foreach ($usuariosChofer as $uc)<option value="{{ $uc->id }}">{{ $uc->email }}</option>@endforeach
                            </select></td>
                        <td><select form="ch-{{ $ch->id }}" class="input" name="estado">@foreach (['disponible' => 'Disponible', 'en_servicio' => 'En servicio', 'no_disponible' => 'No disponible'] as $k => $v)<option value="{{ $k }}" @selected($ch->estado === $k)>{{ $v }}</option>@endforeach</select></td>
                        <td><form method="POST" action="{{ route('personal.chofer.update', $ch) }}" id="ch-{{ $ch->id }}">@csrf @method('PUT')<button class="btn btn-outline btn-xs">Guardar</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('personal.chofer.store') }}" class="filtros">@csrf
            <div class="campo"><label>Nombre</label><input class="input" name="nombre" required></div>
            <div class="campo"><label>Teléfono</label><input class="input" name="telefono"></div>
            <div class="campo"><label>Licencia</label><input class="input" name="licencia" style="width:90px"></div>
            <div class="campo"><label>Jornada</label><select class="input" name="jornada"><option value="completa">Completa</option><option value="part_time">Part-time</option><option value="externo">Externo</option></select></div>
            <input type="hidden" name="estado" value="disponible">
            <button class="btn btn-primary">+ Agregar chofer</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2>Vehículos</h2></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Patente</th><th>Tipo</th><th>Observación</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach ($vehiculos as $v)
                <tr>
                        <td><input form="ve-{{ $v->id }}" class="input" name="nombre" value="{{ $v->nombre }}"><div class="xs muted">{{ $v->asignaciones_count }} servicio(s) este mes</div></td>
                        <td><input form="ve-{{ $v->id }}" class="input" name="patente" value="{{ $v->patente }}" style="width:100px"></td>
                        <td><select form="ve-{{ $v->id }}" class="input" name="tipo">@foreach (\App\Models\Vehiculo::TIPOS as $k => $t)<option value="{{ $k }}" @selected($v->tipo === $k)>{{ $t }}</option>@endforeach</select></td>
                        <td><input form="ve-{{ $v->id }}" class="input" name="observacion" value="{{ $v->observacion }}"></td>
                        <td><select form="ve-{{ $v->id }}" class="input" name="estado">@foreach (['disponible' => 'Disponible', 'en_servicio' => 'En servicio', 'en_mantencion' => 'En mantención'] as $k => $t)<option value="{{ $k }}" @selected($v->estado === $k)>{{ $t }}</option>@endforeach</select></td>
                        <td><form method="POST" action="{{ route('personal.vehiculo.update', $v) }}" id="ve-{{ $v->id }}">@csrf @method('PUT')<button class="btn btn-outline btn-xs">Guardar</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('personal.vehiculo.store') }}" class="filtros">@csrf
            <div class="campo"><label>Nombre</label><input class="input" name="nombre" required placeholder="Carroza 02"></div>
            <div class="campo"><label>Patente</label><input class="input" name="patente" required style="width:100px"></div>
            <div class="campo"><label>Tipo</label><select class="input" name="tipo">@foreach (\App\Models\Vehiculo::TIPOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
            <input type="hidden" name="estado" value="disponible">
            <button class="btn btn-primary">+ Agregar vehículo</button>
        </form>
        <p class="small muted mb-0 mt-1">Use el tipo “Apoyo externo” para registrar la carroza que se pide prestada a otra funeraria cuando se acumulan servicios.</p>
    </div>
</div>
@endsection
