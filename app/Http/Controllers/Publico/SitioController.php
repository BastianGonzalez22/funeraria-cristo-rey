<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Condolencia;
use App\Models\Convenio;
use App\Models\MensajeContacto;
use App\Models\Opinion;
use App\Models\Solicitud;
use App\Models\Urna;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sitio público: catálogo simplificado de urnas, convenios con logos,
 * preguntas frecuentes, contacto/WhatsApp, opiniones y la página del
 * servicio con el libro de condolencias digital.
 */
class SitioController extends Controller
{
    public function inicio()
    {
        return view('publico.inicio', [
            'destacadas' => Urna::with('imagenes')->where('publica', true)->ofrecibles()->orderBy('precio')->limit(4)->get(),
            'convenios' => Convenio::vigentes()->orderBy('institucion')->get(),
            'opiniones' => Opinion::where('publicada', true)->latest()->limit(6)->get(),
        ]);
    }

    public function catalogo(Request $request)
    {
        $filtros = $request->only(['categoria', 'color', 'acabado']);

        return view('publico.catalogo', [
            'urnas' => Urna::with('imagenes')->where('publica', true)->ofrecibles()->filtrar($filtros)->orderBy('categoria')->orderBy('precio')->get(),
            'filtros' => $filtros,
            'colores' => Urna::where('publica', true)->whereNotNull('color')->distinct()->orderBy('color')->pluck('color'),
        ]);
    }

    public function urna(Urna $urna)
    {
        abort_unless($urna->publica, 404);

        return view('publico.urna', ['urna' => $urna->load('imagenes')]);
    }

    public function convenios()
    {
        return view('publico.convenios', ['convenios' => Convenio::vigentes()->orderBy('tipo')->orderBy('institucion')->get()]);
    }

    public function faq()
    {
        return view('publico.faq');
    }

    public function contacto(Request $request)
    {
        return view('publico.contacto', [
            'urna' => $request->query('urna') ? Urna::where('codigo', $request->query('urna'))->first() : null,
        ]);
    }

    public function enviarContacto(Request $request)
    {
        if ($request->filled('sitio_web')) { // honeypot anti-spam
            return back();
        }
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['required', 'regex:/^\+?[0-9 ]{8,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'medio_contacto' => ['required', Rule::in(array_keys(config('funeraria.medios_contacto')))],
            'urna_id' => ['nullable', 'exists:urnas,id'],
            'mensaje' => ['required', 'string', 'max:1500'],
        ], ['telefono.regex' => 'Ingrese un teléfono válido.']);

        MensajeContacto::create($d);

        return back()->with('ok', 'Recibimos su mensaje. Nos comunicaremos a la brevedad por el medio que indicó.');
    }

    public function enviarOpinion(Request $request)
    {
        if ($request->filled('sitio_web')) {
            return back();
        }
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['required', 'string', 'max:1000'],
        ]);
        Opinion::create($d + ['publicada' => false]);

        // Aviso pequeño en el mismo recuadro del formulario (no en la parte superior de la página)
        return redirect()->to(url()->previous().'#opinion')->with('opinion_enviada', true);
    }

    /** Página para compartir con familiares: velatorio, misa, traslado y condolencias. */
    public function servicio(string $token)
    {
        $s = Solicitud::with(['cliente', 'asignacion', 'condolencias' => fn ($q) => $q->where('visible', true)])
            ->where('token_publico', $token)
            ->where('publicar_servicio', true)
            ->whereIn('estado', ['aprobada', 'finalizada'])
            ->firstOrFail();

        return view('publico.servicio', ['s' => $s]);
    }

    public function condolencia(Request $request, string $token)
    {
        $s = Solicitud::where('token_publico', $token)->where('publicar_servicio', true)->firstOrFail();
        if ($request->filled('sitio_web')) {
            return back();
        }
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'mensaje' => ['required', 'string', 'max:1000'],
        ]);
        Condolencia::create($d + ['solicitud_id' => $s->id, 'visible' => true]);

        return back()->with('ok', 'Su mensaje fue agregado al libro de condolencias.');
    }
}
