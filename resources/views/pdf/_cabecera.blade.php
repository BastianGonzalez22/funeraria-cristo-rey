<div class="cab">
    <div class="doc">
        <div class="t">{{ $titulo }}</div>
        <div>{{ $numero }}</div>
        <div class="d">Emitido el {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="n">{{ config('funeraria.nombre') }}</div>
    <div class="d">{{ config('funeraria.direccion') }} · Tel. {{ config('funeraria.telefono') }} · {{ config('funeraria.email') }}</div>
</div>
<div class="pie">Documento digital generado por el Sistema de Gestión de {{ config('funeraria.nombre') }}. Los datos personales se tratan conforme a la Ley N.º 21.719.</div>
