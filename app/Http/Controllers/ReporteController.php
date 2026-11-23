<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Contrato;
use App\Models\Gasto;
use App\Models\Solicitud;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes del administrador: servicios, gastos, cobros, boletas y SEREMI. */
class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $mes = Carbon::parse($request->query('mes', now()->format('Y-m')).'-01')->startOfMonth();
        $fin = $mes->copy()->endOfMonth();

        // Servicios por mes (últimos 12)
        $porMes = collect(range(11, 0))->map(function ($i) use ($mes) {
            $m = $mes->copy()->subMonthsNoOverflow($i);

            return [
                'mes' => $m->translatedFormat('M y'),
                'total' => Solicitud::whereIn('estado', ['aprobada', 'finalizada'])
                    ->whereBetween('fecha_hora', [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()])->count(),
            ];
        });

        $serviciosMes = Solicitud::with('urna')->whereIn('estado', ['aprobada', 'finalizada'])->whereBetween('fecha_hora', [$mes, $fin])->get();
        $masVendidos = $serviciosMes->groupBy('tipo_servicio')->map->count()->sortDesc();
        $urnasMasVendidas = $serviciosMes->filter(fn ($s) => $s->urna)->groupBy(fn ($s) => $s->urna->codigo.' '.$s->urna->modelo)->map->count()->sortDesc()->take(5);

        $gastos = Gasto::whereBetween('fecha', [$mes, $fin])->orderBy('fecha')->get();
        $ingresos = (int) Abono::where('estado', 'registrado')->whereBetween('fecha', [$mes, $fin])->sum('monto');

        return view('reportes.index', [
            'mes' => $mes,
            'porMes' => $porMes,
            'maxMes' => max(1, $porMes->max('total')),
            'masVendidos' => $masVendidos,
            'urnasMasVendidas' => $urnasMasVendidas,
            'totalServiciosMes' => $serviciosMes->count(),
            'gastos' => $gastos,
            'gastosPorCategoria' => $gastos->groupBy('categoria')->map->sum('monto'),
            'totalGastos' => (int) $gastos->sum('monto'),
            'ingresos' => $ingresos,
            'cobros' => Contrato::with('solicitud.cliente')->conSaldo()->orderBy('fecha_vencimiento')->get(),
            'boletas' => Abono::with('contrato.solicitud.cliente')->whereBetween('fecha', [$mes, $fin])->orderByDesc('fecha')->get(),
            'convenios' => Contrato::with('convenio')->whereNotNull('convenio_id')->whereBetween('created_at', [$mes, $fin])->get()->groupBy(fn ($c) => $c->convenio->institucion)->map->count(),
        ]);
    }

    public function guardarGasto(Request $request)
    {
        $d = $request->validate([
            'concepto' => ['required', 'string', 'max:150'],
            'categoria' => ['required', Rule::in(array_keys(config('funeraria.categorias_gasto')))],
            'monto' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
        ]);
        $g = Gasto::create($d + ['usuario_id' => $request->user()->id]);
        AuditService::registrar('gasto_registrado', "Gasto {$g->concepto} por ".clp($g->monto), $g);

        return back()->with('ok', 'Gasto registrado.');
    }

    public function eliminarGasto(Gasto $gasto)
    {
        AuditService::registrar('gasto_eliminado', "Eliminación del gasto {$gasto->concepto} (".clp($gasto->monto).')', $gasto, $gasto->toArray());
        $gasto->delete();

        return back()->with('ok', 'Gasto eliminado.');
    }

    /** Exporta servicios, cobros y abonos del mes en CSV (se abre en Excel). */
    public function exportar(Request $request): StreamedResponse
    {
        $mes = Carbon::parse($request->query('mes', now()->format('Y-m')).'-01')->startOfMonth();
        $fin = $mes->copy()->endOfMonth();

        return $this->csvSecciones("reporte-{$mes->format('Y-m')}.csv", function ($out) use ($mes, $fin) {
            fputcsv($out, ['SERVICIOS DEL MES '.$mes->format('m/Y')], ';');
            fputcsv($out, ['Código', 'Fecha', 'Familia', 'Servicio', 'Urna', 'Estado', 'Contrato', 'Total', 'Abonado', 'Saldo'], ';');
            Solicitud::with(['cliente', 'urna', 'contrato'])->whereBetween('fecha_hora', [$mes, $fin])->orderBy('fecha_hora')->each(function ($s) use ($out) {
                fputcsv($out, [
                    $s->codigo(), $s->fecha_hora->format('d/m/Y H:i'), $s->cliente->nombre_familia, $s->nombreServicio(),
                    $s->urna?->codigo, $s->nombreEstado(), $s->contrato?->numero,
                    $s->contrato?->monto_total, $s->contrato?->totalAbonado(), $s->contrato?->saldo_pendiente,
                ], ';');
            });
            fputcsv($out, [], ';');
            fputcsv($out, ['ABONOS / BOLETAS'], ';');
            fputcsv($out, ['Comprobante', 'Fecha', 'Contrato', 'Familia', 'Medio', 'Monto', 'Estado'], ';');
            Abono::with('contrato.solicitud.cliente')->whereBetween('fecha', [$mes, $fin])->orderBy('fecha')->each(function ($a) use ($out) {
                fputcsv($out, [$a->numero_comprobante, $a->fecha->format('d/m/Y'), $a->contrato->numero, $a->contrato->solicitud->cliente->nombre_familia, $a->nombreMedio(), $a->monto, $a->estado], ';');
            });
            fputcsv($out, [], ';');
            fputcsv($out, ['GASTOS'], ';');
            fputcsv($out, ['Fecha', 'Concepto', 'Categoría', 'Monto'], ';');
            Gasto::whereBetween('fecha', [$mes, $fin])->orderBy('fecha')->each(function ($g) use ($out) {
                fputcsv($out, [$g->fecha->format('d/m/Y'), $g->concepto, etiqueta('categorias_gasto', $g->categoria), $g->monto], ';');
            });
        });
    }

    /** Nómina mensual de fallecidos para la SEREMI de Salud / Registro Civil. */
    public function seremi(Request $request): StreamedResponse
    {
        $mes = Carbon::parse($request->query('mes', now()->format('Y-m')).'-01')->startOfMonth();
        AuditService::registrar('nomina_seremi', 'Exportación de nómina SEREMI '.$mes->format('m/Y'));

        return $this->csvSecciones("nomina-seremi-{$mes->format('Y-m')}.csv", function ($out) use ($mes) {
            fputcsv($out, ['N°', 'Nombre del fallecido', 'RUT', 'Fecha defunción', 'Fecha servicio', 'Contrato', 'Destino final', 'Cementerio / crematorio', 'Comuna destino', 'Traslado fuera de comuna', 'Permiso sanitario'], ';');
            $i = 1;
            Solicitud::with('contrato')->whereIn('estado', ['aprobada', 'finalizada'])
                ->whereBetween('fecha_hora', [$mes, $mes->copy()->endOfMonth()])->orderBy('fecha_hora')
                ->each(function ($s) use ($out, &$i) {
                    fputcsv($out, [
                        $i++, $s->nombre_fallecido, $s->rutFallecidoFormateado(), $s->fecha_defuncion?->format('d/m/Y'),
                        $s->fecha_hora->format('d/m/Y'), $s->contrato?->numero, etiqueta('destinos', $s->destino_final),
                        $s->cementerio_crematorio, $s->comuna_destino, $s->fuera_comuna ? 'Sí' : 'No',
                        etiqueta('estados_permiso', $s->permiso_sanitario),
                    ], ';');
                });
        });
    }

    private function csvSecciones(string $nombre, callable $escribir): StreamedResponse
    {
        return response()->streamDownload(function () use ($escribir) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea tildes
            $escribir($out);
            fclose($out);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
