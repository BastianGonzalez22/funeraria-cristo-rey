<?php

namespace App\Http\Controllers;

use App\Models\AusenciaChofer;
use App\Models\Chofer;
use App\Models\Solicitud;
use App\Models\Vehiculo;
use App\Services\AuditService;
use App\Services\FlotaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FlotaController extends Controller
{
    public function __construct(private FlotaService $flota)
    {
    }

    public function index(Request $request)
    {
        // La fecha elegida se mantiene (y se destaca); la grilla siempre muestra su semana de lunes a domingo
        $elegido = Carbon::parse($request->query('semana', now()->toDateString()))->startOfDay();
        $lunes = $elegido->copy()->startOfWeek(Carbon::MONDAY);
        $dias = collect(range(0, 6))->map(fn ($i) => $lunes->copy()->addDays($i));

        $pendientes = Solicitud::with(['cliente', 'asignacion'])
            ->where('estado', 'aprobada')
            ->where('fecha_hora', '>=', now()->subDay())
            ->orderBy('fecha_hora')
            ->get();

        return view('flota.index', [
            'lunes' => $lunes,
            'elegido' => $elegido,
            'dias' => $dias,
            'vehiculos' => Vehiculo::orderByRaw("CASE tipo WHEN 'carroza' THEN 0 WHEN 'instalacion' THEN 1 ELSE 2 END")->orderBy('nombre')->get(),
            'choferes' => Chofer::with('ausencias')->orderBy('nombre')->get(),
            'matriz' => $this->flota->semana($lunes),
            'ausencias' => AusenciaChofer::with('chofer')->where('estado', '!=', 'finalizada')->orderBy('desde')->get(),
            'pendientes' => $pendientes,
            // Si no se eligió un servicio, se propone el próximo aprobado que aún no tiene vehículo
            'seleccionada' => $request->query('solicitud')
                ? Solicitud::with('asignacion')->find($request->query('solicitud'))
                : $pendientes->first(fn ($p) => ! $p->asignacion),
        ]);
    }

    public function editar(Solicitud $solicitud)
    {
        return redirect(route('flota.index', ['solicitud' => $solicitud->id, 'semana' => $solicitud->fecha_hora->toDateString()]).'#asignar');
    }

    public function asignar(Request $request)
    {
        $d = $request->validate([
            'solicitud_id' => ['required', 'exists:solicitudes,id'],
            'vehiculo_id' => ['required', 'exists:vehiculos,id'],
            'chofer_id' => ['required', 'exists:choferes,id'],
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after:inicio'],
            'origen' => ['nullable', 'string', 'max:200'],
            'destino' => ['nullable', 'string', 'max:200'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'hoja_ruta' => ['nullable', 'array', 'max:10'],
            'hoja_ruta.*.hora' => ['nullable', 'date_format:H:i'],
            'hoja_ruta.*.lugar' => ['nullable', 'string', 'max:120'],
            'hoja_ruta.*.direccion' => ['nullable', 'string', 'max:200'],
        ], ['fin.after' => 'La hora de término debe ser posterior al inicio.']);

        $solicitud = Solicitud::findOrFail($d['solicitud_id']);

        return $this->intentar(
            fn () => $this->flota->asignar($solicitud, $d),
            'Vehículo y chofer asignados. Hoja de ruta guardada.',
            route('flota.index', ['semana' => Carbon::parse($d['inicio'])->toDateString()])
        );
    }

    public function registrarAusencia(Request $request)
    {
        $d = $request->validate([
            'chofer_id' => ['required', 'exists:choferes,id'],
            'motivo' => ['required', Rule::in(array_keys(AusenciaChofer::MOTIVOS))],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'detalle' => ['nullable', 'string', 'max:200'],
        ]);

        $a = AusenciaChofer::create($d);
        $conflictos = $a->chofer->asignaciones()->activas()
            ->whereDate('inicio', '<=', $a->hasta)->whereDate('fin', '>=', $a->desde)->with('solicitud.cliente')->get();
        AuditService::registrar('ausencia_registrada', "Ausencia de {$a->chofer->nombre}: {$a->motivo} {$a->desde->format('d/m')}–{$a->hasta->format('d/m')}", $a->chofer);

        $msg = 'Ausencia registrada. El chofer queda no disponible en esas fechas.';
        if ($conflictos->isNotEmpty()) {
            return back()->with('ok', $msg)->with('error', 'Atención: el chofer tenía servicios asignados en esas fechas ('.$conflictos->map(fn ($c) => $c->solicitud->cliente->nombre_familia)->implode(', ').'). Reasígnelos.');
        }

        return back()->with('ok', $msg);
    }

    public function eliminarAusencia(AusenciaChofer $ausencia)
    {
        AuditService::registrar('ausencia_eliminada', "Eliminación de ausencia de {$ausencia->chofer->nombre}", $ausencia->chofer);
        $ausencia->delete();

        return back()->with('ok', 'Ausencia eliminada.');
    }
}
