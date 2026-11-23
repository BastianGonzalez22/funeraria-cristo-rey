<?php

use App\Http\Controllers\AbonoController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\ConvenioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlotaController;
use App\Http\Controllers\ItinerarioController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Publico\SitioController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReposicionController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\UnidadController;
use App\Http\Controllers\UrnaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\WebAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sitio público (catálogo, convenios, contacto, servicio y condolencias)
|--------------------------------------------------------------------------
*/
Route::controller(SitioController::class)->group(function () {
    Route::get('/', 'inicio')->name('inicio');
    Route::get('/catalogo', 'catalogo')->name('publico.catalogo');
    Route::get('/catalogo/{urna:codigo}', 'urna')->name('publico.urna');
    Route::get('/convenios', 'convenios')->name('publico.convenios');
    Route::get('/preguntas-frecuentes', 'faq')->name('publico.faq');
    Route::get('/contacto', 'contacto')->name('publico.contacto');
    Route::post('/contacto', 'enviarContacto')->middleware('throttle:5,1')->name('publico.contacto.enviar');
    Route::post('/opiniones', 'enviarOpinion')->middleware('throttle:5,1')->name('publico.opinion');
    Route::get('/servicio/{token}', 'servicio')->name('publico.servicio');
    Route::post('/servicio/{token}/condolencias', 'condolencia')->middleware('throttle:10,1')->name('publico.condolencia');
});

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/ingresar', [LoginController::class, 'ingresar'])->middleware('throttle:10,1');
    Route::get('/registro', [RegistroController::class, 'mostrar'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'registrar'])->middleware('throttle:5,1');
});
Route::post('/salir', [LoginController::class, 'salir'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'activo'])->group(function () {

    Route::get('/inicio', [DashboardController::class, 'redirigir'])->name('home');

    /*
    |----------------------------------------------------------------------
    | Portal de familias (módulo "portal")
    |----------------------------------------------------------------------
    */
    Route::prefix('portal')->name('portal.')->middleware('permiso:portal')->controller(PortalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/nueva-solicitud', 'create')->name('create');
        Route::post('/nueva-solicitud', 'store')->name('store');
        Route::get('/solicitudes/{solicitud}', 'show')->name('show');
        Route::get('/solicitudes/{solicitud}/editar', 'edit')->name('edit');
        Route::put('/solicitudes/{solicitud}', 'update')->name('update');
        Route::get('/pagos', 'pagos')->name('pagos');
        Route::post('/pagos/abonar', 'abonar')->middleware('throttle:10,1')->name('abonar');
        Route::get('/comprobantes/{abono}', 'comprobante')->name('comprobante');
        Route::get('/contratos/{contrato}/pdf', 'contratoPdf')->name('contrato.pdf');
    });

    /*
    |----------------------------------------------------------------------
    | Panel interno: cada grupo exige el permiso del módulo
    |----------------------------------------------------------------------
    */
    Route::prefix('panel')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permiso:dashboard')->name('dashboard');

        // Bodega (consulta) — también choferes y reponedores
        Route::middleware('permiso:stock,urnas,reposicion')->group(function () {
            Route::get('/stock', [UrnaController::class, 'index'])->name('urnas.index');
            Route::get('/stock/exportar', [UrnaController::class, 'exportar'])->name('urnas.exportar');
            Route::get('/unidades/{unidad}', [UnidadController::class, 'show'])->name('unidades.show');
            Route::get('/etiquetas', [UnidadController::class, 'etiquetas'])->name('unidades.etiquetas');
        });

        // Reposición de urnas (reponedor, chofer, encargado, administrador)
        Route::middleware('permiso:reposicion')->group(function () {
            Route::get('/reposicion', [ReposicionController::class, 'index'])->name('reposicion.index');
            Route::post('/reposicion', [ReposicionController::class, 'store'])->name('urnas.compra');
        });

        // Rutas e itinerarios de choferes (sin finanzas)
        Route::middleware('permiso:itinerarios')->group(function () {
            Route::get('/rutas', [ItinerarioController::class, 'index'])->name('itinerario.index');
            Route::get('/rutas/{asignacion}', [ItinerarioController::class, 'show'])->name('itinerario.show');
            Route::post('/rutas/{asignacion}/estado', [ItinerarioController::class, 'estado'])->name('itinerario.estado');
        });

        // Solicitudes y detalle del servicio
        Route::middleware('permiso:solicitudes')->group(function () {
            Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
            Route::get('/solicitudes/exportar', [SolicitudController::class, 'exportar'])->name('solicitudes.exportar');
            Route::get('/solicitudes/nueva', [SolicitudController::class, 'create'])->name('solicitudes.create');
            Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
            Route::get('/solicitudes/disponibilidad', [SolicitudController::class, 'disponibilidad'])->name('solicitudes.disponibilidad');
            Route::get('/solicitudes/{solicitud}', [SolicitudController::class, 'show'])->name('solicitudes.show');
            Route::get('/solicitudes/{solicitud}/editar', [SolicitudController::class, 'edit'])->name('solicitudes.edit');
            Route::put('/solicitudes/{solicitud}', [SolicitudController::class, 'update'])->name('solicitudes.update');
            Route::put('/solicitudes/{solicitud}/detalle', [SolicitudController::class, 'actualizarDetalle'])->name('solicitudes.detalle');
            Route::get('/solicitudes/{solicitud}/aprobar', [SolicitudController::class, 'formAprobar'])->name('solicitudes.aprobar.form');
            Route::post('/solicitudes/{solicitud}/aprobar', [SolicitudController::class, 'aprobar'])->name('solicitudes.aprobar');
            Route::post('/solicitudes/{solicitud}/observar', [SolicitudController::class, 'observar'])->name('solicitudes.observar');
            Route::post('/solicitudes/{solicitud}/rechazar', [SolicitudController::class, 'rechazar'])->name('solicitudes.rechazar');
            Route::post('/solicitudes/{solicitud}/finalizar', [SolicitudController::class, 'finalizar'])->name('solicitudes.finalizar');
            Route::get('/solicitudes/{solicitud}/certificado', [SolicitudController::class, 'certificado'])->name('solicitudes.certificado');
            Route::get('/contratos/{contrato}/pdf', [ContratoController::class, 'pdf'])->name('contratos.pdf');
        });

        // Urnas: catálogo, precios, fotos y unidades (reservar / comprar / cambiar / baja)
        Route::middleware('permiso:urnas')->group(function () {
            Route::get('/urnas/catalogo', [UrnaController::class, 'catalogo'])->name('urnas.catalogo');
            Route::get('/urnas/nueva', [UrnaController::class, 'create'])->name('urnas.create');
            Route::post('/urnas', [UrnaController::class, 'store'])->name('urnas.store');
            Route::get('/urnas/{urna}', [UrnaController::class, 'show'])->name('urnas.show');
            Route::get('/urnas/{urna}/editar', [UrnaController::class, 'edit'])->name('urnas.edit');
            Route::put('/urnas/{urna}', [UrnaController::class, 'update'])->name('urnas.update');
            Route::get('/urnas/{urna}/fotos', [UrnaController::class, 'fotos'])->name('urnas.fotos');
            Route::post('/urnas/{urna}/fotos', [UrnaController::class, 'subirFotos'])->name('urnas.fotos.subir');
            Route::put('/urnas/imagenes/{imagen}', [UrnaController::class, 'actualizarImagen'])->name('urnas.imagen.actualizar');
            Route::post('/urnas/imagenes/{imagen}/principal', [UrnaController::class, 'portadaImagen'])->name('urnas.imagen.principal');
            Route::delete('/urnas/imagenes/{imagen}', [UrnaController::class, 'eliminarImagen'])->name('urnas.imagen.eliminar');
            Route::post('/proveedores', [UrnaController::class, 'guardarProveedor'])->name('proveedores.store');
            Route::put('/proveedores/{proveedor}', [UrnaController::class, 'actualizarProveedor'])->name('proveedores.update');

            Route::get('/unidades/{unidad}/reservar', [UnidadController::class, 'reservar'])->name('unidades.reservar');
            Route::post('/unidades/{unidad}/comprar', [UnidadController::class, 'comprar'])->name('unidades.comprar');
            Route::post('/unidades/{unidad}/baja', [UnidadController::class, 'baja'])->name('unidades.baja');
            Route::post('/solicitudes/{solicitud}/cambiar-urna', [UnidadController::class, 'cambiar'])->name('unidades.cambiar');
        });

        // Clientes (registro y ficha)
        Route::middleware('permiso:clientes')->group(function () {
            Route::get('/clientes/registro', [ClienteController::class, 'create'])->name('clientes.create');
            Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
            Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->name('clientes.edit');
            Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->name('clientes.update');
        });
        Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->middleware('permiso:clientes,usuarios')->name('clientes.show');

        // Familias y usuarios (consulta)
        Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios,roles')->name('usuarios.index');

        // Flota y cortejos
        Route::middleware('permiso:flota')->group(function () {
            Route::get('/flota', [FlotaController::class, 'index'])->name('flota.index');
            Route::post('/flota/asignar', [FlotaController::class, 'asignar'])->name('flota.asignar');
            Route::get('/flota/asignacion/{solicitud}', [FlotaController::class, 'editar'])->name('flota.editar');
            Route::post('/flota/ausencias', [FlotaController::class, 'registrarAusencia'])->name('flota.ausencia');
            Route::delete('/flota/ausencias/{ausencia}', [FlotaController::class, 'eliminarAusencia'])->name('flota.ausencia.eliminar');
        });

        // Personal y vehículos
        Route::middleware('permiso:personal')->group(function () {
            Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');
            Route::post('/personal/choferes', [PersonalController::class, 'guardarChofer'])->name('personal.chofer.store');
            Route::put('/personal/choferes/{chofer}', [PersonalController::class, 'actualizarChofer'])->name('personal.chofer.update');
            Route::post('/personal/vehiculos', [PersonalController::class, 'guardarVehiculo'])->name('personal.vehiculo.store');
            Route::put('/personal/vehiculos/{vehiculo}', [PersonalController::class, 'actualizarVehiculo'])->name('personal.vehiculo.update');
        });

        // Calendario
        Route::middleware('permiso:calendario')->group(function () {
            Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
            Route::get('/calendario/exportar', [CalendarioController::class, 'exportar'])->name('calendario.exportar');
        });

        // Convenios y verificación de beneficios
        Route::middleware('permiso:convenios')->group(function () {
            Route::get('/convenios', [ConvenioController::class, 'index'])->name('convenios.index');
            Route::get('/convenios/disponibles', [ConvenioController::class, 'disponibles'])->name('convenios.disponibles');
            Route::get('/convenios/nuevo', [ConvenioController::class, 'create'])->name('convenios.create');
            Route::post('/convenios', [ConvenioController::class, 'store'])->name('convenios.store');
            Route::get('/convenios/{convenio}/editar', [ConvenioController::class, 'edit'])->name('convenios.edit');
            Route::put('/convenios/{convenio}', [ConvenioController::class, 'update'])->name('convenios.update');
            Route::get('/convenios/{convenio}/pdf', [ConvenioController::class, 'pdf'])->name('convenios.pdf');
            Route::post('/beneficios/verificar', [ConvenioController::class, 'verificar'])->name('beneficios.verificar');
            Route::put('/beneficios/{verificacion}', [ConvenioController::class, 'resolverVerificacion'])->name('beneficios.resolver');
        });

        // Sitio web: mensajes, opiniones y condolencias
        Route::middleware('permiso:web')->group(function () {
            Route::get('/sitio-web', [WebAdminController::class, 'index'])->name('web.index');
            Route::post('/sitio-web/mensajes/{mensaje}', [WebAdminController::class, 'atenderMensaje'])->name('web.mensaje');
            Route::post('/sitio-web/opiniones/{opinion}', [WebAdminController::class, 'publicarOpinion'])->name('web.opinion');
            Route::post('/sitio-web/condolencias/{condolencia}', [WebAdminController::class, 'ocultarCondolencia'])->name('web.condolencia');
        });

        // Finanzas
        Route::middleware('permiso:finanzas')->group(function () {
            Route::get('/abonos', [AbonoController::class, 'index'])->name('abonos.index');
            Route::get('/abonos/exportar', [AbonoController::class, 'exportar'])->name('abonos.exportar');
            Route::post('/abonos', [AbonoController::class, 'store'])->name('abonos.store');
            Route::post('/abonos/{abono}/anular', [AbonoController::class, 'anular'])->name('abonos.anular');
            Route::post('/abonos/{abono}/confirmar', [AbonoController::class, 'confirmar'])->name('abonos.confirmar');
            Route::post('/abonos/{abono}/rechazar', [AbonoController::class, 'rechazar'])->name('abonos.rechazar');
            Route::get('/abonos/{abono}/comprobante', [AbonoController::class, 'comprobante'])->name('abonos.comprobante');
            Route::get('/abonos/{abono}/adjunto', [AbonoController::class, 'adjunto'])->name('abonos.adjunto');

            Route::get('/contratos/{contrato}', [ContratoController::class, 'show'])->name('contratos.show');
            Route::put('/contratos/{contrato}', [ContratoController::class, 'update'])->name('contratos.update');
            Route::post('/contratos/{contrato}/liquidar', [ContratoController::class, 'liquidar'])->name('contratos.liquidar');
            Route::get('/contratos/{contrato}/finiquito', [ContratoController::class, 'finiquito'])->name('contratos.finiquito');
        });

        // Reportes
        Route::middleware('permiso:reportes')->group(function () {
            Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
            Route::get('/reportes/exportar', [ReporteController::class, 'exportar'])->name('reportes.exportar');
            Route::get('/reportes/seremi', [ReporteController::class, 'seremi'])->name('reportes.seremi');
            Route::post('/gastos', [ReporteController::class, 'guardarGasto'])->name('gastos.store');
            Route::delete('/gastos/{gasto}', [ReporteController::class, 'eliminarGasto'])->name('gastos.destroy');
        });

        // Usuarios y roles (administración)
        Route::middleware('permiso:roles')->group(function () {
            Route::get('/usuarios/nuevo', [UsuarioController::class, 'create'])->name('usuarios.create');
            Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
            Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
            Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
            Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

            Route::get('/roles', [RolController::class, 'index'])->name('roles.index');
            Route::get('/roles/nuevo', [RolController::class, 'create'])->name('roles.create');
            Route::post('/roles', [RolController::class, 'store'])->name('roles.store');
            Route::get('/roles/{rol}/editar', [RolController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{rol}', [RolController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{rol}', [RolController::class, 'destroy'])->name('roles.destroy');
        });

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->middleware('permiso:auditoria')->name('auditoria.index');

        Route::middleware('permiso:configuracion')->group(function () {
            Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
            Route::post('/configuracion', [ConfiguracionController::class, 'guardar'])->name('configuracion.guardar');
        });
    });
});
