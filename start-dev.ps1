<#
.SYNOPSIS
Запуск локальной среды разработки zippaket-optom: MySQL + встроенный PHP-сервер.

.DESCRIPTION
PHP и MySQL ищутся автоматически в стандартных местах установки Laragon
(C:\laragon, %USERPROFILE%\laragon, C:\tools\laragon) и в PATH — версии в путях
не захардкожены, поэтому скрипт переживает обновление Laragon и работает
на другой машине.

Сайт поднимается через router.php (эмулирует ЧПУ-правила www/.htaccess) —
это единственный канонический способ запуска dev-сервера в проекте.

.PARAMETER Port
Порт dev-сервера. По умолчанию 8000.

.PARAMETER MysqlPort
Порт MySQL. По умолчанию 3306. Если порт уже слушают — MySQL не запускается повторно.

.PARAMETER SkipMysql
Не трогать MySQL вообще (полезно, когда он поднят Laragon'ом или как служба).

.EXAMPLE
.\start-dev.ps1
.EXAMPLE
.\start-dev.ps1 -Port 8077 -SkipMysql
#>
param(
    [int]$Port = 8000,
    [int]$MysqlPort = 3306,
    [switch]$SkipMysql
)

$ErrorActionPreference = 'Stop'

# --- Поиск бинарников -------------------------------------------------------

$LaragonRoots = @(
    'C:\laragon',
    (Join-Path $env:USERPROFILE 'laragon'),
    'C:\tools\laragon'
) | Where-Object { $_ -and (Test-Path $_) }

function Find-Binary {
    param(
        [Parameter(Mandatory)][string]$Name,   # php.exe / mysqld.exe
        [Parameter(Mandatory)][string]$SubDir  # bin\php / bin\mysql
    )

    foreach ($root in $LaragonRoots) {
        $base = Join-Path $root $SubDir
        if (-not (Test-Path $base)) { continue }
        # берём самую свежую версию (каталоги вида php-8.3.30-..., mysql-8.4.3-...)
        $hit = Get-ChildItem -Path $base -Filter $Name -Recurse -File -ErrorAction SilentlyContinue |
               Sort-Object FullName -Descending |
               Select-Object -First 1
        if ($hit) { return $hit.FullName }
    }

    $inPath = Get-Command $Name -ErrorAction SilentlyContinue
    if ($inPath) { return $inPath.Source }

    return $null
}

$PHP = Find-Binary -Name 'php.exe' -SubDir 'bin\php'
if (-not $PHP) {
    Write-Host "Не найден php.exe." -ForegroundColor Red
    Write-Host "Искал в: $($LaragonRoots -join ', ') (подкаталог bin\php) и в PATH." -ForegroundColor Yellow
    Write-Host "Установи Laragon (https://laragon.org) или добавь php.exe в PATH." -ForegroundColor Yellow
    exit 1
}
Write-Host "PHP:   $PHP" -ForegroundColor DarkGray

# --- MySQL ------------------------------------------------------------------

function Test-Port {
    param([int]$P)
    try {
        $c = New-Object System.Net.Sockets.TcpClient
        $ok = $c.ConnectAsync('127.0.0.1', $P).Wait(700)
        $c.Close()
        return $ok
    } catch { return $false }
}

if ($SkipMysql) {
    Write-Host "MySQL: пропущен (-SkipMysql)" -ForegroundColor DarkGray
}
elseif (Test-Port $MysqlPort) {
    # Уже поднят (Laragon, служба, другой процесс) — ничего не делаем.
    Write-Host "MySQL: уже слушает 127.0.0.1:$MysqlPort" -ForegroundColor Green
}
else {
    $MYSQLD = Find-Binary -Name 'mysqld.exe' -SubDir 'bin\mysql'
    if (-not $MYSQLD) {
        Write-Host "MySQL не запущен, и mysqld.exe не найден." -ForegroundColor Red
        Write-Host "Искал в: $($LaragonRoots -join ', ') (подкаталог bin\mysql) и в PATH." -ForegroundColor Yellow
        Write-Host "Запусти MySQL вручную (Laragon -> Start All) или укажи другой порт: -MysqlPort" -ForegroundColor Yellow
        exit 1
    }

    # data-каталог лежит рядом с bin: <...>\mysql-X.Y.Z-winx64\data
    $MYSQLDATA = Join-Path (Split-Path (Split-Path $MYSQLD -Parent) -Parent) 'data'
    if (-not (Test-Path $MYSQLDATA)) {
        Write-Host "Не найден каталог данных MySQL: $MYSQLDATA" -ForegroundColor Red
        Write-Host "Запусти MySQL через Laragon — он инициализирует data-каталог сам." -ForegroundColor Yellow
        exit 1
    }

    Write-Host "Запускаю MySQL ($MYSQLD)..." -ForegroundColor Cyan
    Start-Process -FilePath $MYSQLD `
                  -ArgumentList "--datadir=`"$MYSQLDATA`"", "--port=$MysqlPort" `
                  -WindowStyle Hidden

    $up = $false
    for ($i = 0; $i -lt 20; $i++) {
        if (Test-Port $MysqlPort) { $up = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $up) {
        Write-Host "MySQL не поднялся за 20 с на порту $MysqlPort. Смотри лог в $MYSQLDATA\*.err" -ForegroundColor Red
        exit 1
    }
    Write-Host "MySQL: 127.0.0.1:$MysqlPort" -ForegroundColor Green
}

# --- PHP dev-сервер ---------------------------------------------------------

$WWW    = Join-Path $PSScriptRoot 'www'
$ROUTER = Join-Path $PSScriptRoot 'router.php'

if (-not (Test-Path (Join-Path $WWW 'includes\config.php'))) {
    Write-Host "ВНИМАНИЕ: нет www/includes/config.php — сайт упадёт." -ForegroundColor Yellow
    Write-Host "Скопируй www/includes/config.example.php и заполни (см. SETUP.md)." -ForegroundColor Yellow
}

if (Test-Port $Port) {
    Write-Host "Порт $Port уже занят. Запусти с другим: .\start-dev.ps1 -Port 8077" -ForegroundColor Red
    exit 1
}

Write-Host "Сайт:    http://127.0.0.1:$Port/" -ForegroundColor Green
Write-Host "Админка: http://127.0.0.1:$Port/admin/" -ForegroundColor Green
Write-Host "(Ctrl+C — остановить)" -ForegroundColor DarkGray

# Именно 127.0.0.1, а не localhost: на Windows localhost резолвится в ::1,
# и php -S садится только на IPv6 — часть инструментов туда не достучится.
& $PHP -S "127.0.0.1:$Port" -t $WWW $ROUTER
