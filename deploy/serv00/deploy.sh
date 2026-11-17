#!/bin/sh
# مين الأسبق — update a Serv00 install (run by .github/workflows/deploy-serv00.yml).
# The Vite build arrives from GitHub Actions in public/build.new (512MB RAM is tight for building here).
set -eu

DOMAIN="${DOMAIN:-$(whoami).serv00.net}"
APP="$HOME/domains/$DOMAIN/app"
cd "$APP"

php artisan down --retry=15 --quiet || true
trap 'php artisan up --quiet' EXIT

git pull --ff-only
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet

if [ -f public/build.new/manifest.json ]; then
    rm -rf public/build.old
    [ -d public/build ] && mv public/build public/build.old
    mv public/build.new public/build
    rm -rf public/build.old
fi

php artisan migrate --force
php artisan db:seed --class=QuestionBankSeeder --force
php artisan db:seed --class=AlefYaSeeder --force
php artisan optimize:clear --quiet
php artisan optimize --quiet
php artisan filament:optimize --quiet 2>/dev/null || true
devil www restart "$DOMAIN" >/dev/null 2>&1 || true

echo "✔ Serv00: $(git log -1 --format='%h %s')"
