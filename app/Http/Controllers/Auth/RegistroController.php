<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Rol;
use App\Models\User;
use App\Rules\RutValido;
use App\Support\Rut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/** Registro de familias para el portal de solicitudes (rol solicitante). */
class RegistroController extends Controller
{
    public function mostrar()
    {
        return view('auth.registro');
    }

    public function registrar(Request $request)
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'apellido_familia' => ['required', 'string', 'max:80'],
            'rut' => ['required', new RutValido],
            'telefono' => ['required', 'regex:/^\+?[0-9 ]{8,15}$/'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'telefono.regex' => 'Ingrese un teléfono válido, por ejemplo +56 9 1234 5678.',
        ]);

        $user = DB::transaction(function () use ($d) {
            $user = User::create([
                'rol_id' => Rol::idDe('solicitante'),
                'nombre' => $d['nombre'],
                'email' => $d['email'],
                'telefono' => $d['telefono'],
                'password' => $d['password'],
                'estado' => 'activo',
            ]);

            $cliente = Cliente::where('rut_hash', Rut::hash($d['rut']))->first();
            if ($cliente && ! $cliente->user_id) {
                $cliente->update(['user_id' => $user->id]);
            } else {
                Cliente::create([
                    'user_id' => $user->id,
                    'nombre_familia' => 'Familia '.$d['apellido_familia'],
                    'nombre_contratante' => $d['nombre'],
                    'rut_contratante' => Rut::formatear($d['rut']),
                    'telefono' => $d['telefono'],
                    'email' => $d['email'],
                    'medio_contacto' => 'telefono',
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.create')->with('ok', 'Cuenta creada. Ya puede ingresar su solicitud.');
    }
}
