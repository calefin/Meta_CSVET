#!/bin/bash
# BagParser — deploy na VPS atrás do Nginx Proxy Manager
# Primeiro deploy: bash deploy.sh https://github.com/calefin/bagparser.git
# Atualizar:       cd /opt/apps/bagparser && bash deploy.sh
set -euo pipefail

APP_DIR="/opt/apps/bagparser"
REPO_URL="${1:-}"
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.npm.yml"

log()  { echo -e "\033[0;32m[ok]\033[0m $1"; }
info() { echo -e "\033[0;34m[->]\033[0m $1"; }
err()  { echo -e "\033[0;31m[erro]\033[0m $1"; exit 1; }

command -v docker >/dev/null || err "Docker não encontrado (curl -fsSL https://get.docker.com | sh)"
docker compose version >/dev/null 2>&1 || err "Docker Compose v2 não encontrado"

# 1. Código
if [[ -d "$APP_DIR/.git" ]]; then
    cd "$APP_DIR"
    BEFORE=$(git rev-parse --short HEAD)
    if ! git fetch origin main; then
        echo
        echo "  O GitHub recusou a leitura do repositório (deploy key). Confira:"
        echo "    ssh -i ~/.ssh/bagparser_deploy -T git@github.com     # deve dizer 'successfully authenticated'"
        echo "    git config core.sshCommand                            # deve apontar para ~/.ssh/bagparser_deploy"
        echo "  Para publicar mesmo assim o código que já está aqui: SKIP_FETCH=1 bash deploy.sh"
        [[ "${SKIP_FETCH:-}" == "1" ]] || err "git fetch falhou"
    fi
    git reset --hard origin/main
    AFTER=$(git rev-parse --short HEAD)
    if [[ "$BEFORE" == "$AFTER" ]]; then
        info "Nenhum commit novo no GitHub ($AFTER). Se você esperava mudanças, faça git push no seu PC antes."
    else
        log "Atualizado $BEFORE -> $AFTER"
        git log --oneline "$BEFORE..$AFTER" | head -10
    fi
elif [[ -n "$REPO_URL" ]]; then
    sudo mkdir -p "$(dirname "$APP_DIR")"
    sudo git clone "$REPO_URL" "$APP_DIR"
    sudo chown -R "$USER:$USER" "$APP_DIR"
    cd "$APP_DIR"
else
    err "Repositório não encontrado em $APP_DIR. Use: bash deploy.sh <url_do_repo>"
fi
log "Código em $APP_DIR ($(git rev-parse --short HEAD))"

# 2. .env com segredos gerados (só na primeira vez)
if [[ ! -f .env ]]; then
    cp .env.example .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 24)|" .env
    sed -i "s|^FINGERPRINT_SALT=.*|FINGERPRINT_SALT=$(openssl rand -hex 32)|" .env
    sed -i "s|^TRUST_PROXY_HOPS=.*|TRUST_PROXY_HOPS=2|" .env
    log ".env criado com DB_PASSWORD e FINGERPRINT_SALT aleatórios"
fi

# 3. Rede do Nginx Proxy Manager
if ! grep -q "^NPM_NETWORK=" .env; then
    NPM_CONTAINER=$(docker ps --format '{{.Names}} {{.Image}}' | awk '/nginx-proxy-manager/ {print $1; exit}')
    NPM_NET=""
    if [[ -n "$NPM_CONTAINER" ]]; then
        NPM_NETS=$(docker inspect -f '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' "$NPM_CONTAINER" | grep -v '^$' || true)
        # Prefere a rede compartilhada dos apps (apps-network); senão, a primeira do NPM
        if grep -qx "apps-network" <<<"$NPM_NETS"; then NPM_NET="apps-network"; else NPM_NET=$(head -1 <<<"$NPM_NETS"); fi
    fi
    if [[ -z "$NPM_NET" ]]; then
        docker network ls
        read -rp "Nome da rede do Nginx Proxy Manager: " NPM_NET
    fi
    echo "NPM_NETWORK=$NPM_NET" >> .env
    log "NPM_NETWORK=$NPM_NET (container NPM: ${NPM_CONTAINER:-?})"
fi

# 4. Build e subida
info "Build e subida dos containers..."
# D-64: --pull fetches the latest patched base images (Node, Nginx) on every deploy
$COMPOSE build --pull
$COMPOSE up -d
$COMPOSE ps

# 5. Health check pelo container web (mesmo caminho que o NPM usa)
WEB_PORT=$(grep -E '^WEB_PORT=' .env | cut -d= -f2 | tr -d '[:space:]' || true)
WEB_PORT=${WEB_PORT:-3080}
EXPECTED=$(grep -m1 '"version"' backend/package.json | sed -E 's/.*"([0-9.]+)".*/\1/')
for i in {1..30}; do
    HEALTH=$(curl -fsS "http://127.0.0.1:$WEB_PORT/api/health" 2>/dev/null || true)
    if [[ -n "$HEALTH" ]]; then
        log "API respondendo: $HEALTH"
        # The old container can answer for a moment while the new one starts: wait for the expected version
        if grep -q "\"version\":\"$EXPECTED\"" <<<"$HEALTH"; then
            log "Versão no ar: $EXPECTED"
            break
        fi
        info "Aguardando a versão $EXPECTED..."
    fi
    sleep 2
    [[ $i -eq 30 ]] && err "A API não respondeu com a versão $EXPECTED; veja: $COMPOSE logs api"
done

cat <<EOF

No Nginx Proxy Manager (http://129.121.37.243:81) -> Proxy Hosts -> Add Proxy Host:
  Domain Names     : bagparser.com  www.bagparser.com
  Scheme           : http
  Forward Hostname : bagparser_web
  Forward Port     : 80
  [x] Block Common Exploits   (deixe "Cache Assets" desmarcado: o app já define o cache e o service worker precisa de no-cache)
  Aba SSL: Request a new SSL Certificate, [x] Force SSL, [x] HTTP/2 Support
  (o DNS de bagparser.com e www precisa apontar para 129.121.37.243 antes)

Logs: $COMPOSE logs -f
EOF
