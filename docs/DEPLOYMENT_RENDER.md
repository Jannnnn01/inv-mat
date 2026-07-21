# Despliegue en Render

## Alcance

El repositorio incluye una imagen PHP 8.3/Apache reproducible y un Blueprint `render.yaml`. Apache publica exclusivamente `public/`; Composer instala dependencias sin paquetes de desarrollo y los activos se compilan en una etapa Node separada.

El Blueprint utiliza una instancia `starter` porque los comandos previos al despliegue no están disponibles para servicios web gratuitos. Crear el Blueprint puede generar cargos: revise el plan en Render antes de confirmar.

## Preparación

1. Suba el repositorio a GitHub sin `.env`, credenciales, respaldos ni adjuntos.
2. Confirme que el workflow `CI` finaliza correctamente.
3. En Render, seleccione **New > Blueprint** y conecte el repositorio.
4. Revise `render.yaml` y complete las variables marcadas `sync: false`.

Variables obligatorias:

- `DATABASE_URL`: URL pooler de Neon con `sslmode=require`.
- `storage_bucket`, `storage_region`, `storage_endpoint`, `storage_accessKey` y `storage_secretKey`: almacenamiento S3-compatible privado.
- `email_fromEmail`, `email_SMTPHost`, `email_SMTPUser` y `email_SMTPPass`: proveedor SMTP. El puerto configurado inicialmente es `2525` y debe ajustarse al proveedor.
- `encryption_key`: Render la genera automáticamente. No debe reutilizarse entre entornos.

Variables opcionales:

- `APP_BASE_URL`: URL pública con `/` final cuando se utilice un dominio propio. Si no se define, la aplicación usa `RENDER_EXTERNAL_URL`.
- `storage_pathStyle`: habilítela únicamente si el proveedor S3 la requiere.

## Secuencia del despliegue

1. GitHub Actions valida PHP, PHPUnit, migraciones sobre PostgreSQL 17, smoke transaccional, dependencias, frontend y Docker.
2. Render inicia el despliegue únicamente cuando los checks de GitHub pasan.
3. `ops/check-production-env.php` verifica nombres obligatorios sin imprimir valores.
4. `php spark migrate --all` aplica migraciones antes de activar la nueva versión.
5. Render consulta `/health`; el endpoint comprueba aplicación y conexión PostgreSQL sin revelar el error interno.

## Primer administrador

Desde el Shell de Render ejecute una sola vez:

```sh
php spark invmat:admin:create --username administrador --email administrador@institucion.example
```

El SMTP configurado enviará el enlace de establecimiento de contraseña. No se genera una contraseña visible en consola.

## Validación posterior

- `/health` responde `{"status":"ok"}`.
- `/login` no muestra detalles internos.
- El administrador puede iniciar y cerrar sesión.
- Las tres funciones principales se prueban con datos controlados: entrada, salida y reversión mediante solicitud.
- CSV y PDF se descargan correctamente.
- Un adjunto se carga y descarga desde el bucket privado.
- Los registros de auditoría no contienen contraseñas, tokens ni credenciales.

No ejecute `InitialCatalogSeeder` automáticamente sobre una instalación que ya contenga catálogos administrados.
