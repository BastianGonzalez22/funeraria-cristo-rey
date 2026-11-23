<?php

namespace App\Http\Controllers;

use App\Models\Convenio;
use App\Models\VerificacionBeneficio;
use App\Rules\RutValido;
use App\Services\AuditService;
use App\Services\BeneficioService;
use App\Support\Rut;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ConvenioController extends Controller
{
    public function index(BeneficioService $beneficios)
    {
        return view('convenios.index', [
            'convenios' => Convenio::withCount('contratos')->orderBy('estado')->orderBy('institucion')->get(),
            'verificaciones' => VerificacionBeneficio::with(['usuario', 'solicitud.cliente'])->latest()->limit(15)->get(),
            'enHorario' => $beneficios->dentroDeHorario(),
            'siguienteHabil' => $beneficios->siguienteHabil(),
        ]);
    }

    /** Descripción de convenios disponibles (enlazada desde el registro de clientes). */
    public function disponibles()
    {
        return view('convenios.disponibles', ['convenios' => Convenio::vigentes()->orderBy('tipo')->orderBy('institucion')->get()]);
    }

    public function create()
    {
        return view('convenios.form', ['convenio' => new Convenio(['tipo' => 'afp', 'estado' => 'vigente', 'color' => '#8a6d4b', 'vigencia_desde' => now()])]);
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        if ($request->hasFile('logo')) {
            $d['logo'] = $request->file('logo')->store('convenios', 'public');
        }
        $c = Convenio::create($d);
        AuditService::registrar('convenio_creado', "Alta del convenio {$c->institucion}", $c);

        return redirect()->route('convenios.index')->with('ok', 'Convenio registrado.');
    }

    public function edit(Convenio $convenio)
    {
        return view('convenios.form', ['convenio' => $convenio]);
    }

    public function update(Request $request, Convenio $convenio)
    {
        $d = $this->validar($request);
        if ($request->hasFile('logo')) {
            if ($convenio->logo) {
                Storage::disk('public')->delete($convenio->logo);
            }
            $d['logo'] = $request->file('logo')->store('convenios', 'public');
        }
        $convenio->fill($d);
        $cambios = AuditService::cambios($convenio);
        $convenio->save();
        AuditService::registrar('convenio_modificado', "Modificación del convenio {$convenio->institucion}", $convenio, $cambios);

        return redirect()->route('convenios.index')->with('ok', 'Convenio actualizado.');
    }

    /** PDF desde plantilla única: solo cambia la institución y los datos. */
    public function pdf(Convenio $convenio)
    {
        return Pdf::loadView('pdf.convenio', ['c' => $convenio])->setPaper('letter')->stream('convenio-'.str($convenio->institucion)->slug().'.pdf');
    }

    public function verificar(Request $request, BeneficioService $beneficios)
    {
        $d = $request->validate([
            'rut' => ['required', new RutValido],
            'institucion' => ['required', Rule::in(array_keys(VerificacionBeneficio::INSTITUCIONES))],
            'solicitud_id' => ['nullable', 'exists:solicitudes,id'],
        ]);
        $v = $beneficios->solicitar(Rut::formatear($d['rut']), $d['institucion'], $request->user(), $d['solicitud_id'] ?? null);

        return back()->with($v->estado === 'pendiente' ? 'error' : 'ok', $v->nota);
    }

    public function resolverVerificacion(Request $request, VerificacionBeneficio $verificacion)
    {
        $d = $request->validate([
            'estado' => ['required', Rule::in(['con_beneficio', 'sin_beneficio', 'pendiente', 'por_verificar'])],
            'monto_beneficio' => ['nullable', 'integer', 'min:0'],
            'nota' => ['nullable', 'string', 'max:255'],
        ]);
        $d['monto_beneficio'] = (int) ($d['monto_beneficio'] ?? 0);
        $verificacion->update($d);
        AuditService::registrar('beneficio_resuelto', 'Resultado de verificación '.strtoupper($verificacion->institucion).': '.$d['estado'], $verificacion);

        return back()->with('ok', 'Verificación actualizada.');
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'institucion' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(array_keys(config('funeraria.tipos_convenio')))],
            'beneficio' => ['required', 'string', 'max:255'],
            'descuento_pct' => ['required', 'integer', 'min:0', 'max:100'],
            'monto_cobertura' => ['nullable', 'integer', 'min:0'],
            'requisitos' => ['nullable', 'string', 'max:2000'],
            'documentos' => ['nullable', 'string', 'max:2000'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'vigencia_desde' => ['nullable', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'estado' => ['required', Rule::in(['vigente', 'inactivo'])],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);
        unset($d['logo']);
        $d['monto_cobertura'] = (int) ($d['monto_cobertura'] ?? 0);
        $d['color'] = $d['color'] ?? '#8a6d4b';

        return $d;
    }
}
