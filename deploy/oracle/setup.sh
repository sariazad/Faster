#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# مين الأسبق — one-shot install on an Oracle Cloud "Always Free" Ubuntu server.
#
#   curl -fsSL https://raw.githubusercontent.com/sariazad/Faster/main/deploy/oracle/setup.sh -o setup.sh
#   sudo bash setup.sh your-name.duckdns.org you@example.com
#
# Installs PHP 8.3 + Composer + Node 22 + Caddy (automatic HTTPS), opens ports 80/443,
# clones the game, builds it, creates both SQLite databases, loads the question banks,
# creates the admin account, and sets up a daily backup. Safe to run again.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

DOMAIN="${1:-}"
EMAIL="${2:-}"
REPO="${REPO:-https://github.com/sariazad/Faster.git}"
APP_DIR="${APP_DIR:-/var/www/faster}"
APP_USER="${SUDO_USER:-ubuntu}"
PHP_V="8.3"

say()  { printf '\n\033[1;33m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m✘ %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "شغّله بـ sudo:  sudo bash setup.sh your-name.duckdns.org you@example.com"
[[ -n "$DOMAIN" ]] || die "اكتب الدومين:  sudo bash setup.sh your-name.duckdns.org you@example.com"
[[ -n "$EMAIL" ]] || die "اكتب إيميلك (لشهادة HTTPS):  sudo bash setup.sh $DOMAIN you@example.com"

export DEBIAN_FRONTEND=noninteractive

# ── 1. Packages ──────────────────────────────────────────────────────────────
say "تثبيت PHP ${PHP_V} والأدوات (أول مرة بتاخد كم دقيقة)"
apt-get update -qq
apt-get install -y -qq software-properties-common ca-certificates curl gnupg git unzip sqlite3 \
    debian-keyring debian-archive-keyring apt-transport-https iptables-persistent >/dev/null

if ! apt-cache show "php${PHP_V}-fpm" >/dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php >/dev/null
    apt-get update -qq
fi
apt-get install -y -qq "php${PHP_V}-fpm" "php${PHP_V}-cli" "php${PHP_V}-sqlite3" "php${PHP_V}-mbstring" \
    "php${PHP_V}-xml" "php${PHP_V}-curl" "php${PHP_V}-zip" "php${PHP_V}-intl" "php${PHP_V}-bcmath" \
    "php${PHP_V}-gd" "php${PHP_V}-opcache" >/dev/null
ok "PHP $(php -r 'echo PHP_VERSION;')"

if ! command -v composer >/dev/null; then
    say "تثبيت Composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    ACTUAL="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED" == "$ACTUAL" ]] || die "Composer installer checksum mismatch"
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
ok "Composer $(composer --version 2>/dev/null | awk '{print $3}')"

if ! command -v node >/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d v)" -lt 20 ]]; then
    say "تثبيت Node.js 22"
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >/dev/null
    apt-get install -y -qq nodejs >/dev/null
fi
ok "Node $(node -v)"

if ! command -v caddy >/dev/null; then
    say "تثبيت Caddy (HTTPS تلقائي)"
    curl -fsSL 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -fsSL 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -qq
    apt-get install -y -qq caddy >/dev/null
fi
ok "Caddy $(caddy version | awk '{print $1}')"

# ── 2. PHP settings (big backup uploads, opcache) ───────────────────────────
cat > "/etc/php/${PHP_V}/fpm/conf.d/99-faster.ini" <<INI
upload_max_filesize = 260M
post_max_size = 260M
memory_limit = 512M
max_execution_time = 300
opcache.enable = 1
opcache.memory_consumption = 192
opcache.validate_timestamps = 1
INI
cp "/etc/php/${PHP_V}/fpm/conf.d/99-faster.ini" "/etc/php/${PHP_V}/cli/conf.d/99-faster.ini"

# ── 3. Firewall: Oracle's Ubuntu image blocks everything except SSH ──────────
say "فتح المنفذين 80 و443"
for PORT in 80 443; do
    iptables -C INPUT -p tcp --dport "$PORT" -m state --state NEW -j ACCEPT 2>/dev/null \
        || iptables -I INPUT 6 -p tcp --dport "$PORT" -m state --state NEW -j ACCEPT
done
netfilter-persistent save >/dev/null
ok "المنافذ مفتوحة على السيرفر (لا تنسى قواعد Security List بلوحة Oracle)"

# ── 4. Code ──────────────────────────────────────────────────────────────────
say "تنزيل اللعبة من GitHub"
mkdir -p "$(dirname "$APP_DIR")"
if [[ -d "$APP_DIR/.git" ]]; then
    git -C "$APP_DIR" pull --ff-only -q
else
    git clone -q "$REPO" "$APP_DIR"
fi
chown -R "$APP_USER":www-data "$APP_DIR"
git config --system --add safe.directory "$APP_DIR"

as_app() { sudo -u "$APP_USER" -H bash -c "cd '$APP_DIR' && $*"; }

# ── 5. .env ──────────────────────────────────────────────────────────────────
if [[ ! -f "$APP_DIR/.env" ]]; then
    say "تجهيز ملف الإعدادات .env"
    as_app "cp .env.example .env"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" \
        -e "s|^LOG_LEVEL=.*|LOG_LEVEL=warning|" \
        "$APP_DIR/.env"
    NEW_ENV=1
else
    sed -i -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "$APP_DIR/.env"
    NEW_ENV=0
fi

# ── 6. Build ─────────────────────────────────────────────────────────────────
say "تثبيت مكتبات PHP"
as_app "composer install --no-dev --optimize-autoloader --no-interaction --quiet"
[[ $NEW_ENV -eq 1 ]] && as_app "php artisan key:generate --force --quiet"

say "بناء الواجهة"
as_app "npm ci --no-audit --no-fund --silent && npm run build --silent" >/dev/null

say "قواعد البيانات"
as_app "touch database/database.sqlite database/alefya.sqlite database/akher.sqlite"
as_app "php artisan migrate --force"

# ── 7. Admin + question banks ────────────────────────────────────────────────
if [[ $NEW_ENV -eq 1 ]]; then
    echo
    read -rp "إيميل المشرف (لدخول لوحة التحكم): " ADMIN_EMAIL </dev/tty
    while true; do
        read -rsp "كلمة سر المشرف (8 خانات أو أكثر): " ADMIN_PASSWORD </dev/tty; echo
        [[ ${#ADMIN_PASSWORD} -ge 8 ]] && break
        echo "قصيرة، جرّب مرة ثانية."
    done
    say "تعبئة بنوك الأسئلة (دقيقة تقريباً)"
    sudo -u "$APP_USER" -H env ADMIN_EMAIL="$ADMIN_EMAIL" ADMIN_PASSWORD="$ADMIN_PASSWORD" \
        bash -c "cd '$APP_DIR' && php artisan db:seed --force"
else
    as_app "php artisan db:seed --class=QuestionBankSeeder --force"
    as_app "php artisan db:seed --class=AlefYaSeeder --force"
fi

as_app "php artisan storage:link --quiet 2>/dev/null || true"
as_app "php artisan optimize --quiet && php artisan filament:optimize --quiet 2>/dev/null || true"

# Web server (www-data) writes to storage, cache and the SQLite files (and their folder, for journals).
chown -R "$APP_USER":www-data "$APP_DIR"
chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database" -type d -exec chmod g+s {} +

# ── 8. Caddy site ────────────────────────────────────────────────────────────
say "إعداد Caddy و HTTPS لـ ${DOMAIN}"
cat > /etc/caddy/Caddyfile <<CADDY
{
    email ${EMAIL}
}

${DOMAIN} {
    root * ${APP_DIR}/public
    encode zstd gzip
    request_body {
        max_size 260MB
    }
    php_fastcgi unix//run/php/php${PHP_V}-fpm.sock
    file_server
    header {
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
        -Server
    }
    @static path /build/* /favicon.ico
    header @static Cache-Control "public, max-age=31536000, immutable"
}
CADDY
usermod -aG www-data caddy
systemctl enable --now "php${PHP_V}-fpm" caddy >/dev/null
systemctl reload "php${PHP_V}-fpm"
systemctl restart caddy

# ── 9. Scheduler + daily backup ──────────────────────────────────────────────
say "النسخ الاحتياطي اليومي"
install -m 755 "$APP_DIR/deploy/oracle/backup.sh" /usr/local/bin/faster-backup
cat > /etc/cron.d/faster <<CRON
# مين الأسبق
* * * * * ${APP_USER} cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1
30 3 * * * root APP_DIR=${APP_DIR} /usr/local/bin/faster-backup >> /var/log/faster-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/faster

echo
ok "خلصنا! 🎉"
echo "   اللعبة:        https://${DOMAIN}"
echo "   آخر القائمة:   https://${DOMAIN}/akher"
echo "   ألف ياء:       https://${DOMAIN}/alefya"
echo "   لوحة التحكم:   https://${DOMAIN}/admin"
echo "   للتحديث لاحقاً: sudo bash ${APP_DIR}/deploy/oracle/deploy.sh"
echo
echo "   إذا ما فتح الموقع: تأكد إن الدومين بيأشر على IP السيرفر، وإن المنفذين 80 و443 مفتوحين بـ Security List."
