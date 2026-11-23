<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Calendario mensual de servicios realizados y programados (trazabilidad hacia atrás y adelante). */
class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        $mes = Carbon::parse($request->query('mes', now()->format('Y-m')).'-01')->startOfMonth();
        $dia = Carbon::parse($request->query('dia', $mes->isSameMonth(now()) ? now()->toDateString() : $mes->toDateString()));

        $inicioGrilla = $mes->copy()->startOfWeek(Carbon::MONDAY);
        $finGrilla = $mes->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $servicios = Solicitud::with(['cliente', 'asignacion.vehiculo', 'asignacion.chofer'])
            ->visiblesEnCalendario()
            ->whereBetween('fecha_hora', [$inicioGrilla, $finGrilla->copy()->endOfDay()])
            ->orderBy('fecha_hora')
            ->get()
            ->groupBy(fn ($s) => $s->fecha_hora->toDateString());

        $semanas = [];
        $cursor = $inicioGrilla->copy();
        while ($cursor->lte($finGrilla)) {
            $semana = [];
            for ($i = 0; $i < 7; $i++) {
                $semana[] = $cursor->copy();
                $cursor->addDay();
            }
            $semanas[] = $semana;
        }

        $delMes = Solicitud::whereIn('estado', ['aprobada', 'finalizada'])
            ->whereBetween('fecha_hora', [$mes, $mes->copy()->endOfMonth()]);

        return view('calendario.index', [
            'mes' => $mes,
            'dia' => $dia,
            'semanas' => $semanas,
            'servicios' => $servicios,
            'delDia' => $servicios->get($dia->toDateString(), collect()),
            'totalMes' => (clone $delMes)->count(),
            'realizadosMes' => (clone $delMes)->where('estado', 'finalizada')->count(),
        ]);
    }

    /** Servicios del mes en CSV. */
    public function exportar(Request $request)
    {
        $mes = $this->mesValido($request->query('mes')) ?? now()->format('Y-m');
        $filas = Solicitud::with(['cliente', 'asignacion.vehiculo', 'asignacion.chofer', 'unidad', 'urna'])
            ->visiblesEnCalendario()->delMes($mes)->orderBy('fecha_hora')->get()
            ->map(fn (Solicitud $s) => [
                $s->fecha_hora->format('d/m/Y'), $s->fecha_hora->format('H:i'), $s->cliente->nombre_familia, $s->cliente->nombre_contratante,
                $s->nombre_fallecido, $s->nombreServicio(), $s->lugar_velatorio, $s->cementerio_crematorio,
                $s->asignacion?->vehiculo?->nombre, $s->asignacion?->chofer?->nombre, $s->unidad?->codigo ?? $s->urna?->codigo, $s->nombreEstado(),
            ]);

        return $this->csv("calendario-{$mes}.csv", ['Fecha', 'Hora', 'Familia', 'Contratante', 'Fallecido', 'Servicio', 'Velatorio', 'Cementerio / crematorio', 'Vehículo', 'Chofer', 'Urna', 'Estado'], $filas);
    }
}
