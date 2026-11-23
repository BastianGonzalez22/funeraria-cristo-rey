<?php

namespace App\Services;

use App\Models\MovimientoUrna;
use App\Models\Solicitud;
use App\Models\Urna;
use App\Models\UrnaUnidad;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Historias 3 y 9: bodega por unidades físicas.
 * Cada urna que entra a la bodega recibe su propio código (U-102-0007) y etiqueta.
 *   disponible → reservada (familia con abono; bloqueada y oculta del catálogo)
 *              → vendida (compra confirmada / servicio realizado)
 */
class StockService
{
    public function totalEnBodega(): int
    {
        return UrnaUnidad::enBodega()->count();
    }

    public function totalReservado(): int
    {
        return UrnaUnidad::where('estado', 'reservada')->count();
    }

    public function espacioLibre(): int
    {
        return max(0, (int) config('funeraria.bodega_capacidad') - $this->totalEnBodega());
    }

    /** Indicadores de bodega para el dashboard y la pantalla de stock. */
    public function indicadores(): array
    {
        $porEstado = UrnaUnidad::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $personalizadas = UrnaUnidad::enBodega()->whereHas('urna', fn ($u) => $u->where('categoria', 'personalizada'))->count();

        return [
            'en_bodega' => (int) (($porEstado['disponible'] ?? 0) + ($porEstado['reservada'] ?? 0)),
            'disponibles' => (int) ($porEstado['disponible'] ?? 0),
            'reservadas' => (int) ($porEstado['reservada'] ?? 0),
            'vendidas_mes' => UrnaUnidad::where('estado', 'vendida')->where('vendida_at', '>=', now()->startOfMonth())->count(),
            'personalizadas' => $personalizadas,
            'modelos_pedido' => Urna::where('bajo_pedido', true)->count(),
            'modelos' => Urna::count(),
            'capacidad' => (int) config('funeraria.bodega_capacidad'),
            'libre' => $this->espacioLibre(),
            'por_categoria' => UrnaUnidad::query()->join('urnas', 'urnas.id', '=', 'urna_unidades.urna_id')
                ->whereIn('urna_unidades.estado', ['disponible', 'reservada'])
                ->selectRaw('urnas.categoria as categoria, count(*) as total')->groupBy('urnas.categoria')->pluck('total', 'categoria')->all(),
        ];
    }

    /**
     * Reposición: crea una unidad por cada urna que llega, con código correlativo nuevo.
     * @return Collection<UrnaUnidad>
     */
    public function registrarCompra(int $urnaId, ?int $proveedorId, int $cantidad, int $costoTotal, User $usuario): Collection
    {
        if ($cantidad <= 0) {
            throw new DomainException('La cantidad debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($urnaId, $proveedorId, $cantidad, $costoTotal, $usuario) {
            $urna = Urna::whereKey($urnaId)->lockForUpdate()->firstOrFail();

            $libre = $this->espacioLibre();
            if ($cantidad > $libre) {
                throw new DomainException("La compra supera la capacidad de la bodega: quedan {$libre} espacios de ".config('funeraria.bodega_capacidad').'.');
            }

            $costoUnitario = $cantidad ? (int) round($costoTotal / $cantidad) : 0;
            $unidades = collect();
            for ($i = 0; $i < $cantidad; $i++) {
                $unidades->push(UrnaUnidad::create([
                    'urna_id' => $urna->id,
                    'codigo' => UrnaUnidad::siguienteCodigo($urna),
                    'estado' => 'disponible',
                    'proveedor_id' => $proveedorId,
                    'ingresada_por' => $usuario->id,
                    'costo' => $costoUnitario,
                    'fecha_ingreso' => now()->toDateString(),
                ]));
            }
            $urna->sincronizarStock();

            MovimientoUrna::create([
                'urna_id' => $urna->id,
                'proveedor_id' => $proveedorId,
                'usuario_id' => $usuario->id,
                'tipo' => 'compra',
                'cantidad' => $cantidad,
                'costo_total' => $costoTotal,
                'referencia' => 'Reposición: '.$unidades->first()->codigo.($cantidad > 1 ? ' a '.$unidades->last()->codigo : ''),
            ]);

            AuditService::registrar('stock_compra', "Ingreso de {$cantidad} unidad(es) de {$urna->codigo}", $urna, [
                'codigos' => $unidades->pluck('codigo')->implode(', '),
                'stock_resultante' => $urna->stock_actual,
            ]);

            return $unidades;
        });
    }

    /**
     * Reserva una unidad para la solicitud (debe llamarse dentro de una transacción).
     * Si no se indica unidad, toma la primera disponible del modelo.
     */
    public function reservarUnidad(Solicitud $s, Urna $urna, ?int $unidadId, ?int $usuarioId): ?UrnaUnidad
    {
        $unidad = UrnaUnidad::where('urna_id', $urna->id)
            ->when($unidadId, fn ($q) => $q->whereKey($unidadId))
            ->where('estado', 'disponible')
            ->orderBy('codigo')
            ->lockForUpdate()
            ->first();

        // Personalizadas: si no hay una en bodega, se encarga su fabricación al proveedor.
        if (! $unidad && $urna->bajo_pedido && ! $unidadId) {
            MovimientoUrna::create([
                'urna_id' => $urna->id, 'usuario_id' => $usuarioId, 'tipo' => 'reserva', 'cantidad' => 0,
                'referencia' => 'Fabricar a pedido para '.$s->cliente->nombre_familia.' ('.$s->codigo().')',
            ]);

            return null;
        }

        if (! $unidad) {
            throw new DomainException($unidadId
                ? 'La urna seleccionada ya no está disponible (fue reservada o vendida). Elija otra.'
                : "Sin unidades disponibles de {$urna->codigo} — {$urna->modelo}. No se puede asociar al contrato.");
        }

        $unidad->update(['estado' => 'reservada', 'solicitud_id' => $s->id, 'reservada_at' => now()]);
        $s->urna_unidad_id = $unidad->id;
        $urna->sincronizarStock();

        MovimientoUrna::create([
            'urna_id' => $urna->id, 'usuario_id' => $usuarioId, 'tipo' => 'reserva', 'cantidad' => 1,
            'referencia' => "Unidad {$unidad->codigo} reservada para {$s->cliente->nombre_familia} ({$s->codigo()})",
        ]);

        return $unidad;
    }

    /** Devuelve la unidad de la solicitud a "disponible" (cancelación o cambio de urna). */
    public function liberarUnidad(Solicitud $s, string $motivo): void
    {
        $unidad = $s->urna_unidad_id ? UrnaUnidad::whereKey($s->urna_unidad_id)->lockForUpdate()->first() : null;
        if ($unidad && in_array($unidad->estado, ['reservada', 'vendida'], true)) {
            $unidad->update(['estado' => 'disponible', 'solicitud_id' => null, 'reservada_at' => null, 'vendida_at' => null]);
            $unidad->urna->sincronizarStock();
            MovimientoUrna::create([
                'urna_id' => $unidad->urna_id, 'usuario_id' => auth()->id(), 'tipo' => 'liberacion', 'cantidad' => 1,
                'referencia' => "{$motivo}: unidad {$unidad->codigo} vuelve a estar disponible ({$s->codigo()})",
            ]);
        }
        $s->urna_unidad_id = null;
    }

    /** "Comprar": la reserva pasa a venta. La unidad sale del inventario disponible definitivamente. */
    public function venderUnidad(UrnaUnidad $unidad, ?string $referencia = null): void
    {
        DB::transaction(function () use ($unidad, $referencia) {
            $u = UrnaUnidad::whereKey($unidad->id)->lockForUpdate()->firstOrFail();
            if ($u->estado === 'vendida') {
                return;
            }
            if ($u->estado !== 'reservada') {
                throw new DomainException('Solo se puede confirmar la compra de una urna reservada para una familia.');
            }
            $u->update(['estado' => 'vendida', 'vendida_at' => now()]);
            $u->urna->sincronizarStock();
            MovimientoUrna::create([
                'urna_id' => $u->urna_id, 'usuario_id' => auth()->id(), 'tipo' => 'salida', 'cantidad' => -1,
                'referencia' => $referencia ?? "Compra confirmada de la unidad {$u->codigo}".($u->solicitud ? ' — '.$u->solicitud->cliente->nombre_familia : ''),
            ]);
            AuditService::registrar('urna_vendida', "Compra confirmada de la unidad {$u->codigo}", $u->solicitud ?? $u->urna);
        });
    }

    public function darDeBaja(UrnaUnidad $unidad, string $motivo): void
    {
        DB::transaction(function () use ($unidad, $motivo) {
            $u = UrnaUnidad::whereKey($unidad->id)->lockForUpdate()->firstOrFail();
            if ($u->estado !== 'disponible') {
                throw new DomainException('Solo se pueden dar de baja unidades disponibles (no reservadas ni vendidas).');
            }
            $u->update(['estado' => 'baja', 'observacion' => $motivo]);
            $u->urna->sincronizarStock();
            MovimientoUrna::create([
                'urna_id' => $u->urna_id, 'usuario_id' => auth()->id(), 'tipo' => 'ajuste', 'cantidad' => -1,
                'referencia' => "Baja de la unidad {$u->codigo}: {$motivo}",
            ]);
            AuditService::registrar('urna_baja', "Baja de la unidad {$u->codigo}: {$motivo}", $u->urna);
        });
    }
}
