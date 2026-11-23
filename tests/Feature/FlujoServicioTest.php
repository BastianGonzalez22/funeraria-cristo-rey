<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\Sucursal;
use App\Models\Urna;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\PagoService;
use App\Services\SolicitudService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de integración de las Historias 1 a 4 y del control de acceso.
 */
class FlujoServicioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Urna $urna;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (config('funeraria.permisos_por_rol') as $r => $permisos) {
            Rol::create(['nombre' => $r, 'permisos' => $permisos, 'sistema' => true]);
        }
        $this->admin = User::create(['rol_id' => Rol::idDe('administrador'), 'nombre' => 'Admin', 'email' => 'a@a.cl', 'password' => 'Clave1234']);
        Sucursal::create(['nombre' => 'Principal', 'direccion' => 'Temuco']);
        Vehiculo::create(['nombre' => 'Carroza', 'patente' => 'AA-01', 'tipo' => 'carroza']);
        $this->urna = Urna::create(['codigo' => 'U-1', 'modelo' => 'Lisa', 'categoria' => 'estandar', 'precio' => 300000, 'stock_minimo' => 1]);
        $this->actingAs($this->admin);
        app(\App\Services\StockService::class)->registrarCompra($this->urna->id, null, 1, 0, $this->admin); // una unidad: U-1-0001
        $this->cliente = Cliente::create(['nombre_familia' => 'Familia Test', 'nombre_contratante' => 'Test', 'rut_contratante' => '12.345.678-5', 'telefono' => '+56911112222']);
        $this->actingAs($this->admin);
    }

    private function datos(string $fecha): array
    {
        return [
            'cliente_id' => $this->cliente->id, 'urna_id' => $this->urna->id, 'sucursal_id' => Sucursal::first()->id,
            'tipo_servicio' => 'velatorio_cortejo', 'fecha_hora' => $fecha, 'destino_final' => 'sepultura',
            'nombre_fallecido' => 'Persona Test', 'medio_contacto' => 'telefono',
        ];
    }

    public function test_rechaza_solicitudes_solapadas(): void
    {
        $svc = app(SolicitudService::class);
        $svc->crear($this->datos(now()->addDay()->setTime(10, 0)), $this->admin);

        $this->expectException(DomainException::class);
        $svc->crear($this->datos(now()->addDay()->setTime(11, 0)), $this->admin);
    }

    public function test_aprobar_reserva_stock_y_exige_abono_minimo(): void
    {
        $svc = app(SolicitudService::class);
        $s = $svc->crear($this->datos(now()->addDay()->setTime(10, 0)), $this->admin);

        try {
            $svc->aprobar($s, ['abono_inicial' => 1000], $this->admin);
            $this->fail('Debió exigir el abono mínimo');
        } catch (DomainException) {
        }

        $contrato = $svc->aprobar($s, ['abono_inicial' => 475000, 'medio_pago' => 'efectivo'], $this->admin);

        $this->assertSame(950000, $contrato->monto_total);
        $this->assertSame(475000, $contrato->fresh()->saldo_pendiente);
        $this->assertSame(1, $this->urna->fresh()->stock_reservado);
        $this->assertSame(0, $this->urna->fresh()->disponible());
        $unidad = \App\Models\UrnaUnidad::where('codigo', 'U-1-0001')->first();
        $this->assertSame('reservada', $unidad->estado);
        $this->assertSame($s->id, $unidad->solicitud_id);
        $this->assertSame('aprobada', $s->fresh()->estado);
    }

    public function test_bloquea_aprobacion_sin_stock(): void
    {
        $svc = app(SolicitudService::class);
        $a = $svc->crear($this->datos(now()->addDay()->setTime(10, 0)), $this->admin);
        $b = $svc->crear($this->datos(now()->addDays(2)->setTime(10, 0)), $this->admin);
        $svc->aprobar($a, ['abono_inicial' => 475000], $this->admin);

        $this->expectException(DomainException::class);
        $svc->aprobar($b, ['abono_inicial' => 475000], $this->admin);
    }

    public function test_abono_recalcula_saldo_y_no_supera_saldo(): void
    {
        $svc = app(SolicitudService::class);
        $pagos = app(PagoService::class);
        $s = $svc->crear($this->datos(now()->addDay()->setTime(10, 0)), $this->admin);
        $c = $svc->aprobar($s, ['abono_inicial' => 475000], $this->admin);

        $pagos->registrar($c, ['monto' => 475000, 'medio_pago' => 'cuota_afp'], $this->admin);
        $this->assertSame(0, $c->fresh()->saldo_pendiente);

        $pagos->liquidar($c->fresh());
        $this->assertSame('liquidado', $c->fresh()->estado);

        $this->expectException(DomainException::class);
        $pagos->registrar($c->fresh(), ['monto' => 1, 'medio_pago' => 'efectivo'], $this->admin);
    }

    public function test_cancelar_libera_la_unidad_y_comprar_la_vende(): void
    {
        $svc = app(SolicitudService::class);
        $s = $svc->crear($this->datos(now()->addDay()->setTime(10, 0)), $this->admin);
        $svc->aprobar($s, ['abono_inicial' => 475000], $this->admin);
        $unidad = $s->fresh()->unidad;

        app(\App\Services\StockService::class)->venderUnidad($unidad);
        $this->assertSame('vendida', $unidad->fresh()->estado);

        $svc->rechazar($s->fresh(), 'La familia cambió de funeraria');
        $this->assertSame('disponible', $unidad->fresh()->estado);
        $this->assertSame(1, $this->urna->fresh()->disponible());
    }

    public function test_familia_informa_abono_y_admin_confirma(): void
    {
        $svc = app(SolicitudService::class);
        $s = $svc->crear($this->datos(now()->addDay()->setTime(10, 0)), $this->admin);
        $c = $svc->aprobar($s, ['abono_inicial' => 475000], $this->admin);
        $pagos = app(PagoService::class);

        $a = $pagos->declarar($c, ['monto' => 100000, 'medio_pago' => 'transferencia', 'fecha' => now()->toDateString()], $this->admin,
            \Illuminate\Http\UploadedFile::fake()->create('comprobante.pdf', 10, 'application/pdf'));
        $this->assertSame(475000, $c->fresh()->saldo_pendiente); // aún no se descuenta

        $pagos->confirmar($a, $this->admin);
        $this->assertSame(375000, $c->fresh()->saldo_pendiente);
    }

    public function test_rol_sin_usuarios_se_puede_eliminar(): void
    {
        $rol = Rol::create(['nombre' => 'temporal', 'permisos' => ['calendario']]);
        $this->delete(route('roles.destroy', $rol))->assertRedirect();
        $this->assertDatabaseMissing('roles', ['nombre' => 'temporal']);
    }

    public function test_chofer_no_accede_a_finanzas(): void
    {
        $chofer = User::create(['rol_id' => Rol::idDe('chofer'), 'nombre' => 'Chofer', 'email' => 'c@c.cl', 'password' => 'Clave1234']);
        $this->actingAs($chofer)->get(route('abonos.index'))->assertForbidden();
        $this->actingAs($chofer)->get(route('urnas.index'))->assertOk();
    }

    public function test_paginas_principales_cargan(): void
    {
        foreach (['dashboard', 'solicitudes.index', 'solicitudes.create', 'urnas.index', 'flota.index', 'calendario.index',
            'abonos.index', 'usuarios.index', 'convenios.index', 'reportes.index', 'clientes.create', 'auditoria.index',
            'reposicion.index', 'itinerario.index', 'roles.index', 'configuracion.index', 'urnas.catalogo'] as $ruta) {
            $this->get(route($ruta))->assertOk();
        }
        $this->get(route('inicio'))->assertOk();
        $this->get(route('publico.catalogo'))->assertOk();
    }
}
