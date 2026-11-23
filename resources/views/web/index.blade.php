@extends('layouts.app')
@section('titulo', 'Sitio web y opiniones')
@section('subtitulo', 'Mensajes del formulario, comentarios de clientes y libro de condolencias')
@section('acciones')
    <a href="{{ route('inicio') }}" target="_blank" class="btn btn-outline">Ver sitio público</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-header"><h2>Mensajes recibidos</h2><span class="meta">{{ $mensajes->where('atendido', false)->count() }} sin atender</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Nombre</th><th>Contacto</th><th>Mensaje</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($mensajes as $m)
                <tr class="{{ $m->atendido ? 'muted' : '' }}">
                    <td class="nowrap small">{{ fecha($m->created_at, 'd M, H:i') }}</td>
                    <td class="strong">{{ $m->nombre }}</td>
                    <td class="small">{{ $m->telefono }}<br>{{ $m->email }}<div class="xs">Prefiere: {{ etiqueta('medios_contacto', $m->medio_contacto) }}</div></td>
                    <td class="small" style="max-width:340px">{{ $m->mensaje }} @if ($m->urna)<div class="xs"><span class="badge b-info">Consulta por {{ $m->urna->codigo }} {{ $m->urna->modelo }}</span></div>@endif</td>
                    <td>{!! $m->atendido ? '<span class="badge b-ok">Atendido</span>' : '<span class="badge b-warn">Pendiente</span>' !!}</td>
                    <td class="nowrap">
                        <a class="btn btn-whatsapp btn-xs" target="_blank" href="{{ whatsapp_url($m->telefono, 'Hola '.$m->nombre.', le escribimos de '.config('funeraria.nombre').' por su consulta.') }}">WhatsApp</a>
                        <form method="POST" action="{{ route('web.mensaje', $m) }}" class="inline">@csrf<button class="btn btn-outline btn-xs">{{ $m->atendido ? 'Reabrir' : 'Atendido' }}</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted text-center">Sin mensajes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Opiniones de clientes</h2><span class="meta">Se publican después de revisarlas</span></div>
        <div class="card-body">
            @forelse ($opiniones as $o)
                <div class="opinion mb-1">
                    <div class="flex between"><strong>{{ $o->nombre }}</strong><span class="estrellas">{{ $o->estrellas() }}</span></div>
                    <p class="small mb-1">{{ $o->comentario }}</p>
                    <form method="POST" action="{{ route('web.opinion', $o) }}">@csrf<button class="btn {{ $o->publicada ? 'btn-outline' : 'btn-primary' }} btn-xs">{{ $o->publicada ? 'Ocultar' : 'Publicar' }}</button></form>
                </div>
            @empty
                <p class="muted">Sin opiniones.</p>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Libro de condolencias</h2></div>
        <div class="card-body">
            @forelse ($condolencias as $c)
                <div class="condolencia {{ $c->visible ? '' : 'muted' }}">
                    <div class="flex between"><strong>{{ $c->nombre }}</strong><span class="xs muted">{{ $c->solicitud->cliente->nombre_familia }} ({{ $c->solicitud->cliente->nombre_contratante }}) · {{ fecha($c->created_at, 'd M') }}</span></div>
                    <p class="small mb-1 mt-0">{{ $c->mensaje }}</p>
                    <form method="POST" action="{{ route('web.condolencia', $c) }}">@csrf<button class="btn btn-outline btn-xs">{{ $c->visible ? 'Ocultar' : 'Mostrar' }}</button></form>
                </div>
            @empty
                <p class="muted">Sin mensajes en los libros de condolencias.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
