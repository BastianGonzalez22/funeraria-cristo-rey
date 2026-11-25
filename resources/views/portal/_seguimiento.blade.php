{{-- Seguimiento del servicio para la familia: etapas, traslado (chofer, vehículo, hora, recorrido) y lo que se lleva --}}
@php
    $a = $s->asignacion;
    $u = $s->unidad;
    $aprobada = in_array($s->estado, ['aprobada', 'finalizada'], true);
    $realizado = $s->estado === 'finalizada' || $a?->estado === 'completada';

    // Estado del traslado frente a la hora programada
    $traslado = null;
    if ($a && $a->estado !== 'cancelada') {
        if ($a->estado === 'completada') {
            $traslado = ['b-ok', 'Traslado completado', null];
        } elseif ($a->estado === 'en_curso') {
            $traslado = ['b-info', 'En camino', 'El vehículo ya salió hacia el primer punto del recorrido.'];
        } elseif (now()->greaterThan($a->inicio->copy()->addMinutes(15))) {
            $traslado = ['b-bad', 'Con retraso', 'Ya pasó la hora de salida y el traslado aún no se ha iniciado. Puede llamarnos para saber dónde está.'];
        } elseif (now()->greaterThan($a->inicio->copy()->subHours(2))) {
            $traslado = ['b-warn', 'Sale pronto', 'El traslado está programado para las '.$a->inicio->format('H:i').'.'];
        } else {
            $traslado = ['b-neutral', 'Programado', null];
        }
    }

    $etapas = [
        ['Solicitud recibida', fecha_hora($s->created_at), true],
        ['Servicio aprobado y contrato emitido', $s->contrato ? fecha_hora($s->contrato->created_at) : null, $aprobada],
        ['Urna asignada', $u ? $u->codigo.($u->estado === 'vendida' ? ' · compra confirmada' : ' · reservada') : ($aprobada && $s->urna?->bajo_pedido ? 'Se fabrica a pedido' : null), $aprobada && ($u || $s->urna?->bajo_pedido)],
        ['Traslado programado', $a ? fecha_hora($a->inicio) : null, (bool) $a],
        ['En camino', null, in_array($a?->estado, ['en_curso', 'completada'], true) || $s->estado === 'finalizada'],
        ['Servicio realizado', null, $realizado],
    ];
    $actual = collect($etapas)->search(fn ($e) => ! $e[2]);

    $elementos = collect($s->elementos ?? [])->filter()->keys()->map(fn ($k) => etiqueta('elementos', $k))->values();
    $equipo = collect($s->personal ?? [])->filter(fn ($p) => ! empty($p['persona']));
@endphp

<div class="card">
    <div class="card-header"><h2>Seguimiento del servicio</h2>@if ($traslado)<span class="badge {{ $traslado[0] }}">{{ $traslado[1] }}</span>@endif</div>
    <div class="card-body">
        <ol class="seg-pasos">
            @foreach ($etapas as $i => $e)
                <li class="{{ $e[2] ? 'hecho' : ($i === $actual ? 'actual' : '') }}">
                    <span class="seg-t">{{ $e[0] }}</span>
                    @if ($e[1])<span class="small muted">{{ $e[1] }}</span>@endif
                </li>
            @endforeach
        </ol>

        @if ($s->estado === 'rechazada')
            <p class="small muted mb-0">La solicitud fue rechazada; no hay traslado programado.</p>
        @elseif (! $a)
            <p class="small muted mb-0">Cuando la funeraria asigne el vehículo y el chofer, aquí verá el día, la hora y el recorrido del traslado.</p>
        @else
            @if ($traslado && $traslado[2])
                <div class="aviso {{ $traslado[0] === 'b-bad' ? 'aviso-error' : 'aviso-info' }} mt-2">{{ $traslado[2] }}</div>
            @endif

            <h3 class="mt-2">Traslado</h3>
            <div class="ficha">
                <div class="dato"><div class="k">Día</div><div class="v">{{ fecha($a->inicio, 'l d \d\e F') }}</div></div>
                <div class="dato"><div class="k">Hora de salida</div><div class="v">{{ $a->inicio->format('H:i') }}@if ($a->fin) <span class="small muted">· término estimado {{ $a->fin->format('H:i') }}</span>@endif</div></div>
                <div class="dato"><div class="k">Chofer</div><div class="v">{{ $a->chofer?->nombre ?? 'Por confirmar' }}</div></div>
                <div class="dato"><div class="k">Vehículo</div><div class="v">{{ $a->vehiculo ? (\App\Models\Vehiculo::TIPOS[$a->vehiculo->tipo] ?? $a->vehiculo->tipo).' · '.$a->vehiculo->nombre : 'Por confirmar' }}</div></div>
            </div>

            <h3 class="mt-2">Recorrido</h3>
            <ol class="timeline">
                @if ($a->origen)<li><strong>{{ $a->inicio->format('H:i') }}</strong> · Salida: {{ $a->origen }}</li>@endif
                @foreach ($a->hoja_ruta ?? [] as $p)
                    <li>@if (! empty($p['hora']))<strong>{{ $p['hora'] }}</strong> · @endif{{ $p['lugar'] ?? '' }}@if (! empty($p['direccion'])) <span class="muted">— {{ $p['direccion'] }}</span>@endif</li>
                @endforeach
                @if ($a->destino)<li>@if ($a->fin)<strong>{{ $a->fin->format('H:i') }}</strong> · @endif Destino: {{ $a->destino }}</li>@endif
            </ol>
            <div class="flex flex-wrap mt-2">
                <a href="{{ $a->rutaUrl() }}" target="_blank" class="btn btn-outline btn-sm">Ver recorrido en Google Maps</a>
                <a href="tel:{{ preg_replace('/\s+/', '', config('funeraria.telefono')) }}" class="btn btn-outline btn-sm">Llamar a la funeraria</a>
                <a href="{{ whatsapp_url(null, 'Hola, consulto por el traslado de '.$s->nombre_fallecido.' ('.$s->codigo().').') }}" target="_blank" class="btn btn-whatsapp btn-sm">Consultar por WhatsApp</a>
            </div>
        @endif

        @if ($aprobada && ($elementos->isNotEmpty() || $s->urna))
            <h3 class="mt-2">Lo que llevaremos</h3>
            <ul class="seg-lista">
                @if ($s->urna)<li>Urna {{ $s->urna->modelo }}@if ($u) ({{ $u->codigo }})@endif</li>@endif
                @foreach ($elementos as $el)<li>{{ $el }}</li>@endforeach
            </ul>
        @endif

        @if ($aprobada && $equipo->isNotEmpty())
            <h3 class="mt-2">Equipo a cargo</h3>
            <ul class="seg-lista">
                @foreach ($equipo as $p)<li>@if (! empty($p['tarea'])){{ $p['tarea'] }}: @endif{{ $p['persona'] }}</li>@endforeach
            </ul>
        @endif
    </div>
</div>
