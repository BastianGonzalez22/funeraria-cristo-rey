@extends('layouts.app')
@section('titulo', 'Solicitud '.$s->codigo())
@section('subtitulo', $s->nombre_fallecido.' · '.$s->nombreServicio())
@section('acciones')
    <a href="{{ route('portal.index') }}" class="btn btn-outline">Volver</a>
    @if ($s->esEditablePorSolicitante())<a href="{{ route('portal.edit', $s) }}" class="btn btn-primary">Editar</a>@endif
@endsection

@section('contenido')
@if ($s->estado === 'con_observaciones')
    <div class="aviso aviso-warn"><strong>La funeraria solicita:</strong> {{ $s->observacion }} — <a href="{{ route('portal.edit', $s) }}">Editar solicitud</a></div>
@elseif ($s->estado === 'en_revision')
    <div class="aviso aviso-info">Su solicitud está en revisión. Le contactaremos por {{ mb_strtolower(etiqueta('medios_contacto', $s->medio_contacto)) }}.</div>
@elseif ($s->estado === 'rechazada')
    <div class="aviso aviso-error">La solicitud no pudo ser aceptada: {{ $s->observacion }}</div>
@endif

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2>Detalle</h2><x-estado :valor="$s->estado" /></div>
        <div class="card-body">
            <div class="ficha">
                <div class="dato"><div class="k">Fecha y hora</div><div class="v">{{ fecha_hora($s->fecha_hora) }}</div></div>
                <div class="dato"><div class="k">Servicio</div><div class="v">{{ $s->nombreServicio() }}</div></div>
                <div class="dato"><div class="k">Urna</div><div class="v">@include('portal._urna', ['s' => $s])</div></div>
                <div class="dato"><div class="k">Sucursal</div><div class="v">{{ $s->sucursal->nombre }}</div></div>
                <div class="dato"><div class="k">Destino final</div><div class="v">{{ etiqueta('destinos', $s->destino_final) }}</div></div>
                <div class="dato"><div class="k">Velatorio</div><div class="v">{{ $s->lugar_velatorio ?: '—' }}</div></div>
                <div class="dato"><div class="k">Misa</div><div class="v">{{ $s->misa_hora ? fecha_hora($s->misa_hora) : '—' }} {{ $s->misa_lugar }}</div></div>
                <div class="dato"><div class="k">Cementerio / crematorio</div><div class="v">{{ $s->cementerio_crematorio ?: '—' }}</div></div>
                <div class="dato"><div class="k">Certificado</div><div class="v">{{ $s->certificado_path ? 'Recibido' : 'Pendiente' }}</div></div>
            </div>
            @if (! $s->contrato)
                <p class="small muted mt-2 mb-0">Valor estimado: <strong>{{ clp($s->valorEstimado()) }}</strong> (abono mínimo {{ clp(round($s->valorEstimado() * config('funeraria.abono_minimo_pct') / 100)) }}). El valor final queda en el contrato.</p>
            @endif
        </div>
    </div>
    <div>
        @if ($s->contrato)
            @php $c = $s->contrato; @endphp
            <div class="card">
                <div class="card-header"><h2>Contrato {{ $c->numero }}</h2><a href="{{ route('portal.contrato.pdf', $c) }}" target="_blank" class="btn btn-outline btn-xs">Descargar contrato</a></div>
                <div class="card-body">
                    <div class="totales mt-0">
                        <div><div class="k">Total</div><div class="v">{{ clp($c->monto_total) }}</div></div>
                        <div><div class="k">Abonado</div><div class="v">{{ clp($c->totalAbonado()) }}</div></div>
                        <div><div class="k">Saldo</div><div class="v">{{ clp($c->saldo_pendiente) }}</div></div>
                    </div>
                    <p class="small muted mb-0 mt-2"><a href="{{ route('portal.pagos') }}">Ver pagos y comprobantes →</a></p>
                </div>
            </div>
        @endif
        @if ($s->publicar_servicio && in_array($s->estado, ['aprobada', 'finalizada']))
            <div class="card">
                <div class="card-header"><h2>Compartir con familiares</h2></div>
                <div class="card-body">
                    <p class="small mt-0">Página con el velatorio, la misa, el traslado y el libro de condolencias ({{ $s->condolencias->count() }} mensajes).</p>
                    <div class="flex flex-wrap">
                        <a href="{{ route('publico.servicio', $s->token_publico) }}" target="_blank" class="btn btn-outline btn-sm">Abrir</a>
                        <a href="{{ whatsapp_url('', 'Información del servicio de '.$s->nombre_fallecido.': '.route('publico.servicio', $s->token_publico)) }}" target="_blank" class="btn btn-whatsapp btn-sm">Compartir por WhatsApp</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
