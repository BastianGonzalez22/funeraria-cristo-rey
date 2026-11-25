<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\LugarDestino;
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
            'lugares' => LugarDestino::orderBy('tipo')->orderBy('orden')->orderBy('nombre')->get(),
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

    /** Agrega un cementerio o crematorio a la lista que se ofrece en las solicitudes. */
    public function guardarLugar(Request $request)
    {
        $d = $request->validate([
            'tipo' => ['required', 'in:cementerio,crematorio'],
            'nombre' => ['required', 'string', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:120'],
            'comuna' => ['required', 'string', 'max:60'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['orden' => 'cercanía']);
        $lugar = LugarDestino::create($d + ['orden' => $d['orden'] ?? 50, 'activo' => true]);
        AuditService::registrar('configuracion', "Nuevo lugar de destino: {$lugar->nombre}");

        return back()->with('ok', LugarDestino::TIPOS[$lugar->tipo].' agregado a la lista.');
    }

    public function alternarLugar(LugarDestino $lugar)
    {
        $lugar->update(['activo' => ! $lugar->activo]);

        return back()->with('ok', $lugar->activo ? 'Lugar visible en las solicitudes.' : 'Lugar oculto de las solicitudes.');
    }

    public function eliminarLugar(LugarDestino $lugar)
    {
        AuditService::registrar('configuracion', "Lugar de destino eliminado: {$lugar->nombre}");
        $lugar->delete();

        return back()->with('ok', 'Lugar eliminado de la lista. Las solicitudes que ya lo usaban no cambian.');
    }
}
