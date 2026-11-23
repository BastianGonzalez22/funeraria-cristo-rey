<?php

namespace App\Http\Controllers;

use App\Models\Chofer;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Registro de personal (choferes) y vehículos. */
class PersonalController extends Controller
{
    public function index()
    {
        return view('personal.index', [
            'choferes' => Chofer::with(['usuario', 'ausencias'])->withCount(['asignaciones' => fn ($q) => $q->where('inicio', '>=', now()->startOfMonth())])->orderBy('nombre')->get(),
            'vehiculos' => Vehiculo::withCount(['asignaciones' => fn ($q) => $q->where('inicio', '>=', now()->startOfMonth())])->orderBy('tipo')->get(),
            'usuariosChofer' => User::whereHas('rol', fn ($r) => $r->where('nombre', 'chofer'))->doesntHave('chofer')->get(),
        ]);
    }

    public function guardarChofer(Request $request)
    {
        $c = Chofer::create($this->validarChofer($request));
        AuditService::registrar('chofer_creado', "Alta del chofer {$c->nombre}", $c);

        return back()->with('ok', 'Chofer registrado.');
    }

    public function actualizarChofer(Request $request, Chofer $chofer)
    {
        $chofer->update($this->validarChofer($request, $chofer));

        return back()->with('ok', 'Chofer actualizado.');
    }

    public function guardarVehiculo(Request $request)
    {
        $v = Vehiculo::create($this->validarVehiculo($request));
        AuditService::registrar('vehiculo_creado', "Alta del vehículo {$v->nombre}", $v);

        return back()->with('ok', 'Vehículo registrado.');
    }

    public function actualizarVehiculo(Request $request, Vehiculo $vehiculo)
    {
        $vehiculo->update($this->validarVehiculo($request, $vehiculo));

        return back()->with('ok', 'Vehículo actualizado.');
    }

    private function validarChofer(Request $request, ?Chofer $c = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'licencia' => ['nullable', 'string', 'max:20'],
            'jornada' => ['required', Rule::in(['completa', 'part_time', 'externo'])],
            'estado' => ['required', Rule::in(['disponible', 'en_servicio', 'no_disponible'])],
            'user_id' => ['nullable', 'exists:usuarios,id', Rule::unique('choferes', 'user_id')->ignore($c?->id)],
        ]);
    }

    private function validarVehiculo(Request $request, ?Vehiculo $v = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'patente' => ['required', 'string', 'max:10', Rule::unique('vehiculos', 'patente')->ignore($v?->id)],
            'tipo' => ['required', Rule::in(array_keys(Vehiculo::TIPOS))],
            'observacion' => ['nullable', 'string', 'max:200'],
            'estado' => ['required', Rule::in(['disponible', 'en_servicio', 'en_mantencion'])],
        ]);
    }
}
