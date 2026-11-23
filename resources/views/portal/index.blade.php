@extends('layouts.app')
@section('titulo', 'Mis solicitudes')
@section('subtitulo', $cliente?->nombre_familia ?? auth()->user()->nombre)
@section('acciones')
    <a href="{{ route('portal.create') }}" class="btn btn-primary">+ Nueva solicitud</a>
@endsection

@section('contenido')
@if ($resumen && $resumen['total'] > 0)
    <div class="grid-3 mb-3">
        <div class="kpi"><div class="lbl">Total contratado</div><div class="val">{{ clp($resumen['total']) }}</div></div>
        <div class="kpi"><div class="lbl">Abonado</div><div class="val text-ok">{{ clp($resumen['abonado']) }}</div></div>
        <div class="kpi"><div class="lbl">Saldo pendiente</div><div class="val {{ $resumen['saldo'] ? 'text-bad' : '' }}">{{ clp($resumen['saldo']) }}</div>
            <div class="det"><a href="{{ route('portal.pagos') }}">Ver detalle de pagos →</a></div></div>
    </div>
@endif

<div class="card">
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Solicitud</th><th>Fallecido</th><th>Servicio</th><th>Fecha</th><th>Urna</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($solicitudes as $s)
                <tr>
                    <td>{{ $s->codigo() }}</td>
                    <td class="strong">{{ $s->nombre_fallecido }}</td>
                    <td>{{ $s->nombreServicio() }}</td>
                    <td class="nowrap">{{ fecha_hora($s->fecha_hora) }}</td>
                    <td>{{ $s->urna?->modelo ?? 'Por definir' }}</td>
                    <td><x-estado :valor="$s->estado" /></td>
                    <td class="nowrap">
                        <a href="{{ route('portal.show', $s) }}" class="btn btn-outline btn-xs">Ver</a>
                        @if ($s->esEditablePorSolicitante())<a href="{{ route('portal.edit', $s) }}" class="btn btn-outline btn-xs">Editar</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center muted">Aún no tiene solicitudes. <a href="{{ route('portal.create') }}">Crear la primera</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="aviso aviso-info small">¿Necesita ayuda? Llámenos al <strong>{{ config('funeraria.telefono') }}</strong> o escríbanos por <a href="{{ whatsapp_url(null, 'Hola, tengo una consulta sobre mi solicitud.') }}" target="_blank">WhatsApp</a>. Atención 24 horas.</div>
@endsection
