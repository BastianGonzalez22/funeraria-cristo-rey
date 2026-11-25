<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Imagen del login, imagen de portada y textos del sitio. */
class ConfiguracionController extends Controller
{
    private const IMAGENES = [
        'login_imagen' => 'Imagen del ingreso al sistema',
        'portada_imagen' => 'Imagen de portada del sitio',
        'firma_imagen' => 'Firma y timbre de los documentos PDF',
    ];

    public function index()
    {
        return view('configuracion.index', [
            'imagenes' => collect(self::IMAGENES)->map(fn ($t, $k) => ['titulo' => $t, 'url' => Ajuste::imagen($k)]),
            'login_texto' => Ajuste::valor('login_texto', config('funeraria.lema')),
            'portada_titulo' => Ajuste::valor('portada_titulo', config('funeraria.lema')),
        ]);
    }

    public function guardar(Request $request)
    {
        $request->validate([
            'login_imagen' => ['nullable', 'image', 'max:4096'],
            'portada_imagen' => ['nullable', 'image', 'max:4096'],
            'firma_imagen' => ['nullable', 'image', 'max:2048'],
            'login_texto' => ['nullable', 'string', 'max:200'],
            'portada_titulo' => ['nullable', 'string', 'max:150'],
            'quitar' => ['nullable', 'array'],
            'quitar.*' => ['in:login_imagen,portada_imagen,firma_imagen'],
        ]);

        foreach (array_keys(self::IMAGENES) as $clave) {
            $quitar = in_array($clave, $request->input('quitar', []), true);
            if ($request->hasFile($clave) || $quitar) {
                if ($anterior = Ajuste::valor($clave)) {
                    Storage::disk('public')->delete($anterior);
                }
                Ajuste::guardar($clave, $quitar ? null : $request->file($clave)->store('sitio', 'public'));
            }
        }
        if ($request->filled('quitar')) {
            AuditService::registrar('configuracion', 'Se eliminó la imagen: '.implode(', ', $request->input('quitar')));

            return back()->with('ok', 'Imagen eliminada.');
        }
        Ajuste::guardar('login_texto', $request->input('login_texto'));
        Ajuste::guardar('portada_titulo', $request->input('portada_titulo'));
        AuditService::registrar('configuracion', 'Actualización de la configuración del sitio');

        return back()->with('ok', 'Configuración guardada.');
    }
}
