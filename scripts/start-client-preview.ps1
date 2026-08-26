$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$backendPath = Join-Path $projectRoot 'backend'
$frontendPath = Join-Path $projectRoot 'frontend'
$toolsPath = Join-Path $projectRoot '.tools'
$cloudflaredPath = Join-Path $toolsPath 'cloudflared.exe'
$logsPath = Join-Path $projectRoot '.preview-logs'

New-Item -ItemType Directory -Force -Path $toolsPath, $logsPath | Out-Null

if (-not (Test-Path -LiteralPath $cloudflaredPath)) {
    Write-Host 'Downloading Cloudflare Tunnel...'
    Invoke-WebRequest `
        -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' `
        -OutFile $cloudflaredPath
}

Write-Host 'Starting the Laravel services...'
Push-Location $backendPath
try {
    docker compose up -d
    docker compose exec laravel.test php artisan optimize:clear
} finally {
    Pop-Location
}

$frontendLog = Join-Path $logsPath 'frontend.log'
$frontendErrorLog = Join-Path $logsPath 'frontend-error.log'
Write-Host 'Starting the frontend on port 3001...'
Start-Process `
    -FilePath 'npm.cmd' `
    -ArgumentList @('run', 'dev', '--', '-H', '0.0.0.0', '-p', '3001') `
    -WorkingDirectory $frontendPath `
    -WindowStyle Hidden `
    -RedirectStandardOutput $frontendLog `
    -RedirectStandardError $frontendErrorLog

$ready = $false
for ($attempt = 0; $attempt -lt 60; $attempt++) {
    try {
        $response = Invoke-WebRequest -Uri 'http://localhost:3001/login' -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -eq 200) {
            $ready = $true
            break
        }
    } catch {
        Start-Sleep -Seconds 1
    }
}

if (-not $ready) {
    throw "The frontend did not start. Check $frontendErrorLog"
}

$tunnelLog = Join-Path $logsPath 'tunnel.log'
$tunnelErrorLog = Join-Path $logsPath 'tunnel-error.log'
Write-Host 'Creating the secure public preview link...'
Start-Process `
    -FilePath $cloudflaredPath `
    -ArgumentList @('tunnel', '--url', 'http://localhost:3001', '--no-autoupdate') `
    -WorkingDirectory $projectRoot `
    -WindowStyle Hidden `
    -RedirectStandardOutput $tunnelLog `
    -RedirectStandardError $tunnelErrorLog

$publicUrl = $null
for ($attempt = 0; $attempt -lt 45; $attempt++) {
    Start-Sleep -Seconds 1
    $contents = (Get-Content -LiteralPath $tunnelLog, $tunnelErrorLog -Raw -ErrorAction SilentlyContinue) -join "`n"
    $match = [regex]::Match($contents, 'https://[a-z0-9-]+\.trycloudflare\.com')
    if ($match.Success) {
        $publicUrl = $match.Value
        break
    }
}

if (-not $publicUrl) {
    throw "The tunnel did not return a URL. Check $tunnelErrorLog"
}

Write-Host ''
Write-Host 'CLIENT PREVIEW IS ONLINE' -ForegroundColor Green
Write-Host $publicUrl -ForegroundColor Cyan
Write-Host ''
Write-Host 'Keep this computer, Docker Desktop, and your internet connection running.'
Write-Host 'The URL changes whenever the tunnel is restarted.'
