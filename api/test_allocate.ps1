# Test allocate API với PowerShell

$body = @{
    mact = "2013-04-P07-16655"
    mavt = 500120
    ngnhan = "2025-12-30"
} | ConvertTo-Json

Write-Host "Testing allocate API..." -ForegroundColor Yellow
Write-Host "URL: http://diavatly.com/BHLD/api/allocate.php" -ForegroundColor Cyan
Write-Host "Body: $body" -ForegroundColor Cyan
Write-Host ""

try {
    $response = Invoke-RestMethod -Uri "http://diavatly.com/BHLD/api/allocate.php" `
                                   -Method Post `
                                   -ContentType "application/json" `
                                   -Body $body `
                                   -TimeoutSec 10
    
    Write-Host "SUCCESS!" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 5
} catch {
    Write-Host "ERROR!" -ForegroundColor Red
    Write-Host $_.Exception.Message
    Write-Host ""
    Write-Host "Response:" -ForegroundColor Yellow
    $_.Exception.Response
}
