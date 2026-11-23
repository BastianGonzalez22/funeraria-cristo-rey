<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    private const MAX_INTENTOS = 5;

    public function mostrar()
    {
        return view('auth.login');
    }

    public function ingresar(Request $request)
    {
        $cred = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('rol')->where('email', $cred['email'])->first();

        if ($user && $user->estado === 'bloqueado') {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'La cuenta está bloqueada por intentos fallidos. El administrador debe desbloquearla.']);
        }
        if ($user && $user->estado === 'inactivo') {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'La cuenta está inactiva.']);
        }

        if (! Auth::attempt($cred, $request->boolean('recordar'))) {
            if ($user) {
                $user->intentos_fallidos = $user->intentos_fallidos + 1;
                if ($user->intentos_fallidos >= self::MAX_INTENTOS) {
                    $user->estado = 'bloqueado';
                    AuditService::registrar('usuario_bloqueado', "Bloqueo de {$user->email} por intentos fallidos", $user);
                }
                $user->save();
            }

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Correo o contraseña incorrectos.']);
        }

        $request->session()->regenerate();
        $u = $request->user();
        $u->forceFill(['intentos_fallidos' => 0, 'ultimo_acceso_at' => now()])->save();
        AuditService::registrar('inicio_sesion', "Inicio de sesión de {$u->email}", $u);

        return redirect()->intended(route('home'));
    }

    public function salir(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
