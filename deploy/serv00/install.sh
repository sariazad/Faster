#!/bin/sh
# مين الأسبق — one-time install on a Serv00 free account (FreeBSD, no root).
#   fetch -o - https://raw.githubusercontent.com/sariazad/Faster/main/deploy/serv00/install.sh | sh
# Safe to run again: it skips what is already done.
set -eu

DOMAIN="${DOMAIN:-$(whoami).serv00.net}"
BASE="$HOME/domains/$DOMAIN"
APP="$BASE/app"
REPO="${REPO:-https://github.com/sariazad/Faster.git}"

say() { printf '\n\033[1;33m▶ %s\033[0m\n' "$1"; }

[ -d "$BASE" ] || { echo "ما لقيت $BASE — تأكد إن الموقع $DOMAIN موجود بلوحة DevilWEB (نوع PHP)."; exit 1; }

say "سحب المشروع إلى $APP"
if [ -d "$APP/.git" ]; then
    git -C "$APP" pull --ff-only
else
    git clone --depth 50 "$REPO" "$APP"
fi
cd "$APP"

say "مكتبات PHP (دقيقتين تقريباً)"
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress

say "ملف الإعدادات .env"
if [ ! -f .env ]; then
    cp .env.example .env
    php -r '
        $f = ".env"; $s = file_get_contents($f);
        $set = [
            "APP_ENV" => "production", "APP_DEBUG" => "false", "APP_URL" => "https://'"$DOMAIN"'",
            "LOG_LEVEL" => "warning", "PHP_CLI_SERVER_WORKERS" => "1",
        ];
        foreach ($set as $k => $v) {
            $s = preg_replace("/^#?\\s*$k=.*$/m", "$k=$v", $s, 1, $n);
            if (! $n) { $s .= "\n$k=$v"; }
        }
        file_put_contents($f, $s);
    '
    php artisan key:generate --force
fi

say "قواعد البيانات وبنوك الأسئلة"
touch database/database.sqlite database/alefya.sqlite database/akher.sqlite database/qaddaha.sqlite
php artisan migrate --force
USERS=$(php -r 'try { echo (new PDO("sqlite:database/database.sqlite"))->query("select count(*) from users")->fetchColumn(); } catch (Throwable $e) { echo 0; }')
if [ "${USERS:-0}" = "0" ]; then
    echo "حساب مشرف لوحة الأدمن:"
    printf "الإيميل: "; read -r ADMIN_EMAIL </dev/tty
    while :; do
        stty -echo </dev/tty; printf "كلمة السر (8 خانات أو أكثر): "; read -r ADMIN_PASSWORD </dev/tty; stty echo </dev/tty; echo
        [ "${#ADMIN_PASSWORD}" -ge 8 ] && break
        echo "قصيرة، جرّب مرة ثانية."
    done
    ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASSWORD="$ADMIN_PASSWORD" php artisan db:seed --force
else
    php artisan db:seed --class=QuestionBankSeeder --force
    php artisan db:seed --class=AlefYaSeeder --force
fi

say "الواجهة (Vite) — إذا ما كفت الذاكرة، أول نشر تلقائي من GitHub بيرفعها"
if [ ! -f public/build/manifest.json ]; then
    if NODE_OPTIONS=--max-old-space-size=400 npm ci --no-audit --no-fund --silent && NODE_OPTIONS=--max-old-space-size=400 npm run build --silent; then
        rm -rf node_modules   # 3GB quota: the build is all we need
    else
        echo "⚠ ما زبط البناء هون. شغّل «Run workflow» على deploy-serv00 من GitHub بعد ما تحط المفتاح."
    fi
fi

say "ربط الموقع بالمشروع"
if [ ! -L "$BASE/public_html" ]; then
    [ -e "$BASE/public_html" ] && mv "$BASE/public_html" "$BASE/public_html.orig"
    ln -s "$APP/public" "$BASE/public_html"
fi
php artisan storage:link --quiet 2>/dev/null || true
php artisan optimize --quiet
php artisan filament:optimize --quiet 2>/dev/null || true
devil www restart "$DOMAIN" >/dev/null 2>&1 || true

say "تم ✔  https://$DOMAIN"
echo "الأدمن: https://$DOMAIN/admin"
