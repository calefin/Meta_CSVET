# 📊 Diário de Bordo & Progresso dos Projetos (Meta_CSVET)

Este documento centraliza o **status, roadmap, backlog e histórico de evolução** de cada um dos repositórios que compõem o ecossistema CSVET.

---

## 📌 Painel Geral de Status (Dashboard)

| Projeto | Domínio / Área | Stack Principal | Status Atual | Prioridade |
| :--- | :--- | :--- | :---: | :---: |
| **`csvet.com.br`** | Institucional & Editora | HTML5 / CSS3 / JS / Schema.org | 🟢 Produção | Alta |
| **`journals`** | Editorial & Periódicos | PHP / OJS 3.5 LTS / Docker / MySQL | 🟢 Produção | Alta |
| **`books_csvet`** | Livros & Monografias | PHP / OMP 3.5 LTS / Docker / MySQL | 🟡 Em Implantação | Alta |
| **`vetrank_new`** | Educação Veterinária | TypeScript / React / Node.js | 🟡 Desenvolvimento Ativo | Média |
| **`ProvaGen`** | Avaliações & Ensino | TypeScript / React / Node / Express / tRPC | 🟡 Desenvolvimento Ativo | Alta |
| **`provagen_cell`** | Leitura OMR Mobile | Flutter / Dart / OpenCV | 🟡 Em Desenvolvimento | Alta |
| **`plumora_gen`** | Avicultura & Pedigree | TypeScript / React / Python | 🟡 Em Desenvolvimento | Média |
| **`medvetlegal_producao`** | Perícias Veterinárias | PHP / Laravel / Web | 🟢 Produção | Média |
| **`profatilio`** | Portal do Professor | PHP / Laravel | 🟡 Em Atualização | Média |
| **`skills`** | IA & Produtividade | Markdown / Prompts / AGY Skills | 🟢 Ativo | Alta |
| **`ijoifa`** | Espiritualidade & Tradição | Laravel 12 / Livewire 3 / Tailwind 3 | 🟢 Produção | Média |
| **`odu_journey`** | App Cultural/Espiritual | Flutter / Dart | ⚪ Fase Inicial | Baixa |
| **`bagparser`** | Utilitário Gamer | Web / Vanilla JS / Browser Parsing | 🟢 Estável | Baixa |

*Legenda de Status:*
- 🟢 **Produção / Estável:** Em operação contínua.
- 🟡 **Desenvolvimento Ativo:** Novas features sendo implementadas ativamente.
- ⚪ **Fase Inicial / Ideação:** Estrutura base montada, aguardando sprint.
- 🔴 **Bloqueado:** Dependendo de recurso ou migração crítica.

---

## 📝 Como Registrar o Progresso

Sempre que concluir uma funcionalidade, correção ou marco em qualquer projeto:
1. Localize a seção do projeto abaixo.
2. Adicione uma linha no **Histórico de Alterações** no formato:
   `- [AAAA-MM-DD] Resumo da alteração (Autor/Agente)`.
3. Atualize os **Próximos Passos** marcando as tarefas com `[x]` ou adicionando novas `[ ]`.
4. Comite as alterações no `Meta_CSVET`:
   ```bash
   git add docs/PROGRESS.md
   git commit -m "docs: atualiza progresso de <nome-do-projeto>"
   git push origin main
   ```

---

## 📂 Detalhamento por Projeto

### 1. `csvet.com.br` (Site Institucional CSVET)
- **Objetivo:** Portal de autoridade da marca, dados estruturados institucionais e hub de direcionamento para periódicos e plataformas.
- **Próximos Passos:**
  - [ ] Revisão de SEO técnico e validação de schema `Organization`.
  - [ ] Integração de links dinâmicos para novas publicações do `journals`.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 2. `journals` (journals.csvet.com.br)
- **Objetivo:** Portal de periódicos científicos da CSVET Editora baseado em Open Journal Systems (OJS 3.5 LTS).
- **Próximos Passos:**
  - [ ] Monitoramento de submissões e automação de plugins de tradução com `skills`.
  - [ ] Otimizações no Docker de produção e rotinas de backup.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 3. `books_csvet` (books.csvet.com.br)
- **Objetivo:** Portal de publicação de livros e monografias da Editora CSVET baseado em Open Monograph Press (OMP 3.5 LTS).
- **Próximos Passos:**
  - [ ] Concluir customizações de identidade visual espelhando o `journals`.
  - [ ] Homologação do fluxo editorial completo.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 4. `vetrank_new` (VetRank)
- **Objetivo:** Plataforma de questões comentadas e preparação para provas de graduação, residência e especialização em Medicina Veterinária.
- **Próximos Passos:**
  - [ ] Expansão do banco de questões e filtros por especialidade.
  - [ ] Refatoração das estatísticas de desempenho do aluno.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 5. `ProvaGen` (Plataforma Web de Provas e Gabaritos)
- **Objetivo:** Criação, gestão, diagramação de provas (PDF) e correção automatizada para docentes.
- **Próximos Passos:**
  - [ ] Validação do fluxo de integração com o app móvel `provagen_cell`.
  - [ ] Refino na geração de matrizes de resposta OMR nos PDFs.
- **Histórico de Alterações:**
  - [2026-11-18] Sanitização de chaves sensíveis do Google OAuth em `.env.example` e documentação.
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 6. `provagen_cell` (App Mobile de Leitura OMR)
- **Objetivo:** Aplicativo Flutter offline-first para leitura óptica e correção de gabaritos através da câmera com OpenCV.
- **Próximos Passos:**
  - [ ] Calibração da detecção de âncoras na imagem pelo OpenCV em diferentes iluminações.
  - [ ] Sincronização offline com o backend do `ProvaGen`.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 7. `plumora_gen` (Plumora Gen)
- **Objetivo:** SaaS de gestão de criatórios de aves, pedigree genético (claudograma) e suporte veterinário PigeonVet.
- **Próximos Passos:**
  - [ ] Modelagem do cálculo de coeficiente de consanguinidade (pedigree).
  - [ ] Implementação das fichas de controle de anilhas e vacinas.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 8. `medvetlegal_producao` (MedVet Legal)
- **Objetivo:** Sistema de Medicina Veterinária Legal, laudos periciais e apoio a médicos veterinários peritos.
- **Próximos Passos:**
  - [ ] Revisão de templates de laudos periciais.
  - [ ] Sincronização de recursos com a produção.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 9. `profatilio` (Portal Prof. Atilio)
- **Objetivo:** Portal acadêmico e institucional das atividades de ensino e pesquisa do Prof. Atilio.
- **Próximos Passos:**
  - [ ] Atualização do stack Laravel e integração com Lattes/ORCID.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET` (branch `master`).

---

### 10. `skills` (Skills Antigravity IA)
- **Objetivo:** Conjunto de habilidades de IA para normatização ABNT, integridade científica, tradução e revisão editorial de artigos.
- **Próximos Passos:**
  - [ ] Adicionar novas regras de conformidade para o fluxo de revisão do `journals`.
  - [ ] Padronizar prompts de extração de metadados Crossref e DOI.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 11. `ijoifa` (Ìjọ Ifá Èjìogbè)
- **Objetivo:** Sistema de gestão e plataforma da comunidade e casa de Ifá (Laravel 12 + Livewire 3).
- **Próximos Passos:**
  - [ ] Gestão de consultas, oráculos e histórico cerimonial.
  - [ ] Otimizações no painel Livewire.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 12. `odu_journey` (App Odu Journey)
- **Objetivo:** Aplicativo Flutter para jornadas de estudo e navegação no conhecimento tradicional dos Odùs de Ifá.
- **Próximos Passos:**
  - [ ] Definição da UI/UX inicial e fluxo de navegação offline.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.

---

### 13. `bagparser` (BagParser)
- **Objetivo:** Utilitário client-side de análise de logs de caça e divisão de loot de party hunt no Tibia.
- **Próximos Passos:**
  - [ ] Atualização da tabela de itens para novas versões do jogo quando necessário.
- **Histórico de Alterações:**
  - [2026-11-18] Centralizado no meta-repositório `Meta_CSVET`.
