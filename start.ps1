param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8088,
    [string]$PhpPath = ''
)

$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
$publicDirectory = Join-Path $projectDirectory 'public'
$setupPath = Join-Path $projectDirectory 'scripts\setup.php'

if (-not $PhpPath) {
    $xamppPhp = Join-Path $env:SystemDrive 'xampp\php\php.exe'
    if (Test-Path -LiteralPath $xamppPhp) {
        $PhpPath = $xamppPhp
    } else {
        $phpCommand = Get-Command php -ErrorAction SilentlyContinue
        if ($phpCommand) { $PhpPath = $phpCommand.Source }
    }
}
if (-not $PhpPath -or -not (Test-Path -LiteralPath $PhpPath)) {
    throw 'PHP not found. Install XAMPP or provide -PhpPath C:\path\php.exe.'
}
if (-not (Test-Path -LiteralPath $setupPath)) {
    throw 'Run this script from the complete Jinal project.'
}

$probeClient = [System.Net.Sockets.TcpClient]::new()
$portOccupied = $false
try {
    $connectionTask = $probeClient.ConnectAsync('127.0.0.1', $Port)
    try { $null = $connectionTask.Wait(350) } catch { }
    $portOccupied = $probeClient.Connected
} finally {
    $probeClient.Dispose()
}
if ($portOccupied) {
    throw "Port $Port is already in use. If Jinal is already running, open http://127.0.0.1:$Port/. Otherwise use -Port 8089."
}

Write-Host 'Preparing MySQL database. Start MySQL in XAMPP first.'
& $PhpPath $setupPath
if ($LASTEXITCODE -ne 0) {
    throw 'Database setup failed. Check MySQL and your local database configuration.'
}

$runtimeDirectory = Join-Path $projectDirectory '.runtime'
$null = New-Item -ItemType Directory -Path $runtimeDirectory -Force
$logSuffix = "$Port-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
$stdoutPath = Join-Path $runtimeDirectory "php-$logSuffix.out.log"
$stderrPath = Join-Path $runtimeDirectory "php-$logSuffix.error.log"
$serverArguments = @('-S', "127.0.0.1:$Port", '-t', ('"' + $publicDirectory + '"'))
$serverProcess = Start-Process -FilePath $PhpPath -ArgumentList $serverArguments -WorkingDirectory $projectDirectory -WindowStyle Hidden -RedirectStandardOutput $stdoutPath -RedirectStandardError $stderrPath -PassThru
$localUrl = "http://127.0.0.1:$Port/"
$ready = $false

for ($attempt = 0; $attempt -lt 30; $attempt++) {
    $serverProcess.Refresh()
    if ($serverProcess.HasExited) { break }
    try {
        $response = Invoke-WebRequest -Uri $localUrl -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -eq 200) { $ready = $true; break }
    } catch { }
    Start-Sleep -Milliseconds 250
}

if (-not $ready) {
    $serverProcess.Refresh()
    if (-not $serverProcess.HasExited) { Stop-Process -Id $serverProcess.Id }
    throw "Jinal did not return HTTP 200. Inspect $stderrPath"
}

Set-Content -LiteralPath (Join-Path $runtimeDirectory "server-$Port.pid") -Value $serverProcess.Id -Encoding ascii
Write-Host "Jinal is ready: $localUrl"
Write-Host "Server PID: $($serverProcess.Id). Stop it with: Stop-Process -Id $($serverProcess.Id)"
Write-Host "Server logs: $stderrPath"
Start-Process $localUrl
