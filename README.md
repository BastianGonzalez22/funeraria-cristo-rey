# Sistema de Gestión — Funeraria Cristo Rey

Aplicación web en **Laravel 12 (PHP 8.2+) + Blade + MySQL 8**, construida a partir de las entrevistas con el dueño (Renato Osses, 19 de octubre de 2026) y del informe *Evaluación 2 — Proyecto Integrado* (capítulos 2 a 7, en especial las pantallas de la sección 4.2).

---

## 1. Instalación

Requisitos: PHP 8.2 o superior (con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `gd`, `dom`, `fileinfo`), Composer 2 y MySQL 8 (o MariaDB 10.6+).

```bash
cd funeraria-cristo-rey
composer install
cp .env.example .env
php artisan key:generate

# Crear la base de datos vacía (MySQL):
#   CREATE DATABASE funeraria_cristo_rey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
# y completar DB_USERNAME / DB_PASSWORD en .env

php artisan migrate --seed      # tablas + datos base + datos de demostración
php artisan storage:link        # para las imágenes de urnas y los logos de convenios
php artisan serve               # http://localhost:8000
```

**Prueba rápida sin MySQL:** en `.env` ponga `DB_CONNECTION=sqlite`, borre las demás líneas `DB_*`, cree el archivo vacío `database/database.sqlite` y ejecute `php artisan migrate --seed`.

Para que se actualice cada día el estado de las vacaciones y los permisos de los choferes (opcional), agregue al cron del servidor:
`* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1`

## 2. Usuarios de prueba

Todos usan la contraseña **`CristoRey2026`** (cámbiela en producción).

| Perfil | Correo | Acceso (editable en *Roles y permisos*) |
|---|---|---|
| Administrador (dueño) | admin@cristorey.cl | Todo, incluidas finanzas, reportes, roles, auditoría y configuración |
| Encargado | encargado@cristorey.cl | Todo excepto finanzas, reportes, roles y auditoría |
| Registrador | registro@cristorey.cl | Registra familias y solicitudes, calendario y convenios |
| Reponedor | bodega@cristorey.cl | Bodega y reposición de urnas (etiquetas) |
| Chofer | chofer@cristorey.cl | Sus rutas (sin finanzas) y la bodega |
| Solicitante (familia) | familia@correo.cl | Portal: nueva solicitud, mis solicitudes, mis pagos y abonar |

Tras 5 intentos fallidos la cuenta queda **bloqueada** y el administrador la desbloquea en *Usuarios y Roles → Editar*.

## 2b. Novedades de la versión 2 (revisión con el profesor)

- **Cada urna tiene su propio código** (`U-102-0007`) y **etiqueta imprimible** con código, modelo, acabado (tallada, lisa…) y medidas.
- **Estados de la urna:** disponible → **reservada** (al aprobar con abono; queda bloqueada y desaparece del catálogo) → **vendida** (botón *Comprar*). Se puede **cambiar por otra** y la anterior vuelve a quedar disponible; también *dar de baja*.
- **Medidas** (largo × ancho × alto) y **galería de fotos** con carrusel (flechas, miniaturas, deslizar en el celular).
- **Reposición:** pantalla y rol *reponedor*: qué falta, llegada del proveedor con códigos nuevos y etiquetas.
- **Indicadores:** urnas en bodega, disponibles, reservadas, vendidas del mes y personalizadas.
- **Roles con permisos por módulo:** el administrador crea, edita y **elimina roles**; también puede eliminar usuarios.
- **Usuarios y roles:** registro rápido de familias, lista paginada y **filtro por nombre y por RUT**.
- **Rutas de choferes:** semana con navegación en el mismo bloque, mapa y enlace a la ruta completa en Google Maps; sin información contable.
- **Exportar y filtrar por mes:** solicitudes, abonos, calendario, inventario de urnas y reportes (CSV que abre Excel).
- **Dashboard:** calendario del mes arriba de los indicadores.
- **Nombre del contratante** en chico bajo el nombre de la familia en todas las listas.
- **Portal de la familia:** *Realizar abono* (transferencia + comprobante). Queda **por confirmar** hasta que finanzas lo verifica; recién ahí se descuenta del saldo.
- **Login:** imagen configurable a la derecha y botón *Volver al inicio*. **Inicio:** botón *Ingresar* destacado e imagen de portada configurable (*Configuración*).

**Actualizar una instalación existente:** reemplace los archivos del proyecto (conserve su `.env`) y ejecute:

```bash
composer install
php artisan migrate:fresh --seed   # reinicia con datos de demostración (borra lo ingresado)
# o bien:  php artisan migrate      # conserva sus datos y convierte el stock actual en urnas con código
php artisan optimize:clear
```

## 3. Pantallas (sección 4.2 del informe)

| Pantalla | Ruta | Historia | Qué hace |
|---|---|---|---|
| Ingreso al sistema | `/ingresar` | RNF seguridad | Detecta el perfil y redirige a su pantalla; bloquea tras 5 intentos |
| Dashboard | `/panel/dashboard` | H6 | Servicios del mes vs. promedio de 30, solicitudes en revisión, abonos del mes, saldos por cobrar, próximos servicios, alertas de stock, pagos atrasados, permisos y días sobrecargados |
| Solicitudes de reserva | `/panel/solicitudes` | H2 | Filtro por estado, aprobar u observar, regla de solapamiento, permisos por gestionar y registro de modificaciones |
| Nueva solicitud (familia) | `/portal/nueva-solicitud` | H1 | Asistente de 3 pasos: fecha, hora, servicio, urna, sucursal, destino final, RUT cifrado, medio de contacto, certificado escaneado y aviso del abono mínimo del 50 % |
| Nueva solicitud (personal) | `/panel/solicitudes/nueva` | H1 | Para pedidos por teléfono o WhatsApp; validación de disponibilidad en vivo |
| Aprobación | `/panel/solicitudes/{id}/aprobar` | H2, H3 | Cálculo de total, descuento por convenio y abono mínimo; reserva atómica de la urna y creación del contrato |
| Detalle del servicio | `/panel/solicitudes/{id}` | H5, H8 | Elementos a entregar, documentos, personal asignado, permisos de traslado y sanitario (SEREMI), ruta, cementerio o crematorio, página para familiares y registro de cambios |
| Stock de urnas | `/panel/stock` | H3, H9 | Código, categoría, reservadas, barra respecto del mínimo, capacidad de bodega (30), proveedores y compras sin superar la capacidad |
| Catálogo de urnas | `/panel/urnas/catalogo` | — | Filtros por tipo, color y acabado; elegir una urna abre el registro con esa urna ya seleccionada |
| Flota y cortejos | `/panel/flota` | H5, H8 | Calendario semanal por vehículo, asignación sin solapamientos, vacaciones y permisos, hoja de ruta y envío por WhatsApp |
| Abonos y pagos | `/panel/abonos` | H4, H7 | Abonos con recálculo de saldo, medio de pago (incluida la cuota AFP/IPS), control del 50 %, atrasados, anulación, comprobante PDF, liquidación y finiquito |
| Convenios | `/panel/convenios` | — | Mantenedor con logo, color y vigencia; PDF desde una plantilla única; verificación de beneficio AFP/IPS que queda pendiente después de las 17:00 |
| Usuarios y roles | `/panel/usuarios` | — | Familias filtradas por nombre y RUT (paginadas), registro rápido, ficha completa, usuarios y roles con permisos |
| Registro de clientes | `/panel/clientes/registro` | — | Validación de RUT (dígito verificador), teléfono y correo; selector de convenio y de urna; documentos recibidos |
| Calendario | `/panel/calendario` | H5 | Servicios realizados y programados mes a mes, con el detalle del día |
| Reportes | `/panel/reportes` | H6 | Servicios por mes, servicios más vendidos, control de gastos, seguimiento de cobros, boletas, exportación a CSV/Excel y nómina mensual para la SEREMI |
| Auditoría | `/panel/auditoria` | H10 | Registro de solo inserción de las acciones sobre datos sensibles, contratos, pagos y stock |
| Mi itinerario (chofer) | `/panel/mi-itinerario` | H8 | Solo dónde se vela, a dónde se traslada y los horarios, sin información contable |
| Sitio público | `/` | — | Catálogo simplificado, convenios con logos, preguntas frecuentes, contacto, WhatsApp, opiniones y la página del servicio con el libro de condolencias digital |

## 4. Reglas de negocio implementadas

- **Solapamiento (H2):** no se programa un servicio si cae dentro de ±2 h de otro servicio activo y no queda carroza libre, ni si el día ya tiene 6 servicios. El personal puede forzarlo, y entonces queda marcado como *apoyo externo* (por ejemplo, la carroza de Perquenco).
- **Stock atómico (H3):** al aprobar, la urna se reserva para la familia dentro de una transacción (`DB::transaction` + `lockForUpdate`). No se asocia una urna sin stock disponible. Al marcar el servicio como realizado, la urna sale de bodega. Las urnas personalizadas se manejan *bajo pedido*.
- **Abono mínimo:** para aprobar se exige el 50 % del total, salvo que la cuota mortuoria de AFP/IPS esté en trámite.
- **Saldos (H4):** cada abono descuenta del saldo dentro de una transacción. Un abono no puede superar el saldo. Los abonos no se borran: se anulan, y la anulación queda auditada.
- **Liquidación (H7):** solo con saldo cero; genera el finiquito en PDF.
- **Atrasos:** un contrato vigente con saldo y vencimiento pasado aparece como *pago atrasado* en el dashboard.
- **Flota (H5, H8):** un vehículo o chofer no puede estar en dos servicios a la vez; las ausencias bloquean al chofer y los vehículos en mantención no se ofrecen.
- **Datos sensibles (Ley 21.719):** el RUT del contratante y el del fallecido se guardan cifrados (con un hash para buscar); el certificado se guarda en un disco privado y cada consulta queda registrada; en pantalla el RUT aparece enmascarado.
- **Trazabilidad:** toda modificación de solicitudes, contratos, clientes, stock y usuarios queda en `auditorias` con usuario, fecha, IP y el valor anterior y nuevo de cada campo.
- **Integridad:** todas las claves foráneas usan `ON DELETE RESTRICT`. Los contratos y abonos no tienen borrado lógico.

## 5. Estructura

```
app/
  Http/Controllers/     Controladores por módulo (+ Portal/ y Publico/)
  Http/Middleware/      EnsureRol (RBAC), EnsureUsuarioActivo, SecurityHeaders
  Http/Requests/        SolicitudRequest (validación común portal/panel)
  Models/               21 modelos Eloquent (13 tablas del MER + soporte)
  Services/             SolicitudService, PagoService, FlotaService, StockService,
                        AlertaService, BeneficioService, AuditService
  Support/Rut.php       Validación, formato, enmascarado y hash del RUT
config/funeraria.php    Parámetros del negocio (capacidad, 50 %, horarios, precios…)
database/migrations/    Esquema MySQL
database/seeders/       Datos base (DatabaseSeeder) y de demostración (DemoSeeder)
resources/views/        Vistas Blade con la identidad visual del prototipo
public/css/app.css      Estilos (sin compilación; adaptables a escritorio, tablet y móvil)
public/js/app.js        Asistente por pasos, validación de RUT, filtros y cálculos en vivo
tests/                  PHPUnit: RUT, solapamiento, stock, abonos y control de acceso
```

Los precios base de cada tipo de servicio, la capacidad de la bodega, el porcentaje de abono, la ventana de solapamiento y la hora de corte de AFP/IPS se ajustan en `config/funeraria.php` (o en `.env`).

## 6. Pruebas

```bash
php artisan test
```

## 7. Notas y siguientes pasos

- **MongoDB:** el informe propone MongoDB para la auditoría. Para no exigir la extensión `mongodb` en la práctica, la auditoría se guarda en la tabla `auditorias`, que es de solo inserción: el modelo impide editar o borrar registros. Para pasar a MongoDB basta con instalar `mongodb/laravel-mongodb` y cambiar la persistencia dentro de `App\Services\AuditService`, sin tocar el resto del código.
- **AFP/IPS:** no existe una API pública, así que la verificación registra la consulta, respeta el corte de las 17:00 y el día hábil, y el personal confirma el resultado.
- **WhatsApp:** se usan enlaces `wa.me` con el texto ya armado (hoja de ruta, recordatorios de pago, compartir el servicio). Un chatbot automático requiere la API de WhatsApp Business y queda como mejora futura, igual que el pago en línea y la facturación electrónica (sección 3.14).
- **Autenticación de doble factor (2FA)** para el administrador: prevista para una etapa posterior (sección 6.2).
