#!/usr/bin/env bash
# Project setup for GitHub Codespaces / Dev Containers / local machines.
# Safe to run more than once.
set -e

export XDEBUG_MODE=off

step() { echo; echo "▶ $1"; }

# Fallback for containers built from an older image without intl.
if ! php -m | grep -qi '^intl$'; then
    step "تثبيت إضافة intl"
    sudo apt-get update -y
    sudo apt-get install -y libicu-dev
    sudo PHP_INI_DIR="${PHP_INI_DIR:-/usr/local/etc/php}" docker-php-ext-install intl
fi

# Larger upload limits for question media (for containers built before this was in the image).
ini_dir="${PHP_INI_DIR:-/usr/local/etc/php}/conf.d"
if [ -d "$ini_dir" ] && ! cmp -s .devcontainer/php-uploads.ini "$ini_dir/zz-uploads.ini"; then
    sudo cp .devcontainer/php-uploads.ini "$ini_dir/zz-uploads.ini" 2>/dev/null || true
fi

step "إعداد ملف البيئة"
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Point APP_URL at the Codespace's forwarded port so links and assets work.
if [ -n "$CODESPACE_NAME" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
    sed -i "s#^APP_URL=.*#APP_URL=${url}#" .env
fi

touch database/database.sqlite

step "تثبيت مكتبات PHP (composer)"
if ! composer install --no-interaction --prefer-dist > /tmp/composer.log 2>&1; then
    echo "❌ فشل تثبيت المكتبات. السبب:"
    grep -E "Problem|missing|requires php|affected by security advisories" /tmp/composer.log \
        | sed -E 's/Go to https.*//' | cut -c1-220 | head -15
    echo "(السجل الكامل في /tmp/composer.log)"
    exit 1
fi
tail -3 /tmp/composer.log

if ! grep -q '^APP_KEY=base64' .env; then
    php artisan key:generate --ansi
fi

step "قاعدة البيانات وبنك الأسئلة"
php artisan migrate --seed --force
php artisan storage:link 2>/dev/null || true

step "بناء الواجهة (npm)"
npm install --no-audit --no-fund
npm run build

echo
echo "✅ جاهز. شغّل:  php artisan serve"
echo "   لوحة التحكم: /admin  (admin@example.com / password)"
