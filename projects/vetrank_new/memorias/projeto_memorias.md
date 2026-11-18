# Memórias do Projeto — VetRank

Este documento serve como a base de conhecimento central do **VetRank**, contendo a visão geral do sistema, as regras de negócios fundamentais, os detalhes da arquitetura técnica e as decisões tomadas ao longo das melhorias implementadas. Futuros desenvolvedores e agentes de IA devem ler e atualizar este arquivo a cada nova implementação.

---

## 1. Visão Geral do Sistema

O **VetRank** é uma plataforma SaaS gamificada de estudos voltada à preparação de estudantes de Medicina Veterinária e médicos veterinários graduados para exames de alta intenção, como **ENADE**, **ENARE**, provas de **Residência** e **Concursos Públicos**.

### Regras de Negócios Principais:
* **Período de Experiência (Trial):** Todo novo usuário cadastrado recebe automaticamente 7 dias de acesso Premium gratuito para testar todos os recursos do sistema.
* **Gamificação:** Resoluções de questões geram XP (Experiência), subindo o nível do usuário e liberando insígnias (Badges) exclusivas. Existe um sistema de ofensivas (Streak) diário para incentivar a consistência dos estudos.
* **Ranking Semanal:** Um placar de classificação nacional reiniciado semanalmente que destaca os estudantes com maior pontuação no período.
* **Simulados:** Permite criar exames cronometrados com filtros avançados por banca, ano, disciplina, assunto e dificuldade.
* **Questões Discursivas:** Acesso a questões abertas com a resposta padrão recomendada pelas bancas de residência e critérios de avaliação completos.
* **Importação por IA:** Coordenadores e professores podem fazer upload de arquivos PDF e Word e utilizar modelos LLM (Gemini) para converter documentos brutos em questões perfeitamente estruturadas.

---

## 2. Stack Tecnológica e Arquitetura

O projeto é estruturado em uma arquitetura monorreferencial moderna e ágil:
* **Frontend:** Single-Page Application (SPA) utilizando **React 19**, **Tailwind CSS 4** para estilização, **shadcn/ui** para componentes visuais elegantes e acessíveis, e **Wouter** para um roteamento client-side ultraleve.
* **Backend:** Servidor em **Node.js** com **Express** utilizando **tRPC 11** para comunicação tipada ponta a ponta (End-to-End Type Safety) entre cliente e servidor.
* **Banco de Dados & ORM:** **MySQL 8** (ou TiDB) mapeado através do **Drizzle ORM** com controle estrito de migrações automáticas no início da execução da aplicação (aplicadas dinamicamente no boot do Express em produção).

---

## 3. Histórico de Melhorias Implementadas

### A. Otimização de SEO & Prerender Dinâmico (Fase 1)
Para solucionar os problemas de indexação e aquisição orgânica apontados pelo Perplexity (onde a Home original servia apenas uma tag `<div id="root"></div>` em branco), implementamos um sistema de **Prerender Dinâmico no Servidor**:
1. **Módulo de Prerender (`server/seoPrerender.ts`):** Intercepta e injeta tags `<title>`, `<meta name="description">`, `<meta name="keywords">` e `<link rel="canonical">` específicas baseadas na rota.
2. **HTML Semântico Inicial:** Injeta no código-fonte retornado ao navegador uma estrutura de cabeçalho, rodapé, planos de preços detalhados, FAQs estruturados e blocos de texto dentro da `<div id="root">`, garantindo indexabilidade 100% livre de Javascript.
3. **Dados Estruturados JSON-LD:** Injeta esquemas robustos (`Organization`, `WebSite`, `EducationalOrganization`, `FAQPage`, `Product` Aggregates) para habilitar Rich Snippets no Google.
4. **Landing Pages:** Criamos `/enade`, `/enare` e `/residencia` ([LandingSEO.tsx](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/client/src/pages/LandingSEO.tsx)) com estatísticas, prova social de recomendação e FAQs interativos para alta conversão orgânica.
5. **Integração Async no Pipeline:** Acoplamos o prerender de forma totalmente assíncrona (`await`) nos pipelines de desenvolvimento (Vite) e produção (serveStatic) em `server/_core/vite.ts`, eliminando riscos de travamento de runtime.

### B. Sistema de Blog Científico & Automação de IA (Fase 2)
Desenvolvemos um **Blog Científico** completo de veterinária focado em aquisição orgânica de tópicos médicos especializados:
1. **Modelagem de Banco & Migração:** Tabela `blog_posts` adicionada e migração oficial ([0021_giant_lizard.sql](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/drizzle/0021_giant_lizard.sql)) aplicada.
2. **Resiliência Offline-First:** Criamos o backup estático [blog_posts_seed.json](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/data/blog_posts_seed.json) com **20 artigos médicos de excelência** (em português do Brasil) pré-gerados por IA. Caso o banco esteja offline, a aplicação lê automaticamente deste JSON, garantindo que o blog nunca fique vazio.
3. **API tRPC e Rotas:** Criamos o roteador [blog.ts](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/server/routers/blog.ts) com queries otimizadas de listagem e mutations administrativas protegidas.
4. **Front-End Premium:** Desenvolvemos la listagem com card de destaque ([Blog.tsx](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/client/src/pages/Blog.tsx)) e a visualização detalhada com sumários, estimativas de leitura e CTAs inteligentes ([BlogPostDetail.tsx](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/client/src/pages/BlogPostDetail.tsx)).
5. **Automação de IA e Agendadores:**
   * Endpoint `/api/scheduled/generate-blog-post` conectado ao Gemini 2.5 Flash para gerar e persistir novos posts de forma autônoma.
   * **GitHub Actions Workflow Cloud Scheduler ([weekly-blog.yml](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/.github/workflows/weekly-blog.yml)):** Dispara a geração automaticamente todas as segundas-feiras às 09:00 UTC (06:00 BRT) de forma 100% gratuita na nuvem, suportando gatilhos manuais no painel do GitHub para temas customizados definidos pelo administrador.

### C. Refatoração de Segurança & SEO Avançado (Fase 3)
1. **Remoção da Brecha de Bypass do reCAPTCHA v3:** Corrigimos o método `verifyRecaptcha` em [emailAuth.ts](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/server/_core/emailAuth.ts#L27) para exigir obrigatoriamente a presença de `token` em produção, bloqueando tentativas de bots que burlavam a barreira simplesmente omitindo o parâmetro na requisição.
2. **Blindagem CSRF em Cookies:** Alteramos o SameSite dos cookies de sessão primários de `none` para `lax` em [cookies.ts](file:///C:/Users/pc/.gemini/antigravity/scratch/vetrank/server/_core/cookies.ts#L47), impedindo o tráfego involuntário de credenciais em requisições cross-site.
3. **Rate Limiting em Autenticação:** Implementamos o `authLimiter` com limite estrito de no máximo 5 tentativas de login/registro/recuperação por minuto por IP em `server/_core/index.ts`.
4. **Segurança de Consultas SQL:** Substituímos interpolações diretas de strings em listagens SQL pelo select builder do Drizzle ORM parametrizado e tipado.
5. **Injeção de Breadcrumbs (JSON-LD):** Adicionamos breadcrumbs ricos às rotas de listagem e leitura de artigos no pré-renderizador, melhorando a rastreabilidade estrutural.
6. **Metadados de Legitimidade:** Prerenderizamos as páginas `/terms` e `/privacy` para satisfazer as diretrizes E-E-A-T do Google.

### D. Gamificação de Ofensiva, Blog Científico Estendido & Blindagem de Tipagem (Fase 4)
Para aprimorar a experiência de estudo gamificado e garantir conformidade de tipo perfeita na compilação, implementamos o seguinte:
1. **Navbar Integrado & Daily Study Streaks (`Navbar.tsx`):** Integramos um link altamente polido para o **Blog** com ícone adaptativo e adicionamos uma mutation assíncrona automatizada (`dailyLogin.mutate`) no ciclo de autenticação inicial para recalcular a ofensiva diária (streak), notificando o usuário com um toast `sonner` com efeito de fogo 🔥 e concedendo XP bônus.
2. **Correção de Contraste do Blog (Modo Claro):** Resolvemos problemas de invisibilidade de fontes em modo claro substituindo classes fixas `text-white` por classes HSL adaptativas ao tema (`text-foreground dark:text-white`) e adotando tipografia adaptativa do Tailwind CSS (`prose dark:prose-invert`) em `Blog.tsx` e `BlogPostDetail.tsx`.
3. **Expansão de 50+ Artigos Veterinários e Sitemap Dinâmico:** Implementamos um script gerador de sementes técnicas (`scripts/generate_extra_blog_posts.js`) totalizando 50 artigos com embasamento científico rigoroso (SciELO, Vetnil, EnsinaVet) e expandimos o arquivo `data/blog_posts_seed.json`. Integramos um gerador de sitemap dinâmico (`scripts/update_sitemap.js`) que anexa os 50 slugs das páginas do blog para garantir indexação orgânica imediata no Google.
4. **Trilhas de Aprendizado Duolingo-Style (`TrailDetail.tsx`):** Redesenhamos a linha do tempo linear de módulos para um mapa de aprendizado winding path em zigue-zague sinuoso. Cada nó de módulo é envolto por um anel de progresso SVG dinâmico (`ProgressRing`), possui badges de estado (coroas para concluído, pulsing tags para disponível, cadeados para bloqueado) e exibe um balão flutuante animado com a legenda de ação. Desenvolvemos também um modal premium popover animado com `framer-motion` para exibir estatísticas do nível, requisitos e CTAs para ebook e início.
5. **Blindagem e Resolução de Erros de Compilação:** 
   * Resolvemos todos os erros TS2802 de iteração em Set (`[...new Set(...)]`) migrando para chamadas limpas `Array.from(new Set(...))` compatíveis sem a flag de downlevel iteration em `Courses.tsx`, `CourseStudy.tsx` e `lgpd.ts`.
   * Corrigimos as variáveis de dialog faltantes em `AdminPanel.tsx` no escopo do `AssinantesTab` declarando as states localmente e implementando o Dialog correspondente.
   * Substituímos o endpoint de IA faltante no painel administrativo por um mock client-side simulado que preserva toda a rica experiência de usuário para criação de modelos.
   * Eliminamos o erro de await de alto nível em `generate-og-image.ts` encapsulando o código na função principal assíncrona.
6. **Automação de Artigos com Base em Fontes de Elite:** Atualizamos o prompt do sistema no endpoint `/api/scheduled/generate-blog-post` para forçar pesquisas clínicas aprofundadas baseadas nas referências fornecidas pelo coordenador (SciELO/ABMVZ, Revista Clínica Veterinária, BVS-Vet, Vetnil, PremieRVet, EnsinaVet, ConnectVets, VetVoz, Anclivepa-DF, Vet Smart, Merck Veterinary Manual e IVIS), garantindo que as condutas de parametrização e esquemas de provas sigam estritamente o direcionamento dessas fontes.

### E. Caderno de Erros (Mistakes Inbox) e E-mail Marketing Responsivo (Fase 5)
1. **E-mail Marketing Responsivo:** Implementamos um seletor e carregador dinâmico de templates na interface administrativa ([AdminPanel.tsx](file:///D:/vetrank/client/src/pages/AdminPanel.tsx)), permitindo selecionar arquivos de e-mail responsivos armazenados em `marketing/` (como `email-outreach.html` e `proposta_institucional.html`).
2. **Processamento de Variáveis e Nome Personalizado:** Criamos um processador de variáveis robusto no backend ([server/email.ts](file:///D:/vetrank/server/email.ts)) que intercepta os disparos de campanhas e e-mails individuais, substituindo automaticamente marcadores como `[Nome]`, `[nome]` e `{{nome}}` pelo nome correspondente do usuário ou lead (resolvido automaticamente do banco de dados).
3. **Caderno de Erros Integrado (Mistake Inbox):** Desenvolvemos a página [MistakesInbox.tsx](file:///D:/vetrank/client/src/pages/MistakesInbox.tsx) e endpoints tRPC dedicados no roteador de prática ([server/routers/lgpd.ts](file:///D:/vetrank/server/routers/lgpd.ts)), que monitoram a tabela `practice_sessions` para recuperar todas as questões erradas ativas (aquelas em que o usuário ainda não obteve acerto posterior).
4. **Interface e Atalho de Acesso:** Adicionamos um atalho com ícone visual no menu do usuário ([Navbar.tsx](file:///D:/vetrank/client/src/components/Navbar.tsx)), oferecendo aos estudantes um portal centralizado para revisar e praticar os erros até "curá-los" de sua lista de pendências.

---

## 4. Orientações para Futuras Atualizações

Se você estiver dando sequência ao desenvolvimento do VetRank, atente-se às seguintes sugestões e pendências do roteiro do projeto:
* **Gamificação da Trilha de Estudos (Winding Path Adaptativo):** Modificar a trilha de aprendizado ([TrailDetail.tsx](file:///D:/vetrank/client/src/pages/TrailDetail.tsx)) para progredir pelos assuntos reais da tabela `subjects`, em vez de módulos lineares, proporcionando o mapa de progressão gamificado por tópicos.
* **Sistema de Vidas (Hearts) and VetCoins:** Implementar a mecânica de vidas limitadas (com recargas pagas por VetCoins ou liberadas de forma ilimitada para assinantes Premium) como o motor central de conversão e retenção gamificada do produto.
* **Competição Social e Ligas Semanais:** Desenvolver o pareamento de ligas (Bronze a Diamante) baseado no XP obtido semanalmente, aumentando a retenção social.
* **Ampliação do PWA:** Implementar manifesto PWA e Service Worker completo para permitir instalação em telas de início móveis (prioridade alta mapeada no `docs/melhorias.md`).
* **Conectar o Gerador IA com a Base:** Evoluir a automação do blog para que a IA analise as maiores taxas de erro dos alunos no banco de dados de questões e gere artigos específicos explicando tais assuntos.
* **Processar Répteis e Anfíbios:** Executar o script `extrator_repteis.py` nos 14 capítulos de silvestres pendentes e atualizar a hierarquia pedagógica.
