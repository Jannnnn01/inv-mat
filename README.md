# Inventario de materiales

Aplicacion web institucional para gestionar materiales, bodegas y movimientos de inventario.

## Linea base tecnica

- PHP 8.3
- CodeIgniter 4.7
- PostgreSQL 17
- Bootstrap 5.3, compilado localmente
- JavaScript con esbuild
- Composer 2

La autenticacion con CodeIgniter Shield y los modulos funcionales se incorporaran en las siguientes fases aprobadas.

## Requisitos locales

- PHP 8.3 con `intl`, `mbstring`, `pgsql` y `pdo_pgsql`.
- Composer 2.
- PostgreSQL 17.
- Node.js 20 o posterior y npm.

En el entorno actualmente inspeccionado, `pgsql` y `pdo_pgsql` aun deben habilitarse en el `php.ini` utilizado por PHP CLI y por el servidor web.

## Preparacion

```powershell
composer install
Copy-Item .env.example .env
npm install
npm run build
```

Complete en `.env` las credenciales locales de PostgreSQL. El archivo `.env` esta ignorado y nunca debe subirse al repositorio.

## Ejecucion local

```powershell
php spark serve
```

La aplicacion estara disponible en `http://localhost:8080/` y el health check en `http://localhost:8080/health`.

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
- Errores internos ocultos en produccion.
- Configuracion sensible mediante variables de entorno.

## Estado

Fase 1 en implementacion: scaffold, configuracion segura, PostgreSQL y frontend local.
