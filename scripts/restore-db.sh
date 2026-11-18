#!/usr/bin/env bash
# Restore a backup made by the "backup" container (D-68).
#
#   bash scripts/restore-db.sh backups/bagparser-20261108-0600.dump          # restore over the current database
#   bash scripts/restore-db.sh backups/bagparser-20261108-0600.dump --check  # only test it in a scratch database
#
# Run from the project folder (/opt/apps/bagparser on the VPS). The API is stopped during a real restore and
# started again at the end; everything in the current database is replaced by the backup.
set -euo pipefail
FILE="${1:-}"
MODE="${2:-}"
[[ -f "$FILE" ]] || { echo "Uso: bash scripts/restore-db.sh <arquivo.dump> [--check]"; exit 1; }
COMPOSE="docker compose"
[[ -f docker-compose.npm.yml && -n "${NPM_NETWORK:-$(grep -s '^NPM_NETWORK=' .env | cut -d= -f2)}" ]] && COMPOSE="docker compose -f docker-compose.yml -f docker-compose.npm.yml"
DB=bagparser_db
USER=bagparser_user

docker cp "$FILE" bagparser_db:/tmp/restore.dump

if [[ "$MODE" == "--check" ]]; then
  docker exec bagparser_db sh -c "dropdb -U $USER --if-exists restore_check && createdb -U $USER restore_check && pg_restore -U $USER -d restore_check --no-owner /tmp/restore.dump"
  echo "Tabelas e linhas no backup:"
  docker exec bagparser_db psql -U "$USER" -d restore_check -t -c "SELECT 'usuarios', COUNT(*) FROM users UNION ALL SELECT 'hunts', COUNT(*) FROM hunt_sessions UNION ALL SELECT 'personagens', COUNT(*) FROM characters UNION ALL SELECT 'pagamentos', COUNT(*) FROM billing_payments"
  docker exec bagparser_db sh -c "dropdb -U $USER restore_check; rm -f /tmp/restore.dump"
  echo "OK: o backup abre e tem os dados acima (o banco do site não foi mexido)."
  exit 0
fi

read -r -p "Isto SUBSTITUI o banco atual pelo backup $FILE. Digite RESTAURAR para continuar: " ok
[[ "$ok" == "RESTAURAR" ]] || { echo "Cancelado."; exit 1; }
$COMPOSE stop api
docker exec bagparser_db pg_restore -U "$USER" -d "$DB" --clean --if-exists --no-owner --single-transaction /tmp/restore.dump
docker exec bagparser_db rm -f /tmp/restore.dump
$COMPOSE start api
echo "Restaurado. Confira o site e /api/health."
