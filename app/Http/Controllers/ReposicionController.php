<?php

namespace App\Http\Controllers;

use App\Models\MovimientoUrna;
use App\Models\Proveedor;
use App\Models\Urna;
use App\Services\StockService;
use Illuminate\Http\Request;

/**
 * Pantalla del reponedor: qué falta reponer, registrar la llegada del proveedor
 * (cada urna recibe un código nuevo) e imprimir sus etiquetas.
 */
class ReposicionController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index()
    {
        $urnas = Urna::with('proveedor')->where('bajo_pedido', false)->orderBy('codigo')->get();
        $porReponer = $urnas->filter(fn ($u) => in_array($u->estado, ['reponer', 'en_umbral'], true))
            ->map(function ($u) {
                $u->sugerido = max(1, $u->stock_minimo * 2 - $u->disponible());

                return $u;
            });

        return view('reposicion.index', [
            'porReponer' => $porReponer,
            'urnas' => $urnas,
            'proveedores' => Proveedor::where('estado', 'activo')->orderBy('nombre')->get(),
            'ind' => $this->stock->indicadores(),
            'historial' => MovimientoUrna::with(['urna', 'proveedor', 'usuario'])->where('tipo', 'compra')->latest()->limit(15)->get(),
            'verPrecios' => auth()->user()->puede('urnas'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'urna_id' => ['required', 'exists:urnas,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'cantidad' => ['required', 'integer', 'min:1', 'max:60'],
            'costo_total' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $unidades = $this->stock->registrarCompra((int) $d['urna_id'], $d['proveedor_id'] ?? null, (int) $d['cantidad'], (int) ($d['costo_total'] ?? 0), $request->user());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('reposicion.index', ['nuevas' => $unidades->pluck('id')->implode(',')])
            ->with('ok', 'Llegada registrada: '.$unidades->count().' urna(s) con códigos '.$unidades->first()->codigo.($unidades->count() > 1 ? ' a '.$unidades->last()->codigo : '').'.');
    }
}
