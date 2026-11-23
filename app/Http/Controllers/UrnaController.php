<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use App\Models\Urna;
use App\Models\UrnaImagen;
use App\Models\UrnaUnidad;
use App\Services\AuditService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UrnaController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    /** Stock de urnas: modelos, unidades e indicadores (choferes y reponedores ven sin precios). */
    public function index(Request $request)
    {
        $filtros = $request->only(['q', 'categoria', 'color', 'acabado']);
        $estadoUnidad = $request->query('estado_unidad');

        $urnas = Urna::with(['proveedor', 'imagenes'])->withCount([
            'unidades as disponibles_count' => fn ($q) => $q->where('estado', 'disponible'),
            'unidades as reservadas_count' => fn ($q) => $q->where('estado', 'reservada'),
            'unidades as vendidas_count' => fn ($q) => $q->where('estado', 'vendida'),
        ])->filtrar($filtros)->orderBy('codigo')->get();

        $unidades = UrnaUnidad::with(['urna', 'solicitud.cliente'])
            ->when($estadoUnidad, fn ($q) => $q->where('estado', $estadoUnidad), fn ($q) => $q->enBodega())
            ->when($filtros['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('codigo', 'like', "%{$t}%")->orWhereHas('urna', fn ($u) => $u->where('modelo', 'like', "%{$t}%"))))
            ->when($filtros['categoria'] ?? null, fn ($q, $c) => $q->whereHas('urna', fn ($u) => $u->where('categoria', $c)))
            ->orderBy('codigo')
            ->paginate(25, ['*'], 'pag_unidades')
            ->withQueryString();

        return view('urnas.index', [
            'urnas' => $urnas,
            'unidades' => $unidades,
            'estadoUnidad' => $estadoUnidad,
            'proveedores' => Proveedor::orderBy('nombre')->get(),
            'ind' => $this->stock->indicadores(),
            'filtros' => $filtros,
            'colores' => Urna::whereNotNull('color')->distinct()->orderBy('color')->pluck('color'),
            'verPrecios' => $request->user()->puede('urnas'),
        ]);
    }

    /** Inventario de unidades en CSV (Excel). */
    public function exportar(Request $request)
    {
        $verPrecios = $request->user()->puede('urnas');
        $filas = UrnaUnidad::with(['urna', 'solicitud.cliente', 'proveedor'])->orderBy('codigo')->get()->map(fn ($u) => array_merge([
            $u->codigo, $u->urna->codigo, $u->urna->modelo, etiqueta('categorias_urna', $u->urna->categoria),
            $u->urna->acabado ? etiqueta('acabados_urna', $u->urna->acabado) : '', $u->urna->medidasTexto(),
            $u->nombreEstado(), $u->solicitud?->cliente?->nombre_familia, $u->solicitud?->cliente?->nombre_contratante,
            $u->fecha_ingreso?->format('d/m/Y'), $u->proveedor?->nombre,
        ], $verPrecios ? [$u->urna->precio, $u->costo] : []));

        return $this->csv('inventario-urnas-'.now()->format('Y-m-d').'.csv', array_merge(
            ['Código unidad', 'Modelo', 'Nombre', 'Categoría', 'Acabado', 'Medidas', 'Estado', 'Familia', 'Contratante', 'Ingreso', 'Proveedor'],
            $verPrecios ? ['Precio venta', 'Costo'] : []
        ), $filas);
    }

    /** Catálogo interno: solo modelos que se pueden ofrecer (las reservadas no se muestran). */
    public function catalogo(Request $request)
    {
        $filtros = $request->only(['q', 'categoria', 'color', 'acabado']);
        $todas = $request->boolean('todas');

        return view('urnas.catalogo', [
            'urnas' => Urna::with('imagenes')->filtrar($filtros)->when(! $todas, fn ($q) => $q->ofrecibles())->orderBy('categoria')->orderBy('precio')->get(),
            'filtros' => $filtros,
            'todas' => $todas,
            'colores' => Urna::whereNotNull('color')->distinct()->orderBy('color')->pluck('color'),
            'destino' => $request->query('destino', 'clientes'),
        ]);
    }

    public function show(Urna $urna)
    {
        return view('urnas.show', [
            'urna' => $urna->load(['proveedor', 'imagenes', 'movimientos.usuario', 'movimientos.proveedor']),
            'unidades' => $urna->unidades()->with('solicitud.cliente')->where('estado', '!=', 'baja')->orderBy('codigo')->get(),
        ]);
    }

    public function create()
    {
        return view('urnas.form', [
            'urna' => new Urna(['categoria' => 'estandar', 'acabado' => 'lisa', 'stock_minimo' => config('funeraria.stock_minimo_defecto'), 'publica' => true]),
            'proveedores' => Proveedor::orderBy('nombre')->get(),
            'libre' => $this->stock->espacioLibre(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);
        $inicial = (int) $request->input('stock_inicial', 0);
        if (! $d['bajo_pedido'] && $inicial > $this->stock->espacioLibre()) {
            return back()->withInput()->withErrors(['stock_inicial' => 'Supera la capacidad libre de la bodega ('.$this->stock->espacioLibre().').']);
        }

        $urna = Urna::create($d);
        $this->guardarImagenes($request, $urna);
        if ($inicial > 0) {
            $this->stock->registrarCompra($urna->id, $urna->proveedor_id, $inicial, 0, $request->user());
        }
        AuditService::registrar('urna_creada', "Alta del modelo {$urna->codigo} {$urna->modelo}", $urna);

        return redirect()->route('urnas.show', $urna)->with('ok', 'Modelo registrado.'.($inicial ? " Se crearon {$inicial} unidades con su código." : ''));
    }

    public function edit(Urna $urna)
    {
        return view('urnas.form', ['urna' => $urna->load('imagenes'), 'proveedores' => Proveedor::orderBy('nombre')->get(), 'libre' => $this->stock->espacioLibre()]);
    }

    public function update(Request $request, Urna $urna)
    {
        $d = $this->validar($request, $urna);
        $urna->fill($d);
        $cambios = AuditService::cambios($urna);
        $urna->save();
        $this->guardarImagenes($request, $urna);
        AuditService::registrar('urna_modificada', "Modificación del modelo {$urna->codigo}", $urna, $cambios);

        return redirect()->route('urnas.show', $urna)->with('ok', 'Urna actualizada.');
    }

    /** Administrador de fotos de un modelo (subir, eliminar, título y foto principal). */
    public function fotos(Urna $urna)
    {
        return view('urnas.fotos', ['urna' => $urna->load('imagenes')]);
    }

    public function subirFotos(Request $request, Urna $urna)
    {
        $request->validate([
            'imagenes' => ['required', 'array', 'max:10'],
            'imagenes.*' => ['image', 'max:4096'],
        ], ['imagenes.required' => 'Seleccione al menos una foto.', 'imagenes.*.image' => 'Cada archivo debe ser una imagen (JPG, PNG o WEBP).', 'imagenes.*.max' => 'Cada foto puede pesar hasta 4 MB.']);
        $this->guardarImagenes($request, $urna);
        AuditService::registrar('urna_fotos', 'Se agregaron '.count($request->file('imagenes')).' foto(s) a '.$urna->codigo, $urna);

        return back()->with('ok', 'Fotos agregadas.');
    }

    public function actualizarImagen(Request $request, UrnaImagen $imagen)
    {
        $d = $request->validate(['titulo' => ['nullable', 'string', 'max:80']]);
        $imagen->update(['titulo' => $d['titulo'] ?? null]);

        return back()->with('ok', 'Título de la foto actualizado.');
    }

    /** La foto elegida pasa a ser la primera (la que se ve en el catálogo). */
    public function portadaImagen(UrnaImagen $imagen)
    {
        $min = (int) UrnaImagen::where('urna_id', $imagen->urna_id)->min('orden');
        $imagen->update(['orden' => $min - 1]);

        return back()->with('ok', 'Foto principal actualizada.');
    }

    public function eliminarImagen(UrnaImagen $imagen)
    {
        Storage::disk('public')->delete($imagen->path);
        $urna = $imagen->urna;
        $imagen->delete();
        if ($urna && $urna->imagen === $imagen->path) {
            $urna->update(['imagen' => null]);
        }
        if ($urna) {
            AuditService::registrar('urna_fotos', 'Se eliminó una foto de '.$urna->codigo, $urna);
        }

        return back()->with('ok', 'Foto eliminada.');
    }

    public function guardarProveedor(Request $request)
    {
        $p = Proveedor::create($this->validarProveedor($request));
        AuditService::registrar('proveedor_creado', "Alta del proveedor {$p->nombre}", $p);

        return back()->with('ok', 'Proveedor registrado.');
    }

    public function actualizarProveedor(Request $request, Proveedor $proveedor)
    {
        $proveedor->update($this->validarProveedor($request));

        return back()->with('ok', 'Proveedor actualizado.');
    }

    private function guardarImagenes(Request $request, Urna $urna): void
    {
        $orden = (int) $urna->imagenes()->max('orden');
        foreach ((array) $request->file('imagenes', []) as $archivo) {
            $urna->imagenes()->create(['path' => $archivo->store('urnas', 'public'), 'orden' => ++$orden]);
        }
    }

    private function validarProveedor(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'ciudad' => ['nullable', 'string', 'max:80'],
            'modalidad' => ['nullable', 'string', 'max:150'],
            'periodicidad' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ]) + ['estado' => 'activo'];
    }

    private function validar(Request $request, ?Urna $urna = null): array
    {
        $d = $request->validate([
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('urnas', 'codigo')->ignore($urna?->id)],
            'modelo' => ['required', 'string', 'max:120'],
            'categoria' => ['required', Rule::in(array_keys(config('funeraria.categorias_urna')))],
            'material' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:60'],
            'acabado' => ['nullable', Rule::in(array_keys(config('funeraria.acabados_urna')))],
            'largo_cm' => ['nullable', 'integer', 'between:40,260'],
            'ancho_cm' => ['nullable', 'integer', 'between:20,120'],
            'alto_cm' => ['nullable', 'integer', 'between:15,100'],
            'manillas' => ['nullable', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'precio' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:30'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'imagenes' => ['nullable', 'array', 'max:10'],
            'imagenes.*' => ['image', 'max:4096'],
            'stock_inicial' => ['nullable', 'integer', 'min:0', 'max:30'],
        ], ['codigo.regex' => 'El código solo puede tener letras, números y guiones (ej: U-111).']);
        $d['bajo_pedido'] = $request->boolean('bajo_pedido');
        $d['publica'] = $request->boolean('publica');
        $d['medidas'] = ($d['largo_cm'] ?? null) && ($d['ancho_cm'] ?? null) && ($d['alto_cm'] ?? null)
            ? "{$d['largo_cm']} x {$d['ancho_cm']} x {$d['alto_cm']} cm" : ($urna?->medidas);
        unset($d['imagenes'], $d['stock_inicial']);

        return $d;
    }
}
