@props(['valor', 'texto' => null])
@php
    $mapa = [
        // solicitudes
        'en_revision' => ['b-warn', 'En revisión'],
        'con_observaciones' => ['b-bad', 'Con observaciones'],
        'aprobada' => ['b-ok', 'Aprobada'],
        'rechazada' => ['b-neutral', 'Rechazada'],
        'finalizada' => ['b-neutral', 'Finalizada'],
        // pagos / contratos
        'pagado' => ['b-ok', 'Pagado'],
        'pendiente' => ['b-warn', 'Saldo pendiente'],
        'atrasado' => ['b-bad', 'Pago atrasado'],
        'sin_contrato' => ['b-neutral', 'Sin contrato'],
        'vigente' => ['b-ok', 'Vigente'],
        'liquidado' => ['b-neutral', 'Liquidado'],
        'registrado' => ['b-ok', 'Registrado'],
        'anulado' => ['b-bad', 'Anulado'],
        'inactivo' => ['b-neutral', 'Inactivo'],
        // stock
        'normal' => ['b-ok', 'Normal'],
        'en_umbral' => ['b-warn', 'En umbral'],
        'reponer' => ['b-bad', 'Reponer'],
        'bajo_pedido' => ['b-info', 'Bajo pedido'],
        // unidades de urna / abonos de familias
        'reservada' => ['b-warn', 'Reservada'],
        'vendida' => ['b-info', 'Vendida'],
        'baja' => ['b-bad', 'De baja'],
        'rechazado' => ['b-bad', 'Rechazado'],
        // flota
        'disponible' => ['b-ok', 'Disponible'],
        'en_servicio' => ['b-warn', 'En servicio'],
        'en_mantencion' => ['b-bad', 'En mantención'],
        'no_disponible' => ['b-bad', 'No disponible'],
        'programada' => ['b-info', 'Programada'],
        'en_curso' => ['b-warn', 'En curso'],
        'completada' => ['b-neutral', 'Completada'],
        'cancelada' => ['b-neutral', 'Cancelada'],
        // permisos
        'no_requiere' => ['b-neutral', 'No requiere'],
        'en_tramite' => ['b-warn', 'En trámite'],
        'aprobado' => ['b-ok', 'Aprobado'],
        // usuarios
        'activo' => ['b-ok', 'Activo'],
        'bloqueado' => ['b-bad', 'Bloqueado'],
        // beneficios
        'por_verificar' => ['b-warn', 'Por verificar'],
        'con_beneficio' => ['b-ok', 'Con beneficio'],
        'sin_beneficio' => ['b-neutral', 'Sin beneficio'],
    ];
    [$clase, $etiqueta] = $mapa[$valor] ?? ['b-neutral', ucfirst(str_replace('_', ' ', (string) $valor))];
@endphp
<span {{ $attributes->merge(['class' => 'badge '.$clase]) }}>{{ $texto ?? $etiqueta }}</span>
