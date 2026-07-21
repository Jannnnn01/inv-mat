# Operación, respaldo y recuperación

## Monitoreo mínimo

- Configure una alerta externa sobre `GET /health` y tiempo de respuesta.
- Revise en Render errores HTTP 5xx, reinicios, memoria y fallos del pre-deploy.
- Revise Neon por conexiones, almacenamiento, latencia y errores.
- Configure alertas del proveedor S3 y mantenga el bucket privado, con versionado y cifrado en reposo.
- Nunca publique `phpinfo()`, `.env`, volcados de base de datos ni contenido de `writable/`.

## Respaldo

Neon debe mantener su política administrada de recuperación. Además, genere periódicamente un respaldo lógico cifrado fuera de Render:

```sh
DATABASE_URL='...' BACKUP_DIR='/ruta/privada' ./ops/backup.sh
```

El script usa formato personalizado de PostgreSQL, permisos restrictivos y nunca sobrescribe un archivo. El destino debe estar cifrado y fuera del repositorio. Compruebe la restauración al menos trimestralmente en una base aislada.

## Prueba de restauración

La restauración es destructiva. Use primero una base vacía y distinta de producción:

```sh
RESTORE_DATABASE_URL='...' RESTORE_CONFIRM=RESTORE_INV_MAT ./ops/restore.sh respaldo.dump
```

Después ejecute:

```sh
php spark migrate:status
php spark invmat:inventory:smoke
```

Valide usuarios, existencias, movimientos, auditoría, reportes y referencias de adjuntos antes de permitir tráfico.

## Incidentes y rollback

1. Desactive temporalmente los auto-deploys en Render.
2. Preserve logs y determine si el problema es código, configuración, base o almacenamiento.
3. Para un fallo solo de código, utilice el rollback de Render al último despliegue saludable.
4. No revierta migraciones automáticamente: confirme primero que la versión anterior es compatible con el esquema actual.
5. Restaure una base únicamente cuando exista pérdida o corrupción confirmada y con autorización explícita.
6. Rote inmediatamente cualquier credencial expuesta y actualícela en Render, Neon, SMTP y S3.

## Mantenimiento

- Aplique actualizaciones de Composer y npm mediante una rama y CI.
- Revise semanalmente alertas de dependencias y errores.
- Revise mensualmente usuarios activos y permisos.
- Conserve auditoría por al menos dos años según la regla del sistema.
- Pruebe trimestralmente backup/restauración y recuperación del administrador.
- Documente cada despliegue, migración, incidente y rotación de secretos.
