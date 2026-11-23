<?php

namespace App\Http\Controllers;

use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Rol;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Pantalla "Usuarios y Roles": buscador de familias con ficha completa,
 * últimas familias registradas y usuarios internos con su nivel de acceso.
 */
class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $nombre = $request->query('nombre');
        $rut = $request->query('rut');
        $familias = Cliente::with(['solicitudes' => fn ($s) => $s->latest('fecha_hora'), 'solicitudes.contrato'])
            ->filtrarNombreRut($nombre, $rut)
            ->when($request->query('estado_familia') === 'activas', fn ($q) => $q->whereHas('solicitudes', fn ($s) => $s->activas()))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $idSel = (int) $request->query('familia');
        $seleccionada = $idSel ? Cliente::find($idSel) : $familias->first();

        return view('usuarios.index', [
            'familias' => $familias,
            'totalFamilias' => Cliente::count(),
            'seleccionada' => $seleccionada?->load(['solicitudes' => fn ($s) => $s->latest('fecha_hora'), 'solicitudes.contrato.abonos', 'solicitudes.urna', 'solicitudes.unidad', 'solicitudes.sucursal', 'solicitudes.convenio', 'convenio']),
            'ultimas' => Cliente::latest()->limit(6)->get(),
            'usuarios' => User::with('rol')->whereHas('rol', fn ($r) => $r->where('nombre', '!=', 'solicitante'))->orderBy('rol_id')->orderBy('nombre')->get(),
            'roles' => Rol::withCount('usuarios')->orderByDesc('sistema')->orderBy('nombre')->get(),
            'nombre' => $nombre,
            'rut' => $rut,
            'convenios' => \App\Models\Convenio::vigentes()->orderBy('institucion')->get(),
        ]);
    }

    public function destroy(Request $request, User $usuario)
    {
        if ($usuario->id === $request->user()->id) {
            return back()->with('error', 'No puede eliminar su propia cuenta.');
        }
        if ($usuario->esAdmin() && User::whereHas('rol', fn ($r) => $r->where('nombre', 'administrador'))->count() <= 1) {
            return back()->with('error', 'Debe quedar al menos un administrador.');
        }
        AuditService::registrar('usuario_eliminado', "Eliminación del usuario {$usuario->email}", $usuario);
        $usuario->update(['estado' => 'inactivo']);
        $usuario->delete(); // borrado lógico: el historial y la auditoría se conservan

        return back()->with('ok', 'Usuario eliminado.');
    }

    public function create()
    {
        return view('usuarios.form', ['usuario' => new User(['estado' => 'activo']), 'roles' => Rol::where('estado', 'activo')->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'rol_id' => ['required', Rule::exists('roles', 'id')->where('estado', 'activo')],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'estado' => ['required', Rule::in(['activo', 'inactivo', 'bloqueado'])],
            'crear_chofer' => ['nullable', 'boolean'],
        ]);

        $u = User::create($d);
        if ($request->boolean('crear_chofer') || ($u->puede('itinerarios') && ! $u->puede('flota'))) {
            Chofer::firstOrCreate(['user_id' => $u->id], ['nombre' => $u->nombre, 'telefono' => $u->telefono]);
        }
        AuditService::registrar('usuario_creado', "Creación del usuario {$u->email} ({$u->nombreRol()})", $u);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario creado.');
    }

    public function edit(User $usuario)
    {
        return view('usuarios.form', ['usuario' => $usuario, 'roles' => Rol::where('estado', 'activo')->get()]);
    }

    public function update(Request $request, User $usuario)
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($usuario->id)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'rol_id' => ['required', Rule::exists('roles', 'id')->where('estado', 'activo')],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'estado' => ['required', Rule::in(['activo', 'inactivo', 'bloqueado'])],
        ]);

        if ($usuario->id === $request->user()->id && ($d['estado'] !== 'activo' || (int) $d['rol_id'] !== $usuario->rol_id)) {
            return back()->with('error', 'No puede cambiar su propio rol ni desactivar su propia cuenta.');
        }

        if (empty($d['password'])) {
            unset($d['password']);
        }
        if ($d['estado'] === 'activo') {
            $d['intentos_fallidos'] = 0;
        }

        $usuario->fill($d);
        $cambios = AuditService::cambios($usuario, ['password', 'remember_token']);
        $usuario->save();
        if ($usuario->puede('itinerarios') && ! $usuario->puede('flota')) {
            Chofer::firstOrCreate(['user_id' => $usuario->id], ['nombre' => $usuario->nombre, 'telefono' => $usuario->telefono]);
        }
        AuditService::registrar('usuario_modificado', "Modificación del usuario {$usuario->email}", $usuario, $cambios);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }
}
