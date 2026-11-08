#!/usr/bin/env bash
# مين الأسبق — daily copy of both SQLite databases (+ uploaded media), kept for 14 days.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/faster}"
DEST="${BACKUP_DIR:-/var/backups/faster}"
STAMP="$(date +%F-%H%M)"
mkdir -p "$DEST/$STAMP"

for DB in database alefya; do
    SRC="$APP_DIR/database/$DB.sqlite"
    # .backup makes a consistent copy even while the game is being played.
    [[ -f "$SRC" ]] && sqlite3 "$SRC" ".backup '$DEST/$STAMP/$DB.sqlite'"
done
[[ -d "$APP_DIR/storage/app/public" ]] && tar -czf "$DEST/$STAMP/media.tar.gz" -C "$APP_DIR/storage/app" public

tar -czf "$DEST/faster-$STAMP.tar.gz" -C "$DEST" "$STAMP" && rm -rf "${DEST:?}/$STAMP"
find "$DEST" -name 'faster-*.tar.gz' -mtime +14 -delete
echo "$(date '+%F %T') ✔ $DEST/faster-$STAMP.tar.gz"
