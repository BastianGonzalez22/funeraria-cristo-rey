<?php

namespace Database\Seeders;

use App\Models\AusenciaChofer;
use App\Models\Chofer;
use App\Models\Cliente;
use App\Models\Condolencia;
use App\Models\Contrato;
use App\Models\Convenio;
use App\Models\Gasto;
use App\Models\MensajeContacto;
use App\Models\Opinion;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\Sucursal;
use App\Models\Urna;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\FlotaService;
use App\Services\PagoService;
use App\Services\SolicitudService;
use App\Support\Rut;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Datos de demostración para recorrer todas las pantallas del prototipo.
 * Usa los mismos servicios del sistema, por lo que stock, saldos y
 * auditoría quedan consistentes.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Solicitud::exists()) {
            return; // ya hay datos
        }

        $admin = User::where('email', 'admin@cristorey.cl')->first();
        Auth::setUser($admin); // para que la auditoría registre al usuario

        $svc = app(SolicitudService::class);
        $pagos = app(PagoService::class);
        $flota = app(FlotaService::class);
        $principal = Sucursal::first();
        $urna = fn ($c) => Urna::where('codigo', $c)->value('id');
        $convenio = fn ($n) => Convenio::where('institucion', 'like', "%{$n}%")->value('id');
        $hoy = now()->startOfDay();

        // ------- Historial de 11 meses (indicador de servicios mensuales) -------
        $historicos = $this->clientesGenericos(12);
        for ($m = 11; $m >= 1; $m--) {
            $mes = now()->startOfMonth()->subMonthsNoOverflow($m);
            $n = rand(24, 36) + ($m === 4 ? 20 : 0); // un mes con peak (~60)
            for ($i = 0; $i < $n; $i++) {
                $f = $mes->copy()->addDays(rand(0, $mes->daysInMonth - 1))->setTime(rand(9, 17), [0, 30][rand(0, 1)]);
                Solicitud::create([
                    'cliente_id' => $historicos->random()->id, 'urna_id' => Urna::inRandomOrder()->value('id'),
                    'sucursal_id' => $principal->id, 'usuario_solicitante_id' => $admin->id,
                    'tipo_servicio' => collect(['velatorio_cortejo', 'velatorio_cortejo', 'velatorio_cortejo', 'cortejo', 'cremacion', 'velatorio'])->random(),
                    'fecha_hora' => $f, 'destino_final' => rand(0, 5) ? 'sepultura' : 'cremacion',
                    'nombre_fallecido' => $this->nombre(), 'cementerio_crematorio' => 'Cementerio General de Temuco',
                    'token_publico' => Str::random(32), 'estado' => 'finalizada', 'permiso_sanitario' => 'aprobado',
                    'created_at' => $f->copy()->subDays(2), 'updated_at' => $f,
                ]);
            }
        }

        // ------- Familias del prototipo -------
        $fam = fn ($apellido, $contratante, $tel, $medio = 'telefono') => Cliente::create([
            'nombre_familia' => 'Familia '.$apellido, 'nombre_contratante' => $contratante, 'rut_contratante' => $this->rut(),
            'telefono' => $tel, 'email' => Str::slug($contratante, '.').'@correo.cl', 'direccion' => 'Av. Alemania '.rand(100, 2000).', Temuco',
            'comuna' => 'Temuco', 'medio_contacto' => $medio,
            'documentos' => ['carnet_contratante' => true, 'carnet_fallecido' => true, 'certificado_defuncion' => true],
        ]);

        $base = fn (Cliente $c, string $fallecido, Carbon $f, string $u, array $extra = []) => array_merge([
            'cliente_id' => $c->id, 'urna_id' => $urna($u), 'sucursal_id' => $principal->id, 'tipo_servicio' => 'velatorio_cortejo',
            'fecha_hora' => $f, 'destino_final' => 'sepultura', 'nombre_fallecido' => $fallecido, 'rut_fallecido' => $this->rut(),
            'fecha_defuncion' => $f->copy()->subDays(2)->toDateString(), 'lugar_retiro' => 'Hospital Regional de Temuco',
            'lugar_velatorio' => 'Domicilio familiar, '.$c->direccion, 'misa_lugar' => 'Parroquia San Francisco, Temuco',
            'misa_hora' => $f->copy()->subHours(2), 'cementerio_crematorio' => 'Cementerio General de Temuco',
            'sepultura_hora' => $f->copy()->addHours(2), 'medio_contacto' => $c->medio_contacto,
            'documentos' => ['carnet_contratante' => true, 'carnet_fallecido' => true, 'certificado_defuncion' => true],
        ], $extra);

        // Contreras: aprobada, cuota IPS en trámite, flota asignada
        $contreras = $fam('Contreras', 'María Contreras', '+56 9 8123 4455', 'whatsapp');
        $s1 = $svc->crear($base($contreras, 'José Contreras Ruiz', $hoy->copy()->addDay()->setTime(10, 0), 'U-102', ['convenio_id' => $convenio('IPS')]), $admin, null, true);
        $c1 = $svc->aprobar($s1, ['abono_inicial' => 250000, 'medio_pago' => 'transferencia', 'cuota_mortuoria_en_tramite' => true, 'forzar' => true], $admin);
        $s1->update(['publicar_servicio' => true, 'elementos' => ['cruz' => true, 'tarjetas' => true, 'foto' => true], 'personal' => [['tarea' => 'Retiro y preparación', 'persona' => 'J. Pérez y P. Lillo'], ['tarea' => 'Cortejo', 'persona' => 'P. Lillo']]]);
        $flota->asignar($s1->fresh(), [
            'vehiculo_id' => Vehiculo::where('tipo', 'carroza')->value('id'), 'chofer_id' => Chofer::where('nombre', 'Pedro Lillo')->value('id'),
            'inicio' => $s1->fecha_hora->copy()->subHour(), 'fin' => $s1->fecha_hora->copy()->addHours(3),
            'origen' => $s1->lugar_velatorio, 'destino' => 'Cementerio General de Temuco',
            'hoja_ruta' => [['hora' => '10:00', 'lugar' => 'Recorrido por lugar de trabajo', 'direccion' => 'Av. Caupolicán 500, Temuco'], ['hora' => '11:00', 'lugar' => 'Misa', 'direccion' => 'Parroquia San Francisco, Temuco']],
        ]);
        Condolencia::create(['solicitud_id' => $s1->id, 'nombre' => 'Familia Riquelme', 'mensaje' => 'Nuestro cariño y oraciones para toda la familia. José siempre será recordado.']);

        // Muñoz: en revisión
        $munoz = $fam('Muñoz', 'Luis Muñoz', '+56 9 7712 3344');
        $svc->crear($base($munoz, 'Rosa Muñoz Sepúlveda', $hoy->copy()->addDay()->setTime(15, 30), 'U-101', ['mensaje' => 'Velatorio en casa, por mayor capacidad de asistentes.']), $admin, null, true);

        // Herrera: aprobada, convenio DIPRECA, abono 50%
        $herrera = $fam('Herrera', 'Ana Herrera', '+56 9 6655 1122');
        $s3 = $svc->crear($base($herrera, 'Carlos Herrera Vidal', $hoy->copy()->addDays(2)->setTime(9, 0), 'U-106', ['convenio_id' => $convenio('DIPRECA'), 'tipo_servicio' => 'cortejo']), $admin, null, true);
        $c3 = $svc->aprobar($s3, ['abono_inicial' => 450000, 'medio_pago' => 'efectivo', 'forzar' => true], $admin);
        // La familia Herrera ya confirmó la compra de su urna personalizada
        if ($s3->fresh()->unidad) {
            app(\App\Services\StockService::class)->venderUnidad($s3->fresh()->unidad);
        }

        // Soto: con observaciones (traslado fuera de comuna)
        $soto = $fam('Soto', 'Pedro Soto', '+56 9 5544 8899', 'email');
        $s4 = $svc->crear($base($soto, 'Elena Soto Mardones', $hoy->copy()->addDays(3)->setTime(11, 0), 'U-104', ['fuera_comuna' => true, 'comuna_destino' => 'Cunco', 'cementerio_crematorio' => 'Cementerio de Cunco']), $admin, null, true);
        $svc->observar($s4, 'Falta adjuntar el certificado de defunción y confirmar la comuna de sepultura (requiere resolución de traslado).');

        // Pino: aprobada, con cuenta en el portal
        $pinoUser = User::firstOrCreate(['email' => 'familia@correo.cl'], ['rol_id' => Rol::idDe('solicitante'), 'nombre' => 'Camila Pino', 'telefono' => '+56 9 3311 2233', 'password' => DatabaseSeeder::CLAVE]);
        $pino = $fam('Pino', 'Camila Pino', '+56 9 3311 2233');
        $pino->update(['user_id' => $pinoUser->id, 'email' => 'familia@correo.cl']);
        $s5 = $svc->crear($base($pino, 'Hernán Pino Lagos', $hoy->copy()->addDays(5)->setTime(12, 0), 'U-110', ['tipo_servicio' => 'cremacion', 'destino_final' => 'cremacion', 'cementerio_crematorio' => 'Crematorio Parque del Recuerdo Temuco']), $pinoUser, null, true);
        $c5 = $svc->aprobar($s5, ['abono_inicial' => 440000, 'medio_pago' => 'tarjeta', 'cuotas' => 3, 'forzar' => true], $admin);
        // Abono informado por la familia desde el portal (pendiente de confirmación)
        \App\Models\Abono::create([
            'contrato_id' => $c5->id, 'usuario_id' => $pinoUser->id, 'monto' => 150000, 'medio_pago' => 'transferencia',
            'fecha' => now()->toDateString(), 'referencia' => 'Transferencia cuota 1/3', 'estado' => 'pendiente',
        ]);

        // Fuentes: realizada y pagada (liquidada)
        $fuentes = $fam('Fuentes', 'Rosa Fuentes', '+56 9 2211 9988');
        $s6 = $svc->crear($base($fuentes, 'Óscar Fuentes Mella', $hoy->copy()->subDays(9)->setTime(11, 0), 'U-101'), $admin, null, true);
        $c6 = $svc->aprobar($s6, ['abono_inicial' => 485000, 'medio_pago' => 'efectivo', 'forzar' => true], $admin);
        $pagos->registrar($c6, ['monto' => $c6->fresh()->saldo_pendiente, 'medio_pago' => 'cuota_afp', 'fecha' => $hoy->copy()->subDays(2)->toDateString(), 'referencia' => 'Cuota mortuoria AFP Provida'], $admin);
        $svc->finalizar($s6);
        $pagos->liquidar($c6->fresh());

        // Arancibia y Vera: realizadas con pago atrasado
        foreach ([['Arancibia', 'Rosa Arancibia', 'Juan Arancibia Toro', 18, 'U-108'], ['Vera', 'Pedro Vera', 'Marta Vera Leal', 40, 'U-101']] as [$ap, $con, $fall, $dias, $u]) {
            $c = $fam($ap, $con, '+56 9 '.rand(1000, 9999).' '.rand(1000, 9999));
            $s = $svc->crear($base($c, $fall, $hoy->copy()->subDays($dias)->setTime(10, 30), $u), $admin, null, true);
            $ct = $svc->aprobar($s, ['abono_inicial' => 0, 'cuota_mortuoria_en_tramite' => true, 'forzar' => true, 'fecha_vencimiento' => $hoy->copy()->subDays(3)->toDateString()], $admin);
            $pagos->registrar($ct, ['monto' => (int) round($ct->monto_total * 0.3), 'medio_pago' => 'efectivo', 'fecha' => $hoy->copy()->subDays($dias)->toDateString(), 'referencia' => 'Abono parcial'], $admin);
            $svc->finalizar($s);
        }

        // Más servicios del mes en curso (para el indicador)
        for ($i = 0; $i < 14; $i++) {
            $f = now()->startOfMonth()->addDays(rand(0, max(0, now()->day - 2)))->setTime(rand(9, 17), 0);
            Solicitud::create([
                'cliente_id' => $historicos->random()->id, 'urna_id' => $urna('U-101'), 'sucursal_id' => $principal->id,
                'usuario_solicitante_id' => $admin->id, 'tipo_servicio' => 'velatorio_cortejo', 'fecha_hora' => $f,
                'destino_final' => 'sepultura', 'nombre_fallecido' => $this->nombre(), 'token_publico' => Str::random(32),
                'estado' => 'finalizada', 'permiso_sanitario' => 'aprobado',
            ]);
        }

        // Ausencia de chofer (vacaciones)
        AusenciaChofer::create(['chofer_id' => Chofer::where('nombre', 'Manuel Vega')->value('id'), 'motivo' => 'vacaciones', 'desde' => $hoy->copy()->subDays(2), 'hasta' => $hoy->copy()->addDays(5), 'detalle' => 'Vacaciones de verano']);
        AusenciaChofer::create(['chofer_id' => Chofer::where('nombre', 'Carlos Rojas')->value('id'), 'motivo' => 'permiso', 'desde' => $hoy->copy()->addDays(4), 'hasta' => $hoy->copy()->addDays(4), 'detalle' => 'Trámite personal']);

        // Gastos del mes
        foreach ([
            ['Compra camión de urnas (Santiago)', 'urnas', 4800000], ['Combustible y mantención de carroza', 'combustible', 320000],
            ['Flores, cruces y tarjetas', 'flores', 285000], ['Arriendo de carroza (apoyo Perquenco)', 'arriendo', 120000],
        ] as [$con, $cat, $m]) {
            Gasto::create(['usuario_id' => $admin->id, 'concepto' => $con, 'categoria' => $cat, 'monto' => $m, 'fecha' => now()->startOfMonth()->addDays(rand(0, max(0, now()->day - 1)))]);
        }

        // Sitio web
        Opinion::create(['nombre' => 'Familia Contreras', 'calificacion' => 5, 'comentario' => 'Nos acompañaron en todo momento y se encargaron de los trámites con la AFP. Muy agradecidos.', 'publicada' => true]);
        Opinion::create(['nombre' => 'Rosa Fuentes', 'calificacion' => 5, 'comentario' => 'Atención respetuosa y puntual. El recorrido por el trabajo de mi padre fue muy emotivo.', 'publicada' => true]);
        Opinion::create(['nombre' => 'Jorge M.', 'calificacion' => 4, 'comentario' => 'Buen servicio, nos explicaron claramente los pagos y las cuotas.', 'publicada' => false]);
        MensajeContacto::create(['nombre' => 'Patricia Lagos', 'telefono' => '+56 9 9988 7766', 'medio_contacto' => 'whatsapp', 'urna_id' => $urna('U-102'), 'mensaje' => '¿Tienen disponible la urna tallada roble para esta semana? ¿Cuánto cubre la AFP?']);


    }

    private function clientesGenericos(int $n)
    {
        $apellidos = ['González', 'Rodríguez', 'Pérez', 'Sepúlveda', 'Morales', 'Rojas', 'Jara', 'Sandoval', 'Huenchullán', 'Painemal', 'Cifuentes', 'Navarrete', 'Toledo', 'Saavedra'];

        return collect(range(1, $n))->map(fn ($i) => Cliente::create([
            'nombre_familia' => 'Familia '.$apellidos[$i % count($apellidos)],
            'nombre_contratante' => $this->nombre(),
            'rut_contratante' => $this->rut(),
            'telefono' => '+56 9 '.rand(1000, 9999).' '.rand(1000, 9999),
            'direccion' => 'Temuco', 'comuna' => 'Temuco', 'medio_contacto' => 'telefono', 'estado' => 'inactivo',
        ]));
    }

    private function rut(): string
    {
        $cuerpo = (string) rand(5000000, 24000000);

        return Rut::formatear($cuerpo.Rut::dv($cuerpo));
    }

    private function nombre(): string
    {
        $n = ['José', 'María', 'Juan', 'Rosa', 'Luis', 'Ana', 'Pedro', 'Elena', 'Carlos', 'Marta', 'Jorge', 'Teresa', 'Manuel', 'Gloria'];
        $a = ['González', 'Muñoz', 'Rojas', 'Díaz', 'Pérez', 'Soto', 'Contreras', 'Silva', 'Martínez', 'Sepúlveda', 'Morales', 'Rodríguez', 'Huenchullán', 'Catrileo'];

        return $n[array_rand($n)].' '.$a[array_rand($a)].' '.$a[array_rand($a)];
    }
}
