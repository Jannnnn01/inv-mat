# Pruebas de aceptación

Estas pruebas se ejecutan en el entorno final con datos controlados. Cada caso debe registrar responsable, fecha, resultado y evidencia sin credenciales ni datos sensibles.

## Acceso y permisos

- Administrador: usuarios, bodegas, aprobaciones, valoración, auditoría y reportes financieros.
- Encargado de bodega: catálogos permitidos, entradas, salidas y solicitudes; no puede aprobar su propia solicitud ni consultar costos.
- Consulta: lectura de catálogos, existencias, movimientos y reportes no financieros; no puede modificar datos.
- Una cuenta inactiva pierde acceso y el último administrador activo no puede desactivarse.
- Recuperación y cambio de contraseña invalidan enlaces usados o vencidos.

## Inventario

- Una entrada actualiza existencia y costo promedio dentro de una transacción.
- Una entrada sin costo queda pendiente de valoración con motivo.
- Una salida válida descuenta stock; una salida superior al disponible se rechaza sin cambios parciales.
- Una guía parcial conserva la cantidad pendiente y una entrega posterior no puede excederla.
- Ajustes y reversiones requieren solicitante y aprobador diferentes.
- Los movimientos históricos no se editan ni eliminan; la corrección crea un movimiento compensatorio.
- Materiales no fraccionables rechazan cantidades decimales.

## Reportes, archivos y auditoría

- Los nueve reportes responden a sus filtros y exportan CSV/PDF.
- Un usuario sin `financial.view` no recibe costos ni en pantalla ni en archivos.
- Adjuntos PDF/JPG/PNG válidos se almacenan de forma privada; tipo o tamaño inválido se rechaza.
- La descarga requiere sesión y permiso.
- Auditoría registra cambios y accesos relevantes sin contraseñas, tokens, cookies ni secretos.

## Producción

- `/health` responde en menos de cinco segundos y detecta indisponibilidad de PostgreSQL.
- Errores inesperados muestran una página genérica, nunca stack trace ni SQL.
- Cookies de sesión incluyen `Secure`, `HttpOnly` y `SameSite=Lax`.
- Un reinicio o despliegue no invalida sesiones por pérdida del filesystem local.
- Backup y restauración se comprueban en una base aislada.
- El cliente firma la aceptación o registra observaciones pendientes antes de habilitar uso institucional.
