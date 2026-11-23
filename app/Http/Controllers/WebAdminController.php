<?php

namespace App\Http\Controllers;

use App\Models\Condolencia;
use App\Models\MensajeContacto;
use App\Models\Opinion;

/** Administración del sitio: mensajes del formulario, opiniones y condolencias. */
class WebAdminController extends Controller
{
    public function index()
    {
        return view('web.index', [
            'mensajes' => MensajeContacto::with('urna')->orderBy('atendido')->latest()->limit(50)->get(),
            'opiniones' => Opinion::orderBy('publicada')->latest()->limit(50)->get(),
            'condolencias' => Condolencia::with('solicitud.cliente')->latest()->limit(50)->get(),
        ]);
    }

    public function atenderMensaje(MensajeContacto $mensaje)
    {
        $mensaje->update(['atendido' => ! $mensaje->atendido]);

        return back()->with('ok', 'Mensaje actualizado.');
    }

    public function publicarOpinion(Opinion $opinion)
    {
        $opinion->update(['publicada' => ! $opinion->publicada]);

        return back()->with('ok', $opinion->publicada ? 'Opinión publicada en el sitio.' : 'Opinión ocultada.');
    }

    public function ocultarCondolencia(Condolencia $condolencia)
    {
        $condolencia->update(['visible' => ! $condolencia->visible]);

        return back()->with('ok', 'Condolencia actualizada.');
    }
}
