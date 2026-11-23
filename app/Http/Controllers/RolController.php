<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** El administrador crea, edita y elimina roles con sus permisos por módulo. */
class RolController extends Controller
{
    public function index()
    {
        return view('roles.index', ['roles' => Rol::withCount('usuarios')->orderByDesc('sistema')->orderBy('nombre')->get()]);
    }

    public function create()
    {
        return view('roles.form', ['rol' => new Rol(['permisos' => []])]);
    }

    public function store(Request $request)
    {
        $rol = Rol::create($this->validar($request));
        AuditService::registrar('rol_creado', "Creación del rol {$rol->nombre}", $rol, ['permisos' => $rol->permisos]);

        return redirect()->route('roles.index')->with('ok', "Rol «{$rol->nombre}» creado.");
    }

    public function edit(Rol $rol)
    {
        return view('roles.form', ['rol' => $rol]);
    }

    public function update(Request $request, Rol $rol)
    {
        $d = $this->validar($request, $rol);
        if ($rol->nombre === 'administrador') {
            $d['nombre'] = 'administrador'; // siempre tiene acceso total
            $d['estado'] = 'activo';
        }
        if ($rol->id === $request->user()->rol_id && ! in_array('roles', $d['permisos'], true) && $rol->nombre !== 'administrador') {
            return back()->withInput()->with('error', 'No puede quitarse a sí mismo el permiso de administrar usuarios y roles.');
        }
        $antes = $rol->permisos;
        $rol->update($d);
        AuditService::registrar('rol_modificado', "Modificación del rol {$rol->nombre}", $rol, ['antes' => $antes, 'despues' => $rol->permisos]);

        return redirect()->route('roles.index')->with('ok', "Rol «{$rol->nombre}» actualizado.");
    }

    public function destroy(Rol $rol)
    {
        if ($rol->esProtegido()) {
            return back()->with('error', "El rol «{$rol->nombre}» es necesario para el sistema y no se puede eliminar.");
        }
        if ($rol->usuarios()->withTrashed()->exists()) {
            return back()->with('error', "No se puede eliminar «{$rol->nombre}»: tiene usuarios asignados. Cámbielos de rol primero.");
        }
        AuditService::registrar('rol_eliminado', "Eliminación del rol {$rol->nombre}", $rol, ['permisos' => $rol->permisos]);
        $rol->delete();

        return back()->with('ok', 'Rol eliminado.');
    }

    private function validar(Request $request, ?Rol $rol = null): array
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:40', 'regex:/^[a-záéíóúñ0-9 _-]+$/iu', Rule::unique('roles', 'nombre')->ignore($rol?->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => [Rule::in(array_keys(config('funeraria.modulos')))],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ], ['nombre.regex' => 'El nombre solo puede tener letras, números, espacios y guiones.']);
        $d['nombre'] = mb_strtolower(trim($d['nombre']));
        $d['permisos'] = array_values($d['permisos'] ?? []);

        return $d;
    }
}
