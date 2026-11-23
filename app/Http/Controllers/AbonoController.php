<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\Contrato;
use App\Services\AuditService;
use App\Services\PagoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Módulo financiero: abonos y pagos (solo administrador). */
class AbonoController extends Controller
{
    public function __construct(private PagoService $pagos)
    {
    }

    public function index(Request $request)
    {
        $filtro = $request->query('filtro', 'vigentes');
        $contratos = Contrato::with(['solicitud.cliente', 'convenio', 'abonos'])
            ->when($filtro === 'vigentes', fn ($q) => $q->where('estado', 'vigente'))
            ->when($filtro === 'atrasados', fn ($q) => $q->atrasados())
            ->when($filtro === 'liquidados', fn ($q) => $q->where('estado', 'liquidado'))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('numero', 'like', "%{$t}%")
                ->orWhereHas('solicitud.cliente', fn ($c) => $c->buscar($t))))
            ->when($this->mesValido($request->query('mes')), fn ($q, $m) => $q->whereHas('abonos', fn ($a) => $a->whereBetween('fecha', [\Carbon\Carbon::parse($m.'-01'), \Carbon\Carbon::parse($m.'-01')->endOfMonth()])))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('abonos.index', [
            'contratos' => $contratos,
            'seleccion' => $request->query('contrato'),
            'abiertos' => Contrato::with('solicitud.cliente')->where('estado', 'vigente')->where('saldo_pendiente', '>', 0)->orderByDesc('id')->get(),
            'ultimos' => Abono::with(['contrato.solicitud.cliente', 'usuario'])->where('estado', '!=', 'pendiente')->latest()->limit(12)->get(),
            'porConfirmar' => Abono::with(['contrato.solicitud.cliente', 'usuario'])->where('estado', 'pendiente')->oldest()->get(),
            'mes' => $this->mesValido($request->query('mes')),
            'filtro' => $filtro,
            'totales' => [
                'por_cobrar' => (int) Contrato::conSaldo()->sum('saldo_pendiente'),
                'atrasado' => (int) Contrato::atrasados()->sum('saldo_pendiente'),
                'mes' => (int) Abono::where('estado', 'registrado')->whereBetween('fecha', [now()->startOfMonth(), now()->endOfMonth()])->sum('monto'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'contrato_id' => ['required', 'exists:contratos,id'],
            'monto' => ['required', 'integer', 'min:1'],
            'medio_pago' => ['required', Rule::in(array_keys(config('funeraria.medios_pago')))],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'adjunto' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $contrato = Contrato::findOrFail($d['contrato_id']);

        return $this->intentar(
            fn () => $this->pagos->registrar($contrato, $d, $request->user(), $request->file('adjunto')),
            'Abono registrado. El saldo se recalculó y se emitió el comprobante digital.',
            fn (Abono $a) => route('abonos.index', ['contrato' => $contrato->id, 'comprobante' => $a->id])
        );
    }

    public function anular(Request $request, Abono $abono)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        return $this->intentar(fn () => $this->pagos->anular($abono, $d['motivo']), 'Abono anulado. El saldo fue restituido.');
    }

    /** Confirma un abono informado por la familia desde el portal (se descuenta del saldo). */
    public function confirmar(Request $request, Abono $abono)
    {
        return $this->intentar(fn () => $this->pagos->confirmar($abono, $request->user()), 'Abono confirmado: el saldo se actualizó y se emitió el comprobante.');
    }

    public function rechazar(Request $request, Abono $abono)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:255']]);

        return $this->intentar(fn () => $this->pagos->rechazarDeclarado($abono, $d['motivo'], $request->user()), 'Abono rechazado. La familia verá el motivo en su portal.');
    }

    /** Abonos de un mes (o todos) en CSV para Excel. */
    public function exportar(Request $request)
    {
        $mes = $this->mesValido($request->query('mes'));
        $filas = Abono::with(['contrato.solicitud.cliente', 'usuario'])
            ->when($mes, fn ($q) => $q->whereBetween('fecha', [\Carbon\Carbon::parse($mes.'-01'), \Carbon\Carbon::parse($mes.'-01')->endOfMonth()]))
            ->orderBy('fecha')->get()
            ->map(fn (Abono $a) => [
                $a->numero_comprobante ?? '(por confirmar)', $a->fecha->format('d/m/Y'), $a->contrato->numero,
                $a->contrato->solicitud->cliente->nombre_familia, $a->contrato->solicitud->cliente->nombre_contratante,
                $a->nombreMedio(), $a->monto, $a->estado, $a->usuario->nombre,
            ]);

        return $this->csv('abonos-'.($mes ?? 'todos').'.csv', ['Comprobante', 'Fecha', 'Contrato', 'Familia', 'Contratante', 'Medio', 'Monto', 'Estado', 'Registrado por'], $filas);
    }

    public function comprobante(Abono $abono)
    {
        abort_unless($abono->numero_comprobante, 404, 'El comprobante se emite al confirmar el abono.');
        $abono->load(['contrato.solicitud.cliente', 'contrato.solicitud.sucursal', 'usuario']);
        AuditService::registrar('comprobante_emitido', "Emisión del comprobante {$abono->numero_comprobante}", $abono->contrato);

        return Pdf::loadView('pdf.comprobante', ['abono' => $abono])
            ->setPaper('letter')
            ->stream("comprobante-{$abono->numero_comprobante}.pdf");
    }

    public function adjunto(Abono $abono)
    {
        abort_unless($abono->adjunto_path && Storage::disk('local')->exists($abono->adjunto_path), 404);

        return Storage::disk('local')->response($abono->adjunto_path);
    }
}
