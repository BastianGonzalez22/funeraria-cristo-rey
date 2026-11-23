@extends('layouts.app')
@section('titulo', 'Flota y Cortejos')
@section('subtitulo', 'Calendario de disponibilidad de vehículos y choferes')
@section('acciones')
    <a href="{{ route('itinerario.index', ['semana' => $lunes->toDateString()]) }}" class="btn btn-outline">Rutas de choferes</a>
@endsection

@section('contenido')
<div class="grid-2-1">
    <div class="card">
        <div class="card-header">
            <h2>Semana del {{ $lunes->translatedFormat('d') }} al {{ $lunes->copy()->addDays(6)->translatedFormat('d M Y') }}</h2>
            <div class="flex">
                <a href="{{ route('flota.index', ['semana' => $lunes->copy()->subWeek()->toDateString()]) }}" class="btn btn-outline btn-sm">‹ Anterior</a>
                <a href="{{ route('flota.index') }}" class="btn btn-outline btn-sm">Hoy</a>
                <a href="{{ route('flota.index', ['semana' => $lunes->copy()->addWeek()->toDateString()]) }}" class="btn btn-outline btn-sm">Siguiente ›</a>
                <form method="GET" class="inline"><input class="input" type="date" name="semana" value="{{ $lunes->toDateString() }}" onchange="this.form.submit()" style="width:150px;padding:4px 8px" title="Ir a la semana de…"></form>
            </div>
        </div>
        <div class="card-body p0 tabla-wrap">
            <table class="tabla semana">
                <thead><tr><th>Vehículo</th>@foreach ($dias as $d)<th class="{{ $d->isToday() ? 'strong' : '' }}">{{ $d->translatedFormat('D d') }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($vehiculos as $v)
                    <tr>
                        <td><span class="strong">{{ $v->nombre }}</span><div class="xs muted">{{ $v->patente }} @if($v->estado === 'en_mantencion')· <span class="text-bad">mantención</span>@endif</div></td>
                        @foreach ($dias as $d)
                            <td>
                                @forelse ($matriz[$v->id][$d->toDateString()] ?? [] as $a)
                                    <a href="{{ route('solicitudes.show', $a->solicitud_id) }}" class="celda-ocupada {{ $a->estado === 'completada' ? 'ok' : '' }}" title="{{ $a->solicitud->cliente->nombre_familia }} ({{ $a->solicitud->cliente->nombre_contratante }}) · {{ $a->chofer->nombre }}">
                                        {{ $a->inicio->isSameDay($d) ? $a->inicio->format('H:i') : '…' }} {{ \Illuminate\Support\Str::limit(str_replace('Familia ', '', $a->solicitud->cliente->nombre_familia), 10) }}
                                    </a>
                                @empty
                                    <span class="celda-libre">Libre</span>
                                @endforelse
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr>
                    <td><span class="strong">Choferes ausentes</span></td>
                    @foreach ($dias as $d)
                        <td class="xs">
                            @foreach ($choferes as $ch)
                                @if ($ch->ausenteEl($d))<div class="text-bad">{{ $ch->nombreCorto() }}</div>@endif
                            @endforeach
                        </td>
                    @endforeach
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Reglas de asignación</h2></div>
        <div class="card-body">
            <ul class="reglas">
                <li>Un mismo vehículo o chofer no puede asignarse a dos servicios con horarios solapados.</li>
                <li>Solo los vehículos disponibles (no en mantención) aparecen para asignar.</li>
                <li>Las vacaciones y permisos registrados bloquean al chofer en esas fechas.</li>
                <li>Si se acumulan servicios (máx. {{ config('funeraria.max_servicios_dia') }} al día), use “Apoyo externo” (carroza de otra funeraria, p. ej. Perquenco).</li>
                <li>El itinerario con origen, paradas y destino se envía al chofer por WhatsApp con enlaces a Google Maps.</li>
            </ul>
        </div>
    </div>
</div>

<div class="card" id="asignar">
    <div class="card-header"><h2>Asignar vehículo a servicio</h2><span class="meta">{{ $pendientes->whereNull('asignacion')->count() }} servicio(s) aprobado(s) sin flota</span></div>
    <div class="card-body">
        @php $sel = $seleccionada; $asig = $sel?->asignacion; @endphp
        <form method="POST" action="{{ route('flota.asignar') }}">
            @csrf
            <div class="form-grid cols-3">
                <div class="campo" style="grid-column: span 2"><label>Servicio *</label>
                    <select class="input" name="solicitud_id" required onchange="location.href='{{ route('flota.index') }}?solicitud='+this.value+'#asignar'">
                        <option value="">Seleccione…</option>
                        @foreach ($pendientes as $p)
                            <option value="{{ $p->id }}" @selected(old('solicitud_id', $sel?->id) == $p->id)>{{ $p->cliente->nombre_familia }} ({{ $p->cliente->nombre_contratante }}) — {{ fecha($p->fecha_hora, 'D d M, H:i') }} {{ $p->asignacion ? '(asignado)' : '' }}</option>
                        @endforeach
                    </select></div>
                <div class="campo"><label>Vehículo *</label>
                    <select class="input" name="vehiculo_id" required>
                        @foreach ($vehiculos->where('estado', '!=', 'en_mantencion') as $v)
                            <option value="{{ $v->id }}" @selected(old('vehiculo_id', $asig?->vehiculo_id) == $v->id)>{{ $v->nombre }} ({{ \App\Models\Vehiculo::TIPOS[$v->tipo] }})</option>
                        @endforeach
                    </select></div>
                <div class="campo"><label>Chofer *</label>
                    <select class="input" name="chofer_id" required>
                        @foreach ($choferes as $ch)
                            <option value="{{ $ch->id }}" @selected(old('chofer_id', $asig?->chofer_id) == $ch->id) @disabled($ch->estado === 'no_disponible')>{{ $ch->nombre }}{{ $ch->jornada === 'part_time' ? ' (part-time)' : '' }}{{ $ch->jornada === 'externo' ? ' (externo)' : '' }}{{ $sel && $ch->ausenteEl($sel->fecha_hora) ? ' — AUSENTE' : '' }}</option>
                        @endforeach
                    </select></div>
                <div class="campo"><label>Inicio *</label>
                    <input class="input" type="datetime-local" name="inicio" required value="{{ old('inicio', ($asig?->inicio ?? $sel?->fecha_hora?->copy()->subHour())?->format('Y-m-d\TH:i')) }}"></div>
                <div class="campo"><label>Término *</label>
                    <input class="input" type="datetime-local" name="fin" required value="{{ old('fin', ($asig?->fin ?? $sel?->fecha_hora?->copy()->addHours(3))?->format('Y-m-d\TH:i')) }}"></div>
                <div class="campo"><label>Origen</label><input class="input" name="origen" value="{{ old('origen', $asig?->origen ?? $sel?->lugar_velatorio) }}" placeholder="Domicilio del velatorio"></div>
                <div class="campo"><label>Destino</label><input class="input" name="destino" value="{{ old('destino', $asig?->destino ?? $sel?->cementerio_crematorio) }}" placeholder="Cementerio General de Temuco"></div>
                <div class="campo"><label>Notas para el chofer</label><input class="input" name="notas" value="{{ old('notas', $asig?->notas) }}" placeholder="Recorrido por lugar de trabajo, etc."></div>
            </div>

            <h3 class="mt-3 mb-1">Hoja de ruta <button type="button" class="btn btn-outline btn-xs" data-agregar-fila="#filas-ruta">+ Parada</button></h3>
            <table class="tabla">
                <thead><tr><th style="width:110px">Hora</th><th>Lugar</th><th>Dirección</th></tr></thead>
                <tbody id="filas-ruta">
                @php
                    $paradas = old('hoja_ruta', $asig?->hoja_ruta ?: array_filter([
                        $sel?->lugar_velatorio ? ['hora' => $sel->fecha_hora->format('H:i'), 'lugar' => 'Velatorio', 'direccion' => $sel->lugar_velatorio] : null,
                        $sel?->misa_lugar ? ['hora' => $sel->misa_hora?->format('H:i'), 'lugar' => 'Misa / ceremonia', 'direccion' => $sel->misa_lugar] : null,
                        $sel?->cementerio_crematorio ? ['hora' => $sel->sepultura_hora?->format('H:i'), 'lugar' => 'Cementerio / crematorio', 'direccion' => $sel->cementerio_crematorio] : null,
                    ]));
                    $paradas = array_values($paradas ?: [['hora' => '', 'lugar' => '', 'direccion' => '']]);
                @endphp
                @foreach ($paradas as $i => $p)
                    <tr>
                        <td><input class="input" type="time" name="hoja_ruta[{{ $i }}][hora]" value="{{ $p['hora'] ?? '' }}"></td>
                        <td><input class="input" name="hoja_ruta[{{ $i }}][lugar]" value="{{ $p['lugar'] ?? '' }}"></td>
                        <td><input class="input" name="hoja_ruta[{{ $i }}][direccion]" value="{{ $p['direccion'] ?? '' }}"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="form-acciones"><button class="btn btn-primary">Asignar y guardar hoja de ruta</button></div>
        </form>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Ausencias del personal</h2><span class="meta">Vacaciones y permisos</span></div>
        <div class="card-body p0">
            <table class="tabla">
                <thead><tr><th>Chofer</th><th>Motivo</th><th>Período</th><th>Efecto</th><th></th></tr></thead>
                <tbody>
                @forelse ($ausencias as $a)
                    <tr>
                        <td>{{ $a->chofer->nombre }}</td>
                        <td>{{ \App\Models\AusenciaChofer::MOTIVOS[$a->motivo] }}</td>
                        <td class="nowrap">{{ $a->desde->format('d/m') }} – {{ $a->hasta->format('d/m') }}</td>
                        <td><span class="badge {{ $a->estado === 'vigente' ? 'b-bad' : 'b-warn' }}">{{ $a->estado === 'vigente' ? 'No disponible' : 'Programada' }}</span></td>
                        <td><form method="POST" action="{{ route('flota.ausencia.eliminar', $a) }}" data-confirmar="¿Eliminar esta ausencia?">@csrf @method('DELETE')<button class="btn btn-danger btn-xs">×</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted text-center">Sin ausencias registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Registrar vacaciones o permiso</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('flota.ausencia') }}">
                @csrf
                <div class="form-grid">
                    <div class="campo"><label>Chofer</label><select class="input" name="chofer_id">@foreach ($choferes as $ch)<option value="{{ $ch->id }}">{{ $ch->nombre }}</option>@endforeach</select></div>
                    <div class="campo"><label>Motivo</label><select class="input" name="motivo">@foreach (\App\Models\AusenciaChofer::MOTIVOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="campo"><label>Desde</label><input class="input" type="date" name="desde" value="{{ now()->format('Y-m-d') }}" required></div>
                    <div class="campo"><label>Hasta</label><input class="input" type="date" name="hasta" value="{{ now()->format('Y-m-d') }}" required></div>
                    <div class="campo full"><label>Detalle</label><input class="input" name="detalle"></div>
                </div>
                <div class="form-acciones"><button class="btn btn-primary">Registrar</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
