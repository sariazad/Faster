#!/usr/bin/env bash
# One-time setup for GitHub Codespaces / Dev Containers.
set -e

# Filament needs the intl extension; the base PHP image doesn't ship it.
if ! php -m | grep -qi '^intl$'; then
    sudo apt-get update -y
    sudo apt-get install -y libicu-dev
    sudo docker-php-ext-install intl
fi

composer install --no-interaction

if [ ! -f .env ]; then
    cp .env.example .env
fi

# Point APP_URL at the Codespace's forwarded port so links and assets work.
if [ -n "$CODESPACE_NAME" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
    sed -i "s#^APP_URL=.*#APP_URL=${url}#" .env
fi

if ! grep -q '^APP_KEY=base64' .env; then
    php artisan key:generate --ansi
fi

touch database/database.sqlite
php artisan migrate --seed --force
php artisan storage:link || true

npm install
npm run build

echo
echo "✅ جاهز. شغّل:  php artisan serve"
echo "   الدخول للوحة التحكم: /admin  (admin@example.com / password)"
