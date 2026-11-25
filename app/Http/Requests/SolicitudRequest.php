<?php

namespace App\Http\Requests;

use App\Rules\RutValido;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación común de solicitudes (portal de familias y panel interno).
 */
class SolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $portal = $this->routeIs('portal.*');
        $creando = $this->isMethod('post');

        $reglas = [
            'tipo_servicio' => ['required', Rule::in(array_keys(config('funeraria.tipos_servicio')))],
            'fecha' => array_filter(['required', 'date', $creando ? 'after_or_equal:today' : null]),
            'hora' => ['required', 'date_format:H:i'],
            'sucursal_id' => ['required', Rule::exists('sucursales', 'id')->where('estado', 'activa')],
            'destino_final' => ['required', Rule::in(array_keys(config('funeraria.destinos')))],
            'urna_id' => ['nullable', Rule::exists('urnas', 'id')->whereNull('deleted_at')],
            'convenio_id' => ['nullable', Rule::exists('convenios', 'id')->where('estado', 'vigente')],
            'nombre_fallecido' => ['required', 'string', 'max:150'],
            'rut_fallecido' => ['nullable', new RutValido],
            'fecha_defuncion' => ['nullable', 'date', 'before_or_equal:today'],
            'lugar_retiro' => ['nullable', 'string', 'max:200'],
            'lugar_velatorio' => ['nullable', 'string', 'max:200'],
            'misa_lugar' => ['nullable', 'string', 'max:200'],
            'misa_hora' => ['nullable', 'date'],
            'cementerio_crematorio' => ['nullable', 'string', 'max:150'],
            'sepultura_hora' => ['nullable', 'date'],
            'comuna_destino' => ['nullable', 'string', 'max:80'],
            'fuera_comuna' => ['nullable', 'boolean'],
            'medio_contacto' => ['required', Rule::in(array_keys(config('funeraria.medios_contacto')))],
            'mensaje' => ['nullable', 'string', 'max:1000'],
            'certificado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'documentos' => ['nullable', 'array'],
            'documentos.*' => [Rule::in(array_keys(config('funeraria.documentos')))],
            'forzar' => ['nullable', 'boolean'],
        ];

        if ($portal) {
            $reglas['rut_contratante'] = ['required', new RutValido];
            $reglas['telefono'] = ['required', 'regex:/^\+?[0-9 ]{8,15}$/'];
        } elseif ($creando) {
            $reglas += [
                'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
                'nombre_familia' => ['required_without:cliente_id', 'nullable', 'string', 'max:120'],
                'nombre_contratante' => ['required_without:cliente_id', 'nullable', 'string', 'max:150'],
                'rut_contratante' => ['required_without:cliente_id', 'nullable', new RutValido],
                'telefono' => ['required_without:cliente_id', 'nullable', 'regex:/^\+?[0-9 ]{8,15}$/'],
                'email' => ['nullable', 'email', 'max:150'],
                'direccion' => ['nullable', 'string', 'max:200'],
                'caso_especial' => ['nullable', 'boolean'],
            ];
        } else {
            $reglas['caso_especial'] = ['nullable', 'boolean'];
        }

        return $reglas;
    }

    /**
     * La misa y la sepultura deben quedar dentro del servicio: no antes de su inicio
     * ni más de 3 días después (el servicio dura 48 h, con posibilidad de extensión).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->hasAny(['fecha', 'hora', 'misa_hora', 'sepultura_hora'])) {
                return;
            }
            try {
                $inicio = Carbon::parse($this->input('fecha').' '.$this->input('hora'));
            } catch (\Throwable) {
                return;
            }
            $limite = $inicio->copy()->addHours((int) config('funeraria.duracion_servicio_horas', 48) + 24);
            $misa = $this->filled('misa_hora') ? Carbon::parse($this->input('misa_hora')) : null;
            $sepultura = $this->filled('sepultura_hora') ? Carbon::parse($this->input('sepultura_hora')) : null;

            foreach (['misa_hora' => [$misa, 'La misa'], 'sepultura_hora' => [$sepultura, 'La sepultura o cremación']] as $campo => [$f, $nombre]) {
                if (! $f) {
                    continue;
                }
                if ($f->lt($inicio)) {
                    $v->errors()->add($campo, "{$nombre} no puede ser antes del inicio del servicio ({$inicio->format('d/m/Y H:i')}).");
                } elseif ($f->gt($limite)) {
                    $v->errors()->add($campo, "{$nombre} debe ser dentro de los 3 días del servicio (a más tardar el {$limite->format('d/m/Y H:i')}). Revise la fecha.");
                }
            }
            if ($misa && $sepultura && $sepultura->lt($misa)) {
                $v->errors()->add('sepultura_hora', 'La sepultura o cremación no puede ser antes de la misa.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'fecha.after_or_equal' => 'La fecha del servicio no puede ser anterior a hoy.',
            'telefono.regex' => 'Ingrese un teléfono válido, por ejemplo +56 9 1234 5678.',
            'certificado.mimes' => 'El certificado debe ser PDF o imagen (JPG/PNG).',
            'certificado.max' => 'El certificado no puede superar 5 MB.',
            '*.required_without' => 'Este campo es obligatorio si no selecciona una familia registrada.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_servicio' => 'tipo de servicio',
            'sucursal_id' => 'sucursal',
            'destino_final' => 'destino final',
            'urna_id' => 'urna',
            'nombre_fallecido' => 'nombre del fallecido',
            'rut_fallecido' => 'RUT del fallecido',
            'rut_contratante' => 'RUT del contratante',
            'medio_contacto' => 'medio de contacto',
        ];
    }

    /** Campos listos para el modelo Solicitud. */
    public function datosSolicitud(): array
    {
        $campos = [
            'tipo_servicio', 'sucursal_id', 'destino_final', 'urna_id', 'convenio_id',
            'nombre_fallecido', 'rut_fallecido', 'fecha_defuncion', 'lugar_retiro', 'lugar_velatorio',
            'misa_lugar', 'misa_hora', 'cementerio_crematorio', 'sepultura_hora', 'comuna_destino',
            'medio_contacto', 'mensaje',
        ];
        $d = collect($this->validated())->only($campos)->all();
        foreach (['urna_id', 'convenio_id', 'rut_fallecido', 'fecha_defuncion', 'misa_hora', 'sepultura_hora'] as $nullable) {
            if (array_key_exists($nullable, $d) && $d[$nullable] === '') {
                $d[$nullable] = null;
            }
        }
        $d['fecha_hora'] = Carbon::parse($this->input('fecha').' '.$this->input('hora'));
        $d['fuera_comuna'] = $this->boolean('fuera_comuna');
        if ($this->has('caso_especial') || ! $this->routeIs('portal.*')) {
            $d['caso_especial'] = $this->boolean('caso_especial');
        }
        if ($this->has('documentos')) {
            $d['documentos'] = array_fill_keys($this->input('documentos', []), true);
        }

        return $d;
    }
}
