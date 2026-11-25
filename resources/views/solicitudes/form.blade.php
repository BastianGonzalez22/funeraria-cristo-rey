@extends('layouts.app')
@php $editando = $solicitud->exists; @endphp
@section('titulo', $editando ? 'Editar solicitud '.$solicitud->codigo() : 'Nueva solicitud')
@section('subtitulo', $editando ? 'Si la familia cambia algún dato, el cambio queda registrado con usuario y fecha' : 'Ingreso de la solicitud recibida por teléfono, WhatsApp o en oficina')
@section('acciones')
    <a href="{{ $editando ? route('solicitudes.show', $solicitud) : route('solicitudes.index') }}" class="btn btn-outline">Volver</a>
@endsection

@section('contenido')
@php
    $docs = old('documentos', array_keys(array_filter($solicitud->documentos ?? [])));
    $dt = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('solicitudes.update', $solicitud) : route('solicitudes.store') }}">
    @csrf
    @if ($editando) @method('PUT') @endif

    <div class="grid-2-1">
        <div>
            @unless ($editando)
                <div class="card">
                    <div class="card-header"><h2>Familia / contratante</h2><a href="{{ route('clientes.create') }}" class="small">Registro completo de cliente →</a></div>
                    <div class="card-body">
                        <div class="campo mb-2">
                            <label>Familia ya registrada</label>
                            <select class="input" name="cliente_id" id="cliente_id">
                                <option value="">— Nueva familia (completar abajo) —</option>
                                @if ($clienteSeleccionado && ! $clientes->contains('id', $clienteSeleccionado->id))
                                    <option value="{{ $clienteSeleccionado->id }}" selected>{{ $clienteSeleccionado->nombre_familia }} — {{ $clienteSeleccionado->nombre_contratante }}</option>
                                @endif
                                @foreach ($clientes as $c)
                                    <option value="{{ $c->id }}" @selected(old('cliente_id', $solicitud->cliente_id) == $c->id)>{{ $c->nombre_familia }} — {{ $c->nombre_contratante }} ({{ $c->telefono }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-grid" id="nueva-familia">
                            <div class="campo"><label>Nombre de la familia</label><input class="input" name="nombre_familia" value="{{ old('nombre_familia') }}" placeholder="Familia Contreras"></div>
                            <div class="campo"><label>Nombre del contratante</label><input class="input" name="nombre_contratante" value="{{ old('nombre_contratante') }}"></div>
                            <div class="campo"><label>RUT del contratante</label><input class="input" name="rut_contratante" value="{{ old('rut_contratante') }}" data-rut placeholder="12.345.678-5"></div>
                            <div class="campo"><label>Teléfono</label><input class="input" name="telefono" value="{{ old('telefono') }}" placeholder="+56 9 1234 5678"></div>
                            <div class="campo"><label>Correo</label><input class="input" type="email" name="email" value="{{ old('email') }}"></div>
                            <div class="campo"><label>Domicilio del contratante</label><input class="input" name="direccion" value="{{ old('direccion') }}"></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="aviso aviso-info">Familia: <strong>{{ $solicitud->cliente->nombre_familia }}</strong> — {{ $solicitud->cliente->nombre_contratante }}
                    · <a href="{{ route('clientes.edit', $solicitud->cliente) }}">editar datos de la familia</a></div>
            @endunless

            <div class="card">
                <div class="card-header"><h2>Detalle de la solicitud</h2></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="campo"><label>Fecha del servicio *</label>
                            <input class="input" type="date" name="fecha" required value="{{ old('fecha', $solicitud->fecha_hora?->format('Y-m-d')) }}"></div>
                        <div class="campo"><label>Hora (cortejo / inicio) *</label>
                            <input class="input" type="time" name="hora" required value="{{ old('hora', $solicitud->fecha_hora?->format('H:i') ?? '11:00') }}"></div>
                        <div class="campo full"><div data-disponibilidad="{{ route('solicitudes.disponibilidad') }}" data-excepto="{{ $solicitud->id }}" class="aviso aviso-info small mb-0">Seleccione fecha y hora para validar el solapamiento.</div></div>
                        <div class="campo"><label>Tipo de servicio *</label>
                            <select class="input" name="tipo_servicio" required>
                                @foreach (config('funeraria.tipos_servicio') as $k => $t)
                                    <option value="{{ $k }}" @selected(old('tipo_servicio', $solicitud->tipo_servicio) === $k)>{{ $t['nombre'] }} — {{ clp($t['precio']) }}</option>
                                @endforeach
                            </select></div>
                        <div class="campo"><label>Urna</label>
                            <select class="input" name="urna_id">
                                <option value="">— Por definir —</option>
                                @foreach ($urnas as $u)
                                    <option value="{{ $u->id }}" @selected(old('urna_id', $solicitud->urna_id) == $u->id) @disabled(! $u->puedeAsociarse() && $u->id != $solicitud->urna_id)>
                                        {{ $u->codigo }} {{ $u->modelo }} — {{ clp($u->precio) }} · {{ $u->bajo_pedido ? 'bajo pedido' : $u->disponible().' disp.' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="ayuda"><a href="{{ route('urnas.catalogo', ['destino' => 'solicitud']) }}" target="_blank">Ver catálogo con fotos</a>. Las urnas sin stock no se pueden asociar.</div></div>
                        <div class="campo"><label>Sucursal *</label>
                            <select class="input" name="sucursal_id" required>
                                @foreach ($sucursales as $su)
                                    <option value="{{ $su->id }}" @selected(old('sucursal_id', $solicitud->sucursal_id) == $su->id)>{{ $su->nombre }}</option>
                                @endforeach
                            </select></div>
                        <div class="campo"><label>Destino final *</label>
                            <select class="input" name="destino_final" required>
                                @foreach (config('funeraria.destinos') as $k => $v)
                                    <option value="{{ $k }}" @selected(old('destino_final', $solicitud->destino_final) === $k)>{{ $v }}</option>
                                @endforeach
                            </select></div>
                        <div class="campo"><label>Convenio</label>
                            <select class="input" name="convenio_id">
                                <option value="">Sin convenio</option>
                                @foreach ($convenios as $cv)
                                    <option value="{{ $cv->id }}" @selected(old('convenio_id', $solicitud->convenio_id) == $cv->id)>{{ $cv->institucion }} — {{ $cv->beneficio }}</option>
                                @endforeach
                            </select>
                            <div class="ayuda"><a href="{{ route('convenios.disponibles') }}" target="_blank">Ver convenios disponibles</a></div></div>
                        <div class="campo"><label>Medio de contacto preferido *</label>
                            <select class="input" name="medio_contacto" required>
                                @foreach (config('funeraria.medios_contacto') as $k => $v)
                                    <option value="{{ $k }}" @selected(old('medio_contacto', $solicitud->medio_contacto) === $k)>{{ $v }}</option>
                                @endforeach
                            </select></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Datos del fallecido</h2><span class="meta">Datos sensibles: el RUT se guarda cifrado</span></div>
                <div class="card-body">
                    <div class="form-grid cols-3">
                        <div class="campo"><label>Nombre completo *</label><input class="input" name="nombre_fallecido" required value="{{ old('nombre_fallecido', $solicitud->nombre_fallecido) }}"></div>
                        <div class="campo"><label>RUT</label><input class="input" name="rut_fallecido" data-rut value="{{ old('rut_fallecido', $solicitud->rut_fallecido) }}"></div>
                        <div class="campo"><label>Fecha de defunción</label><input class="input" type="date" name="fecha_defuncion" value="{{ old('fecha_defuncion', $solicitud->fecha_defuncion?->format('Y-m-d')) }}"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Logística del servicio</h2></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="campo"><label>Lugar de retiro</label><input class="input" name="lugar_retiro" value="{{ old('lugar_retiro', $solicitud->lugar_retiro) }}" placeholder="Hospital Regional / domicilio"></div>
                        <div class="campo"><label>Lugar del velatorio</label><input class="input" name="lugar_velatorio" value="{{ old('lugar_velatorio', $solicitud->lugar_velatorio) }}" placeholder="Domicilio de la familia / capilla"></div>
                        <div class="campo"><label>Misa / ceremonia (lugar)</label><input class="input" name="misa_lugar" value="{{ old('misa_lugar', $solicitud->misa_lugar) }}"></div>
                        <div class="campo"><label>Misa / ceremonia (hora)</label><input class="input" type="datetime-local" name="misa_hora" value="{{ old('misa_hora', $dt($solicitud->misa_hora)) }}">
                        <div class="ayuda">Dentro de los 3 días del servicio.</div>@error('misa_hora')<div class="error">{{ $message }}</div>@enderror</div>
                        <div class="campo"><label>Cementerio o crematorio</label>@include('partials.campo-destino', ['solicitud' => $solicitud])</div>
                        <div class="campo"><label>Hora de sepultura / cremación</label><input class="input" type="datetime-local" name="sepultura_hora" value="{{ old('sepultura_hora', $dt($solicitud->sepultura_hora)) }}">
                        <div class="ayuda">Dentro de los 3 días del servicio.</div>@error('sepultura_hora')<div class="error">{{ $message }}</div>@enderror</div>
                        <div class="campo"><label>Comuna de destino</label><input class="input" name="comuna_destino" value="{{ old('comuna_destino', $solicitud->comuna_destino) }}" placeholder="Temuco"></div>
                        <div class="campo">
                            <label>&nbsp;</label>
                            <label class="check"><input type="checkbox" name="fuera_comuna" value="1" @checked(old('fuera_comuna', $solicitud->fuera_comuna))> Traslado fuera de la comuna/región (requiere resolución de traslado)</label>
                            <label class="check"><input type="checkbox" name="caso_especial" value="1" @checked(old('caso_especial', $solicitud->caso_especial))> Caso especial (muerte violenta / investigación)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h2>Documentos recibidos</h2></div>
                <div class="card-body">
                    @foreach (config('funeraria.documentos') as $k => $v)
                        <label class="check"><input type="checkbox" name="documentos[]" value="{{ $k }}" @checked(in_array($k, $docs))> {{ $v }}</label>
                    @endforeach
                    <div class="campo mt-2">
                        <label>Certificado de defunción (escaneado)</label>
                        <input class="input" type="file" name="certificado" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="ayuda">PDF o imagen, máx. 5 MB. Se guarda en un almacenamiento privado.
                            @if ($solicitud->certificado_path) <br>Ya hay un certificado cargado; subir otro lo reemplaza. @endif</div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2>Mensaje adicional</h2></div>
                <div class="card-body">
                    <textarea class="input" name="mensaje" maxlength="1000" placeholder="Indicaciones de la familia, recorrido especial, etc.">{{ old('mensaje', $solicitud->mensaje) }}</textarea>
                    <label class="check mt-2"><input type="checkbox" name="forzar" value="1" @checked(old('forzar'))> Registrar aunque el horario se solape (se marcará “requiere apoyo externo”)</label>
                </div>
            </div>
            <div class="aviso aviso-warn small">Para aceptar la solicitud se requiere un abono mínimo del <strong>{{ config('funeraria.abono_minimo_pct') }}%</strong> del valor del servicio (o la cuota mortuoria AFP/IPS en trámite).</div>
            <button class="btn btn-primary" style="width:100%;justify-content:center">{{ $editando ? 'Guardar cambios' : 'Registrar solicitud' }}</button>
        </div>
    </div>
</form>
@endsection
