# ============================================================
#  SmartKiné — installation automatique (Windows + XAMPP)
#  Lancer depuis le dossier du projet, dans PowerShell :
#     powershell -ExecutionPolicy Bypass -File .\installer.ps1
#  Prérequis : XAMPP démarré (MySQL vert), PHP, Composer, Node.js
# ============================================================
$ErrorActionPreference = "Stop"
$racine = $PSScriptRoot

function Etape($texte) { Write-Host "`n==> $texte" -ForegroundColor Cyan }
function Ok($texte)    { Write-Host "    OK  $texte" -ForegroundColor Green }
function Stop-Erreur($texte) { Write-Host "    ERREUR : $texte" -ForegroundColor Red; exit 1 }

# ---------- 1. Vérification des outils ----------
Etape "Vérification des outils"
foreach ($outil in @("php", "composer", "node", "npm")) {
    if (-not (Get-Command $outil -ErrorAction SilentlyContinue)) { Stop-Erreur "$outil est introuvable dans le PATH." }
}
$phpVersion = (php -r "echo PHP_VERSION;")
Ok "PHP $phpVersion"
if ([version]($phpVersion -replace '[^0-9.].*$','') -lt [version]"8.2") { Stop-Erreur "PHP 8.2 minimum est requis (version actuelle : $phpVersion)." }
# XAMPP sous Windows : OpenSSL a besoin de son fichier de configuration (sinon les clés JWT ne se génèrent pas)
if (-not $env:OPENSSL_CONF) {
    $cnf = @("C:\xampp\php\extras\ssl\openssl.cnf", "C:\xampp\apache\conf\openssl.cnf") | Where-Object { Test-Path $_ } | Select-Object -First 1
    if ($cnf) { $env:OPENSSL_CONF = $cnf }
}
$extensions = php -m
foreach ($ext in @("sodium", "pdo_mysql", "intl")) {
    if ($extensions -notcontains $ext) { Write-Host "    ATTENTION : extension PHP '$ext' non activée (php.ini de XAMPP : retirer le ; devant extension=$ext)" -ForegroundColor Yellow }
}

# ---------- 2. Backend Symfony ----------
Set-Location "$racine\backend"

Etape "Fichier .env"
if (-not (Test-Path ".env")) {
    $secret = -join ((1..32) | ForEach-Object { '{0:x2}' -f (Get-Random -Maximum 256) })
    (Get-Content ".env.example" -Raw).Replace("remplacez_par_une_chaine_aleatoire", $secret) | Set-Content ".env" -Encoding ascii
    Ok ".env créé à partir de .env.example"
} else {
    # Ajoute les nouvelles variables qui manquent dans un .env existant
    $existant = Get-Content ".env" -Raw
    foreach ($ligne in Get-Content ".env.example") {
        if ($ligne -match '^([A-Z_]+)=') {
            $cle = $Matches[1]
            if ($existant -notmatch "(?m)^$cle=") { Add-Content ".env" $ligne; Ok "ajout de $cle dans .env" }
        }
    }
}

Etape "Dépendances PHP (Composer)"
composer install --no-interaction
if ($LASTEXITCODE -ne 0) { Stop-Erreur "composer install a échoué." }
Ok "vendor installé"

Etape "Clés JWT"
php bin/console lexik:jwt:generate-keypair --skip-if-exists
Ok "config/jwt/private.pem et public.pem"

Etape "Base de données MySQL"
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
if ($LASTEXITCODE -ne 0) { Stop-Erreur "la migration a échoué (MySQL est-il démarré dans XAMPP ?)." }
Ok "tables créées"

$demo = Read-Host "Charger les données de démonstration ? (o/n)"
if ($demo -eq "o") { php bin/console app:demo }

# ---------- 3. Frontend Angular ----------
Etape "Dépendances Angular (npm)"
Set-Location "$racine\frontend"
npm install
if ($LASTEXITCODE -ne 0) { Stop-Erreur "npm install a échoué." }
Ok "node_modules installé"

Set-Location $racine
Write-Host "`nInstallation terminée. Lancez maintenant : powershell -ExecutionPolicy Bypass -File .\demarrer.ps1" -ForegroundColor Green
