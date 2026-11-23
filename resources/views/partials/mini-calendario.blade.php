{{-- Mini calendario mensual. Variables: $mesCal (Carbon), $porDia (colección agrupada por Y-m-d), $rutaMes (nombre de ruta para navegar) --}}
@php
    $ini = $mesCal->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
    $fin = $mesCal->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $previo = $mesCal->copy()->subMonthNoOverflow()->format('Y-m');
    $sig = $mesCal->copy()->addMonthNoOverflow()->format('Y-m');
@endphp
<div class="flex between mb-1">
    <a class="btn btn-outline btn-xs" href="{{ route($rutaMes, ['mes' => $previo]) }}">‹</a>
    <strong style="text-transform:capitalize;font-family:var(--serif)">{{ $mesCal->translatedFormat('F Y') }}</strong>
    <a class="btn btn-outline btn-xs" href="{{ route($rutaMes, ['mes' => $sig]) }}">›</a>
</div>
<table class="mini-cal">
    <thead><tr>@foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
    <tbody>
    @for ($c = $ini->copy(); $c->lte($fin); $c->addWeek())
        <tr>
            @for ($k = 0; $k < 7; $k++)
                @php $d = $c->copy()->addDays($k); $n = isset($porDia[$d->toDateString()]) ? $porDia[$d->toDateString()]->count() : 0; @endphp
                <td class="{{ $d->month !== $mesCal->month ? 'fuera' : '' }} {{ $d->isToday() ? 'hoy' : '' }}">
                    @if ($d->month === $mesCal->month)
                        <a href="{{ route('calendario.index', ['mes' => $d->format('Y-m'), 'dia' => $d->toDateString()]) }}" class="{{ $n ? 'con' : '' }}" title="{{ $n }} servicio(s)">{{ $d->day }}@if ($n)<span class="cnt">{{ $n }}</span>@endif</a>
                    @else
                        <span>{{ $d->day }}</span>
                    @endif
                </td>
            @endfor
        </tr>
    @endfor
    </tbody>
</table>
