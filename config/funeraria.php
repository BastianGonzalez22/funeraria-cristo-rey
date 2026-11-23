<?php

/*
|--------------------------------------------------------------------------
| Parámetros del negocio — Funeraria Cristo Rey
|--------------------------------------------------------------------------
| Valores obtenidos de las entrevistas con el dueño (19 de octubre de 2026)
| y del informe del Proyecto Integrado.
*/

return [

    'nombre' => 'Funeraria Cristo Rey',
    'lema' => 'Acompañamos a su familia con respeto y dedicación',
    'telefono' => env('FUNERARIA_TELEFONO', '+56 9 1234 5678'),
    'whatsapp' => env('FUNERARIA_WHATSAPP', '56912345678'),
    'email' => env('FUNERARIA_EMAIL', 'contacto@funerariacristorey.cl'),
    'direccion' => env('FUNERARIA_DIRECCION', 'Temuco, Región de La Araucanía'),

    // Bodega: capacidad física y reposición habitual al llegar a 5 unidades.
    'bodega_capacidad' => (int) env('BODEGA_CAPACIDAD', 30),
    'stock_minimo_defecto' => 5,

    // Abono mínimo para aceptar una solicitud (mitad del valor del servicio).
    'abono_minimo_pct' => 50,

    // Días para pagar el saldo antes de que el contrato quede "atrasado".
    'dias_vencimiento' => 30,

    // Validación de solapamiento: ventana (horas) alrededor del cortejo
    // en la que un mismo recurso (carroza) no puede estar en dos servicios.
    'ventana_solapamiento_horas' => 2,

    // Promedio mensual de servicios (indicador del dashboard) y máximo diario.
    'promedio_servicios_mes' => 30,
    'max_servicios_dia' => 6,

    // Duración estándar de un servicio (velatorio) en horas.
    'duracion_servicio_horas' => 48,

    // Hora a partir de la cual AFP / IPS dejan de responder consultas.
    'hora_corte_beneficios' => 17,

    // Cuota mortuoria AFP (UF) informada por el dueño.
    'cuota_mortuoria_uf' => 15,

    'tipos_servicio' => [
        'velatorio_cortejo' => ['nombre' => 'Velatorio + Cortejo', 'precio' => 650000],
        'velatorio' => ['nombre' => 'Velatorio', 'precio' => 450000],
        'cortejo' => ['nombre' => 'Cortejo', 'precio' => 350000],
        'cremacion' => ['nombre' => 'Cremación', 'precio' => 550000],
        'traslado' => ['nombre' => 'Traslado', 'precio' => 250000],
    ],

    'destinos' => [
        'sepultura' => 'Sepultura',
        'cremacion' => 'Crematorio',
    ],

    'categorias_urna' => [
        'estandar' => 'Estándar',
        'infantil' => 'Infantil',
        'sobredimensionada' => 'Sobredimensionada',
        'personalizada' => 'Personalizada',
        'cremacion' => 'Cremación',
    ],

    'acabados_urna' => [
        'lisa' => 'Lisa',
        'tallada' => 'Tallada',
        'metalica' => 'Metálica',
        'lacada' => 'Lacada',
    ],

    // En todo servicio se entrega lo mismo; solo cambia el precio de la urna.
    'elementos' => [
        'cruz' => 'Cruz',
        'tarjetas' => 'Tarjetas de agradecimiento',
        'foto' => 'Foto marcada',
        'flores' => 'Flores',
        'copia_certificado' => 'Copia del certificado de defunción',
        'hoja_medidas' => 'Hoja de medidas de la urna',
        'libro_condolencias' => 'Libro de condolencias',
    ],

    'documentos' => [
        'carnet_contratante' => 'Carné del contratante',
        'carnet_fallecido' => 'Carné del fallecido',
        'certificado_defuncion' => 'Certificado de defunción',
    ],

    'tareas_personal' => [
        'retiro' => 'Retiro y preparación',
        'instalacion' => 'Instalación del velatorio',
        'cortejo' => 'Cortejo',
        'sepultura' => 'Sepultura / cremación',
    ],

    'medios_contacto' => [
        'telefono' => 'Teléfono',
        'whatsapp' => 'WhatsApp',
        'email' => 'Correo electrónico',
    ],

    'medios_pago' => [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta débito / crédito',
        'cuota_afp' => 'Cuota mortuoria AFP',
        'cuota_ips' => 'Cuota mortuoria IPS',
        'convenio' => 'Pago por convenio (institución)',
    ],

    'estados_permiso' => [
        'no_requiere' => 'No requiere',
        'pendiente' => 'Pendiente',
        'en_tramite' => 'En trámite',
        'aprobado' => 'Aprobado',
    ],

    'tipos_convenio' => [
        'afp' => 'AFP',
        'ips' => 'IPS',
        'caja_prevision' => 'Caja de previsión',
        'empresa' => 'Empresa / institución',
    ],

    /*
    | Módulos del sistema. Cada rol tiene una lista de módulos permitidos;
    | el administrador puede crear roles nuevos marcando estos permisos.
    */
    'modulos' => [
        'dashboard' => ['Dashboard', 'Indicadores, alertas y próximos servicios'],
        'solicitudes' => ['Solicitudes', 'Ingresar, aprobar, observar y ver el detalle del servicio'],
        'clientes' => ['Registro de clientes', 'Registrar y editar familias'],
        'urnas' => ['Catálogo y precios de urnas', 'Crear modelos, precios, fotos, reservar y vender unidades'],
        'stock' => ['Bodega (consulta)', 'Ver el inventario de urnas sin precios'],
        'reposicion' => ['Reposición de urnas', 'Registrar llegadas de proveedor e imprimir etiquetas'],
        'flota' => ['Flota y cortejos', 'Asignar vehículos y choferes, ausencias'],
        'calendario' => ['Calendario', 'Servicios realizados y programados'],
        'itinerarios' => ['Rutas e itinerarios', 'Ver rutas y horarios de traslado (sin finanzas)'],
        'personal' => ['Personal y vehículos', 'Mantenedor de choferes y vehículos'],
        'convenios' => ['Convenios', 'Convenios y verificación AFP/IPS'],
        'usuarios' => ['Familias y usuarios (consulta)', 'Buscador de familias y su ficha'],
        'web' => ['Sitio web', 'Mensajes, opiniones y condolencias'],
        'finanzas' => ['Finanzas', 'Abonos, pagos, contratos y liquidaciones'],
        'reportes' => ['Reportes', 'Reportes, gastos y exportaciones'],
        'roles' => ['Administrar usuarios y roles', 'Crear, editar y eliminar usuarios y roles'],
        'auditoria' => ['Auditoría', 'Registro inalterable de acciones'],
        'configuracion' => ['Configuración del sitio', 'Imagen del login y de la portada'],
        'portal' => ['Portal de familias', 'Nueva solicitud, mis solicitudes y mis pagos'],
    ],

    'permisos_por_rol' => [
        'administrador' => ['dashboard', 'solicitudes', 'clientes', 'urnas', 'stock', 'reposicion', 'flota', 'calendario', 'itinerarios', 'personal', 'convenios', 'usuarios', 'web', 'finanzas', 'reportes', 'roles', 'auditoria', 'configuracion'],
        'encargado' => ['dashboard', 'solicitudes', 'clientes', 'urnas', 'stock', 'reposicion', 'flota', 'calendario', 'itinerarios', 'personal', 'convenios', 'usuarios', 'web'],
        'registrador' => ['dashboard', 'solicitudes', 'clientes', 'usuarios', 'calendario', 'convenios'],
        'reponedor' => ['stock', 'reposicion'],
        'chofer' => ['itinerarios', 'stock', 'reposicion'],
        'solicitante' => ['portal'],
    ],

    // Datos para que la familia abone por transferencia desde el portal
    'cuenta_bancaria' => [
        'banco' => env('FUNERARIA_BANCO', 'Banco Estado'),
        'tipo' => env('FUNERARIA_TIPO_CUENTA', 'Cuenta corriente'),
        'numero' => env('FUNERARIA_NUMERO_CUENTA', '000-0-000000-0'),
        'titular' => env('FUNERARIA_TITULAR', 'Funeraria Cristo Rey'),
        'rut' => env('FUNERARIA_RUT', '76.000.000-0'),
        'email' => env('FUNERARIA_EMAIL', 'contacto@funerariacristorey.cl'),
    ],

    'categorias_gasto' => [
        'urnas' => 'Compra de urnas',
        'combustible' => 'Combustible y mantención',
        'flores' => 'Flores, cruces y tarjetas',
        'arriendo' => 'Arriendo de carrozas (apoyo)',
        'personal' => 'Personal',
        'otros' => 'Otros',
    ],
];
