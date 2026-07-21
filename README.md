# Inventario de materiales

Aplicacion web institucional para gestionar materiales, bodegas y movimientos de inventario.

## Linea base tecnica

- PHP 8.3
- CodeIgniter 4.7
- PostgreSQL 17
- Bootstrap 5.3, compilado localmente
- JavaScript con esbuild
- Composer 2

- CodeIgniter Shield 1.3 para autenticacion y autorizacion.

## Requisitos locales

- PHP 8.3 con `intl`, `mbstring`, `pgsql` y `pdo_pgsql`.
- Composer 2.
- PostgreSQL 17 local o una base PostgreSQL administrada como Neon.
- Node.js 20 o posterior y npm.

PHP debe tener habilitadas las extensiones `pgsql` y `pdo_pgsql` tanto en CLI como en el servidor web.

## Preparacion

```powershell
composer install
Copy-Item .env.example .env
npm install
npm run build
```

Complete en `.env` las credenciales locales de PostgreSQL. El archivo `.env` esta ignorado y nunca debe subirse al repositorio.

Si se utiliza Neon, configure la conexion SSL en `.env` con los datos entregados por Neon. Nunca copie la URL real de conexion en archivos versionados.

## Base de datos y autenticacion

Ejecute las migraciones oficiales de Shield, Settings y las migraciones de la aplicacion:

```powershell
php spark migrate --all
php spark migrate:status
php spark db:seed InitialCatalogSeeder
```

No existe registro publico. Para crear el primer administrador, primero inicie Mailpit u otro servidor SMTP de desarrollo en el puerto configurado y ejecute:

```powershell
php spark invmat:admin:create --username administrador --email admin@institucion.edu
```

El comando no genera ni muestra una contrasena para compartir. Crea una credencial aleatoria inaccesible y envia un enlace de un solo uso para establecer la contrasena. Si el envio falla despues de crear la cuenta, corrija SMTP y reenvie el enlace:

```powershell
php spark invmat:admin:create --email admin@institucion.edu --resend
```

En la configuracion local de ejemplo, Mailpit recibe SMTP en `127.0.0.1:1025` y su interfaz normalmente se consulta en `http://127.0.0.1:8025`.

## Ejecucion local

```powershell
php spark serve
```

La aplicacion estara disponible en `http://localhost:8080/` y el health check en `http://localhost:8080/health`.

Las rutas principales de autenticacion son:

- `/login`: inicio de sesion.
- `/login/magic-link`: recuperacion de acceso.
- `/dashboard`: area autenticada.
- `/admin/usuarios`: gestion de cuentas, solo para administradores autorizados.
- `/admin/auditoria`: consulta de acciones y accesos administrativos, protegida por permisos de auditoría.
- `/reportes`: consulta y exportación de reportes operativos, protegida por `reports.view` y `reports.export`.
- `/catalogos/materiales`: catálogo de materiales.
- `/catalogos/categorias`: categorías.
- `/catalogos/proveedores`: proveedores.
- `/catalogos/unidades`: unidades de medida.
- `/catalogos/bodegas`: bodegas, solo para administradores.
- `/inventario/existencias`: stock físico por material y bodega.
- `/inventario/movimientos`: entradas, salidas e historial inmutable.
- `/inventario/solicitudes`: solicitudes de ajustes y reversiones.
- `/inventario/alertas`: alertas de stock bajo por material y bodega.
- `/inventario/valoraciones`: completar y corregir valoraciones, solo con permisos financieros.
- Las salidas permiten registrar guía/remisión, traslado, cantidades solicitadas, entregadas y pendientes.
- `/inventario/despachos/pendientes`: seguimiento y entrega posterior de cantidades pendientes.
- Cada movimiento admite respaldos privados PDF, JPG, JPEG o PNG de hasta 10 MB.

Para Apache/XAMPP o Laragon, el document root debe apuntar exclusivamente a `public/`.

## Verificacion

```powershell
composer validate --strict
composer audit
composer test
php spark routes
php spark invmat:inventory:smoke
npm run build
npm audit
docker build --tag inv-mat:local .
```

## Seguridad inicial

- Auto-routing deshabilitado.
- Rutas declaradas con verbos HTTP.
- CSRF basado en sesion.
- Content Security Policy habilitada.
- Cookies `HttpOnly`, `SameSite=Lax` y `Secure` en produccion.
- Registro publico deshabilitado.
- Login y recuperacion protegidos por limitacion de intentos.
- Enlaces de invitacion y recuperacion de un solo uso con expiracion de 15 minutos.
- Un unico rol principal por usuario, reforzado por una restriccion en PostgreSQL.
- Prohibicion de desactivar la propia cuenta o el ultimo administrador activo.
- Cuentas inactivas expulsadas de las rutas protegidas.
- Catálogos sin endpoints de eliminación física.
- Existencias separadas por material y bodega mediante `NUMERIC(14,3)`.
- Validación de cantidades enteras para materiales no fraccionables.
- Movimientos y detalles protegidos contra actualización o eliminación mediante PostgreSQL.
- Bloqueo de existencias y transacciones atómicas para impedir stock negativo.
- Costo promedio ponderado con existencia física y valorada separadas.
- Entradas sin costo almacenadas como pendientes de valoración y con motivo obligatorio.
- Ajustes y reversiones con solicitante y aprobador diferentes.
- Eventos financieros inmutables para completar o corregir costos sin alterar movimientos.
- Dashboard operativo y alertas derivadas del stock real por bodega.
- Guías de salida inmutables: las líneas pendientes no descuentan existencias.
- Entregas posteriores vinculadas a la guía original, con bloqueo transaccional contra sobreentrega.
- Una reversión de entrega posterior reabre automáticamente el pendiente correspondiente.
- Archivos con MIME real, SHA-256, nombre interno aleatorio, descarga autenticada y archivado lógico.
- Auditoría general inmutable con usuario, acción, módulo, resultado, IP protegida, agente resumido y valores sanitizados.
- Contraseñas, tokens, cookies, claves y secretos se excluyen de los valores registrados en auditoría.
- Los eventos de auditoría se conservan por un mínimo de dos años y no tienen rutas de modificación o eliminación.
- Adaptadores de almacenamiento local privado y S3-compatible para producción.
- Errores internos ocultos en produccion.
- Configuracion sensible mediante variables de entorno.

## Estado

Implementación funcional y cierre técnico incorporados: autenticación, autorización, usuarios, catálogos, existencias, movimientos, solicitudes, dashboard, alertas, valoración financiera, guías/remisiones, adjuntos privados, entregas pendientes, auditoría, reportes, pruebas automatizadas, Docker, GitHub Actions, Blueprint de Render, sesiones PostgreSQL y procedimientos de backup/rollback.

El código queda preparado para despliegue. La activación externa todavía requiere conectar el repositorio en Render, completar secretos de Neon/SMTP/S3, aceptar el plan de servicio y ejecutar las pruebas de aceptación del cliente. Consulte [Despliegue en Render](docs/DEPLOYMENT_RENDER.md), [Operación y recuperación](docs/OPERATIONS.md) y [Pruebas de aceptación](docs/ACCEPTANCE.md).

## Reportes

La ruta `/reportes` incluye existencias actuales, stock bajo, kardex por material, entradas, salidas, ajustes y reversiones, movimientos por usuario, materiales más utilizados y auditoría administrativa. Todos admiten filtros aplicables y exportación CSV/PDF. Los costos solo se incorporan cuando el usuario posee `financial.view`; la auditoría administrativa exige además `audit.view` y la dirección IP requiere `audit.sensitive`.

## Auditoría

La auditoría registra automáticamente operaciones con cambio de estado, accesos administrativos y descargas protegidas. Los valores de entrada se limitan en tamaño y se sanitizan antes de almacenarse. Nunca se conservan contraseñas, tokens, cookies, claves API, credenciales ni secretos.

La dirección IP se considera información protegida. Solo se muestra completa a usuarios con `audit.sensitive`; los demás reciben una representación parcial. La tabla `audit_events` impide `UPDATE` y `DELETE` mediante PostgreSQL y cada evento incluye una fecha mínima de retención de dos años. No existe una ruta web para eliminar auditorías.

## Almacenamiento privado

En desarrollo se utiliza `storage.driver = local`; los objetos quedan fuera de `public/`, bajo `writable/private-uploads`. En producción configure `storage.driver = s3` y las variables `storage.bucket`, `storage.region`, `storage.endpoint`, `storage.accessKey`, `storage.secretKey` y `storage.pathStyle`. El bucket debe permanecer privado. Las credenciales solo pertenecen al `.env` del entorno.
