@extends('layouts.app')
@section('titulo', 'Aprobar solicitud '.$s->codigo())
@section('subtitulo', $s->cliente->nombre_familia.' ('.$s->cliente->nombre_contratante.') · '.$s->nombreServicio().' · '.fecha_hora($s->fecha_hora))
@section('acciones')
    <a href="{{ route('solicitudes.show', $s) }}" class="btn btn-outline">Volver</a>
@endsection

@section('contenido')
@if ($conflicto)
    <div class="aviso aviso-warn"><strong>Solapamiento detectado:</strong> {{ $conflicto }}</div>
@endif
@php $aPedido = $s->urna && $s->urna->bajo_pedido; $puedeAprobar = $unidades->isNotEmpty() || $aPedido; @endphp
@if (! $puedeAprobar)
    <div class="aviso aviso-error">No hay urnas disponibles en bodega. Registre una reposición antes de aprobar.</div>
@elseif ($s->urna && ! $elegida && ! $aPedido)
    <div class="aviso aviso-warn">No quedan unidades del modelo solicitado ({{ $s->urna->codigo }} {{ $s->urna->modelo }}). Elija otra urna con la familia.</div>
@endif

<form method="POST" action="{{ route('solicitudes.aprobar', $s) }}" data-calculo data-urna="{{ $s->urna?->precio ?? 0 }}" data-pct="{{ config('funeraria.abono_minimo_pct') }}">
    @csrf
    <div class="grid-2-1">
        <div class="card">
            <div class="card-header"><h2>Contrato</h2><span class="meta">Se generará con número correlativo</span></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="campo"><label>Valor del servicio ({{ $s->nombreServicio() }})</label>
                        <input class="input" type="number" name="monto_servicio" min="0" step="1000" value="{{ old('monto_servicio', $s->precioServicio()) }}" required></div>
                    <div class="campo"><label>Urna que se reserva (código de la unidad) *</label>
                        <select class="input" name="urna_unidad_id" @required(! $aPedido)>
                            @if ($aPedido)<option value="" data-precio="{{ $s->urna->precio }}">A pedido: {{ $s->urna->codigo }} {{ $s->urna->modelo }} — {{ clp($s->urna->precio) }}</option>@endif
                            @foreach ($unidades->groupBy(fn ($u) => $u->urna->modelo) as $modelo => $grupo)
                                <optgroup label="{{ $modelo }} — {{ clp($grupo->first()->urna->precio) }}">
                                    @foreach ($grupo as $un)
                                        <option value="{{ $un->id }}" data-precio="{{ $un->urna->precio }}" @selected(old('urna_unidad_id', $elegida?->id) == $un->id)>{{ $un->codigo }} · {{ $un->urna->modelo }}{{ $un->urna->medidasTexto() ? ' · '.$un->urna->medidasTexto() : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="ayuda">Solo aparecen urnas disponibles. Al aprobar queda reservada y bloqueada para esta familia.</div></div>
                    <div class="campo"><label>Convenio</label>
                        <select class="input" name="convenio_id">
                            <option value="" data-pct="0">Sin convenio</option>
                            @foreach ($convenios as $c)
                                <option value="{{ $c->id }}" data-pct="{{ $c->descuento_pct }}" @selected(old('convenio_id', $s->convenio_id) == $c->id)>{{ $c->institucion }} ({{ $c->descuento_pct }}% dcto.)</option>
                            @endforeach
                        </select></div>
                    <div class="campo"><label>Descuento ($)</label>
                        <input class="input" type="number" name="descuento" min="0" value="{{ old('descuento') }}">
                        <div class="ayuda">Se calcula según el convenio; puede ajustarlo manualmente.</div></div>
                    <div class="campo"><label>Abono inicial ($)</label>
                        <input class="input" type="number" name="abono_inicial" min="0" value="{{ old('abono_inicial') }}"></div>
                    <div class="campo"><label>Medio de pago del abono</label>
                        <select class="input" name="medio_pago">
                            @foreach (config('funeraria.medios_pago') as $k => $v)
                                <option value="{{ $k }}" @selected(old('medio_pago') === $k)>{{ $v }}</option>
                            @endforeach
                        </select></div>
                    <div class="campo"><label>Cuotas para el saldo</label>
                        <input class="input" type="number" name="cuotas" min="1" max="24" value="{{ old('cuotas', 1) }}"></div>
                    <div class="campo"><label>Vencimiento del saldo</label>
                        <input class="input" type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento', $s->fecha_hora->copy()->addDays(config('funeraria.dias_vencimiento'))->format('Y-m-d')) }}"></div>
                    <div class="campo full">
                        <label class="check"><input type="checkbox" name="cuota_mortuoria_en_tramite" value="1" @checked(old('cuota_mortuoria_en_tramite'))> Cuota mortuoria AFP / IPS en trámite (permite aprobar sin el abono mínimo)</label>
                        @if ($conflicto)
                            <label class="check"><input type="checkbox" name="forzar" value="1"> Aprobar igualmente coordinando apoyo externo</label>
                        @endif
                    </div>
                    <div class="campo full"><label>Cláusulas adicionales</label>
                        <textarea class="input" name="clausulas_adicionales" placeholder="Acuerdos particulares con la familia">{{ old('clausulas_adicionales') }}</textarea></div>
                </div>
            </div>
        </div>
        <div>
            <div class="card">
                <div class="card-header"><h2>Resumen</h2></div>
                <div class="card-body">
                    <table class="tabla">
                        <tr><td>Subtotal</td><td class="text-right" data-out="subtotal">—</td></tr>
                        <tr><td><strong>Total contrato</strong></td><td class="text-right strong" data-out="total">—</td></tr>
                        <tr><td>Abono mínimo ({{ config('funeraria.abono_minimo_pct') }}%)</td><td class="text-right text-warn strong" data-out="minimo">—</td></tr>
                    </table>
                    <ul class="reglas mt-2">
                        <li>La urna elegida (con su código) queda reservada para la familia, bloqueada y fuera del catálogo (transacción atómica).</li>
                        <li>Si dos personas aprueban a la vez, el bloqueo de fila evita usar la misma última urna.</li>
                        <li>El abono inicial genera su comprobante digital.</li>
                    </ul>
                </div>
            </div>
            <button class="btn btn-ok" style="width:100%;justify-content:center" @disabled(! $puedeAprobar)>Aprobar y generar contrato</button>
        </div>
    </div>
</form>
@endsection
