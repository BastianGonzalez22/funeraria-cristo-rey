<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use App\Services\AuditService;
use App\Services\PagoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ContratoController extends Controller
{
    public function show(Contrato $contrato)
    {
        $contrato->load(['solicitud.cliente', 'solicitud.urna', 'convenio', 'abonos.usuario']);

        return view('contratos.show', [
            'contrato' => $contrato,
            'modificaciones' => \App\Models\Auditoria::with('usuario')->where('auditable_type', Contrato::class)->where('auditable_id', $contrato->id)->latest('created_at')->get(),
        ]);
    }

    /** Plan de pago: cuotas y fecha de vencimiento (la familia paga en partes). */
    public function update(Request $request, Contrato $contrato)
    {
        $d = $request->validate([
            'cuotas' => ['required', 'integer', 'min:1', 'max:24'],
            'fecha_vencimiento' => ['required', 'date'],
            'cuota_mortuoria_en_tramite' => ['nullable', 'boolean'],
            'clausulas_adicionales' => ['nullable', 'string', 'max:2000'],
        ]);
        $d['cuota_mortuoria_en_tramite'] = $request->boolean('cuota_mortuoria_en_tramite');

        $contrato->fill($d);
        $cambios = AuditService::cambios($contrato);
        $contrato->save();
        AuditService::registrar('contrato_modificado', "Modificación del plan de pago del contrato {$contrato->numero}", $contrato, $cambios);

        return back()->with('ok', 'Plan de pago actualizado.');
    }

    public function liquidar(Contrato $contrato, PagoService $pagos)
    {
        return $this->intentar(fn () => $pagos->liquidar($contrato), 'Contrato liquidado. Ya puede descargar el finiquito.');
    }

    /** Contrato digital (reemplaza el formulario autocopiativo). */
    public function pdf(Contrato $contrato)
    {
        $contrato->load(['solicitud.cliente', 'solicitud.urna', 'solicitud.sucursal', 'convenio', 'abonos']);
        AuditService::registrar('contrato_impreso', "Descarga del contrato {$contrato->numero}", $contrato);

        return Pdf::loadView('pdf.contrato', ['c' => $contrato])->setPaper('letter')->stream("contrato-{$contrato->numero}.pdf");
    }

    public function finiquito(Contrato $contrato)
    {
        abort_unless($contrato->estado === 'liquidado', 403, 'El contrato aún no está liquidado.');
        $contrato->load(['solicitud.cliente', 'abonos']);

        return Pdf::loadView('pdf.finiquito', ['c' => $contrato])->setPaper('letter')->stream("finiquito-{$contrato->numero}.pdf");
    }
}
