@extends('layouts.app')
@php $editando = $solicitud->exists; @endphp
@section('titulo', $editando ? 'Editar solicitud' : 'Nueva Solicitud de Reserva')
@section('subtitulo', 'Portal de usuario — complete los datos y la funeraria revisará su solicitud')

@section('contenido')
@if ($editando && $solicitud->estado === 'con_observaciones')
    <div class="aviso aviso-warn"><strong>La funeraria indicó:</strong> {{ $solicitud->observacion }}</div>
@endif
@php
    $dt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
    $docs = old('documentos', array_keys(array_filter($solicitud->documentos ?? [])));
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('portal.update', $solicitud) : route('portal.store') }}" data-wizard>
    @csrf
    @if ($editando) @method('PUT') @endif

    <div class="pasos">
        <div class="paso"><span class="c">1</span>Detalle del servicio</div>
        <div class="paso"><span class="c">2</span>Datos y mensaje</div>
        <div class="paso"><span class="c">3</span>Confirmación</div>
    </div>

    <div class="card" style="max-width:860px;margin:0 auto">
        <div class="panel-paso">
            <div class="card-header"><h2>Detalle de la solicitud</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="campo"><label>Fecha del servicio *</label>
                        <input class="input" type="date" name="fecha" required min="{{ now()->format('Y-m-d') }}" value="{{ old('fecha', $solicitud->fecha_hora?->format('Y-m-d')) }}">
                        @error('fecha')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Hora solicitada *</label>
                        <input class="input" type="time" name="hora" required value="{{ old('hora', $solicitud->fecha_hora?->format('H:i') ?? '11:00') }}">
                        <div class="ayuda">El sistema valida que el horario esté disponible.</div></div>
                    <div class="campo"><label>Tipo de servicio *</label>
                        <select class="input" name="tipo_servicio" required>
                            @foreach (config('funeraria.tipos_servicio') as $k => $t)
                                <option value="{{ $k }}" @selected(old('tipo_servicio', $solicitud->tipo_servicio) === $k)>{{ $t['nombre'] }}</option>
                            @endforeach
                        </select></div>
                    <div class="campo"><label>Urna deseada</label>
                        <select class="input" name="urna_id">
                            <option value="">Quiero asesoría para elegir</option>
                            @foreach ($urnas as $u)
                                <option value="{{ $u->id }}" @selected(old('urna_id', $solicitud->urna_id) == $u->id) @disabled(! $u->puedeAsociarse())>{{ $u->codigo }} {{ $u->modelo }} — {{ clp($u->precio) }}{{ $u->puedeAsociarse() ? '' : ' (sin stock)' }}</option>
                            @endforeach
                        </select>
                        <div class="ayuda"><a href="{{ route('publico.catalogo') }}" target="_blank">Ver catálogo con fotos</a></div></div>
                    <div class="campo"><label>Sucursal *</label>
                        <select class="input" name="sucursal_id" required>
                            @foreach ($sucursales as $s)<option value="{{ $s->id }}" @selected(old('sucursal_id', $solicitud->sucursal_id) == $s->id)>{{ $s->nombre }}</option>@endforeach
                        </select></div>
                    <div class="campo"><label>Destino final *</label>
                        <select class="input" name="destino_final" required>
                            @foreach (config('funeraria.destinos') as $k => $v)<option value="{{ $k }}" @selected(old('destino_final', $solicitud->destino_final) === $k)>{{ $v }}</option>@endforeach
                        </select></div>
                    <div class="campo"><label>Convenio (si corresponde)</label>
                        <select class="input" name="convenio_id">
                            <option value="">Sin convenio / no sé</option>
                            @foreach ($convenios as $c)<option value="{{ $c->id }}" @selected(old('convenio_id', $solicitud->convenio_id) == $c->id)>{{ $c->institucion }}</option>@endforeach
                        </select></div>
                    <div class="campo"><label>Lugar del velatorio</label>
                        <input class="input" name="lugar_velatorio" value="{{ old('lugar_velatorio', $solicitud->lugar_velatorio) }}" placeholder="Domicilio o capilla"></div>
                    <div class="campo"><label>Cementerio o crematorio</label>
                        <input class="input" name="cementerio_crematorio" value="{{ old('cementerio_crematorio', $solicitud->cementerio_crematorio) }}"></div>
                    <div class="campo"><label>Misa / ceremonia (hora)</label>
                        <input class="input" type="datetime-local" name="misa_hora" value="{{ old('misa_hora', $dt($solicitud->misa_hora)) }}"></div>
                    <div class="campo full"><label class="check"><input type="checkbox" name="fuera_comuna" value="1" @checked(old('fuera_comuna', $solicitud->fuera_comuna))> La sepultura es fuera de Temuco (requiere permiso de traslado)</label>
                        <input class="input mt-1" name="comuna_destino" value="{{ old('comuna_destino', $solicitud->comuna_destino) }}" placeholder="Comuna de destino"></div>
                </div>
            </div>
            <div class="card-body flex between" style="border-top:1px solid var(--border)">
                <span></span><button type="button" class="btn btn-primary" data-siguiente>Siguiente →</button>
            </div>
        </div>

        <div class="panel-paso">
            <div class="card-header"><h2>Datos del contratante y del fallecido</h2></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="campo"><label>Nombre del fallecido *</label>
                        <input class="input" name="nombre_fallecido" required value="{{ old('nombre_fallecido', $solicitud->nombre_fallecido) }}">
                        @error('nombre_fallecido')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>RUT del fallecido</label>
                        <input class="input" name="rut_fallecido" data-rut value="{{ old('rut_fallecido', $solicitud->rut_fallecido) }}"></div>
                    <div class="campo"><label>RUT del contratante *</label>
                        <input class="input" name="rut_contratante" required data-rut value="{{ old('rut_contratante', $cliente?->rut_contratante) }}">
                        <div class="ayuda">Dato sensible: se almacena cifrado.</div>
                        @error('rut_contratante')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Teléfono *</label>
                        <input class="input" name="telefono" required value="{{ old('telefono', $cliente?->telefono ?? auth()->user()->telefono) }}">
                        @error('telefono')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo"><label>Medio de contacto *</label>
                        <select class="input" name="medio_contacto">
                            @foreach (config('funeraria.medios_contacto') as $k => $v)<option value="{{ $k }}" @selected(old('medio_contacto', $solicitud->medio_contacto) === $k)>{{ $v }}</option>@endforeach
                        </select></div>
                    <div class="campo"><label>Certificado de defunción (escaneado)</label>
                        <input class="input" type="file" name="certificado" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="ayuda">PDF o imagen, máx. 5 MB. Se entrega en el hospital.</div>
                        @error('certificado')<div class="error">{{ $message }}</div>@enderror</div>
                    <div class="campo full"><label>Documentos que ya tiene</label>
                        <div class="checks-grid">
                            @foreach (config('funeraria.documentos') as $k => $v)
                                <label class="check"><input type="checkbox" name="documentos[]" value="{{ $k }}" @checked(in_array($k, $docs))> {{ $v }}</label>
                            @endforeach
                        </div></div>
                    <div class="campo full"><label>Mensaje adicional</label>
                        <textarea class="input" name="mensaje" maxlength="1000" placeholder="Ej: solicitamos un recorrido por su lugar de trabajo">{{ old('mensaje', $solicitud->mensaje) }}</textarea>
                        <div class="ayuda">Campo opcional.</div></div>
                </div>
            </div>
            <div class="card-body flex between" style="border-top:1px solid var(--border)">
                <button type="button" class="btn btn-outline" data-anterior>← Anterior</button>
                <button type="button" class="btn btn-primary" data-siguiente>Siguiente →</button>
            </div>
        </div>

        <div class="panel-paso">
            <div class="card-header"><h2>Confirmación</h2></div>
            <div class="card-body">
                <table class="tabla">
                    <tr><td>Fecha y hora</td><td><span data-resumen="fecha"></span> · <span data-resumen="hora"></span></td></tr>
                    <tr><td>Tipo de servicio</td><td data-resumen="tipo_servicio"></td></tr>
                    <tr><td>Urna</td><td data-resumen="urna_id"></td></tr>
                    <tr><td>Sucursal</td><td data-resumen="sucursal_id"></td></tr>
                    <tr><td>Destino final</td><td data-resumen="destino_final"></td></tr>
                    <tr><td>Fallecido</td><td data-resumen="nombre_fallecido"></td></tr>
                    <tr><td>Contacto</td><td><span data-resumen="telefono"></span> · <span data-resumen="medio_contacto"></span></td></tr>
                </table>
                <div class="aviso aviso-warn mt-2 mb-0">Si confirma, la solicitud quedará <strong>“En revisión”</strong> hasta su aprobación. Para aceptarla se requiere un abono mínimo del
                    {{ config('funeraria.abono_minimo_pct') }}% del valor del servicio, y los datos se pueden editar mientras esté en revisión.</div>
            </div>
            <div class="card-body flex between" style="border-top:1px solid var(--border)">
                <button type="button" class="btn btn-outline" data-anterior>← Anterior</button>
                <button class="btn btn-primary">{{ $editando ? 'Guardar cambios' : 'Enviar solicitud' }}</button>
            </div>
        </div>
    </div>
</form>
@endsection
