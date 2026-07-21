#!/usr/bin/env sh
set -eu

if [ -z "${DATABASE_URL:-}" ]; then
  echo "DATABASE_URL es obligatoria." >&2
  exit 1
fi

backup_dir="${BACKUP_DIR:-/tmp/inv-mat-backups}"
mkdir -p "$backup_dir"
backup_file="$backup_dir/inv-mat-$(date -u +%Y%m%dT%H%M%SZ).dump"

if [ -e "$backup_file" ]; then
  echo "El archivo de respaldo ya existe; no se sobrescribirá." >&2
  exit 1
fi

pg_dump --dbname="$DATABASE_URL" --format=custom --no-owner --no-privileges --file="$backup_file"
chmod 600 "$backup_file"
echo "Respaldo creado: $backup_file"
