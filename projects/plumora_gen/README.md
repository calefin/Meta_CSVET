# 🪶 Plumora Gen

> **Plataforma de Gestão para Criadores de Aves Exóticas, Domésticas e Pombos Correio**

Plumora Gen é uma plataforma SaaS completa para criadores de aves, com módulos de gestão de plantel, reprodução, saúde, vacinação, genética, pedigree (claudograma) e suporte veterinário especializado via **PigeonVet**.

**Concepção científica e veterinária:** Prof. Dr. Atilio Sersun Calefi  
Médico Veterinário, MSc e DSc — Patologia Veterinária, Farmacologia e Toxicologia  
[profatilio.com.br](https://www.profatilio.com.br)

---

## Stack Tecnológica

| Camada | Tecnologia |
|--------|-----------|
| Backend | FastAPI + Python 3.11 |
| Banco de dados | PostgreSQL 16 |
| ORM | SQLAlchemy 2.0 + Alembic |
| Cache/Filas | Redis + Celery |
| Storage | MinIO (self-hosted) |
| Frontend | React 18 + Vite + TypeScript |
| Estilização | Tailwind CSS + inline styles |
| Estado | Zustand + TanStack Query |
| Formulários | React Hook Form + Zod |
| i18n | i18next (PT-BR / EN-US) |
| Infra | Docker Compose + Nginx |
| Pagamentos | Mercado Pago |
| E-mail | Resend |

---

## Módulos Implementados

### Backend
- ✅ **Auth** — Registro, login JWT, refresh token, reset de senha
- ✅ **Aves** — CRUD completo com controle por plano (freemium), exportação do plantel em CSV
- ✅ **Espécies & Raças** — Calopsita, Pombo, Periquito, Agapornis, Rosela, Canário, Forpus
- ✅ **Reprodução** — Casais, gaiolas (cadastro reutilizável com checagem de ocupação), posturas, filhotes com promoção a ave do plantel (vínculo automático ao pedigree), índices zootécnicos
- ✅ **Dashboard do Criador** — Endpoint agregado com estatísticas, composição do plantel, reprodução ativa (com previsão de eclosão) e alertas priorizados (vacina atrasada, saúde, PigeonVet pendente, carência de medicação)
- ✅ **Saúde** — Registros de saúde, sintomas, peso, temperatura, gravidade
- ✅ **PigeonVet** — Fila de consultas veterinárias, triagem por prioridade, resposta
- ✅ **Vacinação** — Registro individual, lotes automáticos, agenda de reforços (incluindo atrasadas)
- ✅ **Medicação** — Biblioteca de medicamentos, tratamentos individuais e em lote
- ✅ **Alimentação** — Log de alimentação, planos alimentares
- ✅ **Provas** — Cadastro de competições, resultados, velocidade calculada
- ✅ **Pedigree** — Claudograma recursivo, coeficiente de consanguinidade (Wright)
- ✅ **Genética (Psitacídeos)** — Calculadora multi-loci: re, se, do, in, cd, multi-alélicos
- ✅ **Genética (Pombo)** — Sistema ZW, haplótipos cor+diluído, epistasia, alertas clínicos
- ✅ **Billing** — Planos freemium/básico/pro/premium, integração Mercado Pago (checkout único anual, assinatura recorrente mensal, webhook), bloqueio de acesso por inadimplência (preserva dados)
- ✅ **E-mail Marketing** — Templates, campanhas segmentadas (papel/plano/locale/espécie), envio via Gmail SMTP, descadastro sem login
- ✅ **Admin** — Gestão de usuários, assinaturas, dashboard financeiro
- ✅ **Seeds** — Populador automático: espécies, loci genéticos, vacinas, planos

### Frontend
- ✅ **Dashboard** — Estatísticas, alertas priorizados (vacina/saúde/PigeonVet/carência), reprodução ativa, composição do plantel, aves recentes
- ✅ **Painel Aves** — Grid de fichas com filtros, exportação em planilha
- ✅ **Vacinação** — Registro individual + lotes com seleção por espécie/localização/manual
- ✅ **Reprodução** — Gestão de casais, gaiolas, posturas e filhotes (com promoção a ave)
- ✅ **Saúde** — Registros com sintomas e solicitação de consulta vet
- ✅ **Genética** — Calculadora fenotípica com tabela de resultados
- ✅ **Provas** — Cadastro de competições e resultados
- ✅ **Claudograma** — Visualização em árvore com coeficiente de Wright
- ✅ **PigeonVet** — Painel do veterinário com fila e resposta
- ✅ **Admin** — Dashboard, gestão de usuários e campanhas de e-mail marketing
- ✅ **i18n** — Português (PT-BR) e Inglês (EN-US)
- ✅ **Auth** — Login e registro com JWT

---

## Estrutura do Projeto

```
plumora_gen/
├── backend/
│   ├── app/
│   │   ├── main.py              # FastAPI app
│   │   ├── config.py            # Settings (pydantic-settings)
│   │   ├── database.py          # SQLAlchemy engine
│   │   ├── dependencies.py      # get_db, get_current_user, require_role
│   │   ├── models/              # SQLAlchemy models
│   │   ├── schemas/             # Pydantic schemas
│   │   ├── routers/             # API endpoints
│   │   ├── services/            # Business logic
│   │   │   └── genetics/        # Calculadora genética
│   │   └── tasks/               # Celery tasks (PDF, email)
│   ├── Dockerfile
│   └── requirements.txt
├── frontend/
│   ├── src/
│   │   ├── App.tsx
│   │   ├── router/              # React Router v6
│   │   ├── components/          # Layout, UI components
│   │   ├── pages/               # breeder/, vet/, admin/
│   │   ├── services/api.ts      # Axios + todos endpoints
│   │   ├── store/               # Zustand (auth, ui)
│   │   ├── types/index.ts       # TypeScript types
│   │   └── locales/             # PT-BR e EN-US
│   ├── Dockerfile
│   └── package.json
├── nginx/nginx.conf
├── seeds/
│   ├── seed_db.py               # Populador do banco
│   └── genetics/                # Loci por espécie
├── docker-compose.yml
├── docker-compose.prod.yml
└── .env.example
```

---

## Início Rápido

### Desenvolvimento Local

```bash
# 1. Clone e configure o ambiente
cp .env.example .env
# Edite .env com suas configurações

# 2. Suba os serviços com Docker Compose
docker compose up -d db redis minio

# 3. Backend
cd backend
pip install -r requirements.txt
# Crie o banco e popule os dados
python -m alembic upgrade head
python ../seeds/seed_db.py
uvicorn app.main:app --reload

# 4. Frontend
cd ../frontend
npm install
npm run dev
```

### Docker Compose Completo

```bash
cp .env.example .env
docker compose up --build
# App disponível em http://localhost
# API Docs: http://localhost/api/docs
```

> Lista completa de comandos (dev, testes, deploy em produção via SSH) em [COMANDOS.md](COMANDOS.md).

---

## API

Documentação interativa disponível em:
- **Swagger UI:** `/api/docs`
- **ReDoc:** `/api/redoc`

Endpoints principais:
```
POST   /api/auth/register
POST   /api/auth/login
GET    /api/birds
POST   /api/birds
GET    /api/birds/{id}/pedigree
POST   /api/genetics/calculate
GET    /api/vaccines/schedule
POST   /api/vaccines/lots
GET    /api/vet/queue
POST   /api/vet/consults/{id}/respond
GET    /api/admin/dashboard
```

---

## Planos

| Recurso | Free | Basic | Pro | Premium |
|---------|------|-------|-----|---------|
| Aves | até 10 | até 50 | até 200 | Ilimitado |
| Claudogramas/mês | 2 | 10 | Ilimitado | Ilimitado |
| PigeonVet | — | 1/mês | 3/mês | Ilimitado |
| Exportação PDF | — | ✓ | ✓ | ✓ |
| Relatórios avançados | — | — | ✓ | ✓ |
| Preço | R$ 0 | R$ 19,90/mês | R$ 49,90/mês | R$ 89,90/mês |

---

## Fases de Desenvolvimento

- ✅ **v4.0** — Estrutura completa, MVP funcional
- 🔲 **v4.1** — Módulo de alimentação completo, PDFs
- 🔲 **v4.2** — Módulo genético completo com todas as espécies
- 🔲 **v4.3** — App mobile (React Native / PWA)
- 🔲 **v5.0** — IA para triagem de sintomas, marketplace

---

## Identidade Visual

```css
--plumora-green-deep: #1a3d2b;
--plumora-green-mid:  #2d6a4f;
--plumora-gold:       #c8922a;
--plumora-cream:      #faf6f0;
--plumora-terracotta: #c45c3d;
```

**Tipografia:** Cormorant Garamond (display) + DM Sans (interface)

---

*Plumora Gen v4.0 — Mai/2026 · Especificação técnica: PLUMORA_SPEC.md*
