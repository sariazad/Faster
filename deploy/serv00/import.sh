#!/bin/sh
# مين الأسبق — replace this Serv00 copy's content with a bundle from the Oracle server
# (the four SQLite databases + uploaded images). Run by .github/workflows/sync-to-serv00.yml.
#   sh import.sh ~/sync-in
set -eu

IN="${1:?folder with the bundle}"
DOMAIN="${DOMAIN:-$(whoami).serv00.net}"
APP="$HOME/domains/$DOMAIN/app"
KEEP="$HOME/domains/$DOMAIN/sync-backups/$(date +%Y%m%d-%H%M)"   # ~/backups belongs to Serv00 itself (read-only)
cd "$APP"

[ -f "$IN/database.sqlite" ] || { echo "ما في database.sqlite بالحزمة — ما غيّرت شي."; exit 1; }

php artisan down --retry=15 --quiet || true
trap 'php artisan up --quiet' EXIT

echo "▶ نسخة احتياطية من المحتوى الحالي: $KEEP"
mkdir -p "$KEEP"
for DB in database alefya akher qaddaha; do
    [ -f "database/$DB.sqlite" ] && cp "database/$DB.sqlite" "$KEEP/"
done
[ -d storage/app/public ] && tar -czf "$KEEP/media.tar.gz" -C storage/app public

echo "▶ نقل قواعد البيانات"
for DB in database alefya akher qaddaha; do
    if [ -f "$IN/$DB.sqlite" ]; then
        rm -f "database/$DB.sqlite-wal" "database/$DB.sqlite-shm"
        cp "$IN/$DB.sqlite" "database/$DB.sqlite"
        echo "  ✓ $DB"
    fi
done

if [ -f "$IN/media.tar.gz" ]; then
    echo "▶ نقل الصور المرفوعة"
    rm -rf storage/app/public
    tar -xzf "$IN/media.tar.gz" -C storage/app
fi

php artisan storage:link --quiet 2>/dev/null || true
php artisan migrate --force
php artisan optimize:clear --quiet
php artisan optimize --quiet
php artisan filament:optimize --quiet 2>/dev/null || true
rm -rf "$IN"

# keep only the last 5 pre-sync backups (3GB quota)
ls -dt "$HOME/domains/$DOMAIN"/sync-backups/* 2>/dev/null | tail -n +6 | xargs rm -rf 2>/dev/null || true

echo "✔ تم النقل. النسخة القديمة محفوظة بـ $KEEP"
