<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Contrato;
use App\Models\Solicitud;
use App\Services\AlertaService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Redirige a cada perfil a su pantalla inicial. */
    public function redirigir(Request $request)
    {
        $ruta = $request->user()->rutaInicio();
        if (! $ruta) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Su perfil no tiene módulos asignados. Contacte al administrador.']);
        }

        return redirect()->route($ruta);
    }

    public function index(Request $request, AlertaService $alertas, StockService $stock)
    {
        $u = $request->user();
        $inicioMes = now()->startOfMonth();
        $finMes = now()->endOfMonth();

        $serviciosMes = Solicitud::whereIn('estado', ['aprobada', 'finalizada'])
            ->whereBetween('fecha_hora', [$inicioMes, $finMes])->count();
        $serviciosMesAnterior = Solicitud::whereIn('estado', ['aprobada', 'finalizada'])
            ->whereBetween('fecha_hora', [$inicioMes->copy()->subMonthNoOverflow(), $inicioMes->copy()->subSecond()])->count();

        $kpis = [
            'servicios_mes' => $serviciosMes,
            'servicios_mes_anterior' => $serviciosMesAnterior,
            'en_revision' => Solicitud::whereIn('estado', ['en_revision', 'con_observaciones'])->count(),
            'hoy' => Solicitud::activas()->whereDate('fecha_hora', today())->count(),
        ];

        if ($u->puede('finanzas')) {
            $abonosMes = (int) Abono::where('estado', 'registrado')->whereBetween('fecha', [$inicioMes, $finMes])->sum('monto');
            $abonosAnt = (int) Abono::where('estado', 'registrado')
                ->whereBetween('fecha', [$inicioMes->copy()->subMonthNoOverflow(), $inicioMes->copy()->subDay()])->sum('monto');
            $kpis['abonos_mes'] = $abonosMes;
            $kpis['abonos_variacion'] = $abonosAnt > 0 ? round(($abonosMes - $abonosAnt) / $abonosAnt * 100) : null;
            $kpis['saldos'] = (int) Contrato::conSaldo()->sum('saldo_pendiente');
            $kpis['familias_con_saldo'] = Contrato::conSaldo()->count();
        }

        $proximos = Solicitud::with(['cliente', 'asignacion.vehiculo'])
            ->activas()
            ->where('fecha_hora', '>=', now()->startOfDay())
            ->orderBy('fecha_hora')
            ->limit(8)
            ->get();

        // Mini calendario del mes (arriba del dashboard)
        $mes = $request->query('mes') && preg_match('/^\d{4}-\d{2}$/', $request->query('mes'))
            ? Carbon::parse($request->query('mes').'-01') : now()->startOfMonth();
        $porDia = Solicitud::visiblesEnCalendario()
            ->whereBetween('fecha_hora', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])
            ->get(['id', 'fecha_hora', 'estado'])
            ->groupBy(fn ($s) => $s->fecha_hora->toDateString());

        return view('dashboard.index', [
            'mesCal' => $mes,
            'porDia' => $porDia,
            'stockInd' => $stock->indicadores(),
            'kpis' => $kpis,
            'proximos' => $proximos,
            'stock' => $alertas->stockBajo(),
            'atrasados' => $u->puede('finanzas') ? $alertas->pagosAtrasados() : collect(),
            'alertas' => $alertas->para($u),
        ]);
    }
}
