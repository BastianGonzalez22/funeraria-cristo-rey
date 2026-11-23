@extends('layouts.publico')
@section('titulo', 'Servicio de '.$s->nombre_fallecido)
@section('contenido')
<section class="hero" style="padding:48px 24px">
    <div class="wrap">
        <p class="small" style="letter-spacing:.15em;text-transform:uppercase;color:#b59a77">En memoria de</p>
        <h1>{{ $s->nombre_fallecido }}</h1>
        <p>{{ $s->cliente->nombre_familia }} agradece su compañía en este momento.</p>
    </div>
</section>
<section class="seccion-pub">
    <div class="grid-2">
        <div>
            <div class="card">
                <div class="card-header"><h2>Información del servicio</h2></div>
                <div class="card-body p0">
                    <table class="tabla">
                        @if ($s->lugar_velatorio)
                            <tr><td class="strong">Velatorio</td><td>{{ $s->lugar_velatorio }}<br><a href="{{ mapa_url($s->lugar_velatorio) }}" target="_blank" class="small">Cómo llegar</a></td></tr>
                        @endif
                        @if ($s->misa_lugar || $s->misa_hora)
                            <tr><td class="strong">Misa / ceremonia</td><td>{{ $s->misa_hora ? ucfirst(fecha($s->misa_hora, 'l d \d\e F, H:i')) : '' }}<br>{{ $s->misa_lugar }}
                                @if ($s->misa_lugar)<br><a href="{{ mapa_url($s->misa_lugar) }}" target="_blank" class="small">Cómo llegar</a>@endif</td></tr>
                        @endif
                        <tr><td class="strong">Cortejo</td><td>{{ ucfirst(fecha($s->fecha_hora, 'l d \d\e F, H:i')) }}</td></tr>
                        @foreach ($s->asignacion?->hoja_ruta ?? [] as $p)
                            <tr><td>{{ $p['hora'] ?? '' }}</td><td>{{ $p['lugar'] ?? '' }} — {{ $p['direccion'] ?? '' }}</td></tr>
                        @endforeach
                        @if ($s->cementerio_crematorio)
                            <tr><td class="strong">{{ $s->destino_final === 'cremacion' ? 'Crematorio' : 'Cementerio' }}</td><td>{{ $s->cementerio_crematorio }} {{ $s->sepultura_hora ? '· '.$s->sepultura_hora->format('H:i') : '' }}<br><a href="{{ mapa_url($s->cementerio_crematorio) }}" target="_blank" class="small">Cómo llegar</a></td></tr>
                        @endif
                    </table>
                </div>
                <div class="card-body"><a class="btn btn-whatsapp btn-sm" target="_blank" href="{{ whatsapp_url('', 'Información del servicio de '.$s->nombre_fallecido.': '.url()->current()) }}">Compartir por WhatsApp</a></div>
            </div>
        </div>
        <div>
            <div class="card">
                <div class="card-header"><h2>Libro de condolencias</h2><span class="meta">{{ $s->condolencias->count() }} mensajes</span></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('publico.condolencia', $s->token_publico) }}" class="mb-3">
                        @csrf
                        <input type="text" name="sitio_web" class="hidden" tabindex="-1" autocomplete="off">
                        <div class="campo mb-1"><label>Su nombre</label><input class="input" name="nombre" required maxlength="120"></div>
                        <div class="campo mb-1"><label>Mensaje</label><textarea class="input" name="mensaje" required maxlength="1000"></textarea></div>
                        <button class="btn btn-primary btn-sm">Firmar el libro</button>
                    </form>
                    @foreach ($s->condolencias as $c)
                        <div class="condolencia"><strong>{{ $c->nombre }}</strong> <span class="xs muted">{{ fecha($c->created_at, 'd M, H:i') }}</span><p class="small mb-0 mt-0">{{ $c->mensaje }}</p></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
