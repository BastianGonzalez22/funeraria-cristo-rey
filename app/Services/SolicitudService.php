<?php

namespace App\Services;

use App\Models\AsignacionFlota;
use App\Models\Abono;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Convenio;
use App\Models\MovimientoUrna;
use App\Models\Solicitud;
use App\Models\Urna;
use App\Models\UrnaUnidad;
use App\Models\User;
use App\Models\Vehiculo;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Núcleo del negocio: Historias 1, 2 y 3.
 */
class SolicitudService
{
    public function __construct(private StockService $stock)
    {
    }

    /* ------------------------------------------------------------------
     | Validación de solapamiento (Historia 2)
     * ------------------------------------------------------------------ */

    public function solapadas(Carbon $fechaHora, ?int $exceptoId = null): Collection
    {
        $v = (int) config('funeraria.ventana_solapamiento_horas', 2);

        return Solicitud::with('cliente')
            ->activas()
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->where('fecha_hora', '>', $fechaHora->copy()->subHours($v))
            ->where('fecha_hora', '<', $fechaHora->copy()->addHours($v))
            ->orderBy('fecha_hora')
            ->get();
    }

    /** Servicios que pueden realizarse simultáneamente con la flota propia. */
    public function capacidadSimultanea(): int
    {
        return max(1, Vehiculo::where('tipo', 'carroza')->asignables()->count());
    }

    public function serviciosDelDia(Carbon $fecha, ?int $exceptoId = null): int
    {
        return Solicitud::activas()
            ->when($exceptoId, fn ($q) => $q->where('id', '!=', $exceptoId))
            ->whereDate('fecha_hora', $fecha->toDateString())
            ->count();
    }

    /**
     * Devuelve null si el horario está libre, o un mensaje que explica el conflicto.
     */
    public function conflictoHorario(Carbon $fechaHora, ?int $exceptoId = null): ?string
    {
        $solapadas = $this->solapadas($fechaHora, $exceptoId);
        if ($solapadas->count() >= $this->capacidadSimultanea()) {
            $lista = $solapadas->map(fn ($s) => $s->cliente->nombre_familia.' ('.$s->fecha_hora->format('H:i').')')->implode(', ');

            $v = (int) config('funeraria.ventana_solapamiento_horas', 2);

            return "Hay otro servicio que comienza a menos de {$v} horas ({$lista}) y no queda carroza propia libre. "
                .'Elija otra hora ese mismo día o marque "Registrar aunque el horario se solape" para coordinar apoyo externo.';
        }
        if ($this->serviciosDelDia($fechaHora, $exceptoId) >= (int) config('funeraria.max_servicios_dia')) {
            return 'Ya existen '.config('funeraria.max_servicios_dia').' servicios ese día (capacidad máxima).';
        }

        return null;
    }

    /* ------------------------------------------------------------------
     | Creación (Historia 1)
     * ------------------------------------------------------------------ */

    public function crear(array $datos, User $usuario, ?UploadedFile $certificado = null, bool $forzar = false): Solicitud
    {
        $fecha = Carbon::parse($datos['fecha_hora']);

        if ($conflicto = $this->conflictoHorario($fecha)) {
            if (! $forzar) {
                throw new DomainException($conflicto);
            }
            $datos['requiere_apoyo_externo'] = true;
        }

        if (! empty($datos['urna_id'])) {
            $urna = Urna::findOrFail($datos['urna_id']);
            if (! $urna->puedeAsociarse()) {
                throw new DomainException("La urna {$urna->codigo} no tiene stock disponible.");
            }
        }

        return DB::transaction(function () use ($datos, $usuario, $certificado) {
            $datos['usuario_solicitante_id'] = $usuario->id;
            $datos['token_publico'] = Str::random(32);
            $datos['estado'] = 'en_revision';
            $datos['elementos'] = $datos['elementos'] ?? array_fill_keys(array_keys(config('funeraria.elementos')), false);
            $datos['permiso_traslado'] = ! empty($datos['fuera_comuna']) ? 'pendiente' : 'no_requiere';

            if ($certificado) {
                $datos['certificado_path'] = $certificado->store('certificados', 'local');
                $datos['documentos'] = array_merge($datos['documentos'] ?? [], ['certificado_defuncion' => true]);
            }

            $solicitud = Solicitud::create($datos);

            AuditService::registrar('solicitud_creada', "Creación de la solicitud {$solicitud->codigo()}", $solicitud, [
                'familia' => $solicitud->cliente->nombre_familia,
                'fecha_hora' => $solicitud->fecha_hora->format('d/m/Y H:i'),
            ]);

            return $solicitud;
        });
    }

    /** Edición (la familia cambia algún dato). Valida de nuevo el horario y deja registro. */
    public function actualizar(Solicitud $solicitud, array $datos, ?UploadedFile $certificado = null, bool $forzar = false): Solicitud
    {
        if (in_array($solicitud->estado, ['rechazada', 'finalizada'], true)) {
            throw new DomainException('No se puede modificar una solicitud rechazada o finalizada.');
        }

        if (isset($datos['fecha_hora'])) {
            $fecha = Carbon::parse($datos['fecha_hora']);
            if (! $fecha->equalTo($solicitud->fecha_hora) && ($conflicto = $this->conflictoHorario($fecha, $solicitud->id))) {
                if (! $forzar) {
                    throw new DomainException($conflicto);
                }
                $datos['requiere_apoyo_externo'] = true;
            }
        }

        return DB::transaction(function () use ($solicitud, $datos, $certificado) {
            $nuevaUrna = $datos['urna_id'] ?? $solicitud->urna_id;
            if ($solicitud->estado === 'aprobada' && (int) $nuevaUrna !== (int) $solicitud->urna_id) {
                $this->cambiarUrnaAprobada($solicitud, (int) $nuevaUrna);
                unset($datos['urna_id']);
            }

            if ($certificado) {
                $datos['certificado_path'] = $certificado->store('certificados', 'local');
                $datos['documentos'] = array_merge($solicitud->documentos ?? [], $datos['documentos'] ?? [], ['certificado_defuncion' => true]);
            }

            $solicitud->fill($datos);
            $cambios = AuditService::cambios($solicitud, ['rut_fallecido', 'token_publico']);
            $solicitud->save();

            if ($cambios) {
                AuditService::registrar('solicitud_modificada', 'Modificación de '.$solicitud->codigo().': '.implode(', ', array_keys($cambios)), $solicitud, $cambios);
            }

            // Si cambia el horario y hay flota asignada, se desplaza la asignación.
            if (isset($cambios['fecha_hora']) && $solicitud->asignacion) {
                $a = $solicitud->asignacion;
                $dur = (int) abs($a->inicio->diffInMinutes($a->fin));
                $a->update(['inicio' => $solicitud->fecha_hora, 'fin' => $solicitud->fecha_hora->copy()->addMinutes($dur)]);
            }

            return $solicitud->fresh();
        });
    }

    /* ------------------------------------------------------------------
     | Aprobación con descuento atómico de stock (Historias 2 y 3)
     * ------------------------------------------------------------------ */

    public function aprobar(Solicitud $solicitud, array $datos, User $usuario): Contrato
    {
        return DB::transaction(function () use ($solicitud, $datos, $usuario) {
            /** @var Solicitud $s */
            $s = Solicitud::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            if (! in_array($s->estado, ['en_revision', 'con_observaciones'], true)) {
                throw new DomainException('La solicitud ya fue procesada (estado: '.$s->nombreEstado().').');
            }
            if (empty($datos['forzar']) && ($conflicto = $this->conflictoHorario($s->fecha_hora, $s->id))) {
                throw new DomainException($conflicto);
            }

            // Unidad física elegida (opcional). Si es de otro modelo, la solicitud pasa a ese modelo.
            $unidadId = ! empty($datos['urna_unidad_id']) ? (int) $datos['urna_unidad_id'] : null;
            if ($unidadId && ($elegida = UrnaUnidad::find($unidadId)) && $elegida->urna_id !== $s->urna_id) {
                $s->urna_id = $elegida->urna_id;
            }
            if (! $s->urna_id) {
                throw new DomainException('Debe seleccionar una urna antes de aprobar.');
            }

            // Bloqueo de fila sobre el modelo y la unidad: dos aprobaciones simultáneas no pueden tomar la misma urna.
            /** @var Urna $urna */
            $urna = Urna::whereKey($s->urna_id)->lockForUpdate()->firstOrFail();

            $convenio = ! empty($datos['convenio_id']) ? Convenio::find($datos['convenio_id']) : $s->convenio;
            if ($convenio && ! $convenio->estaVigente()) {
                throw new DomainException('El convenio seleccionado no está vigente.');
            }

            $montoServicio = (int) ($datos['monto_servicio'] ?? $s->precioServicio());
            $montoUrna = (int) $urna->precio;
            $subtotal = $montoServicio + $montoUrna;
            $descuento = array_key_exists('descuento', $datos) && $datos['descuento'] !== null
                ? (int) $datos['descuento']
                : ($convenio ? $convenio->descuentoPara($subtotal) : 0);
            $total = max(0, $subtotal - $descuento);
            $minimo = (int) round($total * config('funeraria.abono_minimo_pct') / 100);
            $abonoInicial = (int) ($datos['abono_inicial'] ?? 0);
            $enTramite = ! empty($datos['cuota_mortuoria_en_tramite']);

            if ($abonoInicial > $total) {
                throw new DomainException('El abono inicial no puede superar el total del contrato.');
            }
            if ($abonoInicial < $minimo && ! $enTramite) {
                throw new DomainException('Para aprobar se requiere un abono mínimo de '.clp($minimo).' (50% del valor del servicio), o marcar la cuota mortuoria AFP/IPS en trámite.');
            }

            // Reserva de la unidad física para la familia (queda bloqueada y fuera del catálogo)
            $unidad = $this->stock->reservarUnidad($s, $urna, $unidadId, $usuario->id);

            $contrato = Contrato::create([
                'numero' => Contrato::siguienteNumero(),
                'solicitud_id' => $s->id,
                'convenio_id' => $convenio?->id,
                'monto_servicio' => $montoServicio,
                'monto_urna' => $montoUrna,
                'descuento' => $descuento,
                'monto_total' => $total,
                'abono_minimo' => $minimo,
                'saldo_pendiente' => $total,
                'cuotas' => max(1, (int) ($datos['cuotas'] ?? 1)),
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? $s->fecha_hora->copy()->addDays((int) config('funeraria.dias_vencimiento'))->toDateString(),
                'cuota_mortuoria_en_tramite' => $enTramite,
                'clausulas_adicionales' => $datos['clausulas_adicionales'] ?? null,
                'estado' => 'vigente',
            ]);

            if ($abonoInicial > 0) {
                app(PagoService::class)->registrarEnTransaccion($contrato, [
                    'monto' => $abonoInicial,
                    'medio_pago' => $datos['medio_pago'] ?? 'efectivo',
                    'fecha' => now()->toDateString(),
                    'referencia' => 'Abono inicial',
                ], $usuario);
            }

            $s->fill([
                'estado' => 'aprobada',
                'convenio_id' => $convenio?->id,
                'observacion' => null,
            ])->save();

            $codigoUrna = $unidad?->codigo ?? $urna->codigo.' (a pedido)';
            AuditService::registrar('solicitud_aprobada', "Aprobación de {$s->codigo()}; urna {$codigoUrna} reservada; contrato {$contrato->numero}", $s, [
                'urna' => $codigoUrna.' '.$urna->modelo,
                'contrato' => $contrato->numero,
                'total' => $total,
                'abono_inicial' => $abonoInicial,
            ]);

            return $contrato;
        });
    }

    public function observar(Solicitud $solicitud, string $observacion): void
    {
        if (! in_array($solicitud->estado, ['en_revision', 'con_observaciones'], true)) {
            throw new DomainException('Solo se pueden observar solicitudes en revisión.');
        }
        $solicitud->update(['estado' => 'con_observaciones', 'observacion' => $observacion]);
        AuditService::registrar('solicitud_observada', "Observación de {$solicitud->codigo()}: {$observacion}", $solicitud);
    }

    public function rechazar(Solicitud $solicitud, string $motivo): void
    {
        DB::transaction(function () use ($solicitud, $motivo) {
            $s = Solicitud::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            if (in_array($s->estado, ['rechazada', 'finalizada'], true)) {
                throw new DomainException('La solicitud ya está cerrada.');
            }
            if ($s->estado === 'aprobada') {
                $this->stock->liberarUnidad($s, 'Cancelación del servicio');
                $s->asignacion?->update(['estado' => 'cancelada']);
            }
            $s->fill(['estado' => 'rechazada', 'observacion' => $motivo])->save();
            AuditService::registrar('solicitud_rechazada', "Rechazo/cancelación de {$s->codigo()}: {$motivo}", $s);
        });
    }

    /** Servicio realizado: la urna sale físicamente de bodega. */
    public function finalizar(Solicitud $solicitud): void
    {
        DB::transaction(function () use ($solicitud) {
            $s = Solicitud::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            if ($s->estado !== 'aprobada') {
                throw new DomainException('Solo se pueden finalizar servicios aprobados.');
            }
            $unidad = $s->urna_unidad_id ? UrnaUnidad::find($s->urna_unidad_id) : null;
            if ($unidad && $unidad->estado === 'reservada') {
                $this->stock->venderUnidad($unidad, "Entrega de la unidad {$unidad->codigo} en el servicio {$s->codigo()} — {$s->cliente->nombre_familia}");
            } elseif (! $unidad && $s->urna_id) {
                MovimientoUrna::create([
                    'urna_id' => $s->urna_id,
                    'usuario_id' => auth()->id(),
                    'tipo' => 'salida',
                    'cantidad' => 0,
                    'referencia' => 'Entrega (a pedido) en servicio '.$s->codigo().' — '.$s->cliente->nombre_familia,
                ]);
            }
            $s->update(['estado' => 'finalizada']);
            $s->asignacion?->update(['estado' => 'completada']);
            AuditService::registrar('servicio_finalizado', "Servicio {$s->codigo()} realizado", $s);
        });
    }

    /**
     * "Modificar si quiere otra": cambia la urna de una solicitud aprobada.
     * Libera la unidad anterior, reserva la nueva y ajusta el contrato.
     */
    public function cambiarUrna(Solicitud $solicitud, int $nuevaUrnaId, ?int $unidadId = null): void
    {
        DB::transaction(function () use ($solicitud, $nuevaUrnaId, $unidadId) {
            $s = Solicitud::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            if ($s->estado !== 'aprobada') {
                throw new DomainException('Solo se puede cambiar la urna de una solicitud aprobada.');
            }
            $anterior = $s->unidad?->codigo ?? $s->urna?->codigo;
            $this->cambiarUrnaAprobada($s, $nuevaUrnaId, $unidadId);
            $s->save();
            AuditService::registrar('urna_cambiada', "Cambio de urna en {$s->codigo()}: {$anterior} → ".($s->unidad()->value('codigo') ?? $s->urna->codigo), $s);
        });
    }

    /** Debe llamarse dentro de una transacción. */
    private function cambiarUrnaAprobada(Solicitud $s, int $nuevaUrnaId, ?int $unidadId = null): void
    {
        $nueva = Urna::whereKey($nuevaUrnaId)->lockForUpdate()->firstOrFail();
        if (! $nueva->puedeAsociarse()) {
            throw new DomainException("La urna {$nueva->codigo} no tiene unidades disponibles.");
        }

        $contrato = Contrato::whereKey($s->contrato?->id)->lockForUpdate()->first();
        if ($contrato) {
            $diferencia = (int) $nueva->precio - $contrato->monto_urna;
            $nuevoTotal = max(0, $contrato->monto_total + $diferencia);
            $abonado = $contrato->totalAbonado();
            if ($abonado > $nuevoTotal) {
                throw new DomainException('El nuevo total sería menor a lo ya abonado. Anule abonos antes de cambiar la urna.');
            }
        }

        $this->stock->liberarUnidad($s, 'Cambio de urna solicitado por la familia');
        $s->urna_id = $nueva->id;
        $this->stock->reservarUnidad($s, $nueva, $unidadId, auth()->id());

        if ($contrato) {
            $contrato->update([
                'monto_urna' => $nueva->precio,
                'monto_total' => $nuevoTotal,
                'abono_minimo' => (int) round($nuevoTotal * config('funeraria.abono_minimo_pct') / 100),
                'saldo_pendiente' => $nuevoTotal - $abonado,
            ]);
        }
    }

    /** Busca o crea la ficha de la familia a partir de los datos del formulario. */
    public function clienteDesdeFormulario(array $d, ?User $usuarioPortal = null): Cliente
    {
        if ($usuarioPortal && $usuarioPortal->cliente) {
            $c = $usuarioPortal->cliente;
            $c->update(array_filter([
                'telefono' => $d['telefono'] ?? null,
                'medio_contacto' => $d['medio_contacto'] ?? null,
                'rut_contratante' => $d['rut_contratante'] ?? null,
            ]));

            return $c;
        }

        $existente = Cliente::where('rut_hash', \App\Support\Rut::hash($d['rut_contratante']))->first();
        if ($existente && (! $usuarioPortal || ! $existente->user_id)) {
            if ($usuarioPortal) {
                $existente->update(['user_id' => $usuarioPortal->id]);
            }

            return $existente;
        }

        return Cliente::create([
            'user_id' => $usuarioPortal?->id,
            'nombre_familia' => $d['nombre_familia'],
            'nombre_contratante' => $d['nombre_contratante'],
            'rut_contratante' => \App\Support\Rut::formatear($d['rut_contratante']),
            'telefono' => $d['telefono'],
            'email' => $d['email'] ?? null,
            'direccion' => $d['direccion'] ?? null,
            'medio_contacto' => $d['medio_contacto'] ?? 'telefono',
        ]);
    }
}
