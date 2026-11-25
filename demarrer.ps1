# ============================================================
#  SmartKiné — démarre l'API Symfony et le frontend Angular
#     powershell -ExecutionPolicy Bypass -File .\demarrer.ps1
# ============================================================
$racine = $PSScriptRoot

# API Symfony sur http://127.0.0.1:8000
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$racine\backend'; php -S 127.0.0.1:8000 -t public"

# Mailpit (facultatif) : boîte e-mail de test sur http://localhost:8025
if (Get-Command mailpit -ErrorAction SilentlyContinue) {
    Start-Process powershell -ArgumentList "-NoExit", "-Command", "mailpit"
} elseif (Test-Path "$racine\tools\mailpit.exe") {
    Start-Process powershell -ArgumentList "-NoExit", "-Command", "& '$racine\tools\mailpit.exe'"
} else {
    Write-Host "Mailpit non trouvé : les e-mails ne seront pas visibles (l'application fonctionne quand même)." -ForegroundColor Yellow
}

# Frontend Angular sur http://localhost:4200
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$racine\frontend'; npm start"

# Attend qu'Angular réponde (2 minutes maximum) avant d'ouvrir le navigateur
Write-Host "Démarrage d'Angular en cours..." -ForegroundColor Cyan
for ($i = 0; $i -lt 120; $i++) {
    try { Invoke-WebRequest "http://localhost:4200" -UseBasicParsing -TimeoutSec 2 | Out-Null; break } catch { Start-Sleep -Seconds 1 }
}
Start-Process "http://localhost:4200"
Write-Host "SmartKiné est prêt : http://localhost:4200 (fermez les fenêtres PowerShell ouvertes pour arrêter)." -ForegroundColor Green
