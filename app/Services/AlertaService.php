<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\MensajeContacto;
use App\Models\Solicitud;
use App\Models\Urna;
use App\Models\User;
use App\Models\VerificacionBeneficio;

/**
 * Alertas de la pantalla principal: stock, pagos atrasados, permisos,
 * días sobrecargados, mensajes del sitio y beneficios pendientes.
 */
class AlertaService
{
    public function stockBajo()
    {
        return Urna::where('bajo_pedido', false)
            ->whereIn('estado', ['reponer', 'en_umbral'])
            ->orderByRaw("CASE estado WHEN 'reponer' THEN 0 ELSE 1 END")
            ->orderBy('codigo')
            ->get();
    }

    public function pagosAtrasados()
    {
        return Contrato::with('solicitud.cliente')->atrasados()->orderBy('fecha_vencimiento')->get();
    }

    public function permisosPendientes()
    {
        return Solicitud::with('cliente')
            ->whereIn('estado', ['aprobada', 'en_revision', 'con_observaciones'])
            ->where(fn ($q) => $q->whereIn('permiso_traslado', ['pendiente', 'en_tramite'])
                ->orWhereIn('permiso_sanitario', ['pendiente', 'en_tramite']))
            ->orderBy('fecha_hora')
            ->get();
    }

    /** Días de los próximos 7 con más servicios de los que la flota propia cubre. */
    public function diasSobrecargados(): array
    {
        $capacidad = app(SolicitudService::class)->capacidadSimultanea();
        $max = (int) config('funeraria.max_servicios_dia');
        $res = [];
        $porDia = Solicitud::activas()
            ->whereBetween('fecha_hora', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->get()
            ->groupBy(fn ($s) => $s->fecha_hora->toDateString());

        foreach ($porDia as $dia => $servicios) {
            $choques = 0;
            $ordenados = $servicios->sortBy('fecha_hora')->values();
            $v = (int) config('funeraria.ventana_solapamiento_horas');
            for ($i = 1; $i < $ordenados->count(); $i++) {
                if ($ordenados[$i]->fecha_hora->diffInMinutes($ordenados[$i - 1]->fecha_hora, true) < $v * 60) {
                    $choques++;
                }
            }
            if ($servicios->count() >= $max || $choques >= $capacidad) {
                $res[] = ['dia' => $dia, 'cantidad' => $servicios->count(), 'choques' => $choques];
            }
        }

        return $res;
    }

    public function para(User $u): array
    {
        $alertas = [];

        foreach ($u->puede('stock', 'urnas', 'reposicion') ? $this->stockBajo() : [] as $urna) {
            $alertas[] = [
                'tipo' => $urna->estado === 'reponer' ? 'peligro' : 'aviso',
                'grupo' => 'stock',
                'texto' => "{$urna->codigo} {$urna->modelo}: {$urna->disponible()} disponibles (mín. {$urna->stock_minimo})",
                'url' => $u->puede('reposicion') ? route('reposicion.index') : route('urnas.index'),
            ];
        }

        if ($u->puede('finanzas')) {
            $pend = \App\Models\Abono::where('estado', 'pendiente')->count();
            if ($pend) {
                $alertas[] = ['tipo' => 'peligro', 'grupo' => 'pagos', 'texto' => "{$pend} abono(s) informado(s) por familias esperan confirmación", 'url' => route('abonos.index').'#por-confirmar'];
            }
            foreach ($this->pagosAtrasados() as $c) {
                $alertas[] = [
                    'tipo' => 'peligro',
                    'grupo' => 'pagos',
                    'texto' => "Pago atrasado: {$c->solicitud->cliente->nombre_familia} ({$c->numero}) debe ".clp($c->saldo_pendiente),
                    'url' => route('abonos.index', ['contrato' => $c->id]),
                ];
            }
        }

        if ($u->puede('solicitudes')) {
            foreach ($this->permisosPendientes() as $s) {
                $pend = collect([
                    in_array($s->permiso_traslado, ['pendiente', 'en_tramite'], true) ? 'traslado ('.etiqueta('estados_permiso', $s->permiso_traslado).')' : null,
                    in_array($s->permiso_sanitario, ['pendiente', 'en_tramite'], true) ? 'sanitario SEREMI ('.etiqueta('estados_permiso', $s->permiso_sanitario).')' : null,
                ])->filter()->implode(', ');
                $alertas[] = [
                    'tipo' => 'aviso',
                    'grupo' => 'permisos',
                    'texto' => "Permiso por gestionar: {$s->cliente->nombre_familia} — {$pend} · servicio ".$s->fecha_hora->format('d/m H:i'),
                    'url' => route('solicitudes.show', $s),
                ];
            }
            foreach ($this->diasSobrecargados() as $d) {
                $alertas[] = [
                    'tipo' => 'peligro',
                    'grupo' => 'agenda',
                    'texto' => 'Día con servicios acumulados: '.fecha($d['dia'], 'l d M').' ('.$d['cantidad'].' servicios). Coordine apoyo externo.',
                    'url' => route('calendario.index', ['dia' => $d['dia'], 'mes' => substr($d['dia'], 0, 7)]),
                ];
            }
        }
        if ($u->puede('web')) {
            $msj = MensajeContacto::where('atendido', false)->count();
            if ($msj) {
                $alertas[] = ['tipo' => 'info', 'grupo' => 'web', 'texto' => "{$msj} mensaje(s) del sitio web sin atender", 'url' => route('web.index')];
            }
        }
        if ($u->puede('convenios')) {
            $ver = VerificacionBeneficio::whereIn('estado', ['pendiente', 'por_verificar'])->count();
            if ($ver) {
                $alertas[] = ['tipo' => 'info', 'grupo' => 'beneficios', 'texto' => "{$ver} verificación(es) de beneficio AFP/IPS pendiente(s)", 'url' => route('convenios.index').'#verificaciones'];
            }
        }

        return $alertas;
    }
}
