#!/bin/sh
# Daily PostgreSQL backup (D-68). Runs inside the "backup" container (postgres:16-alpine) of docker-compose.yml.
#  - one compressed dump per day at BACKUP_HOUR_UTC (default 06 UTC = 03:00 Brasília), right away when the last
#    one is older than a day (first start, server was off)
#  - each dump is checked with pg_restore --list before it counts; a broken file never replaces a good one
#  - keeps BACKUP_KEEP_DAYS days (default 14); /backups/latest.json tells the admin panel how it went
# Restore: scripts/restore-db.sh <file>
set -u
KEEP="${BACKUP_KEEP_DAYS:-14}"
HOUR="${BACKUP_HOUR_UTC:-6}"
DIR=/backups
mkdir -p "$DIR"

status() { # ok file size message
  printf '{"ok":%s,"file":"%s","size":%s,"at":"%s","message":"%s"}\n' "$1" "$2" "$3" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$4" > "$DIR/latest.json.tmp" &&
    mv "$DIR/latest.json.tmp" "$DIR/latest.json"
}

run_backup() {
  name="bagparser-$(date -u +%Y%m%d-%H%M).dump"
  tmp="$DIR/.$name.tmp"
  echo "backup: dumping to $name"
  if pg_dump --format=custom --compress=9 --no-owner --file="$tmp" && pg_restore --list "$tmp" > /dev/null 2>&1; then
    mv "$tmp" "$DIR/$name"
    size=$(stat -c %s "$DIR/$name")
    status true "$name" "$size" "ok"
    echo "backup: ok ($size bytes)"
    # Old dumps go only after a good new one exists
    find "$DIR" -maxdepth 1 -name 'bagparser-*.dump' -mtime "+$KEEP" -print -delete
  else
    rm -f "$tmp"
    status false "$name" 0 "pg_dump failed"
    echo "backup: FAILED" >&2
  fi
}

# Wait for the database
until pg_isready -q; do sleep 5; done

# Back up now when there is no backup from the last 24 h
recent=$(find "$DIR" -maxdepth 1 -name 'bagparser-*.dump' -mmin -1440 | head -n 1)
[ -z "$recent" ] && run_backup

while true; do
  now=$(date -u +%s)
  today_run=$(date -u -d "@$now" +%Y-%m-%d 2>/dev/null || date -u +%Y-%m-%d)
  target=$(date -u -d "$today_run $HOUR:00:00" +%s 2>/dev/null || echo $((now + 86400)))
  [ "$target" -le "$now" ] && target=$((target + 86400))
  sleep $((target - now))
  run_backup
done
