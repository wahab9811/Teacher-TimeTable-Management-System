# Wait for git to be available
Write-Host "Waiting for Git installation to finish..."
while (-not (Test-Path "C:\Program Files\Git\cmd\git.exe")) {
    Start-Sleep -Seconds 5
}
Write-Host "Git found. Running commit generation..."
$env:Path += ";C:\Program Files\Git\cmd"

powershell -ExecutionPolicy Bypass -File generate_commits.ps1
