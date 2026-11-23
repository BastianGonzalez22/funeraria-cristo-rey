<?php

namespace App\Http\Controllers;

use App\Models\AsignacionFlota;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Rutas de los choferes: semana con navegación en el mismo bloque, hojas de ruta
 * y enlaces a Google Maps. Solo dónde se vela, a dónde se traslada y los
 * horarios: los choferes no ven información contable.
 */
class ItinerarioController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $soloPropias = ! $u->puede('flota');   // quien no administra la flota ve solo sus rutas
        $lunes = Carbon::parse($request->query('semana', now()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $dias = collect(range(0, 6))->map(fn ($i) => $lunes->copy()->addDays($i));
        $dia = Carbon::parse($request->query('dia', $lunes->isSameWeek(now()) ? now()->toDateString() : $lunes->toDateString()));

        $base = AsignacionFlota::with(['solicitud.cliente', 'vehiculo', 'chofer'])
            ->whereIn('estado', ['programada', 'en_curso', 'completada'])
            ->when($soloPropias, fn ($q) => $q->where('chofer_id', $u->chofer?->id ?? 0));

        $semana = (clone $base)->whereBetween('inicio', [$lunes->copy()->startOfDay(), $lunes->copy()->addDays(6)->endOfDay()])
            ->orderBy('inicio')->get();

        return view('itinerario.index', [
            'lunes' => $lunes,
            'dias' => $dias,
            'dia' => $dia,
            'porDia' => $semana->groupBy(fn ($a) => $a->inicio->toDateString()),
            'delDia' => $semana->filter(fn ($a) => $a->inicio->isSameDay($dia))->values(),
            'esChofer' => $soloPropias,
            'sinFicha' => $soloPropias && ! $u->chofer,
        ]);
    }

    public function show(Request $request, AsignacionFlota $asignacion)
    {
        $this->autorizar($request, $asignacion);

        return view('itinerario.show', ['a' => $asignacion->load(['solicitud.cliente', 'solicitud.urna', 'solicitud.unidad', 'vehiculo', 'chofer'])]);
    }

    public function estado(Request $request, AsignacionFlota $asignacion)
    {
        $this->autorizar($request, $asignacion);
        $d = $request->validate(['estado' => ['required', Rule::in(['en_curso', 'completada'])]]);
        $asignacion->update($d);
        AuditService::registrar('itinerario_estado', 'Hoja de ruta '.$asignacion->solicitud->codigo().' → '.$d['estado'], $asignacion->solicitud);

        return back()->with('ok', 'Estado del traslado actualizado.');
    }

    private function autorizar(Request $request, AsignacionFlota $a): void
    {
        $u = $request->user();
        if (! $u->puede('flota')) {
            abort_unless($u->chofer && $u->chofer->id === $a->chofer_id, 403, 'Esta hoja de ruta no está asignada a usted.');
        }
    }
}
