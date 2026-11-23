<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Convenio;
use App\Models\Urna;
use App\Rules\RutValido;
use App\Services\AuditService;
use App\Support\Rut;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    public function create(Request $request)
    {
        return view('clientes.form', [
            'cliente' => new Cliente([
                'convenio_id' => $request->query('convenio'),
                'urna_preferida_id' => $request->query('urna'),
                'medio_contacto' => 'telefono',
                'comuna' => 'Temuco',
            ]),
            'convenios' => Convenio::vigentes()->orderBy('institucion')->get(),
            'urnas' => Urna::orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validar($request);

        if (Cliente::where('rut_hash', Rut::hash($d['rut_contratante']))->exists()) {
            return back()->withInput()->withErrors(['rut_contratante' => 'Ya existe una familia registrada con este RUT.']);
        }

        $cliente = Cliente::create($d);
        AuditService::registrar('cliente_registrado', "Registro de {$cliente->nombre_familia}", $cliente);

        if ($request->input('volver') === 'usuarios') {
            return redirect()->route('usuarios.index', ['familia' => $cliente->id])->with('ok', "Familia {$cliente->nombre_familia} registrada.");
        }
        if ($request->input('accion') === 'solicitud') {
            return redirect()->route('solicitudes.create', ['cliente' => $cliente->id])->with('ok', 'Familia registrada. Complete ahora la solicitud del servicio.');
        }

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Familia registrada correctamente.');
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['convenio', 'urnaPreferida', 'solicitudes.contrato.abonos', 'solicitudes.urna', 'solicitudes.sucursal', 'usuario']);

        return view('clientes.show', [
            'cliente' => $cliente,
            'resumen' => $cliente->resumenPagos(),
        ]);
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.form', [
            'cliente' => $cliente,
            'convenios' => Convenio::vigentes()->orderBy('institucion')->get(),
            'urnas' => Urna::orderBy('codigo')->get(),
        ]);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $d = $this->validar($request);

        $otro = Cliente::where('rut_hash', Rut::hash($d['rut_contratante']))->where('id', '!=', $cliente->id)->exists();
        if ($otro) {
            return back()->withInput()->withErrors(['rut_contratante' => 'Otra familia ya tiene registrado este RUT.']);
        }

        $cliente->fill($d);
        $cambios = AuditService::cambios($cliente, ['rut_contratante', 'rut_hash']);
        $cliente->save();
        AuditService::registrar('cliente_modificado', "Modificación de {$cliente->nombre_familia}", $cliente, $cambios);

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Datos de la familia actualizados.');
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre_familia' => ['required', 'string', 'max:120'],
            'nombre_contratante' => ['required', 'string', 'max:150'],
            'rut_contratante' => ['required', new RutValido],
            'telefono' => ['required', 'regex:/^\+?[0-9 ]{8,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['required', 'string', 'max:200'],
            'comuna' => ['nullable', 'string', 'max:80'],
            'medio_contacto' => ['required', Rule::in(array_keys(config('funeraria.medios_contacto')))],
            'convenio_id' => ['nullable', 'exists:convenios,id'],
            'urna_preferida_id' => ['nullable', 'exists:urnas,id'],
            'documentos' => ['nullable', 'array'],
            'documentos.*' => [Rule::in(array_keys(config('funeraria.documentos')))],
            'notas' => ['nullable', 'string', 'max:1000'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
        ], [
            'telefono.regex' => 'Ingrese un teléfono válido, por ejemplo +56 9 1234 5678.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        $d['rut_contratante'] = Rut::formatear($d['rut_contratante']);
        $d['documentos'] = array_fill_keys($d['documentos'] ?? [], true);
        $d['estado'] = $d['estado'] ?? 'activo';

        return $d;
    }
}
