@echo off
REM ==== مين الأسبق: تشغيل اللعبة على هالجهاز، والجوالات بتدخل من نفس الواي فاي ====
chcp 65001 >nul
setlocal
cd /d "%~dp0.."

for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notmatch 'vEthernet|Loopback|WSL|VirtualBox|VMware' } | Sort-Object { $_.PrefixOrigin -ne 'Dhcp' } | Select-Object -First 1).IPAddress"`) do set IP=%%i
if "%IP%"=="" set IP=localhost

echo.
echo ==================================================
echo   على هالجهاز:      http://localhost:8000
echo   من الجوالات:      http://%IP%:8000
echo   (لازم الجوالات على نفس شبكة الواي فاي)
echo   لوحة التحكم:      http://localhost:8000/admin
echo ==================================================
echo   أول مرة: إذا سألك ويندوز عن الجدار الناري اختار "Allow" للشبكات الخاصة.
echo   لإيقاف اللعبة سكّر هالنافذة.
echo.

start "" http://localhost:8000
php artisan serve --host=0.0.0.0 --port=8000
pause
