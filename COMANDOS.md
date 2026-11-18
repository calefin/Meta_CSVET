# Comandos Úteis — Plumora Gen

## Backend (`backend/`)

```bash
pip install -r requirements.txt
python -m alembic upgrade head            # aplicar migrações
python ../seeds/seed_db.py                # popular espécies, loci genéticos, vacinas, planos
uvicorn app.main:app --reload             # dev server (http://localhost:8000)
pytest                                     # todos os testes
pytest tests/test_auth.py                  # um arquivo de teste
pytest tests/test_auth.py::test_register_and_login   # um teste específico
alembic revision --autogenerate -m "..."   # gerar nova migração após mudar models/
```

## Frontend (`frontend/`)

```bash
npm install
npm run dev          # dev server :5173 (proxy /api -> localhost:8000)
npm run build         # tsc + vite build
npm run lint          # eslint --max-warnings 0
```

## Docker Compose (local, dev)

```bash
cp .env.example .env
docker compose up --build      # app em http://localhost, docs em /api/docs
docker compose down
docker compose logs -f
```

## Deploy em produção (VPS via SSH)

```bash
ssh seu_usuario@<ip_da_vps>
```

### Primeiro deploy (clonar)
```bash
curl -fsSL https://get.docker.com | sh    # se Docker não estiver instalado
bash deploy.sh https://github.com/calefin/plumora_gen.git
```

### Deploys seguintes (atualizar)
```bash
cd /opt/apps/plumora
bash deploy.sh
```

### Update manual (sem o script)
```bash
cd /opt/apps/plumora
git pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T backend alembic upgrade head
```

### Comandos úteis em produção
```bash
COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"

$COMPOSE ps                    # status dos containers
$COMPOSE logs -f               # logs de todos os serviços
$COMPOSE logs -f backend       # logs só do backend
$COMPOSE exec -T backend bash  # shell dentro do container backend
$COMPOSE exec -T db psql -U plumora_user -d plumora   # psql direto no banco
$COMPOSE down                  # parar todos os serviços
$COMPOSE restart backend       # reiniciar só um serviço
```

### Verificação pós-deploy
```bash
curl http://plumora_nginx/health    # dentro da rede Docker
curl https://plumoragen.com/health  # após configurar o Nginx Proxy Manager
```

### Configuração no Nginx Proxy Manager (porta :81)
1. Proxy Hosts → Add/Edit Proxy Host
2. Domain Names: `plumoragen.com` `www.plumoragen.com`
3. Scheme: `http` / Forward Hostname: `plumora_nginx` / Forward Port: `80`
4. Marcar: Cache Assets, Block Common Exploits
5. Aba SSL: solicitar Let's Encrypt, marcar Force SSL e HTTP/2 Support
