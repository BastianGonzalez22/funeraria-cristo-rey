@extends('layouts.app')
@php $editando = $convenio->exists; @endphp
@section('titulo', $editando ? 'Editar convenio' : 'Nuevo convenio')
@section('subtitulo', 'El PDF del convenio se genera desde una plantilla única')
@section('acciones')
    <a href="{{ route('convenios.index') }}" class="btn btn-outline">Volver</a>
@endsection

@section('contenido')
<form method="POST" enctype="multipart/form-data" action="{{ $editando ? route('convenios.update', $convenio) : route('convenios.store') }}">
    @csrf
    @if ($editando) @method('PUT') @endif
    <div class="grid-2-1">
        <div class="card"><div class="card-body">
            <div class="form-grid">
                <div class="campo"><label>Institución *</label><input class="input" name="institucion" value="{{ old('institucion', $convenio->institucion) }}" required></div>
                <div class="campo"><label>Tipo *</label>
                    <select class="input" name="tipo">@foreach (config('funeraria.tipos_convenio') as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $convenio->tipo) === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="campo full"><label>Beneficio *</label><input class="input" name="beneficio" value="{{ old('beneficio', $convenio->beneficio) }}" required placeholder="Cuota mortuoria de 15 UF"></div>
                <div class="campo"><label>Descuento (%) *</label><input class="input" type="number" name="descuento_pct" min="0" max="100" value="{{ old('descuento_pct', $convenio->descuento_pct ?? 0) }}" required></div>
                <div class="campo"><label>Monto de cobertura ($)</label><input class="input" type="number" name="monto_cobertura" min="0" value="{{ old('monto_cobertura', $convenio->monto_cobertura) }}"></div>
                <div class="campo"><label>Vigencia desde</label><input class="input" type="date" name="vigencia_desde" value="{{ old('vigencia_desde', $convenio->vigencia_desde?->format('Y-m-d')) }}"></div>
                <div class="campo"><label>Vigencia hasta</label><input class="input" type="date" name="vigencia_hasta" value="{{ old('vigencia_hasta', $convenio->vigencia_hasta?->format('Y-m-d')) }}"></div>
                <div class="campo full"><label>Requisitos</label><textarea class="input" name="requisitos">{{ old('requisitos', $convenio->requisitos) }}</textarea></div>
                <div class="campo full"><label>Documentos habituales</label><textarea class="input" name="documentos">{{ old('documentos', $convenio->documentos) }}</textarea></div>
                <div class="campo"><label>Contacto</label><input class="input" name="contacto" value="{{ old('contacto', $convenio->contacto) }}"></div>
                <div class="campo"><label>Estado *</label>
                    <select class="input" name="estado"><option value="vigente" @selected(old('estado', $convenio->estado) === 'vigente')>Vigente</option><option value="inactivo" @selected(old('estado', $convenio->estado) === 'inactivo')>Inactivo</option></select>
                    <div class="ayuda">Un convenio con contratos no se elimina: se desactiva.</div></div>
            </div>
        </div></div>
        <div>
            <div class="card"><div class="card-body">
                <div class="campo mb-2"><label>Logo</label><input class="input" type="file" name="logo" accept="image/*">
                    @if ($convenio->logoUrl())<img src="{{ $convenio->logoUrl() }}" alt="" style="max-width:100%;margin-top:8px">@endif</div>
                <div class="campo"><label>Color institucional</label><input class="input" type="color" name="color" value="{{ old('color', $convenio->color ?? '#8a6d4b') }}" style="height:40px"></div>
            </div></div>
            <button class="btn btn-primary" style="width:100%;justify-content:center">Guardar</button>
        </div>
    </div>
</form>
@endsection
