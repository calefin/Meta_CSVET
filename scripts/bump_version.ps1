# ============================================================
# bump_version.ps1 — Incrementa versão do Odù Journey
# Uso: .\scripts\bump_version.ps1 -Type patch
#      .\scripts\bump_version.ps1 -Type minor
#      .\scripts\bump_version.ps1 -Type major
# ============================================================
param(
    [ValidateSet('patch','minor','major')]
    [string]$Type = 'patch'
)

$pubspec = "pubspec.yaml"
$content = Get-Content $pubspec -Raw

# Extrai versão atual
if ($content -notmatch 'version:\s+(\d+)\.(\d+)\.(\d+)\+(\d+)') {
    Write-Error "Versão não encontrada em $pubspec"
    exit 1
}

$major = [int]$Matches[1]
$minor = [int]$Matches[2]
$patch = [int]$Matches[3]
$build = [int]$Matches[4]

$oldVersion = "$major.$minor.$patch+$build"

switch ($Type) {
    'patch' { $patch++; $build++ }
    'minor' { $minor++; $patch = 0; $build++ }
    'major' { $major++; $minor = 0; $patch = 0; $build++ }
}

$newVersion = "$major.$minor.$patch+$build"
$content = $content -replace "version:\s+$([regex]::Escape($oldVersion))", "version: $newVersion"
Set-Content $pubspec $content -Encoding utf8

# Atualiza app_info.dart
$appInfo = "lib/config/app_info.dart"
if (Test-Path $appInfo) {
    $info = Get-Content $appInfo -Raw
    $semver = "$major.$minor.$patch"
    $info = $info -replace "const String CURRENT_VERSION = '[^']*'", "const String CURRENT_VERSION = '$semver'"
    Set-Content $appInfo $info -Encoding utf8
    Write-Host "app_info.dart atualizado para $semver"
}

Write-Host "Versão: $oldVersion → $newVersion" -ForegroundColor Green

# Pergunta se quer criar git tag
$confirm = Read-Host "Criar git tag v$newVersion? (s/n)"
if ($confirm -eq 's') {
    git add pubspec.yaml $appInfo
    git commit -m "chore: bump version to $newVersion"
    git tag "v$newVersion"
    Write-Host "Tag v$newVersion criada. Use 'git push --tags' para publicar." -ForegroundColor Cyan
}
