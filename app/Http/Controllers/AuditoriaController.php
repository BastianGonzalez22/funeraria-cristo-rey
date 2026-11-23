<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $registros = Auditoria::with('usuario')
            ->when($request->query('evento'), fn ($q, $e) => $q->where('evento', $e))
            ->when($request->query('usuario'), fn ($q, $u) => $q->where('usuario_id', $u))
            ->when($request->query('desde'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('hasta'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('auditoria.index', [
            'registros' => $registros,
            'eventos' => Auditoria::select('evento')->distinct()->orderBy('evento')->pluck('evento'),
            'usuarios' => User::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
