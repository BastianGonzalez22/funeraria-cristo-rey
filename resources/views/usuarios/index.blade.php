@extends('layouts.app')
@section('titulo', 'Usuarios y Roles')
@section('subtitulo', 'Familias atendidas, contratos, pagos y usuarios internos del sistema')
@section('acciones')
    @if (auth()->user()->puede('clientes'))<a href="#registrar-familia" class="btn btn-outline" onclick="document.getElementById('registrar-familia').open=true">+ Registrar familia</a>@endif
    @can('administrar-usuarios')<a href="{{ route('roles.index') }}" class="btn btn-outline">Roles y permisos</a><a href="{{ route('usuarios.create') }}" class="btn btn-primary">+ Usuario</a>@endcan
@endsection

@section('contenido')
@php $verFinanzas = auth()->user()->puede('finanzas', 'usuarios'); @endphp

@if (auth()->user()->puede('clientes'))
<details class="card" id="registrar-familia" @if ($errors->any()) open @endif>
    <summary class="card-header" style="cursor:pointer;list-style:none"><h2>+ Registrar una familia</h2><span class="meta">Registro rápido; el registro completo está en “Registro de clientes”</span></summary>
    <div class="card-body">
        <form method="POST" action="{{ route('clientes.store') }}">
            @csrf
            <input type="hidden" name="volver" value="usuarios">
            <input type="hidden" name="medio_contacto" value="telefono">
            <div class="form-grid cols-3">
                <div class="campo"><label>Nombre de la familia *</label><input class="input" name="nombre_familia" value="{{ old('nombre_familia') }}" required placeholder="Familia Contreras"></div>
                <div class="campo"><label>Nombre del contratante *</label><input class="input" name="nombre_contratante" value="{{ old('nombre_contratante') }}" required></div>
                <div class="campo"><label>RUT *</label><input class="input" name="rut_contratante" value="{{ old('rut_contratante') }}" data-rut required placeholder="12.345.678-5"></div>
                <div class="campo"><label>Teléfono *</label><input class="input" name="telefono" value="{{ old('telefono') }}" required placeholder="+56 9 1234 5678"></div>
                <div class="campo"><label>Domicilio *</label><input class="input" name="direccion" value="{{ old('direccion') }}" required></div>
                <div class="campo"><label>Convenio</label>
                    <select class="input" name="convenio_id"><option value="">Sin convenio</option>@foreach ($convenios as $c)<option value="{{ $c->id }}">{{ $c->institucion }}</option>@endforeach</select></div>
            </div>
            <div class="form-acciones"><button class="btn btn-primary">Registrar familia</button></div>
        </form>
    </div>
</details>
@endif

<div class="grid-1-2">
    <div class="card">
        <div class="card-header"><h2>Buscar familias</h2><span class="meta">{{ $familias->total() }} de {{ $totalFamilias }}</span></div>
        <div class="card-body" style="padding-bottom:8px">
            <form method="GET" class="form-grid" style="grid-template-columns:1fr 1fr auto;align-items:end;gap:8px">
                <div class="campo"><label>Nombre</label><input class="input" name="nombre" value="{{ $nombre }}" placeholder="Apellido o contratante" data-filtro-lista="#lista-familias" autocomplete="off"></div>
                <div class="campo"><label>RUT</label><input class="input" name="rut" value="{{ $rut }}" placeholder="12.345.678-5" data-rut></div>
                <button class="btn btn-primary btn-sm">Buscar</button>
            </form>
            @if ($nombre || $rut)<a href="{{ route('usuarios.index') }}" class="small">Limpiar filtros</a>@endif
        </div>
        <div class="lista-familias" id="lista-familias">
            @forelse ($familias as $f)
                @php $ult = $f->solicitudes->first(); $c = $ult?->contrato; @endphp
                <a href="{{ route('usuarios.index', array_merge(request()->query(), ['familia' => $f->id])) }}" class="{{ $seleccionada?->id === $f->id ? 'activo' : '' }}"
                   data-buscar="{{ $f->nombre_familia }} {{ $f->nombre_contratante }} {{ $f->telefono }}">
                    <div><div class="n">{{ $f->nombre_familia }}</div><div class="s">{{ $f->nombre_contratante }} · {{ $f->rutEnmascarado() }}</div></div>
                    @if ($c)<x-estado :valor="$c->estado === 'liquidado' ? 'pagado' : $c->estadoPago()" />@else<x-estado valor="sin_contrato" />@endif
                </a>
            @empty
                <div class="card-body muted">No se encontraron familias con ese nombre o RUT.</div>
            @endforelse
        </div>
        {{ $familias->links() }}
    </div>

    <div class="card">
        @if ($seleccionada)
            @php $r = $seleccionada->resumenPagos(); $ult = $seleccionada->solicitudes->first(); @endphp
            <div class="card-header"><div><h2>{{ $seleccionada->nombre_familia }}</h2><div class="small muted">{{ $seleccionada->nombre_contratante }}</div></div><x-estado :valor="$r['estado']" /></div>
            <div class="card-body">
                <div class="ficha">
                    <div class="dato"><div class="k">Contratante</div><div class="v">{{ $seleccionada->nombre_contratante }}</div></div>
                    <div class="dato"><div class="k">RUT (cifrado)</div><div class="v">{{ $seleccionada->rutEnmascarado() }}</div></div>
                    <div class="dato"><div class="k">Teléfono</div><div class="v">{{ $seleccionada->telefono }}</div></div>
                    <div class="dato"><div class="k">Contacto preferido</div><div class="v">{{ etiqueta('medios_contacto', $seleccionada->medio_contacto) }}</div></div>
                    <div class="dato"><div class="k">Contrato</div><div class="v">{{ $ult?->contrato?->numero ?? '—' }}</div></div>
                    <div class="dato"><div class="k">Servicio pedido</div><div class="v">{{ $ult?->nombreServicio() ?? '—' }}</div></div>
                    <div class="dato"><div class="k">Solicitado el</div><div class="v">{{ $ult ? fecha($ult->created_at) : '—' }}</div></div>
                    <div class="dato"><div class="k">Fecha realización</div><div class="v">{{ $ult ? fecha_hora($ult->fecha_hora) : '—' }}</div></div>
                    <div class="dato"><div class="k">Urna</div><div class="v">{{ $ult?->unidad?->codigo ?? $ult?->urna?->codigo ?? '—' }} {{ $ult?->urna?->modelo }}</div></div>
                    <div class="dato"><div class="k">Sucursal</div><div class="v">{{ $ult?->sucursal?->nombre ?? '—' }}</div></div>
                    <div class="dato"><div class="k">Convenio</div><div class="v">{{ $seleccionada->convenio?->institucion ?? ($ult?->convenio?->institucion ?? '—') }}</div></div>
                    <div class="dato"><div class="k">Destino final</div><div class="v">{{ $ult ? etiqueta('destinos', $ult->destino_final) : '—' }}</div></div>
                </div>
                @if ($verFinanzas)
                    <div class="totales">
                        <div><div class="k">Total del servicio</div><div class="v">{{ clp($r['total']) }}</div></div>
                        <div><div class="k">Abonado</div><div class="v">{{ clp($r['abonado']) }}</div></div>
                        <div><div class="k">Saldo pendiente</div><div class="v {{ $r['saldo'] ? 'text-bad' : '' }}">{{ clp($r['saldo']) }}</div></div>
                    </div>
                @endif
            </div>
            @if ($verFinanzas)
                <div class="card-body p0 tabla-wrap">
                    <table class="tabla">
                        <thead><tr><th>Fecha</th><th>Pago</th><th>Medio</th><th class="text-right">Monto</th><th>Estado</th></tr></thead>
                        <tbody>
                        @php $abonos = $seleccionada->solicitudes->flatMap(fn ($s) => $s->contrato?->abonos ?? collect())->sortBy('fecha'); @endphp
                        @forelse ($abonos as $a)
                            <tr class="{{ in_array($a->estado, ['anulado', 'rechazado']) ? 'muted' : '' }}"><td>{{ fecha($a->fecha, 'd M') }}</td><td>{{ $a->referencia ?: 'Abono' }}</td><td>{{ $a->nombreMedio() }}</td><td class="text-right">{{ clp($a->monto) }}</td>
                                <td>@if ($a->estado === 'pendiente')<x-estado valor="pendiente" texto="Por confirmar" />@else<x-estado :valor="$a->estado" />@endif</td></tr>
                        @empty
                            <tr><td colspan="5" class="muted text-center">Sin pagos registrados.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="card-body flex flex-wrap">
                <a class="btn btn-outline btn-sm" href="{{ route('clientes.show', $seleccionada) }}">Ficha completa</a>
                @if ($ult && auth()->user()->puede('solicitudes'))<a class="btn btn-outline btn-sm" href="{{ route('solicitudes.show', $ult) }}">Detalle del servicio</a>@endif
                @if (auth()->user()->puede('solicitudes'))<a class="btn btn-outline btn-sm" href="{{ route('solicitudes.create', ['cliente' => $seleccionada->id]) }}">+ Nueva solicitud</a>@endif
                <a class="btn btn-whatsapp btn-sm" target="_blank" href="{{ whatsapp_url($seleccionada->telefono) }}">WhatsApp</a>
            </div>
        @else
            <div class="card-body muted">Seleccione una familia para ver su ficha.</div>
        @endif
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Usuarios del sistema</h2>@can('administrar-usuarios')<a href="{{ route('usuarios.create') }}" class="btn btn-outline btn-xs">+ Usuario</a>@endcan</div>
        <div class="card-body p0 tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Usuario</th><th>Perfil</th><th>Estado</th>@can('administrar-usuarios')<th></th>@endcan</tr></thead>
                <tbody>
                @foreach ($usuarios as $usr)
                    <tr>
                        <td><span class="strong">{{ $usr->nombre }}</span><div class="xs muted">{{ $usr->email }}</div></td>
                        <td>{{ ucfirst($usr->nombreRol()) }}<div class="xs muted">{{ $usr->nivelAcceso() }}</div></td>
                        <td><x-estado :valor="$usr->estado" /></td>
                        @can('administrar-usuarios')
                            <td class="nowrap">
                                <a href="{{ route('usuarios.edit', $usr) }}" class="btn btn-outline btn-xs">Editar</a>
                                @if ($usr->id !== auth()->id())
                                    <form method="POST" action="{{ route('usuarios.destroy', $usr) }}" class="inline" data-confirmar="¿Eliminar al usuario {{ $usr->nombre }}? Su historial se conserva en la auditoría.">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">Eliminar</button></form>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><h2>Roles</h2>@can('administrar-usuarios')<a href="{{ route('roles.index') }}" class="btn btn-outline btn-xs">Administrar roles</a>@endcan</div>
            <div class="card-body p0">
                <table class="tabla">
                    <tbody>
                    @foreach ($roles as $rl)
                        <tr><td class="strong">{{ ucfirst($rl->nombre) }}</td><td><div class="chips">@foreach (array_slice($rl->etiquetasPermisos(), 0, 4) as $p)<span>{{ $p }}</span>@endforeach @if (count($rl->etiquetasPermisos()) > 4)<span>+{{ count($rl->etiquetasPermisos()) - 4 }}</span>@endif</div></td><td class="small">{{ $rl->usuarios_count }} usuario(s)</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Últimas familias registradas</h2></div>
            <div class="card-body p0">
                <table class="tabla">
                    <tbody>
                    @foreach ($ultimas as $f)
                        <tr><td><x-familia :cliente="$f" :url="route('usuarios.index', ['familia' => $f->id])" /></td><td class="small">{{ fecha($f->created_at) }}</td><td class="small">{{ etiqueta('medios_contacto', $f->medio_contacto) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
