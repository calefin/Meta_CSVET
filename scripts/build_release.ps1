# ============================================================
# build_release.ps1 — Gera AAB assinado para a Play Store
# Uso: .\scripts\build_release.ps1
# ============================================================

# Lê versão atual
$pubspec = Get-Content "pubspec.yaml" -Raw
if ($pubspec -match 'version:\s+(\S+)') {
    $version = $Matches[1]
} else {
    $version = "unknown"
}

Write-Host "Construindo Odù Journey v$version..." -ForegroundColor Cyan

# Garante dependências atualizadas
flutter pub get
if (-not $?) { Write-Error "flutter pub get falhou"; exit 1 }

# Analisa código
Write-Host "Analisando código..."
$errors = flutter analyze lib/ 2>&1 | Select-String "error -"
if ($errors) {
    Write-Error "Erros encontrados:`n$errors"
    exit 1
}

# Build AAB
Write-Host "Gerando AAB..."
flutter build appbundle --release
if (-not $?) { Write-Error "Build falhou"; exit 1 }

$aab = "build\app\outputs\bundle\release\app-release.aab"
if (Test-Path $aab) {
    $size = (Get-Item $aab).Length / 1MB
    Write-Host "Build concluído: $aab ($([math]::Round($size,1)) MB)" -ForegroundColor Green
    Write-Host "Próximo passo: faça upload no Google Play Console." -ForegroundColor Yellow
} else {
    Write-Warning "AAB não encontrado em $aab"
}
