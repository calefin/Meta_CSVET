# VetRank — Referência de Comandos

## Desenvolvimento local

```bash
pnpm dev                        # sobe dev server na porta 3000
pnpm build                      # build completo (vite + esbuild backend)
pnpm start                      # roda o build em produção local
pnpm check                      # type-check TypeScript (tsc --noEmit)
pnpm test                       # roda os testes (vitest)
```

## Banco de dados

```bash
pnpm db:push                    # gera migração + aplica no banco
```

## Git

```bash
git add <arquivos>
git commit -m "tipo: descrição"
git push origin main
```

## Deploy no VPS

```bash
cd /opt/apps/vetrank && git pull origin main && docker compose up -d --build
```

> O build roda dentro do container (pnpm install + pnpm build). Demora ~450s por ser CPU-bound.

### Ver logs em tempo real

```bash
docker compose logs -f          # todos os containers
docker compose logs -f app      # só o container da aplicação
```

### Reiniciar sem rebuild

```bash
docker compose restart
```

### Derrubar tudo

```bash
docker compose down
```

## Blog

```bash
pnpm blog:generate              # gera post sobre tema aleatório
pnpm blog:generate "tema"       # gera post sobre tema específico
```

## Endpoints agendados (disparo manual — só de dentro do VPS)

Pela internet o caminho `/api/scheduled/` responde 403. Exigem o header `X-Cron-Secret` (ver CLAUDE.md).
⚠️ Disparar manualmente envia e-mails de verdade para todos os usuários.

```bash
H=/root/.config/vetrank-cron.header
# Questão do dia
curl -s -X POST -H @$H http://127.0.0.1:3001/api/scheduled/send-daily-question

# E-mails de expiração de plano
curl -s -X POST -H @$H http://127.0.0.1:3001/api/scheduled/send-expiry-emails

# Digest semanal de novas questões
curl -s -X POST -H @$H http://127.0.0.1:3001/api/scheduled/send-new-questions-digest
```

Resultado do cron: `/var/log/vetrank-cron.log`. Erros de SMTP: `docker logs vetrank | grep "SMTP ERROR"`.
