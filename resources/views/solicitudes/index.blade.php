@extends('layouts.app')
@section('titulo', 'Solicitudes de Reserva')
@section('subtitulo', 'Flujo de evaluación y aprobación')
@section('acciones')
    <a href="{{ route('solicitudes.exportar', request()->query()) }}" class="btn btn-outline">Exportar (Excel)</a>
    <a href="{{ route('solicitudes.create') }}" class="btn btn-primary">+ Nueva solicitud</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-header">
        <h2>Listado de solicitudes</h2>
        <form method="GET" class="filtros">
            <input class="input" name="q" value="{{ request('q') }}" placeholder="Nombre familia, contratante o fallecido" style="width:230px">
            <input class="input" name="rut" value="{{ request('rut') }}" placeholder="RUT contratante" style="width:140px" data-rut>
            <input class="input" type="month" name="mes" value="{{ $mes }}" style="width:150px" title="Filtrar por mes">
            <select class="input" name="estado" style="width:190px" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                @foreach (\App\Models\Solicitud::ESTADOS as $k => $v)
                    <option value="{{ $k }}" @selected($estado === $k)>{{ $v }} ({{ $conteos[$k] ?? 0 }})</option>
                @endforeach
            </select>
            <button class="btn btn-outline btn-sm">Filtrar</button>
            @if (request()->hasAny(['q', 'rut', 'mes', 'estado']))<a href="{{ route('solicitudes.index') }}" class="small">Limpiar</a>@endif
        </form>
    </div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead>
            <tr><th>Código</th><th>Familia</th><th>Fecha / hora</th><th>Servicio</th><th>Urna</th><th>Mensaje</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody>
            @forelse ($solicitudes as $s)
                <tr>
                    <td class="small muted">{{ $s->codigo() }}</td>
                    <td><x-familia :cliente="$s->cliente" :url="route('solicitudes.show', $s)" />
                        @if ($s->caso_especial)<span class="badge b-bad" title="Muerte violenta / investigación">Caso especial</span>@endif
                        @if ($s->requiere_apoyo_externo)<span class="badge b-info">Apoyo externo</span>@endif
                    </td>
                    <td class="nowrap">{{ fecha($s->fecha_hora, 'd M, H:i') }}</td>
                    <td>{{ $s->nombreServicio() }}</td>
                    <td>{{ $s->urna ? $s->urna->modelo : '—' }}@if ($s->urna_unidad_id)<div class="xs muted" style="font-family:monospace">{{ $s->unidad?->codigo }}</div>@endif</td>
                    <td class="small muted" style="max-width:200px">{{ \Illuminate\Support\Str::limit($s->observacion ?: $s->mensaje, 50) ?: '—' }}</td>
                    <td><x-estado :valor="$s->estado" /></td>
                    <td>
                        <div class="acciones">
                            @if (in_array($s->estado, ['en_revision', 'con_observaciones']))
                                <a href="{{ route('solicitudes.aprobar.form', $s) }}" class="btn btn-ok btn-xs">Aprobar</a>
                                <form method="POST" action="{{ route('solicitudes.observar', $s) }}" class="inline">
                                    @csrf <input type="hidden" name="observacion">
                                    <button class="btn btn-danger btn-xs" data-motivo="observacion" data-pregunta="¿Qué debe corregir o completar el solicitante?">Observar</button>
                                </form>
                            @endif
                            <a href="{{ route('solicitudes.show', $s) }}" class="btn btn-outline btn-xs">Ver detalle</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center muted">No hay solicitudes con este filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $solicitudes->links() }}
</div>

<div class="aviso aviso-info small">
    <strong>Regla de validación de solapamiento:</strong> una solicitud no puede programarse si su horario se cruza
    (±{{ config('funeraria.ventana_solapamiento_horas') }} h) con otro servicio activo y no queda carroza disponible, ni si el día ya tiene
    {{ config('funeraria.max_servicios_dia') }} servicios. El personal puede forzarla marcando “apoyo externo” (carroza de otra funeraria).
    Al aprobar se reserva y descuenta la urna y se exige el abono mínimo del {{ config('funeraria.abono_minimo_pct') }}%.
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Permisos por gestionar</h2><span class="meta">Traslados y permisos sanitarios</span></div>
        <div class="card-body p0 tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Familia</th><th>Traslado</th><th>Sanitario (SEREMI)</th><th>Servicio</th></tr></thead>
                <tbody>
                @forelse ($permisos as $s)
                    <tr>
                        <td><x-familia :cliente="$s->cliente" :url="route('solicitudes.show', $s)" />
                            @if ($s->fuera_comuna)<div class="xs muted">Fuera de la comuna: {{ $s->comuna_destino ?: 's/i' }}</div>@endif</td>
                        <td><x-estado :valor="$s->permiso_traslado" /></td>
                        <td><x-estado :valor="$s->permiso_sanitario" /></td>
                        <td class="nowrap">{{ fecha($s->fecha_hora, 'd M, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted text-center">Sin permisos pendientes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Registro de modificaciones</h2><span class="meta">Auditoría de contratos y solicitudes</span></div>
        <div class="card-body p0 tabla-wrap">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Cambio</th></tr></thead>
                <tbody>
                @forelse ($modificaciones as $m)
                    <tr>
                        <td class="nowrap small">{{ fecha($m->created_at, 'd M, H:i') }}</td>
                        <td class="small">{{ $m->usuario?->nombre ?? 'Sistema' }}</td>
                        <td class="small">{{ $m->descripcion }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted text-center">Sin registros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
