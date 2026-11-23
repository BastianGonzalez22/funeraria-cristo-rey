<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitudRequest;
use App\Models\Auditoria;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Convenio;
use App\Models\Solicitud;
use App\Models\Sucursal;
use App\Models\Urna;
use App\Models\UrnaUnidad;
use App\Models\Vehiculo;
use App\Services\AlertaService;
use App\Services\AuditService;
use App\Services\SolicitudService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SolicitudController extends Controller
{
    public function __construct(private SolicitudService $servicio)
    {
    }

    public function index(Request $request, AlertaService $alertas)
    {
        $estado = $request->query('estado');
        $solicitudes = $this->filtradas($request)
            ->orderByRaw("CASE estado WHEN 'en_revision' THEN 0 WHEN 'con_observaciones' THEN 1 WHEN 'aprobada' THEN 2 ELSE 3 END")
            ->orderBy('fecha_hora')
            ->paginate(15)
            ->withQueryString();

        $modificaciones = Auditoria::with('usuario')
            ->whereIn('auditable_type', [Solicitud::class, Contrato::class])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('solicitudes.index', [
            'solicitudes' => $solicitudes,
            'estado' => $estado,
            'mes' => $this->mesValido($request->query('mes')),
            'permisos' => $alertas->permisosPendientes(),
            'modificaciones' => $modificaciones,
            'conteos' => Solicitud::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado'),
        ]);
    }

    /** Exporta a Excel (CSV) las solicitudes con los mismos filtros de la pantalla (mes, estado, nombre, RUT). */
    public function exportar(Request $request)
    {
        $filas = $this->filtradas($request)->with('unidad')->orderBy('fecha_hora')->get()->map(fn (Solicitud $s) => [
            $s->codigo(), $s->fecha_hora->format('d/m/Y H:i'), $s->cliente->nombre_familia, $s->cliente->nombre_contratante,
            $s->cliente->telefono, $s->nombre_fallecido, $s->nombreServicio(), etiqueta('destinos', $s->destino_final),
            $s->unidad?->codigo ?? $s->urna?->codigo, $s->urna?->modelo, $s->nombreEstado(), $s->contrato?->numero,
        ]);
        $sufijo = $this->mesValido($request->query('mes')) ?? now()->format('Y-m-d');

        return $this->csv("solicitudes-{$sufijo}.csv", ['Código', 'Fecha', 'Familia', 'Contratante', 'Teléfono', 'Fallecido', 'Servicio', 'Destino', 'Urna (unidad)', 'Modelo', 'Estado', 'Contrato'], $filas);
    }

    private function filtradas(Request $request)
    {
        return Solicitud::with(['cliente', 'urna', 'unidad', 'contrato'])
            ->when($request->query('estado'), fn ($q, $e) => $q->where('estado', $e))
            ->buscarFamilia($request->query('q'))
            ->when($request->query('rut'), fn ($q, $r) => $q->whereHas('cliente', fn ($c) => $c->filtrarNombreRut(null, $r)))
            ->delMes($this->mesValido($request->query('mes')));
    }

    public function create(Request $request)
    {
        $cliente = $request->query('cliente') ? Cliente::find($request->query('cliente')) : null;

        return view('solicitudes.form', $this->datosFormulario() + [
            'solicitud' => new Solicitud([
                'tipo_servicio' => 'velatorio_cortejo',
                'destino_final' => 'sepultura',
                'medio_contacto' => $cliente?->medio_contacto ?? 'telefono',
                'urna_id' => $request->query('urna', $cliente?->urna_preferida_id),
                'convenio_id' => $request->query('convenio', $cliente?->convenio_id),
                'cliente_id' => $cliente?->id,
                'documentos' => $cliente?->documentos,
            ]),
            'clienteSeleccionado' => $cliente,
            'clientes' => Cliente::orderByDesc('id')->limit(300)->get(['id', 'nombre_familia', 'nombre_contratante', 'telefono']),
        ]);
    }

    public function store(SolicitudRequest $request)
    {
        return $this->intentar(function () use ($request) {
            $datos = $request->datosSolicitud();
            $datos['cliente_id'] = $request->filled('cliente_id')
                ? (int) $request->input('cliente_id')
                : $this->servicio->clienteDesdeFormulario($request->validated())->id;

            return $this->servicio->crear($datos, $request->user(), $request->file('certificado'), $request->boolean('forzar'));
        }, 'Solicitud registrada y enviada a revisión.', fn ($s) => route('solicitudes.show', $s));
    }

    public function show(Solicitud $solicitud)
    {
        $solicitud->load(['cliente', 'urna', 'unidad', 'sucursal', 'convenio', 'contrato.abonos', 'asignacion.vehiculo', 'asignacion.chofer', 'condolencias', 'solicitante']);

        return view('solicitudes.show', [
            's' => $solicitud,
            'modificaciones' => $solicitud->modificaciones()->with('usuario')->limit(20)->get(),
            'choferes' => Chofer::orderBy('nombre')->get(),
            'unidadesLibres' => $solicitud->estado === 'aprobada' ? UrnaUnidad::with('urna')->disponibles()->orderBy('codigo')->get() : collect(),
            'solapadas' => in_array($solicitud->estado, Solicitud::ACTIVAS, true) ? $this->servicio->solapadas($solicitud->fecha_hora, $solicitud->id) : collect(),
        ]);
    }

    public function edit(Solicitud $solicitud)
    {
        abort_if(in_array($solicitud->estado, ['rechazada', 'finalizada'], true), 403, 'La solicitud está cerrada.');

        return view('solicitudes.form', $this->datosFormulario() + [
            'solicitud' => $solicitud->load('cliente'),
            'clienteSeleccionado' => $solicitud->cliente,
            'clientes' => collect(),
        ]);
    }

    public function update(SolicitudRequest $request, Solicitud $solicitud)
    {
        return $this->intentar(
            fn () => $this->servicio->actualizar($solicitud, $request->datosSolicitud(), $request->file('certificado'), $request->boolean('forzar')),
            'Solicitud actualizada. El cambio quedó registrado.',
            route('solicitudes.show', $solicitud)
        );
    }

    /** Detalle del servicio: elementos, personal, permisos, cementerio y publicación. */
    public function actualizarDetalle(Request $request, Solicitud $solicitud)
    {
        $estadosPermiso = array_keys(config('funeraria.estados_permiso'));
        $d = $request->validate([
            'elementos' => ['nullable', 'array'],
            'elementos.*' => [Rule::in(array_keys(config('funeraria.elementos')))],
            'documentos' => ['nullable', 'array'],
            'documentos.*' => [Rule::in(array_keys(config('funeraria.documentos')))],
            'personal' => ['nullable', 'array'],
            'personal.*.tarea' => ['nullable', 'string', 'max:80'],
            'personal.*.persona' => ['nullable', 'string', 'max:120'],
            'permiso_traslado' => ['required', Rule::in($estadosPermiso)],
            'permiso_sanitario' => ['required', Rule::in($estadosPermiso)],
            'cementerio_crematorio' => ['nullable', 'string', 'max:150'],
            'cementerio_contacto' => ['nullable', 'string', 'max:150'],
            'sepultura_hora' => ['nullable', 'date'],
            'lugar_velatorio' => ['nullable', 'string', 'max:200'],
            'misa_lugar' => ['nullable', 'string', 'max:200'],
            'misa_hora' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $solicitud->fill([
            'elementos' => array_fill_keys($d['elementos'] ?? [], true),
            'documentos' => array_merge($solicitud->documentos ?? [], array_fill_keys(array_keys(config('funeraria.documentos')), false), array_fill_keys($d['documentos'] ?? [], true)),
            'personal' => collect($d['personal'] ?? [])->filter(fn ($p) => ! empty($p['persona']))->values()->all(),
            'permiso_traslado' => $d['permiso_traslado'],
            'permiso_sanitario' => $d['permiso_sanitario'],
            'cementerio_crematorio' => $d['cementerio_crematorio'] ?? null,
            'cementerio_contacto' => $d['cementerio_contacto'] ?? null,
            'cementerio_confirmado' => $request->boolean('cementerio_confirmado'),
            'sepultura_hora' => $d['sepultura_hora'] ?? null,
            'lugar_velatorio' => $d['lugar_velatorio'] ?? null,
            'misa_lugar' => $d['misa_lugar'] ?? null,
            'misa_hora' => $d['misa_hora'] ?? null,
            'publicar_servicio' => $request->boolean('publicar_servicio'),
        ]);
        if ($solicitud->estado !== 'con_observaciones' && array_key_exists('observacion', $d)) {
            $solicitud->observacion = $d['observacion'];
        }

        $cambios = AuditService::cambios($solicitud, ['rut_fallecido']);
        $solicitud->save();
        if ($cambios) {
            AuditService::registrar('detalle_servicio', 'Detalle del servicio '.$solicitud->codigo().' actualizado: '.implode(', ', array_keys($cambios)), $solicitud, $cambios);
        }

        return back()->with('ok', 'Detalle del servicio guardado.');
    }

    public function formAprobar(Solicitud $solicitud)
    {
        $solicitud->load(['cliente', 'urna', 'convenio']);
        abort_unless(in_array($solicitud->estado, ['en_revision', 'con_observaciones'], true), 403, 'La solicitud ya fue procesada.');

        $unidades = UrnaUnidad::with('urna')->disponibles()->orderBy('codigo')->get();
        $elegida = $unidades->firstWhere('id', (int) request('unidad'))
            ?? $unidades->firstWhere('urna_id', $solicitud->urna_id);

        return view('solicitudes.aprobar', [
            's' => $solicitud,
            'unidades' => $unidades,
            'elegida' => $elegida,
            'convenios' => Convenio::vigentes()->orderBy('institucion')->get(),
            'conflicto' => $this->servicio->conflictoHorario($solicitud->fecha_hora, $solicitud->id),
        ]);
    }

    public function aprobar(Request $request, Solicitud $solicitud)
    {
        $d = $request->validate([
            'monto_servicio' => ['required', 'integer', 'min:0'],
            'urna_unidad_id' => ['nullable', 'exists:urna_unidades,id'],
            'convenio_id' => ['nullable', 'exists:convenios,id'],
            'descuento' => ['nullable', 'integer', 'min:0'],
            'abono_inicial' => ['nullable', 'integer', 'min:0'],
            'medio_pago' => ['nullable', Rule::in(array_keys(config('funeraria.medios_pago')))],
            'cuotas' => ['nullable', 'integer', 'min:1', 'max:24'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'cuota_mortuoria_en_tramite' => ['nullable', 'boolean'],
            'clausulas_adicionales' => ['nullable', 'string', 'max:2000'],
            'forzar' => ['nullable', 'boolean'],
        ]);

        return $this->intentar(
            fn () => $this->servicio->aprobar($solicitud, $d, $request->user()),
            'Solicitud aprobada: urna reservada y contrato generado.',
            route('solicitudes.show', $solicitud)
        );
    }

    public function observar(Request $request, Solicitud $solicitud)
    {
        $d = $request->validate(['observacion' => ['required', 'string', 'max:1000']]);

        return $this->intentar(fn () => $this->servicio->observar($solicitud, $d['observacion']), 'Solicitud observada. El solicitante podrá editarla.');
    }

    public function rechazar(Request $request, Solicitud $solicitud)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:1000']]);

        return $this->intentar(fn () => $this->servicio->rechazar($solicitud, $d['motivo']), 'Solicitud rechazada / cancelada.');
    }

    public function finalizar(Solicitud $solicitud)
    {
        return $this->intentar(fn () => $this->servicio->finalizar($solicitud), 'Servicio marcado como realizado. La urna se descontó de bodega.');
    }

    public function certificado(Solicitud $solicitud)
    {
        abort_unless($solicitud->certificado_path && Storage::disk('local')->exists($solicitud->certificado_path), 404);
        AuditService::registrar('certificado_consultado', 'Consulta del certificado de defunción de '.$solicitud->codigo(), $solicitud);

        return Storage::disk('local')->response($solicitud->certificado_path);
    }

    /** Endpoint usado por el formulario para avisar solapamientos en vivo. */
    public function disponibilidad(Request $request)
    {
        $request->validate(['fecha' => ['required', 'date'], 'hora' => ['required', 'date_format:H:i']]);
        $fecha = Carbon::parse($request->fecha.' '.$request->hora);
        $conflicto = $this->servicio->conflictoHorario($fecha, $request->integer('excepto') ?: null);

        return response()->json([
            'disponible' => $conflicto === null,
            'mensaje' => $conflicto ?? 'Horario disponible.',
            'servicios_del_dia' => $this->servicio->serviciosDelDia($fecha),
        ]);
    }

    private function datosFormulario(): array
    {
        return [
            'sucursales' => Sucursal::activas()->orderBy('id')->get(),
            'urnas' => Urna::orderBy('categoria')->orderBy('codigo')->get(),
            'convenios' => Convenio::vigentes()->orderBy('institucion')->get(),
            'vehiculos' => Vehiculo::count(),
        ];
    }
}
