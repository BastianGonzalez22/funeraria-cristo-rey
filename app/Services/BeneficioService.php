<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificacionBeneficio;
use Carbon\Carbon;

/**
 * Verificación de beneficios AFP / IPS.
 * Las entidades no responden después de las 17:00 ni en fines de semana:
 * en ese caso la verificación queda "pendiente" hasta el siguiente día hábil
 * (o se debe llamar al número 600). No existe API pública, por lo que el
 * resultado lo confirma el personal en el portal de la institución.
 */
class BeneficioService
{
    public function dentroDeHorario(?Carbon $momento = null): bool
    {
        $m = $momento ?? now();

        return $m->isWeekday() && $m->hour >= 9 && $m->hour < (int) config('funeraria.hora_corte_beneficios');
    }

    public function siguienteHabil(?Carbon $momento = null): Carbon
    {
        $m = ($momento ?? now())->copy();
        if ($m->isWeekday() && $m->hour < 9) {
            return $m->setTime(9, 0);
        }
        do {
            $m->addDay();
        } while (! $m->isWeekday());

        return $m->setTime(9, 0);
    }

    public function solicitar(string $rut, string $institucion, User $u, ?int $solicitudId = null): VerificacionBeneficio
    {
        $enHorario = $this->dentroDeHorario();

        $v = VerificacionBeneficio::create([
            'usuario_id' => $u->id,
            'solicitud_id' => $solicitudId,
            'rut_fallecido' => $rut,
            'institucion' => $institucion,
            'estado' => $enHorario ? 'por_verificar' : 'pendiente',
            'consultar_desde' => $enHorario ? now() : $this->siguienteHabil(),
            'nota' => $enHorario
                ? 'Consultar ahora en el portal de la institución.'
                : 'Fuera de horario (después de las '.config('funeraria.hora_corte_beneficios').':00). Consultar el siguiente día hábil o llamar al 600.',
        ]);

        AuditService::registrar('beneficio_consulta', 'Verificación de beneficio '.strtoupper($institucion), $v);

        return $v;
    }
}
