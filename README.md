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

Para Apache/XAMPP o Laragon, el document root debe apuntar exclusivamente a `public/`.

## Verificacion

```powershell
composer validate --strict
composer audit
composer test
php spark routes
npm run build
npm audit
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
- Errores internos ocultos en produccion.
- Configuracion sensible mediante variables de entorno.

## Estado

Fases 1 y 2 implementadas: base tecnica, PostgreSQL, frontend local, autenticacion, recuperacion, roles y gestion administrativa de usuarios. Los catalogos y movimientos de inventario se incorporaran en las siguientes fases.
