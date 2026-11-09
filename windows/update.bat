@echo off
REM ==== مين الأسبق: جيب آخر التحديثات من GitHub ====
chcp 65001 >nul
cd /d "%~dp0.."
git pull || (echo [X] فشل git pull & pause & exit /b 1)
call composer install --no-interaction --prefer-dist
php artisan migrate --force
call npm install --no-audit --no-fund
call npm run build
echo [OK] تحدّث. شغّل start.bat
pause
