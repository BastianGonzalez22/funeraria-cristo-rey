@extends('layouts.app')
@section('titulo', 'Detalle del servicio')
@section('subtitulo', $s->codigo().' · '.$s->cliente->nombre_familia.' ('.$s->cliente->nombre_contratante.') · '.$s->nombreServicio())
@section('acciones')
    <a href="{{ route('solicitudes.index') }}" class="btn btn-outline">Volver</a>
    @if (! in_array($s->estado, ['rechazada', 'finalizada']))
        <a href="{{ route('solicitudes.edit', $s) }}" class="btn btn-outline">Editar solicitud</a>
    @endif
    @if (in_array($s->estado, ['en_revision', 'con_observaciones']))
        <a href="{{ route('solicitudes.aprobar.form', $s) }}" class="btn btn-ok">Aprobar</a>
    @endif
    @if ($s->contrato)
        <a href="{{ route('contratos.pdf', $s->contrato) }}" target="_blank" class="btn btn-primary">Contrato PDF</a>
    @endif
@endsection

@section('contenido')
@php $esAdmin = auth()->user()->puede('finanzas'); $dt = fn ($v) => $v ? $v->format('Y-m-d\TH:i') : ''; @endphp

@if ($s->estado === 'con_observaciones' && $s->observacion)
    <div class="aviso aviso-warn"><strong>Observación al solicitante:</strong> {{ $s->observacion }}</div>
@elseif ($s->estado === 'rechazada')
    <div class="aviso aviso-error"><strong>Rechazada / cancelada:</strong> {{ $s->observacion }}</div>
@endif
@if ($solapadas->isNotEmpty())
    <div class="aviso aviso-warn">Este servicio se cruza con: {{ $solapadas->map(fn ($x) => $x->cliente->nombre_familia.' ('.$x->fecha_hora->format('d/m H:i').')')->implode(', ') }}.
        @if ($s->requiere_apoyo_externo) Marcado con <strong>apoyo externo</strong>. @endif</div>
@endif
@if ($s->caso_especial)
    <div class="aviso aviso-error">Caso especial (muerte violenta / investigación): coordinar con SML y fiscalía antes del retiro.</div>
@endif

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-header">
                <div><h2>{{ $s->cliente->nombre_familia }}</h2><div class="small muted">{{ $s->cliente->nombre_contratante }}</div></div>
                <x-estado :valor="$s->estado" />
            </div>
            <div class="card-body">
                <div class="ficha">
                    <div class="dato"><div class="k">Contratante</div><div class="v"><a href="{{ route('clientes.show', $s->cliente) }}">{{ $s->cliente->nombre_contratante }}</a></div></div>
                    <div class="dato"><div class="k">Teléfono</div><div class="v">{{ $s->cliente->telefono }}</div></div>
                    <div class="dato"><div class="k">RUT contratante</div><div class="v">{{ $s->cliente->rutEnmascarado() }}</div></div>
                    <div class="dato"><div class="k">Fallecido</div><div class="v">{{ $s->nombre_fallecido }}</div></div>
                    <div class="dato"><div class="k">RUT fallecido</div><div class="v">{{ $s->rut_fallecido ? \App\Support\Rut::enmascarar($s->rut_fallecido) : '—' }}</div></div>
                    <div class="dato"><div class="k">Contacto preferido</div><div class="v">{{ etiqueta('medios_contacto', $s->medio_contacto) }}</div></div>
                    <div class="dato"><div class="k">Fecha del servicio</div><div class="v">{{ fecha_hora($s->fecha_hora) }}</div></div>
                    <div class="dato"><div class="k">Sucursal</div><div class="v">{{ $s->sucursal->nombre }}</div></div>
                    <div class="dato"><div class="k">Destino final</div><div class="v">{{ etiqueta('destinos', $s->destino_final) }}</div></div>
                    <div class="dato"><div class="k">Urna</div><div class="v">{{ $s->urna ? $s->urna->modelo : 'Por definir' }}@if ($s->unidad)<br><a href="{{ auth()->user()->puede('stock', 'urnas', 'reposicion') ? route('unidades.show', $s->unidad) : '#' }}" style="font-family:monospace">{{ $s->unidad->codigo }}</a> <x-estado :valor="$s->unidad->estado" />@elseif ($s->estado === 'aprobada' && $s->urna?->bajo_pedido)<br><span class="badge b-info">A pedido</span>@endif</div></div>
                    <div class="dato"><div class="k">Convenio</div><div class="v">{{ $s->convenio?->institucion ?? 'Sin convenio' }}</div></div>
                    <div class="dato"><div class="k">Ingresada por</div><div class="v">{{ $s->solicitante->nombre }} · {{ fecha($s->created_at) }}</div></div>
                </div>
                @if ($s->mensaje)
                    <div class="aviso aviso-info small mt-2 mb-0"><strong>Mensaje:</strong> {{ $s->mensaje }}</div>
                @endif
                <div class="flex flex-wrap mt-2">
                    <a class="btn btn-whatsapp btn-sm" target="_blank" rel="noopener" href="{{ whatsapp_url($s->cliente->telefono, 'Estimada familia, le escribimos de '.config('funeraria.nombre').' respecto del servicio de '.$s->nombre_fallecido.'.') }}">WhatsApp familia</a>
                    <a class="btn btn-outline btn-sm" href="tel:{{ preg_replace('/\s/', '', $s->cliente->telefono) }}">Llamar</a>
                    @if ($s->certificado_path)
                        <a class="btn btn-outline btn-sm" target="_blank" href="{{ route('solicitudes.certificado', $s) }}">Ver certificado de defunción</a>
                    @endif
                </div>
            </div>
        </div>

        @if ($s->contrato)
            @php $c = $s->contrato; @endphp
            <div class="card">
                <div class="card-header"><h2>Contrato {{ $c->numero }}</h2><x-estado :valor="$c->estadoPago()" /></div>
                <div class="card-body">
                    <div class="totales mt-0">
                        <div><div class="k">Total</div><div class="v">{{ clp($c->monto_total) }}</div></div>
                        <div><div class="k">Abonado</div><div class="v">{{ clp($c->totalAbonado()) }}</div></div>
                        <div><div class="k">Saldo</div><div class="v {{ $c->saldo_pendiente ? 'text-bad' : 'text-ok' }}">{{ clp($c->saldo_pendiente) }}</div></div>
                    </div>
                    <p class="small muted mt-2 mb-0">
                        Abono mínimo {{ clp($c->abono_minimo) }} — {{ $c->cumpleAbonoMinimo() ? 'cumplido' : 'faltan '.clp($c->faltaParaMinimo()) }}.
                        @if ($c->cuotas > 1) Saldo en {{ $c->cuotas }} cuotas de {{ clp($c->valorCuota()) }}. @endif
                        Vence el {{ fecha($c->fecha_vencimiento) }}.
                        @if ($c->cuota_mortuoria_en_tramite) <span class="badge b-info">Cuota mortuoria en trámite</span> @endif
                    </p>
                    @if ($esAdmin)
                        <div class="flex mt-2">
                            <a class="btn btn-primary btn-sm" href="{{ route('abonos.index', ['contrato' => $c->id]) }}">Registrar abono</a>
                            <a class="btn btn-outline btn-sm" href="{{ route('contratos.show', $c) }}">Ver contrato y pagos</a>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if ($s->estado === 'aprobada' && auth()->user()->puede('urnas'))
            <div class="card" id="cambiar-urna">
                <div class="card-header"><h2>Urna de la familia</h2>@if ($s->unidad)<x-estado :valor="$s->unidad->estado" />@endif</div>
                <div class="card-body">
                    @if ($s->unidad)
                        <p class="mt-0 mb-1"><strong style="font-family:monospace;font-size:16px">{{ $s->unidad->codigo }}</strong> · {{ $s->urna->modelo }} · {{ $s->urna->medidasTexto() }}</p>
                    @else
                        <p class="mt-0 mb-1">{{ $s->urna?->modelo }} — <span class="badge b-info">se fabrica a pedido</span></p>
                    @endif
                    <div class="flex flex-wrap">
                        @if ($s->unidad && $s->unidad->estado === 'reservada')
                            <form method="POST" action="{{ route('unidades.comprar', $s->unidad) }}" data-confirmar="¿Confirmar la compra de la urna {{ $s->unidad->codigo }}?">@csrf<button class="btn btn-ok btn-sm">Comprar (confirmar venta)</button></form>
                        @endif
                        @if ($s->unidad)<a href="{{ route('unidades.etiquetas', ['ids' => $s->unidad->id]) }}" target="_blank" class="btn btn-outline btn-sm">Etiqueta</a>@endif
                    </div>
                    <form method="POST" action="{{ route('unidades.cambiar', $s) }}" class="mt-2" data-confirmar="¿Cambiar la urna? La actual volverá a quedar disponible y el contrato se ajustará al nuevo precio.">
                        @csrf
                        <label class="lbl">¿La familia quiere otra? Cambiar por:</label>
                        <div class="flex">
                            <select class="input" name="urna_unidad_id" required>
                                <option value="">Seleccione una urna disponible…</option>
                                @foreach ($unidadesLibres->groupBy(fn ($u) => $u->urna->modelo) as $modelo => $grupo)
                                    <optgroup label="{{ $modelo }} — {{ clp($grupo->first()->urna->precio) }}">
                                        @foreach ($grupo as $un)<option value="{{ $un->id }}">{{ $un->codigo }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button class="btn btn-outline btn-sm">Cambiar</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('solicitudes.detalle', $s) }}">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><h2>Elementos a entregar</h2><span class="meta">{{ collect($s->elementos)->filter()->count() }}/{{ count(config('funeraria.elementos')) }} listos</span></div>
                <div class="card-body checks-grid">
                    @foreach (config('funeraria.elementos') as $k => $v)
                        <label class="check"><input type="checkbox" name="elementos[]" value="{{ $k }}" @checked($s->elementoEntregado($k))> {{ $v }}</label>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Documentos recibidos</h2></div>
                <div class="card-body checks-grid">
                    @foreach (config('funeraria.documentos') as $k => $v)
                        <label class="check"><input type="checkbox" name="documentos[]" value="{{ $k }}" @checked($s->documentoRecibido($k))> {{ $v }}</label>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Personal asignado</h2><button type="button" class="btn btn-outline btn-xs" data-agregar-fila="#filas-personal">+ Agregar</button></div>
                <div class="card-body p0">
                    <table class="tabla">
                        <thead><tr><th>Tarea</th><th>Persona(s)</th></tr></thead>
                        <tbody id="filas-personal">
                        @php $personal = $s->personal ?: collect(config('funeraria.tareas_personal'))->map(fn ($t) => ['tarea' => $t, 'persona' => ''])->values()->all(); @endphp
                        @foreach ($personal as $i => $p)
                            <tr>
                                <td><input class="input" name="personal[{{ $i }}][tarea]" value="{{ $p['tarea'] ?? '' }}"></td>
                                <td><input class="input" name="personal[{{ $i }}][persona]" value="{{ $p['persona'] ?? '' }}" placeholder="Ej: J. Pérez y P. Lillo" list="lista-choferes"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <datalist id="lista-choferes">@foreach ($choferes as $ch)<option value="{{ $ch->nombre }}">@endforeach</datalist>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Permisos</h2></div>
                <div class="card-body form-grid">
                    <div class="campo"><label>Traslado {{ $s->fuera_comuna ? '(fuera de la comuna: '.($s->comuna_destino ?: 's/i').')' : '' }}</label>
                        <select class="input" name="permiso_traslado">
                            @foreach (config('funeraria.estados_permiso') as $k => $v)<option value="{{ $k }}" @selected($s->permiso_traslado === $k)>{{ $v }}</option>@endforeach
                        </select></div>
                    <div class="campo"><label>Permiso sanitario (SEREMI de Salud)</label>
                        <select class="input" name="permiso_sanitario">
                            @foreach (config('funeraria.estados_permiso') as $k => $v)<option value="{{ $k }}" @selected($s->permiso_sanitario === $k)>{{ $v }}</option>@endforeach
                        </select></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Velatorio, ceremonia y cementerio</h2></div>
                <div class="card-body form-grid">
                    <div class="campo"><label>Lugar del velatorio</label><input class="input" name="lugar_velatorio" value="{{ $s->lugar_velatorio }}"></div>
                    <div class="campo"><label>Misa / ceremonia</label><input class="input" name="misa_lugar" value="{{ $s->misa_lugar }}"></div>
                    <div class="campo"><label>Hora de misa</label><input class="input" type="datetime-local" name="misa_hora" value="{{ $dt($s->misa_hora) }}"></div>
                    <div class="campo"><label>Cementerio / crematorio</label><input class="input" name="cementerio_crematorio" value="{{ $s->cementerio_crematorio }}"></div>
                    <div class="campo"><label>Hora sepultura / cremación</label><input class="input" type="datetime-local" name="sepultura_hora" value="{{ $dt($s->sepultura_hora) }}"></div>
                    <div class="campo"><label>Contacto cementerio</label><input class="input" name="cementerio_contacto" value="{{ $s->cementerio_contacto }}" placeholder="Administración — teléfono"></div>
                    <div class="campo full">
                        <label class="check"><input type="checkbox" name="cementerio_confirmado" value="1" @checked($s->cementerio_confirmado)> Hora confirmada con el cementerio / crematorio</label>
                        <label class="check"><input type="checkbox" name="publicar_servicio" value="1" @checked($s->publicar_servicio)> Publicar página del servicio para familiares (velatorio, misa, traslado y libro de condolencias)</label>
                    </div>
                    @if ($s->estado !== 'con_observaciones')
                        <div class="campo full"><label>Notas internas</label><textarea class="input" name="observacion">{{ $s->observacion }}</textarea></div>
                    @endif
                </div>
            </div>
            <div class="form-acciones mt-0 mb-3"><button class="btn btn-primary">Guardar detalle del servicio</button></div>
        </form>
    </div>

    <div>
        <div class="card">
            <div class="card-header">
                <h2>Ruta y horarios</h2>
                @if ($s->estado === 'aprobada' && auth()->user()->puede('flota'))
                    <a href="{{ route('flota.editar', $s) }}" class="btn btn-outline btn-xs">{{ $s->asignacion ? 'Modificar asignación' : 'Asignar vehículo' }}</a>
                @endif
            </div>
            <div class="card-body p0">
                @if ($s->asignacion)
                    @php $a = $s->asignacion; @endphp
                    <div class="card-body small">
                        <strong>{{ $a->vehiculo->nombre }}</strong> · Chofer {{ $a->chofer->nombre }} · {{ $a->inicio->format('d/m H:i') }}–{{ $a->fin->format('d/m H:i') }}
                        <x-estado :valor="$a->estado" />
                        @if ($a->apoyo_externo)<span class="badge b-info">Apoyo externo</span>@endif
                    </div>
                    <table class="tabla">
                        <thead><tr><th>Parada</th><th>Dirección</th><th>Hora</th></tr></thead>
                        <tbody>
                        <tr><td>Salida</td><td>{{ $a->origen ?: '—' }}</td><td>{{ $a->inicio->format('H:i') }}</td></tr>
                        @foreach ($a->hoja_ruta ?? [] as $p)
                            <tr><td>{{ $p['lugar'] ?? '' }}</td><td>@if (! empty($p['direccion']))<a href="{{ mapa_url($p['direccion']) }}" target="_blank">{{ $p['direccion'] }}</a>@else — @endif</td><td>{{ $p['hora'] ?? '' }}</td></tr>
                        @endforeach
                        <tr><td>Destino</td><td>{{ $a->destino ?: '—' }}</td><td>{{ $a->fin->format('H:i') }}</td></tr>
                        </tbody>
                    </table>
                    <div class="card-body flex flex-wrap">
                        <a class="btn btn-whatsapp btn-sm" target="_blank" rel="noopener" href="{{ whatsapp_url($a->chofer->telefono, $a->textoWhatsapp()) }}">Enviar hoja de ruta al chofer</a>
                    </div>
                @else
                    <div class="card-body muted small">{{ $s->estado === 'aprobada' ? 'Aún no se asigna vehículo ni chofer.' : 'La flota se asigna una vez aprobada la solicitud.' }}</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Coordinación con cementerio o crematorio</h2></div>
            <div class="card-body small">
                <div class="ficha">
                    <div class="dato"><div class="k">Destino</div><div class="v">{{ etiqueta('destinos', $s->destino_final) }}</div></div>
                    <div class="dato"><div class="k">Lugar</div><div class="v">{{ $s->cementerio_crematorio ?: '—' }}</div></div>
                    <div class="dato"><div class="k">Hora</div><div class="v">{{ $s->sepultura_hora ? fecha_hora($s->sepultura_hora) : '—' }}</div></div>
                </div>
                <p class="mb-0 mt-2">{!! $s->cementerio_confirmado ? '<span class="badge b-ok">Confirmado</span>' : '<span class="badge b-warn">Por confirmar</span>' !!} {{ $s->cementerio_contacto }}</p>
            </div>
        </div>

        @if ($s->publicar_servicio && in_array($s->estado, ['aprobada', 'finalizada']))
            @php $url = route('publico.servicio', $s->token_publico); @endphp
            <div class="card">
                <div class="card-header"><h2>Página para familiares</h2><span class="meta">{{ $s->condolencias->count() }} condolencia(s)</span></div>
                <div class="card-body">
                    <p class="small muted mt-0">Comparta este enlace: muestra el velatorio, la misa, el traslado y el libro de condolencias digital.</p>
                    <div class="flex flex-wrap">
                        <a href="{{ $url }}" target="_blank" class="btn btn-outline btn-sm">Abrir página</a>
                        <button type="button" class="btn btn-outline btn-sm" data-copiar="{{ $url }}">Copiar enlace</button>
                        <a class="btn btn-whatsapp btn-sm" target="_blank" href="{{ whatsapp_url($s->cliente->telefono, 'Información del servicio de '.$s->nombre_fallecido.': '.$url) }}">Enviar por WhatsApp</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2>Acciones</h2></div>
            <div class="card-body flex flex-wrap">
                @if (in_array($s->estado, ['en_revision', 'con_observaciones']))
                    <form method="POST" action="{{ route('solicitudes.observar', $s) }}" class="inline">@csrf<input type="hidden" name="observacion">
                        <button class="btn btn-outline btn-sm" data-motivo="observacion" data-pregunta="¿Qué debe corregir o completar el solicitante?">Observar</button></form>
                @endif
                @if ($s->estado === 'aprobada')
                    <form method="POST" action="{{ route('solicitudes.finalizar', $s) }}" class="inline" data-confirmar="¿Confirmar que el servicio fue realizado? La urna se descontará de bodega.">@csrf
                        <button class="btn btn-ok btn-sm">Marcar servicio realizado</button></form>
                @endif
                @if (! in_array($s->estado, ['rechazada', 'finalizada']))
                    <form method="POST" action="{{ route('solicitudes.rechazar', $s) }}" class="inline">@csrf<input type="hidden" name="motivo">
                        <button class="btn btn-danger btn-sm" data-motivo="motivo" data-pregunta="Motivo del rechazo o cancelación:">{{ $s->estado === 'aprobada' ? 'Cancelar servicio' : 'Rechazar' }}</button></form>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Registro de modificaciones</h2></div>
            <div class="card-body">
                <ul class="timeline">
                    @forelse ($modificaciones as $m)
                        <li><strong>{{ fecha($m->created_at, 'd M, H:i') }}</strong> · {{ $m->usuario?->nombre ?? 'Sistema' }}<br>{{ $m->descripcion }}
                            @if (is_array($m->detalle) && $m->evento === 'solicitud_modificada')
                                <div class="xs muted">@foreach ($m->detalle as $campo => $cambio){{ $campo }}: {{ $cambio['antes'] ?? '' }} → {{ $cambio['despues'] ?? '' }}@if (! $loop->last); @endif @endforeach</div>
                            @endif
                        </li>
                    @empty
                        <li class="muted">Sin modificaciones.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
