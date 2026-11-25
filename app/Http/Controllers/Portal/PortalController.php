<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudRequest;
use App\Models\Abono;
use App\Models\Contrato;
use App\Models\Convenio;
use App\Models\Solicitud;
use App\Models\Sucursal;
use App\Models\Urna;
use App\Services\PagoService;
use App\Services\SolicitudService;
use App\Support\Rut;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Portal del usuario solicitante: crea y edita sus solicitudes y revisa
 * su carpeta de pagos (cuánto se cobró, cuánto abonó y cuánto le falta).
 */
class PortalController extends Controller
{
    public function __construct(private SolicitudService $servicio)
    {
    }

    public function index(Request $request)
    {
        $cliente = $request->user()->cliente;

        return view('portal.index', [
            'cliente' => $cliente,
            'solicitudes' => $cliente ? $cliente->solicitudes()->with(['urna', 'unidad', 'contrato'])->latest()->get() : collect(),
            'resumen' => $cliente?->resumenPagos(),
        ]);
    }

    public function create(Request $request)
    {
        return view('portal.form', $this->datos() + [
            'solicitud' => new Solicitud([
                'tipo_servicio' => 'velatorio_cortejo',
                'destino_final' => 'sepultura',
                'medio_contacto' => $request->user()->cliente?->medio_contacto ?? 'telefono',
                'urna_id' => $request->query('urna'),
            ]),
            'cliente' => $request->user()->cliente,
        ]);
    }

    public function store(SolicitudRequest $request)
    {
        return $this->intentar(function () use ($request) {
            $u = $request->user();
            $cliente = $this->servicio->clienteDesdeFormulario([
                'rut_contratante' => Rut::formatear($request->input('rut_contratante')),
                'telefono' => $request->input('telefono'),
                'medio_contacto' => $request->input('medio_contacto'),
                'nombre_familia' => 'Familia '.$u->nombre,
                'nombre_contratante' => $u->nombre,
                'email' => $u->email,
            ], $u);
            $datos = $request->datosSolicitud() + ['cliente_id' => $cliente->id];

            return $this->servicio->crear($datos, $u, $request->file('certificado'));
        }, 'Su solicitud fue enviada. La funeraria la revisará y se comunicará con usted.', fn ($s) => route('portal.show', $s));
    }

    public function show(Request $request, Solicitud $solicitud)
    {
        $this->autorizar($request, $solicitud);

        return view('portal.show', ['s' => $solicitud->load(['urna', 'unidad', 'sucursal', 'contrato.abonos', 'asignacion', 'condolencias'])]);
    }

    public function edit(Request $request, Solicitud $solicitud)
    {
        $this->autorizar($request, $solicitud);
        abort_unless($solicitud->esEditablePorSolicitante(), 403, 'La solicitud ya fue procesada. Para cambios comuníquese con la funeraria.');

        return view('portal.form', $this->datos() + ['solicitud' => $solicitud, 'cliente' => $request->user()->cliente]);
    }

    public function update(SolicitudRequest $request, Solicitud $solicitud)
    {
        $this->autorizar($request, $solicitud);
        abort_unless($solicitud->esEditablePorSolicitante(), 403);

        return $this->intentar(function () use ($request, $solicitud) {
            $s = $this->servicio->actualizar($solicitud, $request->datosSolicitud(), $request->file('certificado'));
            if ($s->estado === 'con_observaciones') {
                $s->update(['estado' => 'en_revision']);
            }

            return $s;
        }, 'Solicitud actualizada y enviada nuevamente a revisión.', route('portal.show', $solicitud));
    }

    public function pagos(Request $request)
    {
        $cliente = $request->user()->cliente;
        $contratos = $cliente ? $cliente->contratos()->with(['abonos', 'solicitud'])->get() : collect();

        return view('portal.pagos', [
            'contratos' => $contratos,
            'resumen' => $cliente?->resumenPagos(),
            'cuenta' => config('funeraria.cuenta_bancaria'),
        ]);
    }

    /** La familia informa un abono (transferencia/depósito) con su comprobante; queda por confirmar. */
    public function abonar(Request $request, PagoService $pagos)
    {
        $d = $request->validate([
            'contrato_id' => ['required', 'exists:contratos,id'],
            'monto' => ['required', 'integer', 'min:1000'],
            'medio_pago' => ['required', \Illuminate\Validation\Rule::in(['transferencia', 'tarjeta', 'efectivo'])],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'comprobante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'comprobante.required' => 'Adjunte el comprobante de la transferencia o depósito.',
            'monto.min' => 'El monto mínimo para informar un abono es $1.000.',
        ]);
        $contrato = Contrato::findOrFail($d['contrato_id']);
        $this->autorizar($request, $contrato->solicitud);

        return $this->intentar(
            fn () => $pagos->declarar($contrato, $d, $request->user(), $request->file('comprobante')),
            'Recibimos su abono. La funeraria lo verificará y se descontará de su saldo; le avisaremos cuando esté confirmado.',
            route('portal.pagos')
        );
    }

    public function comprobante(Request $request, Abono $abono)
    {
        $this->autorizar($request, $abono->contrato->solicitud);
        abort_if($abono->estado !== 'registrado' && $abono->estado !== 'anulado', 404, 'El comprobante se emite cuando la funeraria confirma el abono.');
        $abono->load(['contrato.solicitud.cliente', 'contrato.solicitud.sucursal', 'usuario', 'confirmadoPor']);

        return Pdf::loadView('pdf.comprobante', ['abono' => $abono])->stream("comprobante-{$abono->numero_comprobante}.pdf");
    }

    public function contratoPdf(Request $request, Contrato $contrato)
    {
        $this->autorizar($request, $contrato->solicitud);
        $contrato->load(['solicitud.cliente', 'solicitud.urna', 'solicitud.sucursal', 'convenio', 'abonos']);

        return Pdf::loadView('pdf.contrato', ['c' => $contrato])->stream("contrato-{$contrato->numero}.pdf");
    }

    private function autorizar(Request $request, Solicitud $s): void
    {
        $cliente = $request->user()->cliente;
        abort_unless($cliente && $s->cliente_id === $cliente->id, 403);
    }

    private function datos(): array
    {
        return [
            'sucursales' => Sucursal::activas()->get(),
            'urnas' => Urna::where('publica', true)->ofrecibles()->orderBy('categoria')->orderBy('precio')->get(),
            'convenios' => Convenio::vigentes()->orderBy('institucion')->get(),
        ];
    }
}
