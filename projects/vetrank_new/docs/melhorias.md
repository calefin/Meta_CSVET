# VetRank — Melhorias e Implementações

Lista de funcionalidades planejadas, melhorias e débitos técnicos.
Prioridade: Alta / Média / Baixa.

---

## UX / Frontend

| # | Descrição | Prioridade | Status |
|---|-----------|------------|--------|
| 1 | **PWA — Instalação como app nativo** | Alta | Pendente |
| 2 | Modo offline básico (simulados em cache) | Média | Pendente |

### PWA — Instalação como app nativo

Permitir que o usuário instale o VetRank no celular como se fosse um app nativo (ícone na tela inicial, sem barra de endereço, tela cheia).

**O que precisa:**
- `manifest.json` com `name`, `short_name`, `icons` (192px e 512px), `start_url`, `display: standalone`, `theme_color`
- Service Worker registrado (mínimo: cache do shell da aplicação)
- Meta tags no `index.html`: `apple-mobile-web-app-capable`, `apple-touch-icon`, `theme-color`
- Banner de instalação customizado (interceptar evento `beforeinstallprompt`) ou instrução "Adicionar à tela inicial"

**Comportamento esperado:**
- Android (Chrome): prompt automático ou botão "Instalar app" na interface
- iOS (Safari): instrução manual "Compartilhar → Adicionar à Tela de Início"
- Desktop (Chrome/Edge): ícone de instalação na barra de endereço

**Referências:** Vite PWA Plugin (`vite-plugin-pwa`) simplifica o setup — gera manifest e service worker automaticamente.

---

## Conteúdo / Questões

| # | Descrição | Prioridade | Status |
|---|-----------|------------|--------|
| 3 | Atualizar subjectTag das questões de fisiologia importadas (subjectId 30007) | Alta | Pendente (tagger_questions.py pronto, rodar no VPS) |
| 4 | Importar questões de répteis e anfíbios geradas pelo extrator_repteis.py | Alta | Pendente |
| 5 | Expandir hierarquia: Anatomia, Bioquímica, Patologia Geral | Média | Pendente |

---

## Backend / Infra

| # | Descrição | Prioridade | Status |
|---|-----------|------------|--------|
| 6 | Filtros de questão por subjectTag na API (simulados por sistema fisiológico) | Média | Pendente |
| 7 | Relatório de desempenho por sistema fisiológico / subjectTag | Média | Pendente |

---

## Scripts / Ferramentas

| # | Descrição | Prioridade | Status |
|---|-----------|------------|--------|
| 8 | extrator_repteis.py — rodar para todos os 14 capítulos de D:/pdfs/repteis | Alta | Pendente |
| 9 | tagger_questions.py TAG_ONLY — rodar no VPS para fisiologia (subjectId 30007) | Alta | Pendente |
