#!/usr/bin/env bash
# مين الأسبق — pull the latest version from GitHub and rebuild.   sudo bash deploy.sh
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/faster}"
APP_USER="$(stat -c %U "$APP_DIR")"
PHP_V="8.3"
as_app() { sudo -u "$APP_USER" -H bash -c "cd '$APP_DIR' && $*"; }

[[ $EUID -eq 0 ]] || { echo "شغّله بـ sudo: sudo bash $0"; exit 1; }

echo "▶ نسخة احتياطية قبل التحديث"
APP_DIR="$APP_DIR" /usr/local/bin/faster-backup || true

echo "▶ سحب آخر نسخة"
as_app "php artisan down --retry=15 --quiet || true"
trap 'as_app "php artisan up --quiet"' EXIT
as_app "git pull --ff-only"

echo "▶ المكتبات والواجهة"
as_app "composer install --no-dev --optimize-autoloader --no-interaction --quiet"
as_app "npm ci --no-audit --no-fund --silent && npm run build --silent" >/dev/null

echo "▶ قواعد البيانات"
as_app "php artisan migrate --force"
as_app "php artisan db:seed --class=QuestionBankSeeder --force"
as_app "php artisan db:seed --class=AlefYaSeeder --force"

as_app "php artisan optimize:clear --quiet && php artisan optimize --quiet && php artisan filament:optimize --quiet 2>/dev/null || true"
chown -R "$APP_USER":www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
systemctl reload "php${PHP_V}-fpm"

echo "✔ تم التحديث: $(git -C "$APP_DIR" log -1 --format='%h %s')"
