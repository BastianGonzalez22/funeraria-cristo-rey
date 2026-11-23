<?php

namespace App\Services;

use App\Models\AsignacionFlota;
use App\Models\Chofer;
use App\Models\Solicitud;
use App\Models\Vehiculo;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Historias 5 y 8: asignación de vehículo y chofer sin solapamientos.
 */
class FlotaService
{
    public function vehiculoOcupado(int $vehiculoId, Carbon $inicio, Carbon $fin, ?int $exceptoSolicitud = null): ?AsignacionFlota
    {
        return AsignacionFlota::with('solicitud.cliente')
            ->activas()
            ->where('vehiculo_id', $vehiculoId)
            ->when($exceptoSolicitud, fn ($q) => $q->where('solicitud_id', '!=', $exceptoSolicitud))
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->first();
    }

    public function choferOcupado(int $choferId, Carbon $inicio, Carbon $fin, ?int $exceptoSolicitud = null): ?AsignacionFlota
    {
        return AsignacionFlota::with('solicitud.cliente')
            ->activas()
            ->where('chofer_id', $choferId)
            ->when($exceptoSolicitud, fn ($q) => $q->where('solicitud_id', '!=', $exceptoSolicitud))
            ->where('inicio', '<', $fin)
            ->where('fin', '>', $inicio)
            ->first();
    }

    public function asignar(Solicitud $solicitud, array $datos): AsignacionFlota
    {
        $inicio = Carbon::parse($datos['inicio']);
        $fin = Carbon::parse($datos['fin']);

        if ($fin->lte($inicio)) {
            throw new DomainException('La hora de término debe ser posterior a la de inicio.');
        }
        if ($solicitud->estado !== 'aprobada') {
            throw new DomainException('Solo se puede asignar flota a solicitudes aprobadas.');
        }

        return DB::transaction(function () use ($solicitud, $datos, $inicio, $fin) {
            $vehiculo = Vehiculo::whereKey($datos['vehiculo_id'])->lockForUpdate()->firstOrFail();
            $chofer = Chofer::whereKey($datos['chofer_id'])->lockForUpdate()->firstOrFail();

            if ($vehiculo->estado === 'en_mantencion') {
                throw new DomainException("{$vehiculo->nombre} está en mantención.");
            }
            if ($o = $this->vehiculoOcupado($vehiculo->id, $inicio, $fin, $solicitud->id)) {
                throw new DomainException("{$vehiculo->nombre} ya está asignado a {$o->solicitud->cliente->nombre_familia} ({$o->inicio->format('d/m H:i')}–{$o->fin->format('H:i')}). Considere solicitar apoyo externo.");
            }
            if ($chofer->estado === 'no_disponible') {
                throw new DomainException("{$chofer->nombre} está marcado como no disponible.");
            }
            if ($aus = $chofer->ausenteEntre($inicio, $fin)) {
                throw new DomainException("{$chofer->nombre} tiene {$aus->motivo} registrada del {$aus->desde->format('d/m')} al {$aus->hasta->format('d/m')}.");
            }
            if ($o = $this->choferOcupado($chofer->id, $inicio, $fin, $solicitud->id)) {
                throw new DomainException("{$chofer->nombre} ya conduce en el servicio de {$o->solicitud->cliente->nombre_familia} a esa hora.");
            }

            $paradas = collect($datos['hoja_ruta'] ?? [])
                ->filter(fn ($p) => ! empty($p['lugar']) || ! empty($p['direccion']))
                ->values()->all();

            $asignacion = AsignacionFlota::updateOrCreate(
                ['solicitud_id' => $solicitud->id],
                [
                    'vehiculo_id' => $vehiculo->id,
                    'chofer_id' => $chofer->id,
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'origen' => $datos['origen'] ?? $solicitud->lugar_velatorio,
                    'destino' => $datos['destino'] ?? $solicitud->cementerio_crematorio,
                    'hoja_ruta' => $paradas,
                    'notas' => $datos['notas'] ?? null,
                    'apoyo_externo' => $vehiculo->tipo === 'externo' || $chofer->jornada === 'externo',
                    'estado' => 'programada',
                ]
            );

            if ($asignacion->apoyo_externo) {
                $solicitud->update(['requiere_apoyo_externo' => true]);
            }

            AuditService::registrar('flota_asignada', "Asignación de {$vehiculo->nombre} y {$chofer->nombre} a {$solicitud->codigo()}", $solicitud, [
                'inicio' => $inicio->format('d/m/Y H:i'),
                'fin' => $fin->format('d/m/Y H:i'),
            ]);

            return $asignacion;
        });
    }

    /** Matriz semanal [vehiculo_id][Y-m-d] => asignaciones (para el calendario de flota). */
    public function semana(Carbon $lunes): array
    {
        $domingo = $lunes->copy()->addDays(6)->endOfDay();
        $asignaciones = AsignacionFlota::with(['solicitud.cliente', 'chofer'])
            ->whereIn('estado', ['programada', 'en_curso', 'completada'])
            ->where('inicio', '<=', $domingo)
            ->where('fin', '>=', $lunes)
            ->get();

        $matriz = [];
        foreach ($asignaciones as $a) {
            $d = $a->inicio->copy()->startOfDay();
            while ($d->lte($a->fin) && $d->lte($domingo)) {
                if ($d->gte($lunes->copy()->startOfDay())) {
                    $matriz[$a->vehiculo_id][$d->toDateString()][] = $a;
                }
                $d->addDay();
            }
        }

        return $matriz;
    }
}
