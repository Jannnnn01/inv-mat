#!/usr/bin/env sh
set -eu

if [ "${RESTORE_CONFIRM:-}" != "RESTORE_INV_MAT" ]; then
  echo "Restauración cancelada. Define RESTORE_CONFIRM=RESTORE_INV_MAT de forma explícita." >&2
  exit 1
fi
if [ -z "${RESTORE_DATABASE_URL:-}" ] || [ -z "${1:-}" ] || [ ! -f "$1" ]; then
  echo "Uso: RESTORE_DATABASE_URL=... RESTORE_CONFIRM=RESTORE_INV_MAT ./ops/restore.sh respaldo.dump" >&2
  exit 1
fi

pg_restore --dbname="$RESTORE_DATABASE_URL" --clean --if-exists --no-owner --no-privileges --exit-on-error "$1"
echo "Restauración finalizada. Ejecuta pruebas de integridad antes de habilitar tráfico."
