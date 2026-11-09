@echo off
REM ==== مين الأسبق: تجهيز المشروع على ويندوز (مرة وحدة، وآمن لو تكرر) ====
chcp 65001 >nul
setlocal
cd /d "%~dp0.."

where php >nul 2>nul || (echo [X] PHP مش موجود. ثبّت Laravel Herd من herd.laravel.com وافتحه مرة، بعدين جرّب كمان مرة. & pause & exit /b 1)
where composer >nul 2>nul || (echo [X] Composer مش موجود. Herd بيثبّته، افتح Herd مرة وسكّر هالنافذة وافتحها من جديد. & pause & exit /b 1)
where npm >nul 2>nul || (echo [X] Node.js مش موجود. ثبّت نسخة LTS من nodejs.org. & pause & exit /b 1)

php -r "exit(version_compare(PHP_VERSION,'8.2','>=')?0:1);" || (echo [X] بدنا PHP 8.2 أو أحدث. غيّر النسخة من إعدادات Herd. & pause & exit /b 1)
php -m | findstr /i /x "intl" >nul || echo [!] إضافة intl مش مفعّلة. فعّلها من Herd ^> PHP ^> Extensions.
php -m | findstr /i /x "zip" >nul || echo [!] إضافة zip مش مفعّلة. فعّلها من Herd ^> PHP ^> Extensions.

echo.
echo == ملف البيئة ==
if not exist .env copy .env.example .env >nul
powershell -NoProfile -Command "(Get-Content .env) -replace '^SESSION_LIFETIME=120$','SESSION_LIFETIME=1440' -replace '^APP_URL=.*','APP_URL=http://localhost:8000' | Set-Content -Encoding UTF8 .env"
for %%f in (database.sqlite alefya.sqlite akher.sqlite) do if not exist database\%%f type nul > database\%%f

echo.
echo == مكتبات PHP (composer) ==
call composer install --no-interaction --prefer-dist || (echo [X] فشل composer install & pause & exit /b 1)

findstr /b "APP_KEY=base64" .env >nul || php artisan key:generate --ansi

echo.
echo == قاعدة البيانات وبنوك الأسئلة ==
php artisan migrate --seed --force || (echo [X] فشلت قاعدة البيانات & pause & exit /b 1)
php artisan storage:link >nul 2>nul

echo.
echo == بناء الواجهة (npm) ==
call npm install --no-audit --no-fund || (echo [X] فشل npm install & pause & exit /b 1)
call npm run build || (echo [X] فشل npm run build & pause & exit /b 1)

echo.
echo [OK] جاهز! شغّل start.bat
echo      لوحة التحكم: /admin  (admin@example.com / password)
pause
