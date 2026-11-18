#!/usr/bin/env bash
# Ajusta o config.inc.php de PRODUÇÃO depois que o instalador web terminar.
# Uso (via SSH, na pasta do site):  bash scripts/configure-production.sh
# Faz backup do config antes em ~/backups (FORA da raiz pública: o arquivo contém senhas).
set -euo pipefail
cd "$(dirname "$0")/.."
CFG=config.inc.php
DOMAIN="${DOMAIN:-books.csvet.com.br}"

[ -f "$CFG" ] || { echo "config.inc.php não existe: conclua o instalador web primeiro."; exit 1; }
grep -q '^installed = On' "$CFG" || { echo "OMP ainda não instalado (installed = Off)."; exit 1; }

BK="${BACKUP_DIR:-$HOME/backups}"; mkdir -p "$BK"; chmod 700 "$BK"
cp -p "$CFG" "$BK/config.inc.php.$(date +%Y%m%d%H%M)"
echo "==> Backup em $BK"

set_ini(){ # set_ini chave valor  (substitui a 1ª ocorrência de "chave = ...")
    local k="$1" v="$2"
    if grep -q "^$k = " "$CFG"; then
        K="$k" V="$v" perl -0pi -e 's/^\Q$ENV{K}\E = .*$/$ENV{K} . " = " . $ENV{V}/me' "$CFG"
        echo "  $k = $v"
    else
        echo "  (aviso) chave $k não encontrada"
    fi
}

echo "==> Ajustando $CFG para https://$DOMAIN"
set_ini base_url      "\"https://$DOMAIN\""
set_ini restful_urls  "On"
set_ini allowed_hosts "'[\"$DOMAIN\"]'"
set_ini force_ssl     "On"
set_ini time_zone     "\"America/Sao_Paulo\""
set_ini enable_beacon "Off"
chmod 640 "$CFG"

echo "==> Pronto. Teste: https://$DOMAIN"
echo "    Falta configurar o bloco [email] (SMTP) manualmente; veja o README."
