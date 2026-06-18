# Запуск локальной среды разработки zippaket-optom
# PHP 8.3 + MySQL 8.0 (из Laragon), dev-сервер на http://localhost:8000
$ErrorActionPreference = 'Stop'

$PHP   = "C:\Users\USER\laragon\bin\php\php-8.3.31-nts-Win32-vs16-x64\php.exe"
$MYSQLD = "C:\Users\USER\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe"
$MYSQLDATA = "C:\Users\USER\laragon\bin\mysql\mysql-8.0.30-winx64\data"
$WWW   = Join-Path $PSScriptRoot "www"

# 1. MySQL (если ещё не слушает 3306)
if (-not (Test-NetConnection 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue).TcpTestSucceeded) {
    Write-Host "Запускаю MySQL..." -ForegroundColor Cyan
    Start-Process -FilePath $MYSQLD -ArgumentList "--datadir=`"$MYSQLDATA`"","--port=3306" -WindowStyle Hidden
    for ($i=0; $i -lt 20; $i++) {
        if ((Test-NetConnection 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue).TcpTestSucceeded) { break }
        Start-Sleep -Seconds 1
    }
}
Write-Host "MySQL: 127.0.0.1:3306" -ForegroundColor Green

# 2. PHP dev-сервер с router (ЧПУ /product/<id>, каталог и т.п.)
$ROUTER = Join-Path $PSScriptRoot "router.php"
Write-Host "Сайт: http://localhost:8000  (Ctrl+C для остановки)" -ForegroundColor Green
& $PHP -S localhost:8000 -t $WWW $ROUTER
