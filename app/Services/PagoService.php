<?php

namespace App\Services;

use App\Models\Abono;
use App\Models\Contrato;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Historias 4 y 7: abonos, recálculo de saldo y liquidación.
 */
class PagoService
{
    public function registrar(Contrato $contrato, array $datos, User $usuario, ?UploadedFile $adjunto = null): Abono
    {
        return DB::transaction(function () use ($contrato, $datos, $usuario, $adjunto) {
            if ($adjunto) {
                $datos['adjunto_path'] = $adjunto->store('comprobantes', 'local');
            }

            return $this->registrarEnTransaccion($contrato, $datos, $usuario);
        });
    }

    /** Debe llamarse dentro de una transacción. */
    public function registrarEnTransaccion(Contrato $contrato, array $datos, User $usuario): Abono
    {
        /** @var Contrato $c */
        $c = Contrato::whereKey($contrato->id)->lockForUpdate()->firstOrFail();
        $monto = (int) $datos['monto'];

        if ($c->estado === 'liquidado') {
            throw new DomainException("El contrato {$c->numero} ya está liquidado.");
        }
        if ($monto <= 0) {
            throw new DomainException('El monto del abono debe ser mayor a cero.');
        }
        if ($monto > $c->saldo_pendiente) {
            throw new DomainException('El abono ('.clp($monto).') supera el saldo pendiente ('.clp($c->saldo_pendiente).').');
        }

        $abono = Abono::create([
            'contrato_id' => $c->id,
            'usuario_id' => $usuario->id,
            'monto' => $monto,
            'medio_pago' => $datos['medio_pago'],
            'fecha' => $datos['fecha'] ?? now()->toDateString(),
            'referencia' => $datos['referencia'] ?? null,
            'adjunto_path' => $datos['adjunto_path'] ?? null,
            'estado' => 'registrado',
            'confirmado_por' => $usuario->id,
            'confirmado_at' => now(),
        ]);
        $abono->numero_comprobante = sprintf('CP-%d-%05d', now()->year, $abono->id);
        $abono->save();

        $c->saldo_pendiente = $c->saldo_pendiente - $monto;
        if (in_array($datos['medio_pago'], ['cuota_afp', 'cuota_ips'], true)) {
            $c->cuota_mortuoria_en_tramite = false;
        }
        $c->save();

        AuditService::registrar('abono_registrado', "Abono {$abono->numero_comprobante} por ".clp($monto)." al contrato {$c->numero}", $c, [
            'monto' => $monto,
            'medio' => $abono->medio_pago,
            'saldo_resultante' => $c->saldo_pendiente,
        ]);

        return $abono;
    }

    public function anular(Abono $abono, string $motivo): void
    {
        DB::transaction(function () use ($abono, $motivo) {
            $a = Abono::whereKey($abono->id)->lockForUpdate()->firstOrFail();
            $c = Contrato::whereKey($a->contrato_id)->lockForUpdate()->firstOrFail();

            if ($a->estado === 'anulado') {
                throw new DomainException('El abono ya estaba anulado.');
            }
            if ($c->estado === 'liquidado') {
                throw new DomainException('No se puede anular un abono de un contrato liquidado.');
            }

            $a->update(['estado' => 'anulado', 'motivo_anulacion' => $motivo]);
            $c->saldo_pendiente = $c->saldo_pendiente + $a->monto;
            $c->save();

            AuditService::registrar('abono_anulado', "Anulación del abono {$a->numero_comprobante}: {$motivo}", $c, [
                'monto' => $a->monto,
                'saldo_resultante' => $c->saldo_pendiente,
            ]);
        });
    }

    /* ------------------------------------------------------------------
     | Abonos realizados por la familia desde el portal
     | Quedan "pendientes" hasta que la funeraria verifica la transferencia.
     * ------------------------------------------------------------------ */

    public function declarar(Contrato $contrato, array $datos, User $familia, UploadedFile $comprobante): Abono
    {
        $monto = (int) $datos['monto'];
        $pendiente = (int) $contrato->abonos()->where('estado', 'pendiente')->sum('monto');

        if ($contrato->estado === 'liquidado') {
            throw new DomainException('El contrato ya está pagado y liquidado.');
        }
        if ($monto <= 0) {
            throw new DomainException('El monto debe ser mayor a cero.');
        }
        if ($monto + $pendiente > $contrato->saldo_pendiente) {
            throw new DomainException('El monto supera el saldo pendiente ('.clp($contrato->saldo_pendiente).($pendiente ? ', con '.clp($pendiente).' ya informados por confirmar' : '').').');
        }

        $abono = Abono::create([
            'contrato_id' => $contrato->id,
            'usuario_id' => $familia->id,
            'monto' => $monto,
            'medio_pago' => $datos['medio_pago'],
            'fecha' => $datos['fecha'],
            'referencia' => $datos['referencia'] ?? null,
            'adjunto_path' => $comprobante->store('comprobantes', 'local'),
            'estado' => 'pendiente',
        ]);

        AuditService::registrar('abono_declarado', "La familia informó un abono de ".clp($monto)." al contrato {$contrato->numero}", $contrato);

        return $abono;
    }

    public function confirmar(Abono $abono, User $usuario): Abono
    {
        return DB::transaction(function () use ($abono, $usuario) {
            $a = Abono::whereKey($abono->id)->lockForUpdate()->firstOrFail();
            $c = Contrato::whereKey($a->contrato_id)->lockForUpdate()->firstOrFail();

            if ($a->estado !== 'pendiente') {
                throw new DomainException('Este abono ya fue procesado.');
            }
            if ($c->estado === 'liquidado' || $a->monto > $c->saldo_pendiente) {
                throw new DomainException('El abono supera el saldo pendiente del contrato ('.clp($c->saldo_pendiente).'). Rechácelo o contacte a la familia.');
            }

            $a->fill([
                'estado' => 'registrado',
                'numero_comprobante' => sprintf('CP-%d-%05d', now()->year, $a->id),
                'confirmado_por' => $usuario->id,
                'confirmado_at' => now(),
            ])->save();

            $c->saldo_pendiente = $c->saldo_pendiente - $a->monto;
            $c->save();

            AuditService::registrar('abono_confirmado', "Confirmación del abono {$a->numero_comprobante} (".clp($a->monto).") informado por la familia", $c, [
                'saldo_resultante' => $c->saldo_pendiente,
            ]);

            return $a;
        });
    }

    public function rechazarDeclarado(Abono $abono, string $motivo, User $usuario): void
    {
        if ($abono->estado !== 'pendiente') {
            throw new DomainException('Este abono ya fue procesado.');
        }
        $abono->update(['estado' => 'rechazado', 'motivo_anulacion' => $motivo, 'confirmado_por' => $usuario->id, 'confirmado_at' => now()]);
        AuditService::registrar('abono_rechazado', 'Rechazo de abono informado por la familia: '.$motivo, $abono->contrato);
    }

    /** Historia 7: liquidación (finiquito) cuando el saldo es cero. */
    public function liquidar(Contrato $contrato): void
    {
        DB::transaction(function () use ($contrato) {
            $c = Contrato::whereKey($contrato->id)->lockForUpdate()->firstOrFail();
            if ($c->estado === 'liquidado') {
                throw new DomainException('El contrato ya está liquidado.');
            }
            if ($c->saldo_pendiente > 0) {
                throw new DomainException('No se puede liquidar: queda un saldo de '.clp($c->saldo_pendiente).'.');
            }
            $c->update(['estado' => 'liquidado', 'liquidado_at' => now()]);
            AuditService::registrar('contrato_liquidado', "Liquidación del contrato {$c->numero}", $c);
        });
    }
}
