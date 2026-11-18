# Prepara e sobe o ambiente local do OMP e executa a instalação na primeira vez.
# Uso (na raiz do repositório):  powershell -ExecutionPolicy Bypass -File scripts\setup.ps1

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root
$utf8 = New-Object System.Text.UTF8Encoding($false)

function New-Secret([int]$len = 20) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'.ToCharArray()
    -join (1..$len | ForEach-Object { $chars | Get-Random })
}

# 1. .env com senhas aleatórias, se ainda não existir
if (-not (Test-Path .env)) {
    $env_ = (Get-Content .env.example -Raw) `
        -replace 'DB_PASSWORD=.*', "DB_PASSWORD=$(New-Secret)" `
        -replace 'DB_ROOT_PASSWORD=.*', "DB_ROOT_PASSWORD=$(New-Secret)" `
        -replace 'OMP_ADMIN_PASSWORD=.*', "OMP_ADMIN_PASSWORD=$(New-Secret 16)"
    [IO.File]::WriteAllText("$root\.env", $env_, $utf8)
    Write-Host '.env criado com senhas aleatórias.'
}
$cfg = @{}
Get-Content .env | Where-Object { $_ -match '^\s*([A-Z_]+)=(.*)$' } | ForEach-Object { $cfg[$Matches[1]] = $Matches[2].Trim() }
$port = if ($cfg.HTTP_PORT) { $cfg.HTTP_PORT } else { '8081' }
$baseUrl = "http://localhost:$port"

# 2. Código do OMP (mesmo script usado no servidor) e config.inc.php local
if (-not (Test-Path index.php)) {
    bash scripts/install-omp.sh
    if ($LASTEXITCODE -ne 0) { throw 'install-omp.sh falhou' }
}
if (-not (Test-Path config.inc.php)) {
    $c = Get-Content config.TEMPLATE.inc.php -Raw
    $c = $c -replace '(?m)^base_url = .*$', "base_url = `"$baseUrl`"" `
            -replace '(?m)^restful_urls = Off', 'restful_urls = On' `
            -replace '(?m)^time_zone = .*$', 'time_zone = "America/Sao_Paulo"'
    [IO.File]::WriteAllText("$root\config.inc.php", $c, $utf8)
    Write-Host 'config.inc.php criado.'
}

# 3. Sobe os contêineres
docker compose up -d --wait
if ($LASTEXITCODE -ne 0) { throw 'docker compose up falhou' }

# 4. Instala o OMP se ainda não estiver instalado
if ((Get-Content config.inc.php -Raw) -match '(?m)^installed = On') {
    Write-Host "OMP já instalado: $baseUrl"
    exit 0
}

for ($i = 0; $i -lt 30; $i++) {
    try { Invoke-WebRequest "$baseUrl/index.php/index/install" -UseBasicParsing -TimeoutSec 5 | Out-Null; break }
    catch { Start-Sleep 2 }
}

Write-Host 'Executando o instalador do OMP (pode levar alguns minutos)...'
$fields = [ordered]@{
    installing        = '0'
    locale            = 'pt_BR'
    timeZone          = 'America/Sao_Paulo'
    filesDir          = '/var/www/files'
    adminUsername     = $cfg.OMP_ADMIN_USER
    adminPassword     = $cfg.OMP_ADMIN_PASSWORD
    adminPassword2    = $cfg.OMP_ADMIN_PASSWORD
    adminEmail        = $cfg.OMP_ADMIN_EMAIL
    databaseDriver    = 'mysqli'
    databaseHost      = 'db'
    databaseUsername  = $cfg.DB_USER
    databasePassword  = $cfg.DB_PASSWORD
    databaseName      = $cfg.DB_NAME
    oaiRepositoryId   = 'books.csvet.com.br'
    enableBeacon      = '0'
}
# Corpo montado à mão: o hashtable não repete a chave additionalLocales[] (en e es)
$pairs = @($fields.GetEnumerator() | ForEach-Object { "$($_.Key)=$([uri]::EscapeDataString([string]$_.Value))" })
$pairs += 'additionalLocales%5B%5D=en', 'additionalLocales%5B%5D=es'
$body = $pairs -join '&'
$r = Invoke-WebRequest "$baseUrl/index/en/install/install" -Method Post -Body $body -ContentType 'application/x-www-form-urlencoded' -UseBasicParsing -TimeoutSec 600

if ((Get-Content config.inc.php -Raw) -match '(?m)^installed = On') {
    Write-Host "Instalação concluída: $baseUrl  (admin: $($cfg.OMP_ADMIN_USER), senha no .env)"
} else {
    $out = "$root\install-response.html"
    [IO.File]::WriteAllText($out, $r.Content, $utf8)
    throw "A instalação não foi concluída. Veja $out e 'docker compose logs app'."
}
