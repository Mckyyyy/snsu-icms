# test_system.ps1 - Run the ICMS-DPT-RRT automated test suite on Windows (XAMPP)
# Usage (PowerShell, from the project folder):
#   .\test_system.ps1
#   .\test_system.ps1 -BaseUrl "http://localhost/TrackingSystem"

param(
    [string]$BaseUrl = "http://localhost/TrackingSystem",
    [string]$Php = "C:\xampp\php\php.exe"
)

if (-not (Test-Path $Php)) {
    $found = Get-Command php -ErrorAction SilentlyContinue
    if ($found) { $Php = $found.Source } else {
        Write-Host "PHP not found. Pass -Php 'C:\path\to\php.exe'." -ForegroundColor Red
        exit 1
    }
}

Write-Host "Checking $BaseUrl/index.php ..." -NoNewline
try {
    $r = Invoke-WebRequest -Uri "$BaseUrl/index.php" -UseBasicParsing -TimeoutSec 10
    Write-Host " HTTP $($r.StatusCode)" -ForegroundColor Green
} catch {
    Write-Host " not reachable. Start Apache and MySQL in XAMPP first." -ForegroundColor Red
    exit 1
}

& $Php "$PSScriptRoot\test_suite.php" $BaseUrl
exit $LASTEXITCODE
