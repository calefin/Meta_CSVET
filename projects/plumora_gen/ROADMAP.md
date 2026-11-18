# Plumora Gen — Mapa de Implementação

> **Versão:** 4.0 · **Empresa:** CSVET Editora, Cursos e Treinamentos  
> **Contato:** calefi@csvet.com.br · **Atualizado:** junho/2026

---

## Status Geral

| Módulo | Status | Observação |
|---|---|---|
| Autenticação JWT | ✅ Pronto | Register / Login / Refresh / Me |
| Perfil do Criador | ✅ Pronto | Endereço, associação, CPF, CRMV |
| Gestão de Aves | ✅ Pronto | CRUD + limite por plano + exportação CSV do plantel |
| Anilhas (Rings) | ✅ Pronto | Registro, associação a ave, stats |
| Reprodução | ✅ Pronto | Pares, gaiolas (cadastro + checagem de ocupação), ninhadas, filhotes com promoção a ave |
| Dashboard do Criador | ✅ Pronto | Stats, alertas priorizados (vacina/saúde/PigeonVet/carência), reprodução ativa |
| E-mail Marketing | ✅ Pronto | Templates, campanhas segmentadas, envio via Gmail, descadastro |
| Genética Psitacídeos | ✅ Pronto | re/se/do/in/cd + multi-alélico |
| Genética Pombo | ✅ Pronto | ZW, haplótipos, epistasia |
| Pedigree / Claudograma | ✅ Pronto | Wright coefficient, export PDF base |
| Saúde / Prontuário | ✅ Pronto | Registros + abertura de chamado vet |
| Vacinação Individual | ✅ Pronto | Com dados CRMV, lote e hash SHA256 |
| Vacinação em Lote | ✅ Pronto | Por espécie / local / manual |
| Medicação | ✅ Pronto | Catálogo + registros imutáveis |
| Alimentação | ✅ Pronto | Catálogo + diário de consumo |
| Corridas (Pombo) | ✅ Pronto | Prova, resultado, velocidade |
| PigeonVet — Chamados | ✅ Pronto | Telemedicina e presencial |
| PigeonVet — Notificação e-mail | ✅ Pronto | Para calefi@csvet.com.br e criador |
| Landing Page | ✅ Pronto | Hero, features, planos, CTA |
| Termos de Serviço (LGPD) | ✅ Pronto | CSVET, aceite no cadastro |
| Política de Privacidade | ✅ Pronto | LGPD art. 18, DPO definido |
| Billing / Assinatura Mercado Pago | 🔶 Parcial | Checkout (Preference/PreApproval), webhook e bloqueio por inadimplência implementados no código; falta testar com credenciais reais/sandbox do MP e configurar `MP_ACCESS_TOKEN`/`MP_WEBHOOK_SECRET` em produção |
| Painel Admin | 🔶 Parcial | Listagem de usuários; métricas básicas; campanhas de e-mail |
| Upload de Foto de Perfil | 🔶 Parcial | Endpoint reservado; integração MinIO pendente |
| Export PDF Pedigree (visual) | 🔶 Parcial | ReportLab básico; claudograma gráfico pendente |
| App Mobile / PWA | ❌ Pendente | Fase 5 |
| Triagem IA de Sintomas | ❌ Pendente | Fase 5 |
| Notificações Push | ❌ Pendente | Fase 5 |
| API pública (integração) | ❌ Pendente | Plano Premium |

### ⚠️ Correções críticas aguardando deploy (19/07/2026)
Correções de bug graves foram feitas no código mas **ainda não foram publicadas em produção**:
- **Celery descartava tasks silenciosamente**: `notification_tasks.py` criava sua própria instância `Celery(...)`, separada da usada pelo worker real (`pdf_tasks.celery_app`) — reset de senha e notificação PigeonVet nunca chegavam a ser enviados. Corrigido (instância única + `include=[...]`).
- **Escalação de privilégio no registro**: `POST /api/auth/register` aceitava `role` livre do corpo da requisição — qualquer pessoa podia se cadastrar como `admin`. Corrigido (`Literal["breeder", "vet"]`).
- **Loci multi-alélicos calculados incorretamente**: ver detalhe na Fase 3. Corrigido em `services/genetics/calculator.py`.
- **Prefixo `/api/api/` duplicado no frontend**: `ProfilePage.tsx`, `FeedPage.tsx`, `MedicationPage.tsx`, `RingsPage.tsx` e `VaccinesPage.tsx` chamavam `api.get('/api/...')` sobre um client axios que já tem `baseURL: '/api'`, gerando 404 em todas essas chamadas. Corrigido (paths relativos ao baseURL).

Todas exigem rebuild + redeploy dos containers `backend`, `celery` e `frontend` para valer.

---

## Fase 1 — Infraestrutura e Core ✅ CONCLUÍDA

- [x] Stack: FastAPI 0.111 + SQLAlchemy 2.0 + PostgreSQL 16
- [x] Frontend: React 18 + Vite + TypeScript + i18n PT-BR/EN-US
- [x] Docker Compose: 7 serviços (db, redis, minio, backend, celery, frontend, nginx)
- [x] Autenticação: JWT (access 15min + refresh 7d) + bcrypt custo 12
- [x] Rate limiting: slowapi (30r/m API, 10r/m auth)
- [x] CORS restrito ao `frontend_url`
- [x] Alembic migrations + seed data
- [x] Seed: 4 planos, 10 espécies, 12 loci Calopsita, 8 produtos vacina

---

## Fase 2 — Módulos Essenciais ✅ CONCLUÍDA

- [x] Gestão de aves com limite por plano
- [x] Reprodução: pares, gaiolas (cadastro reutilizável + checagem de ocupação), ninhadas, filhotes (com promoção a ave do plantel e vínculo automático ao pedigree)
- [x] Saúde: prontuários por ave com severidade (normal/atenção/urgência)
- [x] Vacinação: individual e em lote; produtos do catálogo
- [x] Dados veterinários obrigatórios na vacina: nome, CRMV estado, CRMV número, lote
- [x] Hash SHA256 por registro de vacinação (prova de imutabilidade)
- [x] Sem edição de registros de vacina e medicação — apenas criar ou apagar
- [x] Medicação: catálogo + registros por ave
- [x] Alimentação: produtos + diário de consumo

---

## Fase 3 — Genética e Pedigree ✅ CONCLUÍDA

- [x] Calculadora genética: 6 modos (re, se, do, in, cd, multi-alélico)
- [x] Crossover ligado ao sexo: cin=7%, ych=3%, ino=30%
- [x] Calculadora pombo ZW: haplótipos cor+diluído, epistasia spread/vermelho, padrão asa, pé emplumado
- [x] Alerta clínico automático: barless homozigoto → risco visual
- [x] Pedigree recursivo com coeficiente de consanguinidade (Wright)
- [x] Export PDF base (ReportLab)
- [x] Correção (19/07/2026): loci multi-alélicos (`is_multi_allele=True`) eram computados como punnett square binário simples (mode re/se/do/in checado antes de is_multi_allele) — dispatch corrigido, `_punnett_multi_allele`/`_punnett_multi_allele_se` reescritos com a convenção `+`/mutante correta, testes de regressão adicionados. Afetava ino/aabz/wwpa (calopsita) e todo locus multi-alélico das novas espécies (§6.4).
- [ ] **PENDENTE:** Claudograma visual gráfico (layout árvore genealógica com imagens)

---

## Fase 4 — PigeonVet / Telemedicina ✅ CONCLUÍDA

- [x] Abertura de chamado pelo criador com descrição, prioridade e sintomas
- [x] Tipo de consulta: **telemedicina** ou **presencial** (endereço e data/hora)
- [x] Fila de atendimento para veterinários (role=vet)
- [x] Resposta veterinária com notas clínicas
- [x] Notificação automática por e-mail ao abrir chamado:
  - Destinatário 1: `calefi@csvet.com.br`
  - Destinatário 2: e-mail do criador (confirmação de recebimento)
- [ ] **PENDENTE:** Agendamento integrado (calendário com disponibilidade do vet)
- [ ] **PENDENTE:** Videochamada integrada (telemedicina)
- [ ] **PENDENTE:** Prontuário eletrônico veterinário com assinatura digital
- [ ] **PENDENTE:** CFMV/CRM validação de registro veterinário online

---

## Fase 5 — Perfil, Anilhas e Assinatura ✅ CONCLUÍDA

- [x] Perfil do criador: nome completo, CPF, endereço completo, foto (placeholder)
- [x] Associação ornitológica: nome e número de cadastro
- [x] CRMV: estado + número (para perfil de veterinário)
- [x] Anilhas: registro, associação a aves, estatísticas (total / associadas / livres)
- [x] Assinatura: requer CPF válido (algoritmo oficial) + endereço + nome completo
- [x] Consciência de valores e termos de serviço obrigatória no cadastro e na assinatura
- [x] LGPD: Política de Privacidade completa + registro de consentimento (`terms_accepted_at`)
- [ ] **PENDENTE:** Upload de foto de perfil (MinIO presigned URL)
- [x] Integração Mercado Pago completa no código (checkout Preference/PreApproval, webhook, bloqueio por inadimplência) — ver Fase 6.1 para o que falta antes de produção

---

## Fase 6 — Pendências Críticas (Próximos Sprints)

### 6.1 Mercado Pago — Billing Completo
- [x] Checkout único (Preference, plano anual) e assinatura recorrente (PreApproval, plano mensal)
- [x] Webhook (`payment`, `subscription_preapproval`, `subscription_authorized_payment`) com verificação de assinatura HMAC e idempotência
- [x] Bloqueio de acesso (HTTP 402) para plano pago sem assinatura ativa/vencida — sem apagar dados; plano free e vet/admin nunca bloqueiam
- [ ] **PENDENTE:** Inserir `MP_ACCESS_TOKEN`/`MP_WEBHOOK_SECRET` reais no `.env` de produção
- [ ] **PENDENTE:** Testar ciclo completo no sandbox do Mercado Pago (checkout → webhook → liberação de acesso) — nunca testado com credenciais reais
- [ ] **PENDENTE:** Corrigir troca de plano (upgrade/downgrade) criando uma assinatura órfã no MP em vez de substituir/cancelar a anterior (achado em revisão de código de 19/07/2026, ainda não corrigido)

### 6.2 Upload de Foto de Perfil
- [ ] Endpoint `POST /api/profile/photo` — gerar presigned URL no MinIO
- [ ] Frontend: componente de upload com preview no `ProfilePage`
- [ ] Criar bucket `plumora-uploads` no MinIO (via console ou seed)

### 6.3 E-mail Transacional
- [ ] Configurar chave da API Resend (`SMTP_PASSWORD=re_...`) no `.env`
- [ ] Testar envio de notificação de chamado PigeonVet
- [ ] Template HTML para e-mail de boas-vindas no cadastro
- [ ] Template HTML para lembrete de vacina (Celery beat)
- [ ] Template HTML para confirmação de assinatura

### 6.4 Seed de Loci Genéticos ✅ CONCLUÍDO (19/07/2026)
Loci extraídos de literatura de referência (Martin, "A Guide to Colour Mutations & Genetics in Parrots"; Perez-Beato, "Fundamentals of Color Genetics in Canaries"), restritos a mutações documentadas como comuns/estabelecidas por espécie.
- [x] `seeds/genetics/canary.py` — Canário (13 loci: preto/canela, diluição ágata/isabel, pastel, marfim, opala/onix multi-alélico, eumo, topázio, kobalt, limão, intensivo/frost, mosaico, branco dominante, fator vermelho)
- [x] `seeds/genetics/budgerigar.py` — Periquito Australiano (13 loci: ino/clearbody, canela, opalino, azul/turquesa, greywing/clearwing/dilute, fallow, malhado recessivo, fator escuro, violeta, cinza, malhado dominante, spangle, melanístico)
- [x] `seeds/genetics/lovebird_peachfaced.py` — Agapornis roseicollis (11 loci)
- [x] `seeds/genetics/lovebird_masked.py` — Agapornis personatus/grupo White Eye-ring (8 loci)
- [x] `seeds/genetics/rosella_eastern.py` — Rosela-oriental / Platycercus eximius (7 loci)
- [x] `seeds/genetics/rosella_crimson.py` — Rosela-carmesim / Platycercus elegans (5 loci)
- [x] `seeds/genetics/red_rumped.py` — Red-rumped Parrot / Psephotus haematonotus (8 loci)
- [x] `seeds/genetics/forpus.py` — Forpus azul / Pacific Parrotlet (6 loci)
- [ ] **PENDENTE:** ler o livro de papagaios (297 páginas, `D:\OneDrive\_Acadêmico e Profissional\Livros\A Guide to Colour Mutations & Genetics in Parrots (VetBooks.ir).pdf`) para além do índice de mutações por espécie — texto completo ainda não revisado capítulo a capítulo; loci acima cobrem apenas as mutações "common"/"established"/"under dev." listadas no índice, não combinações nem mutações "uncertain"

### 6.5 Claudograma Visual (PDF)
- [ ] Substituir ReportLab básico por layout gráfico de árvore
- [ ] Caixas coloridas por geração (cores da marca)
- [ ] Logotipo CSVET / Plumora Gen no rodapé do PDF
- [ ] Exportar imagem PNG do pedigree via ReactFlow

### 6.6 Dashboard, E-mail Marketing e Exportação ✅ CONCLUÍDO (19/07/2026)
- [x] Dashboard agregado: endpoint único (`GET /api/dashboard/summary`) com stats do plantel, composição por espécie, reprodução ativa (com data de eclosão prevista via `Species.incubation_days`), e alertas priorizados (vacina atrasada/próxima, saúde urgente/atenção, PigeonVet pendente, carência de medicação)
- [x] Correção de bug: `GET /api/vaccines/schedule` estava excluindo vacinas já atrasadas em vez de sinalizá-las
- [x] E-mail marketing (admin): templates HTML, campanhas com segmentação (papel, plano, locale, data de cadastro, espécie), envio via Gmail SMTP (provedor separado do Resend transacional), link de descadastro sem exigir login, aviso de limite diário de envio
- [x] Exportação do plantel em CSV (`GET /api/birds/export`) — nome, anilha, espécie, raça, pais, status, etc., com BOM UTF-8 para acentuação correta no Excel
- [ ] **PENDENTE:** testar envio real de campanha com `GMAIL_APP_PASSWORD` de verdade (só validado com SDK/SMTP mockado)

---

## Fase 7 — Funcionalidades Avançadas

### 7.1 Índices Zootécnicos
- [ ] Dashboard de índices por ave: peso histórico, FCR (feed conversion ratio)
- [ ] Taxa de fertilidade e eclosão por reprodutor
- [ ] Ranking de reprodutores por desempenho
- [ ] Gráficos Recharts: linha (peso), pizza (resultado ninhadas)

### 7.2 Corridas — Pombo Correio
- [ ] Ranking nacional por raça e distância
- [ ] Histórico de performance por pombo (velocidade média, top corridas)
- [ ] Integração com associações de columbofilia (CSV import)
- [ ] Mapa de rota com Leaflet (origem → destino)

### 7.3 Painel Admin
- [ ] Métricas de negócio: MRR, churn, DAU
- [ ] Gestão de planos (criar/editar planos)
- [ ] Log de auditoria (quem fez o quê e quando)
- [x] Exportação de dados em CSV pelo próprio criador (`GET /api/birds/export`, LGPD: portabilidade) — cobre o plantel; saúde/vacinação/reprodução ainda não exportáveis
- [ ] **PENDENTE:** exportação de dados de negócio pelo admin (todos os usuários/assinaturas)

### 7.4 Agendamento PigeonVet
- [ ] Calendário de disponibilidade do veterinário
- [ ] Criador escolhe slot disponível
- [ ] Confirmação/recusa pelo veterinário
- [ ] Lembrete automático 24h antes (e-mail + push)

---

## Fase 8 — Mobile / PWA (Fase 5 da spec original)

- [ ] Service Worker + manifest.json (PWA instalável)
- [ ] Notificações Push (vacinas, chamados respondidos)
- [ ] Modo offline básico (leitura do plantel em cache)
- [ ] React Native (opcional, fase futura)

---

## Fase 9 — IA e Automação

- [ ] Triagem automática de sintomas (LLM via Claude API)
- [ ] Sugestão genética: "cruzamentos recomendados para esta mutação"
- [ ] Detecção de anomalias em peso/saúde (séries temporais)
- [ ] Chatbot PigeonVet (1ª resposta automática antes do veterinário)

---

## Configurações Necessárias para Produção

```env
# Mercado Pago (obrigatório para billing)
MP_ACCESS_TOKEN=...
MP_WEBHOOK_SECRET=...

# Resend (obrigatório para e-mails transacionais)
SMTP_PASSWORD=re_...
EMAIL_FROM=noreply@plumora.com.br

# Gmail (obrigatório para e-mail marketing — provedor separado do Resend acima)
# GMAIL_APP_PASSWORD é a "senha de app" do Google, não a senha normal da conta
GMAIL_SMTP_USER=...
GMAIL_APP_PASSWORD=...
MARKETING_DAILY_LIMIT=450

# Domínio de produção
FRONTEND_URL=https://plumora.com.br
SECRET_KEY=<openssl rand -hex 32>

# MinIO em produção (ou S3)
MINIO_URL=https://storage.plumora.com.br
MINIO_USER=<usuário seguro>
MINIO_PASSWORD=<senha segura>
```

---

## Decisões Técnicas Registradas

| Decisão | Escolha | Motivo |
|---|---|---|
| Hash imutabilidade | SHA256 por registro | Prova de não-adulteração sem blockchain |
| CPF validation | Algoritmo oficial (dois dígitos verificadores) | Sem dependência externa |
| Genética pombo | Haplótipos ZW (Univ. Utah / Shapiro 2014) | Base científica validada |
| PDF pedigree | ReportLab (básico) | WeasyPrint reservado para futuro visual |
| Bcrypt | custo=12 (≈200ms) | Segurança vs. UX balanceados |
| Timezone | UTC no banco, formatar no frontend | Evita problemas com fuso horário |
| Roles | admin / vet / breeder | RBAC simples e suficiente para v1 |
| i18n | PT-BR (default) + EN-US | Expansão internacional planejada |

---

## Referências Científicas

- **Tyrp1** (cor pombo): Domyan et al., *Science* 2014
- **Slc45a2** (diluído pombo): Domyan et al., *Science* 2014
- **EphB2** (crista pombo): Shapiro et al., *Science* 2013
- **Coeficiente Wright**: Wright, S. (1922). *Coefficients of inbreeding and relationship*
- **Genética psitacídeos**: Genetics of Colour in Parrots, Turnbull/Müller

---

*Documento mantido pela equipe CSVET · Atualizar após cada sprint*
