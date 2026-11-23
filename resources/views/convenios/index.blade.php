@extends('layouts.app')
@section('titulo', 'Convenios')
@section('subtitulo', 'Instituciones para el cobro de cuotas mortuorias y convenios con empresas')
@section('acciones')
    <a href="{{ route('convenios.disponibles') }}" class="btn btn-outline">Convenios disponibles</a>
    <a href="{{ route('convenios.create') }}" class="btn btn-primary">+ Nuevo convenio</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-header"><h2>Mantenedor de convenios</h2><span class="meta">{{ $convenios->where('estado', 'vigente')->count() }} vigentes</span></div>
    <div class="card-body p0 tabla-wrap">
        <table class="tabla">
            <thead><tr><th>Institución</th><th>Tipo</th><th>Beneficio</th><th>Descuento</th><th>Vigencia</th><th>Contratos</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @foreach ($convenios as $c)
                <tr>
                    <td><div class="flex"><span class="logo-convenio" style="width:46px;height:30px;font-size:10px;background:{{ $c->color }}">@if ($c->logoUrl())<img src="{{ $c->logoUrl() }}" alt="">@else{{ $c->sigla() }}@endif</span><span class="strong">{{ $c->institucion }}</span></div></td>
                    <td>{{ etiqueta('tipos_convenio', $c->tipo) }}</td>
                    <td class="small">{{ $c->beneficio }}</td>
                    <td>{{ $c->descuento_pct ? $c->descuento_pct.'%' : '—' }}</td>
                    <td class="nowrap small">{{ $c->vigencia_hasta ? 'hasta '.fecha($c->vigencia_hasta) : 'Indefinida' }}</td>
                    <td>{{ $c->contratos_count }}</td>
                    <td><x-estado :valor="$c->estaVigente() ? 'vigente' : 'inactivo'" /></td>
                    <td class="nowrap"><a class="btn btn-outline btn-xs" href="{{ route('convenios.edit', $c) }}">Editar</a> <a class="btn btn-outline btn-xs" target="_blank" href="{{ route('convenios.pdf', $c) }}">PDF</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2" id="verificaciones">
    <div class="card">
        <div class="card-header"><h2>Verificar beneficio AFP / IPS</h2>
            <span class="badge {{ $enHorario ? 'b-ok' : 'b-warn' }}">{{ $enHorario ? 'En horario de consulta' : 'Fuera de horario' }}</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('beneficios.verificar') }}">
                @csrf
                <div class="form-grid">
                    <div class="campo"><label>RUT del fallecido</label><input class="input" name="rut" data-rut required></div>
                    <div class="campo"><label>Institución</label>
                        <select class="input" name="institucion">@foreach (\App\Models\VerificacionBeneficio::INSTITUCIONES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                </div>
                <div class="form-acciones"><button class="btn btn-primary">Registrar consulta</button></div>
            </form>
            <ul class="reglas">
                <li>Las AFP e IPS no responden después de las {{ config('funeraria.hora_corte_beneficios') }}:00 ni fines de semana: la consulta queda <strong>pendiente</strong> hasta {{ $siguienteHabil->translatedFormat('l d, H:i') }} (o llamar al 600).</li>
                <li>Los menores de 18 años no tienen beneficio; quienes trabajan cotizan en AFP; los jubilados tienen cuota mortuoria de {{ config('funeraria.cuota_mortuoria_uf') }} UF.</li>
                <li>Si el fallecido es joven y sin AFP, la familia paga el servicio completo.</li>
            </ul>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2>Verificaciones recientes</h2></div>
        <div class="card-body p0 tabla-wrap">
            <table class="tabla">
                <thead><tr><th>RUT</th><th>Inst.</th><th>Resultado</th><th></th></tr></thead>
                <tbody>
                @forelse ($verificaciones as $v)
                    <tr>
                        <td class="small">{{ \App\Support\Rut::enmascarar($v->rut_fallecido) }}<div class="xs muted">{{ fecha($v->created_at, 'd M H:i') }} · {{ $v->usuario->nombre }}</div></td>
                        <td>{{ \App\Models\VerificacionBeneficio::INSTITUCIONES[$v->institucion] }}</td>
                        <td><x-estado :valor="$v->estado" />@if ($v->monto_beneficio)<div class="xs">{{ clp($v->monto_beneficio) }}</div>@endif
                            @if ($v->estado === 'pendiente')<div class="xs muted">desde {{ $v->consultar_desde?->format('d/m H:i') }}</div>@endif</td>
                        <td>
                            <form method="POST" action="{{ route('beneficios.resolver', $v) }}" class="flex">@csrf @method('PUT')
                                <select class="input" name="estado" style="width:120px">
                                    @foreach (\App\Models\VerificacionBeneficio::ESTADOS as $k => $t)<option value="{{ $k }}" @selected($v->estado === $k)>{{ $t }}</option>@endforeach
                                </select>
                                <input class="input" type="number" name="monto_beneficio" placeholder="Monto" value="{{ $v->monto_beneficio ?: '' }}" style="width:100px">
                                <button class="btn btn-outline btn-xs">OK</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted text-center">Sin consultas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
