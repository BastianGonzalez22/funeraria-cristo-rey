@extends('layouts.app')
@section('titulo', $cliente->nombre_familia)
@section('subtitulo', 'Contratante: '.$cliente->nombre_contratante.' · contratos, servicios y pagos')
@section('acciones')
    <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-outline">Editar</a>
    <a href="{{ route('solicitudes.create', ['cliente' => $cliente->id]) }}" class="btn btn-primary">+ Nueva solicitud</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-header"><h2>Datos de contacto</h2><x-estado :valor="$resumen['estado']" /></div>
    <div class="card-body">
        <div class="ficha">
            <div class="dato"><div class="k">Contratante</div><div class="v">{{ $cliente->nombre_contratante }}</div></div>
            <div class="dato"><div class="k">RUT</div><div class="v">{{ auth()->user()->puede('finanzas') ? $cliente->rutFormateado() : $cliente->rutEnmascarado() }}</div></div>
            <div class="dato"><div class="k">Teléfono</div><div class="v">{{ $cliente->telefono }}</div></div>
            <div class="dato"><div class="k">Correo</div><div class="v">{{ $cliente->email ?: '—' }}</div></div>
            <div class="dato"><div class="k">Domicilio</div><div class="v">{{ $cliente->direccion ?: '—' }} {{ $cliente->comuna }}</div></div>
            <div class="dato"><div class="k">Medio de contacto</div><div class="v">{{ etiqueta('medios_contacto', $cliente->medio_contacto) }}</div></div>
            <div class="dato"><div class="k">Convenio</div><div class="v">{{ $cliente->convenio?->institucion ?? 'Sin convenio' }}</div></div>
            <div class="dato"><div class="k">Urna preferida</div><div class="v">{{ $cliente->urnaPreferida ? $cliente->urnaPreferida->codigo.' '.$cliente->urnaPreferida->modelo : '—' }}</div></div>
            <div class="dato"><div class="k">Portal</div><div class="v">{{ $cliente->usuario ? 'Con cuenta ('.$cliente->usuario->email.')' : 'Sin cuenta' }}</div></div>
        </div>
        <div class="totales">
            <div><div class="k">Total contratado</div><div class="v">{{ clp($resumen['total']) }}</div></div>
            <div><div class="k">Abonado</div><div class="v">{{ clp($resumen['abonado']) }}</div></div>
            <div><div class="k">Saldo pendiente</div><div class="v {{ $resumen['saldo'] ? 'text-bad' : '' }}">{{ clp($resumen['saldo']) }}</div></div>
        </div>
        <div class="flex mt-2">
            <a class="btn btn-whatsapp btn-sm" target="_blank" rel="noopener" href="{{ whatsapp_url($cliente->telefono) }}">WhatsApp</a>
            @if ($cliente->notas)<span class="small muted">Notas: {{ $cliente->notas }}</span>@endif
        </div>
    </div>
</div>

@foreach ($cliente->solicitudes->sortByDesc('fecha_hora') as $s)
    <div class="card">
        <div class="card-header">
            <h2><a href="{{ route('solicitudes.show', $s) }}">{{ $s->codigo() }}</a> · {{ $s->nombreServicio() }} · {{ $s->nombre_fallecido }}</h2>
            <x-estado :valor="$s->estado" />
        </div>
        <div class="card-body">
            <div class="ficha">
                <div class="dato"><div class="k">Fecha de solicitud</div><div class="v">{{ fecha($s->created_at) }}</div></div>
                <div class="dato"><div class="k">Fecha de realización</div><div class="v">{{ fecha_hora($s->fecha_hora) }}</div></div>
                <div class="dato"><div class="k">Urna</div><div class="v">{{ $s->urna ? $s->urna->codigo.' '.$s->urna->modelo : '—' }}</div></div>
                <div class="dato"><div class="k">Sucursal</div><div class="v">{{ $s->sucursal->nombre }}</div></div>
                <div class="dato"><div class="k">Destino final</div><div class="v">{{ etiqueta('destinos', $s->destino_final) }}</div></div>
                <div class="dato"><div class="k">Contrato</div><div class="v">{{ $s->contrato?->numero ?? 'Sin contrato' }}</div></div>
            </div>
            @if ($s->contrato)
                <div class="tabla-wrap mt-2">
                    <table class="tabla">
                        <thead><tr><th>Fecha</th><th>Comprobante</th><th>Medio</th><th class="text-right">Monto</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse ($s->contrato->abonos as $a)
                            <tr><td>{{ fecha($a->fecha) }}</td><td>{{ $a->numero_comprobante }}</td><td>{{ $a->nombreMedio() }}</td><td class="text-right">{{ clp($a->monto) }}</td><td>@if ($a->estado === 'pendiente')<x-estado valor="pendiente" texto="Por confirmar" />@else<x-estado :valor="$a->estado" />@endif</td></tr>
                        @empty
                            <tr><td colspan="5" class="muted text-center">Sin abonos registrados.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot><tr><td colspan="3">Total {{ clp($s->contrato->monto_total) }} · Saldo</td><td class="text-right">{{ clp($s->contrato->saldo_pendiente) }}</td><td><x-estado :valor="$s->contrato->estadoPago()" /></td></tr></tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endforeach
@endsection
