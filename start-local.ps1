$ErrorActionPreference = 'Stop'
$phpExe = 'C:\wamp64\bin\php\php8.3.6\php.exe'
$sitePort = 8083
$listener = Get-NetTCPConnection -LocalPort $sitePort -State Listen -ErrorAction SilentlyContinue
if ($listener) {
    Write-Output "Port $sitePort already has a listener. Open http://localhost:$sitePort/ or check the process before restarting."
    return
}
Start-Process -FilePath $phpExe -ArgumentList '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0', '-S', "127.0.0.1:$sitePort", 'server.php' -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot 'storage\logs\local-server.log') -RedirectStandardError (Join-Path $PSScriptRoot 'storage\logs\local-server-error.log')
Write-Output "Culbertson Laravel: http://localhost:$sitePort/"
