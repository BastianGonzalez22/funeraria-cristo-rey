<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\Urna;
use App\Models\UrnaUnidad;
use App\Services\SolicitudService;
use App\Services\StockService;
use Illuminate\Http\Request;

/**
 * Unidades físicas de urna: ficha, etiqueta, reservar (con abono), comprar,
 * cambiar por otra y dar de baja.
 */
class UnidadController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function show(UrnaUnidad $unidad)
    {
        $unidad->load(['urna.imagenes', 'solicitud.cliente', 'solicitud.contrato', 'proveedor', 'ingresadaPor']);

        return view('urnas.unidad', [
            'u' => $unidad,
            'pendientes' => $unidad->estado === 'disponible'
                ? Solicitud::with('cliente')->whereIn('estado', ['en_revision', 'con_observaciones'])->orderBy('fecha_hora')->get()
                : collect(),
            'aprobadas' => $unidad->estado === 'disponible'
                ? Solicitud::with(['cliente', 'unidad'])->where('estado', 'aprobada')->orderBy('fecha_hora')->get()
                : collect(),
        ]);
    }

    /** Etiquetas imprimibles: ?ids=1,2,3  o  ?urna=ID (todas las unidades en bodega del modelo). */
    public function etiquetas(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids')))->filter()->map(fn ($i) => (int) $i);
        $unidades = UrnaUnidad::with('urna')
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids))
            ->when($ids->isEmpty() && $request->query('urna'), fn ($q) => $q->where('urna_id', $request->query('urna'))->enBodega())
            ->when($ids->isEmpty() && ! $request->query('urna'), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('codigo')
            ->get();

        return view('urnas.etiquetas', ['unidades' => $unidades]);
    }

    /**
     * Reservar: la reserva se concreta al aprobar la solicitud con su abono.
     * Lleva al formulario de aprobación con esta unidad ya elegida.
     */
    public function reservar(Request $request, UrnaUnidad $unidad)
    {
        $request->validate(['solicitud_id' => ['required', 'exists:solicitudes,id']]);
        $s = Solicitud::findOrFail($request->query('solicitud_id'));

        if ($unidad->estado !== 'disponible') {
            return back()->with('error', 'Esta urna ya no está disponible.');
        }
        if ($s->estado === 'aprobada') {
            return $this->intentar(
                fn () => app(SolicitudService::class)->cambiarUrna($s, $unidad->urna_id, $unidad->id),
                "Urna {$unidad->codigo} asignada a {$s->cliente->nombre_familia}. La anterior quedó disponible.",
                route('solicitudes.show', $s)
            );
        }
        if (! in_array($s->estado, ['en_revision', 'con_observaciones'], true)) {
            return back()->with('error', 'La solicitud ya está cerrada.');
        }

        return redirect()->route('solicitudes.aprobar.form', ['solicitud' => $s->id, 'unidad' => $unidad->id]);
    }

    /** Comprar: la reserva pasa a venta (la urna queda vendida a esa familia). */
    public function comprar(UrnaUnidad $unidad)
    {
        $contrato = $unidad->solicitud?->contrato;
        if ($contrato && ! $contrato->cumpleAbonoMinimo() && ! $contrato->cuota_mortuoria_en_tramite) {
            return back()->with('error', 'Para confirmar la compra la familia debe tener abonado al menos el '.config('funeraria.abono_minimo_pct').'% ('.clp($contrato->abono_minimo).').');
        }

        return $this->intentar(fn () => $this->stock->venderUnidad($unidad), "Compra confirmada: la urna {$unidad->codigo} quedó vendida.");
    }

    /** Modificar: la familia quiere otra urna. */
    public function cambiar(Request $request, Solicitud $solicitud)
    {
        $d = $request->validate(['urna_unidad_id' => ['required', 'exists:urna_unidades,id']]);
        $nueva = UrnaUnidad::findOrFail($d['urna_unidad_id']);

        return $this->intentar(
            fn () => app(SolicitudService::class)->cambiarUrna($solicitud, $nueva->urna_id, $nueva->id),
            "Urna cambiada por {$nueva->codigo}. La anterior volvió a estar disponible y el contrato se ajustó.",
            route('solicitudes.show', $solicitud)
        );
    }

    public function baja(Request $request, UrnaUnidad $unidad)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:200']]);

        return $this->intentar(fn () => $this->stock->darDeBaja($unidad, $d['motivo']), "Unidad {$unidad->codigo} dada de baja.");
    }
}
