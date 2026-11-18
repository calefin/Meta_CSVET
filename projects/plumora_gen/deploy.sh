#!/bin/bash
# Plumora Gen — Deploy VPS com Nginx Proxy Manager
# Uso: bash deploy.sh [repo_git_url]
# Ex:  bash deploy.sh https://github.com/calefin/plumora_gen.git
set -euo pipefail

APP_DIR="/opt/apps/plumora"
REPO_URL="${1:-}"
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✓]${NC} $1"; }
info() { echo -e "${BLUE}[→]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err()  { echo -e "${RED}[✗]${NC} $1"; exit 1; }

echo -e "${BLUE}"
echo "  ╔══════════════════════════════════════════╗"
echo "  ║       Plumora Gen — Deploy VPS           ║"
echo "  ║   /opt/apps/plumora + NPM                ║"
echo "  ╚══════════════════════════════════════════╝"
echo -e "${NC}"

# ─── 1. Verificar Docker ─────────────────────────────────────────────────────
info "Verificando Docker..."
command -v docker &>/dev/null || err "Docker não encontrado. Instale com: curl -fsSL https://get.docker.com | sh"
docker compose version &>/dev/null || err "Docker Compose v2 não encontrado. Atualize o Docker (>= 24.x)."
log "Docker ok: $(docker --version | cut -d' ' -f3 | tr -d ',')"

# ─── 2. Diretório /opt/apps/plumora ─────────────────────────────────────────
info "Preparando $APP_DIR..."
sudo mkdir -p "$APP_DIR"
sudo chown "$USER:$USER" "$APP_DIR"

if [[ -d "$APP_DIR/.git" ]]; then
    info "Repositório já existe — executando git pull..."
    cd "$APP_DIR"
    git pull
    log "Código atualizado"
elif [[ -n "$REPO_URL" ]]; then
    info "Clonando $REPO_URL em $APP_DIR..."
    git clone "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
    log "Clone concluído"
else
    # Código já foi copiado (rsync/scp ou estava lá)
    [[ -f "$APP_DIR/docker-compose.yml" ]] || err "Nenhum repositório encontrado em $APP_DIR. Passe a URL do repo: bash deploy.sh <url>"
    cd "$APP_DIR"
    log "Usando código existente em $APP_DIR"
fi

# ─── 3. Criar .env se não existir ────────────────────────────────────────────
if [[ ! -f ".env" ]]; then
    warn ".env não encontrado — criando a partir do .env.example"
    cp .env.example .env

    # Gerar SECRET_KEY seguro
    SECRET=$(openssl rand -hex 32)
    sed -i "s|change-me-in-production-use-openssl-rand-hex-32|$SECRET|" .env
    sed -i "s|APP_ENV=development|APP_ENV=production|" .env
    sed -i "s|FRONTEND_URL=http://localhost:5173|FRONTEND_URL=https://plumoragen.com|" .env

    echo ""
    warn "ATENÇÃO: Edite o .env com suas configurações de produção!"
    warn "  Obrigatório: DB_PASSWORD, MINIO_PASSWORD, SMTP_PASSWORD"
    warn "  Opcional: STRIPE_SECRET_KEY (para cobranças)"
    echo ""
    warn "Abrindo editor... (CTRL+X para sair do nano após editar)"
    sleep 2
    nano .env
fi
log ".env configurado"

# ─── 4. Detectar rede do Nginx Proxy Manager ─────────────────────────────────
info "Detectando rede do Nginx Proxy Manager..."

if grep -q "^NPM_NETWORK=" .env 2>/dev/null; then
    NPM_NET=$(grep "^NPM_NETWORK=" .env | cut -d= -f2)
    log "NPM_NETWORK já definido no .env: $NPM_NET"
else
    # Tentar detectar automaticamente
    NPM_NET=$(docker network ls --format "{{.Name}}" | grep -iE "nginx.proxy|npm|nginxproxy" | head -1 || true)

    if [[ -z "$NPM_NET" ]]; then
        echo ""
        warn "Rede do NPM não detectada automaticamente."
        echo "  Execute: docker network ls"
        echo "  Procure a rede onde o Nginx Proxy Manager está conectado."
        read -rp "  Digite o nome exato da rede NPM: " NPM_NET
    fi

    echo "NPM_NETWORK=$NPM_NET" >> .env
    log "NPM_NETWORK=$NPM_NET adicionado ao .env"
fi

export NPM_NETWORK="$NPM_NET"

# ─── 5. Build e subir serviços ───────────────────────────────────────────────
info "Fazendo build das imagens..."
$COMPOSE build

info "Subindo serviços em produção..."
$COMPOSE up -d
log "Serviços iniciados"

# ─── 6. Aguardar banco de dados ───────────────────────────────────────────────
info "Aguardando banco de dados ficar pronto..."
for i in {1..30}; do
    if $COMPOSE exec -T db pg_isready -U "${DB_USER:-plumora_user}" &>/dev/null 2>&1; then
        break
    fi
    echo -n "."
    sleep 2
done
echo ""
log "Banco de dados pronto"

# ─── 7. Migrações Alembic ─────────────────────────────────────────────────────
info "Executando migrações Alembic..."
$COMPOSE exec -T backend alembic upgrade head
log "Migrações aplicadas"

# ─── 8. Seed inicial (apenas primeiro deploy) ─────────────────────────────────
if [[ ! -f ".seeded" ]]; then
    info "Executando seed inicial do banco de dados..."
    $COMPOSE exec -T backend python /seeds/seed_db.py && touch .seeded
    log "Seed concluído"
else
    log "Seed já executado anteriormente — pulando"
fi

# ─── 9. Status dos containers ────────────────────────────────────────────────
echo ""
info "Status dos containers:"
$COMPOSE ps

# ─── 10. Instruções NPM ───────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Deploy concluído! Configuração final no NPM:${NC}"
echo -e "${GREEN}══════════════════════════════════════════════${NC}"
echo ""
echo "  No Nginx Proxy Manager (http://129.121.37.243:81):"
echo ""
echo "  1. Acesse Proxy Hosts → Edit ou Add Proxy Host"
echo "  2. Configure:"
echo "     Domain Names   : plumoragen.com  www.plumoragen.com"
echo "     Scheme         : http"
echo "     Forward Hostname: plumora_nginx"
echo "     Forward Port   : 80"
echo "     ✓ Cache Assets"
echo "     ✓ Block Common Exploits"
echo ""
echo "  3. Aba SSL: solicitar certificado Let's Encrypt"
echo "     ✓ Force SSL"
echo "     ✓ HTTP/2 Support"
echo ""
echo "  Verificação:"
echo "    curl http://plumora_nginx/health   (dentro da rede Docker)"
echo "    curl https://plumoragen.com/health  (após configurar NPM)"
echo ""
echo "  Comandos úteis:"
echo "    Logs:   $COMPOSE logs -f"
echo "    Parar:  $COMPOSE down"
echo "    Update: git pull && $COMPOSE build && $COMPOSE up -d && $COMPOSE exec -T backend alembic upgrade head"
