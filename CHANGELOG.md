# Changelog — profatilio.com.br

Registro de versões/implementações deste sistema, para consulta rápida do que
ainda precisa ser importado/portado para o **prof-aid.com** (fork comercial —
ver `SAAS_PLANNING.md`). Cada entrada tem uma especificação completa em
`docs/specs/`, com o detalhe técnico de arquivos alterados e passo a passo de
importação.

Marque a caixa quando a mudança já tiver sido replicada no prof-aid.com.

## 2026-11-12

- [ ] **Resolução de RGM com Dígito de Campus, Suporte ao Caderno Rinoceronte (São Miguel Noite), Consolidação de Fisiologia e Novo UX Docente de Gabaritos.**
  - **Tolerância a Dígitos Extras de Campus no RGM (`ExamMirrorController::generateRgmCandidates`)**:
    - Alunos frequentemente preenchem o RGM com 9 dígitos (incluindo código de campus ou dígito verificador adicional). O sistema agora deriva múltiplos candidatos em memória (removendo prefixo de campus, zeros à esquerda ou sufixo verificador), testando os HMACs SHA256 contra os cofres criptografados sem expor dados.
  - **Caderno Rinoceronte em São Miguel Noite (`2026_2_silvestres_p2.json`)**:
    - Adicionado suporte e alias `A_RHINO` para o caderno 犀牛 (xīniú — rinoceronte), garantindo que alunos que selecionem "Versão A" ou "Versão B (Rinoceronte)" visualizem as dicas corretas de Q1 a Q3 e acessem seu cofre descriptografado com 100% de precisão.
  - **Consolidação de Provas e Alunos em Trânsito**:
    - Inclusão dos 69 alunos de Fisiologia Animal II A2.1 (Paulista Manhã e Noite, escala 0 a 3,50), incluindo aluna em trânsito Laura Trancozo (47605839).
    - Inclusão da aluna em trânsito Isabelle Cerconvis (33735395) sob São Miguel Manhã com a versão B_GRU (caderno Ouriço) e badge de identificação em sala especial.
    - Limpeza de 8 arquivos JSON fragmentados de turmas individuais em `storage/app/exam_mirrors/`.
  - **Novo Catálogo Centralizado de Gabaritos Oficiais (`gabaritos_oficiais.json`)**:
    - Catálogo pedagógico completo com 115 KB abrangendo todas as versões de Fisiologia II e Silvestres P2 com enunciado, alternativa correta, eixos temáticos, temas e justificativas gabaritadas.
  - **Redesenho do UX no Painel do Professor (`GerenciarEspelhosProva`)**:
    - Abas navegáveis no Filament: *Gabaritos Oficiais Comentados*, *Vista de Prova por Aluno* (com comparativo lado a lado Aluno vs Oficial), *Pacotes / Turmas* e *Semestres*.
    - Filtros por disciplina, turma, versão e alternativa.
  - **Espelho Público do Aluno**:
    - Eliminação de ambiguidade em provas puramente objetivas (Card 2 exibe taxa e percentual de aproveitamento objetivo em vez de duplicar a nota do Card 1).
    - Renderização de feedback pedagógico geral e badges para alunos em trânsito.

## 2026-11-09

- [ ] **Aprimoramento da Calibração de Triagem de Revisões Sistemáticas (Conformidade OSF: Suporte ao Kappa Binário e de 3 Categorias)**.
  - **Diagnóstico e Resolução do Paradoxo da Prevalência (Feinstein & Cicchetti)**:
    - Em triagem de títulos e resumos, a prevalência extremamente baixa de artigos elegíveis na base de dados bruta penalizava desproporcionalmente o Kappa de Cohen tradicional em matriz 3x3 (*Include* vs *Exclude* vs *Maybe*), gerando falsos impasses de $\kappa < 0.60$ em duplas com concordância bruta superior a 90%.
  - **Validação pelo Protocolo Registrado no OSF (Item 11b)**:
    - O protocolo registrado determina que qualquer voto *Maybe* ou *Include* avança compulsoriamente para leitura de texto completo. Portanto, a decisão de elegibilidade preliminar na triagem T&A é operacionalmente binária (*Avançar* vs *Excluir*).
  - **Implementações Técnicas (`SystematicReviewService.php`, `SystematicReviewResource.php` e `ViewSystematicReview.php`)**:
    - Novos métodos `pilotKappaBinary()` e `pilotEffectiveKappa()`: O gate de liberação da triagem passa a considerar o Kappa efetivo (maior entre o de 3 categorias e o binário), liberando duplas aptas pelo critério do protocolo registrado sem exigir justificativas desnecessárias.
    - Exibição de ambos os índices no painel administrativo do Filament com distinção visual: κ (3 categorias), κ binário (OSF), concordância percentual bruta e selo indicativo de aptidão OSF.
    - Modal de liberação auditável atualizado com fundamentação metodológica (PRISMA-P / OSF) gravada no histórico de eventos (`logEvent`).
    - Orientações e regras de validação sincronizadas em `StageGuides.php`.

## 2026-11-07

- [ ] **Google AdSense nas páginas públicas** — `public/ads.txt`, partial `partials/adsense` (só em produção) incluída em home, blog, Na Mídia, necropsia, Normas ABNT e perfil do orientador; ID em `services.adsense.client` (`ADSENSE_CLIENT`). Spec: `docs/specs/2026-11-07-google-adsense.md`.

## 2026-11-05

- [ ] **Importador Direto de Planilhas e Lançamento de Alunos sem Scripts + Conformidade LGPD Completa em Devolutivas.**
  - **Importação Direta de Planilhas (CSV/TSV/Excel) no Painel Filament (`/admin/gerenciar-espelhos-prova`)**:
    - Elimina a dependência de scripts Python locais (`exportar_espelhos_site.py` / `exportar_devolutivas_empreendedorismo.py`) com caminhos fixos de máquina.
    - O professor pode carregar um arquivo `.csv`/`.tsv` exportado do Excel ou ZipGrade, ou simplesmente colar o texto das linhas copiadas da planilha diretamente no modal da página administrativa.
    - Criptografia Zero-Knowledge em memória (PHP nativo com HMAC-SHA256 e AES-256-CBC) calculada na hora, atualizando os pacotes de Trabalho Acadêmico e Avaliação Prova instantaneamente.
  - **Inclusão / Ajuste Manual de Aluno Avulso**:
    - Modal interativo para lançar nota de aluno faltante, revisão ou segunda chamada diretamente pela interface administrativa, gerando as chaves anônimas e salvando no cofre sem necessidade de comandos de terminal ou scripts externos.
  - **Remoção de Códigos Numéricos de Oferta (LGPD Art. 12)**: Eliminação rigorosa de qualquer menção ou exposição de números de oferta técnica de turmas em títulos de pacotes, seletores públicos e espelhos de prova.
  - **Padronização de Siglas de Campus e Turnos**: Todas as disciplinas passaram a ter atribuição explícita de campus (`PTA` para Paulista, `SM` para São Miguel, `GRU` para Guarulhos, `AF` para Anália Franco e `VL` para Villa Lobos) e turnos padronizados (`Matutino` e `Noturno`), contemplando `Empreendedorismo e Inovação`, `Medicina dos Animais Silvestres` e `Fisiologia Animal II`.
  - **Disclaimer Obrigatório de Finalidade Educacional e Nota Oficial**: Inclusão de avisos proeminentes na página pública de consulta (`/consulta-avaliacao`) e nos resultados de devolução (Trabalhos e Provas), esclarecendo que a nota oficial válida é exclusivamente a da **Área do Aluno** da universidade e que a plataforma possui caráter meramente pedagógico/educacional.
  - **CRUD Administrativo no Filament (`/admin/gerenciar-espelhos-prova`)**:
    - Grupo de navegação `Gestão Acadêmica & Devolutivas` com destaque no topo do menu.
    - Edição inline de metadados de pacotes (disciplina, campus, avaliação, ano, semestre, escala, e gestão de turmas com adição e remoção).
    - Inicialização de novas disciplinas pré-configuradas (ex.: Fisiologia Animal II).
    - Inspeção/visualização detalhada de pacotes publicados e exclusão com segurança.
  - **Testes Automatizados**: Suíte `ExamMirrorTest` expandida para 9 testes passando com 64 asserções (verificando disclaimer, ausência de códigos de oferta, campus, CRUD de admin, importação de CSV sem script e lançamento manual de aluno avulso). Suíte completa verificada com 161 testes passando.

## 2026-11-04

- [ ] **Módulo de Devolutivas Seguras de Trabalhos & Avaliações (Zero-Knowledge / LGPD) com Bloqueio de Semestre Anterior.**
  - **Suporte a Trabalhos Acadêmicos**: Implementada consulta segura de trabalhos práticos em grupo (ex.: Empreendedorismo e Inovação — Canvas e 5W2H) com base na planilha oficial. Chave derivada anônima por `Package_ID` + `RGM` + `Primeiro Nome` (sem senhas e 100% aderente ao Art. 12 da LGPD).
  - **Definição de Ano Letivo e Semestre**: Pacotes e interface agora possuem especificação clara de ano letivo e semestre (`ano` e `semestre`, ex.: `2026` e `2026.2`). Seletor reativo de ano/semestre no topo da página de consulta pública (`/consulta-avaliacao` e `/devolutivas`).
  - **Bloqueio de Semestres Anteriores Encerrados**: Possibilidade de encerrar o semestre no painel Filament (`/admin/gerenciar-espelhos-prova`) com 1 clique; semestres encerrados bloqueiam imediatamente a visualização de espelhos antigos para os alunos, mantendo liberado apenas o semestre vigente.
  - **Visualização Formativa do Trabalho**: Cards de nota (A2, Trabalho e P-BB online), detalhamento dos critérios oficiais (Proposta de Valor, Canvas, 5W2H) com barras de progresso, e seções de feedback formativo ("🌟 Destaques Positivos & Pontos Fortes" e "💡 Oportunidades de Melhoria").
  - **Design & Contraste Acadêmico**: Navbar com fundo escuro sólido (`#030712`) nas páginas de devolutiva, eliminação de textos acinzentados substituídos por azul marinho de alto contraste (`text-blue-950` e `text-blue-900`), e limpeza de códigos técnicos de turmas.
  - **Integração de Notas Consolidadas**: Pacotes gerados e validados para Silvestres P1, Silvestres P2 consolidado (Paulista Manhã e Noite) e Empreendedorismo A2 (254 alunos, 747 cofres criptografados).
  - **Testes Automatizados**: Suíte `ExamMirrorTest` expandida com 6 testes (carregamento por ano, descriptografia de prova, descriptografia de trabalho, bloqueio de chutes e bloqueio de semestre encerrado). Suíte global com 155 testes passando (1961 asserções).

## 2026-11-03
- [ ] **Triagem Cega: contador de analisados junto do trabalho.** Spec: `docs/specs/2026-11-03-triagem-contador-analisados.md`
  - "Trabalho N de 50" mostrava a posição na ordem sorteada, que salta ao pular os já votados (2 → 17). Por isso parecia ordem errada.
  - Agora o cartão do trabalho e a barra fixa dos botões mostram "Analisados X de Y · faltam Z". No piloto, a conta é por meta (25 + 25). Uma frase explica que a ordem é sorteada e que o sistema pula os votados. Não há migration.
- [ ] **Envio "somente assinatura" com Projeto de TCC e TCC Final.** Spec: `docs/specs/2026-11-03-assinatura-projeto-tcc-e-tcc-final.md`
  - Tipos novos `Projeto de TCC (Assinatura)` e `TCC Final (Assinatura)`. Antes os alunos não os achavam, mandavam como "Outro" e caíam no fluxo de revisão.
  - O checkbox "apenas para assinatura" virou 3 botões no topo do envio: **Para assinar**, **Para corrigir** e **Só para registro** (notas de estágio, certificado de evento). Cada botão filtra o "Tipo de Documento", que só aparece depois da escolha.
  - Campos de arquivo em grid próprio: o do **aluno fica sempre à esquerda** e a **devolução do professor sempre à direita**, com o mesmo destaque visual.
  - Constante `DocumentSubmission::SIGNATURE_ONLY_TYPES` centraliza a lista. Fluxo igual ao do Plano de Estágio (triagem → aguardando assinatura → documento assinado disponível), sem status novo nem migration.
- [ ] **TCC Completo: "Documento Assinado — mas precisa de correções".** Spec: `docs/specs/2026-11-03-tcc-completo-assinado-com-correcoes.md`
  - Status novo, que não conclui o processo: o aluno continua corrigindo e as devoluções seguem normalmente.
  - Exige anexar um PDF assinado novo. Ele é validado como os de conclusão e fica registrado à parte (`metadata.signed_final_file` e o histórico), com nome próprio que as próximas devoluções não sobrescrevem.
  - Download em `/documento/download/{id}/signed_final`, link "Versão Assinada Registrada" no processo e e-mail e Telegram com texto próprio.

## 2026-11-02

- [ ] **Triagem Cega: checklist PICO, travas de lógica e tradução de apoio.** Spec: `docs/specs/2026-11-02-triagem-checklist-traducao.md`
  - **Checklist do protocolo:** perguntas em português por projeto (`database/data/screening_checklists.json`, carregado com `php artisan metanalises:checklists`), cada uma ligada a um código de exclusão. O aluno responde Sim / Não / Não dá para saber, e a tela indica a decisão a partir das respostas DELE (Não → Excluir com o motivo destacado; Não dá para saber → Talvez; tudo Sim → Incluir).
  - **Travas:** os botões só decidem com o checklist completo e pedem confirmação quando a decisão contradiz as próprias respostas. As respostas ficam gravadas no voto (`systematic_review_decisions.checklist`) para a reunião de calibração. Migration nova.
  - **Tradução:** "Ver em português" usa o tradutor embutido do Chrome (no aparelho, nada é gravado), com link para o Google Tradutor quando o navegador não tem. Os motivos genéricos ganham uma tradução abaixo.
  - **E-mail da liberação:** deixa claro que é a triagem oficial e que a calibração (piloto) também conta.

## 2026-11-01

- [ ] **Necropsia: aviso imediato, link direto e SEO centrado no Prof. Atilio.** Spec: `docs/specs/2026-11-01-necropsia-avisos-link-seo.md`
  - **Avisos:** o orçamento (passo 1) agora pede nome e WhatsApp e avisa na hora por e-mail síncrono e Telegram. O aviso da autorização assinada saiu do `try` do PDF. Há honeypot contra robôs.
  - **Painel:** badge "aguardando retorno", coluna "Retorno", ações "WhatsApp", "Link do termo" e "Respondido". Campos do termo só são obrigatórios quando o status é "authorized". O resumo diário lista os orçamentos sem retorno. Migration `contacted_at`.
  - **Link fácil:** `/autorizar-necropsia` para quem já combinou, e a ação "Termo com valor combinado" no painel.
  - **Termo:** "laboratório" trocado por "patologista responsável".
  - **SEO:**
    - página nova `/necropsia-veterinaria` com Service, FAQ e Breadcrumb, sem caracterizar laboratório;
    - meta tags em `/solicitar-necropsia` e `/verificador`;
    - `noindex` nas páginas com token ou dados pessoais;
    - recibos de IC fora do sitemap.

## 2026-10-27

- [ ] **Barra colorida da esteira, prazo da busca e regras do papel.**
  - **Regra das etapas:** `App\Support\ProgramJourney` deduz a etapa de cada projeto do estado real: Preparação → Registro OSF → Busca → Calibração → Triagem → Texto completo → Extração → Análise → Artigo.
  - **Aluno:** barra verde/azul/cinza com "Agora" e "Você" (pelo papel), no Painel e no projeto. Na tela de triagem fica a versão compacta (`partials/sr-journey`).
  - **Central:**
    - barra em miniatura na coluna Etapa;
    - prazo da busca, que não pode começar antes de 01/11 e vai até 14 dias depois do registro, em verde, amarelo ou vermelho, com o caso de janela impossível;
    - cards "registrados" e "buscas com prazo de 3 dias ou menos".
  - **Aviso diário:** `app:alertar-prazo-busca` roda às 07:45 e manda e-mail e Telegram ao professor só quando faltam 3 dias ou menos ou o prazo venceu.
  - **Regras do papel:** a Triagem Cega ganhou o quadro "✅ O que você pode e ❌ o que não pode", com a versão do 1º autor e a do revisor cego (`partials/sr-rules`). Ele vem aberto antes do primeiro voto. Sem migration.

- [ ] **Conversa do projeto fácil de achar.** Os alunos não sabiam onde ficavam as mensagens.
  - **Painel do aluno:** cada projeto tem o botão "💬 Conversa", com "N nova(s)" e a última mensagem (autor e data).
  - **Menu e lista da Triagem Cega:** o número do menu conta também os projetos com mensagem nova, e cada projeto da lista tem o atalho "💬 Conversa".
  - **E-mail e Telegram:** "Abrir a conversa do projeto" leva a `/portal/triagem-cega?projeto=ID#conversa`, que abre a conversa e rola até ela. Os outros avisos da triagem abrem o próprio projeto. O texto avisa que responder ao e-mail não chega à conversa.
  - **Conversa:** destaca as mensagens novas, abre já na última, não fecha a cada decisão (`wire:ignore.self`) e mostra "Você" nas próprias mensagens.
  - Migration `2026_10_27_000001` (`systematic_review_screeners.chat_read_at`).
- [ ] **Triagem mais segura no celular.**
  - **Toque duplo:** cada decisão leva o id do trabalho que estava na tela. Antes, o segundo toque decidia o PRÓXIMO trabalho sem o aluno ler.
  - **Observação:** acompanha cada trabalho e não é apagada ao trocar a decisão.
  - **"← Anterior":** volta ao trabalho recém-decidido mesmo com "só pendentes" marcado.
  - **Motivos de exclusão:** ficam travados enquanto gravam, e a barra fixa do rodapé tem fundo sólido (o resumo aparecia por trás da lista de motivos).
- [ ] **Professor:**
  - remover um triador recarrega a página, e a confiabilidade deixa de listar quem saiu;
  - a ficha da inscrição liga a conta criada depois da aprovação (`ResearchApplication::linkVerifiedUser` no `/activate`), com a migration `2026_10_27_000002`, que corrige as inscrições antigas. Antes, a ficha mostrava "Sem conta" para quem já tinha conta.

## 2026-10-23

- [ ] **Página da revisão sistemática mais legível.**
  - **Cabeçalho:** o cabeçalho curto ("Revisão sistemática · PET-01 (P01)") substitui o título longo em inglês, que espremia os botões, e o título completo vai no topo do conteúdo.
  - **Caixas de aviso:** ficam na largura toda. Viraram `ViewEntry` com `filament/infolists/html-block`, porque o `TextEntry->html()` limita a largura ao conteúdo.
  - **Esteira:** as caixas de seleção são desenhadas em CSS, no lugar do emoji ⬜, que saía lilás.

  Arquivos: `ViewSystematicReview.php` e `SystematicReviewResource.php`. Sem migration.

- [ ] **Nomes padronizados na seleção e nas revisões.** A inscrição e o triador passam a gravar o nome como o cadastro de usuários faz: "JULIA ZAGOLIN COSTA" vira "Julia Zagolin Costa", e "Carvalho pinto" vira "Carvalho Pinto". Algarismos romanos ficam em maiúsculas ("Neto II"). O comando `php artisan app:normalizar-nomes` só lista as mudanças; com `--aplicar`, grava. Ele cobre inscrições, triadores e usuários. Os textos assinados (termo, adesão, assinatura) ficam como foram assinados. Sem migration.

  Spec: [`docs/specs/2026-10-23-nomes-padronizados.md`](docs/specs/2026-10-23-nomes-padronizados.md)

- [ ] **Metanálises: guia da preparação, cartão no painel do aluno, resumo e menu do professor.**
  - **Aluno:** depois de assinar o termo, vê o que fazer agora (ORCID, OSF, links pela conversa), e não mais o guia da fase de conflitos. A trilha não marca mais o protocolo como registrado antes do OSF. O topo do painel lista os projetos, o termo e a próxima ação, com um botão direto.
  - **Professor:** resumo do programa no Dashboard. Novo grupo de menu "Programa de Metanálises" (Central → Revisões → Inscrições → Seleção).
  - Teste do "Congelar protocolo" pelo botão, com o PDF. Sem migration.

  Spec: [`docs/specs/2026-10-23-metanalises-guia-preparacao-e-paineis.md`](docs/specs/2026-10-23-metanalises-guia-preparacao-e-paineis.md)

- [ ] **Correção do erro 500 ao abrir as revisões sistemáticas.** O `GROUP BY` do PRISMA herdava o `ORDER BY seq` da relação `records()`, e o MySQL (`ONLY_FULL_GROUP_BY`) recusava a consulta em todos os projetos. A correção usa `reorder()`. Um guarda na suíte de testes reprova qualquer consulta assim, já que o SQLite aceita. Novo teste abre a revisão importada do JSON v1.1 como admin e como aluno da dupla. Sem migration.

  Spec: [`docs/specs/2026-10-23-erro-500-revisao-mysql-group-by.md`](docs/specs/2026-10-23-erro-500-revisao-mysql-group-by.md)

## 2026-10-22

- [ ] **Central das Metanálises** (`/admin/central-metanalises`): uma tela com todos os projetos (termos, protocolo, OSF, triagem e conversa), as conversas de todos os projetos com resposta direta, o histórico de atividades e a aba "Enviadas por mim" (e-mails aos alunos do programa e mensagens nas conversas). O badge do menu conta os projetos aguardando resposta. Sem migration.

  Spec: [`docs/specs/2026-10-22-central-das-metanalises.md`](docs/specs/2026-10-22-central-das-metanalises.md)

- [ ] **Termo online antes da triagem, conversa registrada do projeto, mensagens e contatos**:
  - **Termo:** o projeto aparece no portal desde a preparação, para assinar o termo online (título oculto até assinar). Depois da assinatura, o aluno vê papel, dupla, protocolo em PDF (só para quem assinou) e a conversa do projeto.
  - **Conversa do projeto:** fica registrada (professor + dupla com termo assinado), com aviso e confirmação de que nada da revisão cega pode ser exposto. Termo v2026.10.7.
  - **Mensagens do professor:** à dupla, individual ou em massa aos marcados, na seleção e na revisão.
  - **Contatos:** CSV com os contatos das duplas.
  - **E-mail:** os avisos de pesquisa vão para `RESEARCH_PROFESSOR_EMAIL` (padrão calefi@csvet.com.br).

  Spec: [`docs/specs/2026-10-22-termo-online-conversa-e-mensagens.md`](docs/specs/2026-10-22-termo-online-conversa-e-mensagens.md)

- [ ] **IA declarada no protocolo v1.1 (triagem priorizada, regra de parada, checagem aleatória e pré-extração do Revisor 1)**:
  - **Triagem:** a de título e resumo passa a ser priorizada por aprendizado ativo próprio do site (TF-IDF + Naive Bayes, um modelo por aluno, retreino a cada 10 decisões), com painel da regra de parada registrada (validação, 10%, 2× relevantes, 100 irrelevantes seguidos) e checagem aleatória automática do Revisor 2 (10%, mín. 100), que reabre a triagem se achar elegíveis. Consenso "relevante para um → texto completo"; não lidos entram como "regra de parada" no PRISMA, nos Métodos e na exportação. O professor vê a coluna "Regra de parada".
  - **Extração:** pré-extração por IA só para o líder (prompt fixo gerado do dicionário, modelo e versão registrados, importação do JSON, aviso no portal, conferência obrigatória e % corrigido), com exportação `PNN_IA_extracao.csv` e prompt no pacote OSF.
  - **Termo do aluno:** v2026.10.6, com só os usos declarados de IA.
  - **Testes:** isolamento entre alunos coberto por teste.

  Spec: [`docs/specs/2026-10-22-ia-declarada-triagem-priorizada-e-extracao.md`](docs/specs/2026-10-22-ia-declarada-triagem-priorizada-e-extracao.md)

## 2026-10-15

- [ ] **Extração em duplicata, trava da base, análise, GRADE e submissão (P7–P14)** — abrir extração cria os estudos dos incluídos (mesclar relatos); piloto; página do aluno "Extração e Risco de Viés" gerada do protocolo (campos com origem, resultados por desfecho, RoB por domínio) com as validações do pipeline e layout de celular; reconciliação lado a lado; trava com SHA-256 e versões justificadas; análises com script/saídas/sessionInfo e reexecução por outra pessoa (post hoc vira desvio); GRADE; contatos com autores; atas; manuscrito e submissão; fase P0–P14 na lista e nos guias; OSF 04/05 e depósito no pacote e nas pendências. Spec: [`docs/specs/2026-10-15-extracao-analise-e-submissao.md`](docs/specs/2026-10-15-extracao-analise-e-submissao.md)
- [ ] **Protocolo registrado (pipeline JSON), OSF e exportações** — importação dos `pipeline_PXX.json` (20 de uma vez, validada com os reais) com PICO, critérios, buscas por base, códigos E1…, extração, RoB, síntese e as 61 respostas do OSF, ligando P01–P20 aos projetos PET/PAT da seleção; congelamento com SHA-256 (JSON + PDF) e formulário somente leitura; registro OSF com DOI validado e embargo (antes de qualquer triagem); desvios e emendas (nova versão); regras do protocolo na triagem (talvez → texto completo, texto completo só incluir/excluir, código obrigatório); pendências do OSF no painel, na lista e por e-mail/Telegram ao congelar e ao encerrar etapas; exportações no padrão do guia (ficha OSF .txt/.json, BUSCA_log, TRIAGEM_TA/FT, PRISMA_contagens com kappa, DESVIOS, modelo e dicionário de extração) e pacote .zip por componente com SHA-256; guias por etapa para o aluno (o que fazer, por quê, faça/não faça contra viés, materiais com DOI, trilha do projeto) e para o professor (fase, portão, o que conferir); Triagem Cega responsiva com barra de decisão fixa no celular. Spec: [`docs/specs/2026-10-15-protocolo-osf-e-exportacoes.md`](docs/specs/2026-10-15-protocolo-osf-e-exportacoes.md)
- [ ] **Seleção de alunos para os projetos de pesquisa** — página pública `/selecao` com requisitos, condições/compromissos, passo a passo numerado da inscrição à submissão, áreas "blindadas" dos 20 projetos, FAQ e formulário que confere os requisitos (curso, semestre, horas, compromissos) ao vivo e no servidor; e-mail de confirmação e link pessoal de acompanhamento com "Você está aqui"; menu do site e sitemap. Painel: chamada pronta em 1 clique, textos de divulgação (e-mail e WhatsApp) com botão copiar, matriz de duplas, inscrições com abas e ações (entrevista, roteiro, selecionar e alocar líder + revisor cego com convite único e inclusão como triador, espera, recusa). Ajustes da tarde: sem menção a IC/TCC (projetos de pesquisa e produção de artigos), áreas mais mascaradas, desligamento por atraso nos compromissos, no termo (v2026.10.2) e no painel (ação Desligar), botões Marcar reunião/Aprovar/Reprovar, comando `app:publicar-selecao`, ordem por triador com HMAC (CRC32 correlacionava as ordens). Blindagem: orientador explícito, mentoria voluntária sem bolsa/horário fixo/vínculo (CLT, Lei 11.788, Lei 9.608), participação intransferível com penalidades legais (CC, Lei 9.610, Lei 9.279) por projetos delimitados pelo professor, escrita obrigatória para revista internacional, triagem sem IA, LGPD completa, termo de adesão no envio com texto exato + SHA-256 + IP e e-mail confirmado pelo link (termo do portal v2026.10.5). Fim do dia: áreas alinhadas aos protocolos atualizados, encerrar/reabrir inscrições (botão e `--encerrar`/`--reabrir`), mudar de linha e realocar projetos, aceite do termo de adesão pela página de acompanhamento para inscrições antigas e confirmação manual de e-mail. Corrige também os nomes de FK acima de 64 caracteres da revisão sistemática (migration testada em MariaDB a partir do estado parcial de produção). Spec: [`docs/specs/2026-10-15-selecao-de-alunos-pesquisa.md`](docs/specs/2026-10-15-selecao-de-alunos-pesquisa.md)
- [ ] **Triagem: brechas e vieses corrigidos + pipeline de submissão por tipo de artigo** — professor só desempata depois que os alunos concluem; triador vinculado só após validar o e-mail; piloto por amostra aleatória; ordem embaralhada por triador; sobrepor consenso exige justificativa (auditoria no relatório); piloto com κ < 0,60 exige justificativa; ritmo por triador; esteira e diretriz por tipo de revisão (escopo, RS sem MA, acurácia, prevalência) e alertas das revistas. Processo de revisão de artigo ganha "Tipo de artigo" (16 tipos, ARRIVE/STROBE-Vet/CONSORT/STARD/CARE/PRISMA/PRISMA-ScR/SANRA), limites × medidas do .docx e checklist de submissão que bloqueia o registro do envio. Spec: [`docs/specs/2026-10-15-triagem-vieses-e-pipelines-de-artigo.md`](docs/specs/2026-10-15-triagem-vieses-e-pipelines-de-artigo.md)
- [ ] **Revisão sistemática / metanálise — triagem cega em dupla** — projeto com protocolo PRISMA-P, registro (OSF) obrigatório antes da triagem, importação do JSON do script `buscar.py` (PubMed/Europe PMC + exportações do Scopus/WoS, DOI completado pelo Crossref, desduplicação, PRISMA-S) ou .ris/.nbib/.csv; triadores orientandos ou externos (convite obriga a criar login de aluno, liberado ao validar o e-mail); termo de confidencialidade/propriedade intelectual assinado no portal; "Triagem Cega" com um trabalho por vez, motivos de exclusão e atalhos; piloto de calibração; kappa de Cohen com IC 95%; conflitos desempatados pelo professor; etapa de texto completo; fluxograma PRISMA 2020, texto de Métodos, planilha de votos, incluídos (.ris/.csv), relatório e termos em PDF; esteira das fases do POP; "Desenvolvimento completo" ao criar o processo de revisão do artigo. Spec: [`docs/specs/2026-10-15-revisao-sistematica-triagem-cega.md`](docs/specs/2026-10-15-revisao-sistematica-triagem-cega.md)

## 2026-10-11

- [ ] **Revisão de Artigos/Livros — complementares no cadastro, upload estável e encerrar rodada** — campo de documentos complementares também no cadastro/edição do processo; o upload passa a enviar um arquivo por vez, sem pré-visualização e com o nome original guardado à parte (antes ficava girando); botão "Encerrar revisões e trazer para mim" para quando alguém não revisa (quem estava na vez é avisado, ninguém sai do processo); teste confirmando que a devolução avisa o próximo da fila. Spec: [`docs/specs/2026-10-11-revisao-complementares-e-encerrar-rodada.md`](docs/specs/2026-10-11-revisao-complementares-e-encerrar-rodada.md)

## 2026-10-08

- [ ] **Revisão de Artigos/Livros — participantes, CRediT, gestão da fila, auditoria e Área do Revisor** — tipo de trabalho; e-mail opcional, telefone e ordem de autoria separada da de revisão; ficha do participante (nome/citação, filiação completa, ORCID validado, CRediT clicável com declaração ao vivo, checklist, aceite e ciência); só quem está na vez baixa; fila + "na vez de quem" + histórico público para todos; disclaimer de mudança de composição/ordem; andamento por e-mail para todos; copiar link/WhatsApp, incluir/remover (quem entregou não sai)/reordenar/trocar quem está na vez; detecção de alterações por versão (sem controle de alterações, idêntico, outro documento) com confirmação; restaurar versão; documentos complementares; bloco de autores e dossiê PDF; link de cadastro do processo; senha opcional/obrigatória e Área do Revisor (`/revisor`) com recuperação de senha; fechamento com revista e versão final. Corrige 429 do rate limit compartilhado entre rotas e participantes que não eram salvos no cadastro. Excluir processo inteiro (digitando EXCLUIR) e cadastro pré-preenchido (instruções padrão, nome curto automático, textos de ajuda); puxar participante de um orientando; e-mails em massa depois da resposta e alertas de atraso/link não aberto no menu; avisos por Telegram (professor, orientandos já vinculados e externos pelo bot); orientando vinculado vê "Revisão de Artigos" no menu do portal. Spec: [`docs/specs/2026-10-08-revisao-artigos-livros-participantes.md`](docs/specs/2026-10-08-revisao-artigos-livros-participantes.md)

## 2026-10-06

- [ ] **Revisão de Artigos (fluxo sequencial sem login)** — cadastro do artigo + .docx + fila de revisores; cada um recebe link pessoal (token só em hash), baixa a versão atual, devolve .docx + comentários + sugestão e declara participação; arquivo renomeado `Nome_vN_rev-INICIAIS.docx`; passa ao próximo e volta ao professor. Painel com cadeia, versões, registro de acompanhamento, botões Avisar/Reenviar link/Prorrogar/Pular; lembrete manual clicando no nome de qualquer revisor (sem envio automático). Docx com macro recusado. Spec: [`docs/specs/2026-10-06-revisao-de-artigos-e-avaliacoes-de-estagio.md`](docs/specs/2026-10-06-revisao-de-artigos-e-avaliacoes-de-estagio.md)
- [ ] **Avaliações de Estágio por aluno/semestre** — relatório passa a incluir fichas não homologadas, média e CH até o momento, fichas detalhadas por aluno, filtro "todos os semestres"/busca e lançamento manual de avaliação. Aluno ganha "Meu Estágio" (locais e CH homologada, sem nota); a ficha de envio não muda (aluno continua digitando a nota, professor valida). Spec: [`docs/specs/2026-10-06-revisao-de-artigos-e-avaliacoes-de-estagio.md`](docs/specs/2026-10-06-revisao-de-artigos-e-avaliacoes-de-estagio.md)

## 2026-10-05

- [ ] **Home: navbar mobile-first, acesso do aluno em destaque e SEO/performance** — navbar de 12 itens enxugada para 6 + botão "Portal do Aluno" (dropdown Serviços), compartilhada por home/blog/na-mídia; no celular ficam logo + botão "Aluno" + menu lateral com card do aluno no topo. Logo recortado (o PNG tinha ~45% de borda transparente e sumia). Hero com grade de acesso rápido. Tailwind CDN trocado por CSS compilado (`resources/css/site.css`), foto em WebP com preload, OG image 1200×630, JSON-LD sem avaliações "self-serving" e sem SearchAction falso. Spec: [`docs/specs/2026-10-05-home-navbar-mobile-seo.md`](docs/specs/2026-10-05-home-navbar-mobile-seo.md)

## 2026-10-02

- [ ] **Devolução ao aluno dando 404** — status de conclusão de etapas de correção ("Conclusão Aprovada", "Versão Final Definida" etc.) exigiam PDF com assinatura ICP-Brasil, e a validação rodava depois de o arquivo já ter sido renomeado no Drive; o save falhava e o próximo gravava um caminho inexistente. Agora só status de assinatura exigem assinatura, a validação vem antes do rename, o download se auto-repara e há o comando `app:reparar-devolucoes`. O botão "Salvar e Notificar Aluno" virou submit do formulário, travado enquanto o PDF ainda está subindo (antes salvava sem o arquivo). Spec: [`docs/specs/2026-10-02-devolucao-404-validacao-assinatura.md`](docs/specs/2026-10-02-devolucao-404-validacao-assinatura.md)
- [ ] **TCC Envio Único bloqueado, devolução assinada invisível ao aluno e login de aluno externo** — (1) "TCC Completo — Envio Único" não exige mais Objetivos aprovados (aluno com TCC pronto fora do sistema ficava sem caminho). (2) Ao anexar novo PDF assinado com o anterior ainda no campo de devolução, o mutator gravava o arquivo antigo; agora grava o novo. (3) Aluno externo ficava preso na Pesquisa Semestral (opções só TCC/Estágio/IC) — externos dispensados; e as mensagens de status do login do portal não apareciam na tela (chave `email` em vez de `data.email`). Spec: [`docs/specs/2026-10-02-tcc-envio-unico-devolucao-e-login-externo.md`](docs/specs/2026-10-02-tcc-envio-unico-devolucao-e-login-externo.md)

## 2026-09-22

- [ ] **Limpeza do Google Drive (arquivos obsoletos de correções antigas)** — nova página admin "Limpeza do Drive" + comando `app:limpar-drive` que acham versões na lixeira, duplicatas de mesmo nome e órfãos (sem vínculo em nenhum registro do banco) nas pastas do app, e apagam definitivamente por ID, em lotes pela fila. Simulação por padrão; certificados e recibos protegidos. Spec: [`docs/specs/2026-09-22-limpeza-drive-arquivos-obsoletos.md`](docs/specs/2026-09-22-limpeza-drive-arquivos-obsoletos.md)
- [ ] **Horários 3h adiantados (fuso UTC)** — `app.timezone` passou de `UTC` para `America/Sao_Paulo` (`APP_TIMEZONE`), com migration que converte uma vez os `timestamp`/`datetime` já gravados em UTC (exceto `blog_posts.published_at` e `invite_tokens.expires_at`, digitados pelo usuário). Agendador passa a rodar no horário de Brasília. Spec: [`docs/specs/2026-09-22-fuso-horario-brasilia.md`](docs/specs/2026-09-22-fuso-horario-brasilia.md)
- [ ] **"Parado Há" e demais datas relativas em inglês ("2 weeks ago")** — o Carbon herdava o locale do ambiente de produção. Fixado `pt_BR` no `AppServiceProvider::boot()` para `Carbon` e `CarbonImmutable`. Spec: [`docs/specs/2026-09-22-parado-ha-em-ingles.md`](docs/specs/2026-09-22-parado-ha-em-ingles.md)
- [ ] **Painel travando: "Out of sort memory" em `document_submissions` + "Enviar Cobrança Agora" síncrona** — sem índice em `updated_at`, o `order by updated_at` do widget de processos em andamento fazia filesort com a linha inteira (JSON `metadata` com o texto das etapas de TCC) e estourava o sort buffer do MySQL da HostGator. Adicionado índice `doc_sub_updated_at_idx`. A cobrança avulsa do `StalledProjectsWidget` passou a ir pela fila (antes SMTP síncrono sem timeout, que pendurava a tela), com tratamento de erro; timeout SMTP agora é 20s (`MAIL_TIMEOUT`). Complemento: `sort_buffer_size` de sessão = 8MB via `MYSQL_ATTR_INIT_COMMAND` (`DB_SORT_BUFFER_SIZE`) e widget seleciona só colunas leves. Spec: [`docs/specs/2026-09-22-out-of-sort-memory-e-cobranca-avulsa-travando.md`](docs/specs/2026-09-22-out-of-sort-memory-e-cobranca-avulsa-travando.md)

## 2026-09-15

- [ ] **"Habilitar" etapa de TCC não reconhecia conteúdo do aluno já preenchido (só olhava arquivo anexado)** — o interceptor de `"Habilitado"` em `DocumentSubmission.php` só considerava a bola com o professor se houvesse `google_drive_file_id` (arquivo). Como Introdução/M&M/Resultados/Discussão/Conclusão aceitam texto direto no editor (`metadata.tcc_stage_text`) e Objetivos/Concepção usam campos de texto próprios (`tcc_general_objective`/`tcc_specific_objectives`, `tcc_conception_*`), habilitar uma etapa só com texto (sem arquivo) devolvia o processo para "Aguardando X do Aluno" em vez de ir para "Aguardando Meu Despacho". Corrigido cobrindo as 7 etapas do pipeline via nova constante `TCC_STAGE_CONTENT_KEYS`; `app:fix-stuck-awaiting-student-statuses` refatorado para reaproveitar essa constante (remedia também os processos já presos em produção). Spec: [`docs/specs/2026-09-15-habilitar-etapa-tcc-nao-reconhecia-texto-do-aluno.md`](docs/specs/2026-09-15-habilitar-etapa-tcc-nao-reconhecia-texto-do-aluno.md)

## 2026-09-01

- [ ] **Quadro Kanban sem estilo nenhum (classes Tailwind soltas nunca compiladas)** — a página customizada `submissions-kanban.blade.php` usava só classes Tailwind cruas (inclusive cor de coluna via interpolação de string), que nunca são compiladas em página customizada do Filament (mesmo bug de `filament-custom-pages-no-css`) — a tela renderizava quase sem estilo, sem grid, sem cor. Reescrita com CSS próprio: board horizontal de verdade (Trello/Linear/Jira-style, com rolagem lateral), cor sólida por coluna, badges de tipo de documento e de urgência (SLA de 3/7 dias reaproveitado do `UrgentActionsWidget`), tema claro/escuro. Testado no navegador local (drag-and-drop, busca, dark mode). Spec: [`docs/specs/2026-09-01-kanban-horizontal-sem-estilo.md`](docs/specs/2026-09-01-kanban-horizontal-sem-estilo.md)
- [ ] **Concepção/Objetivos/etapas de redação do TCC criadas pelo aluno presas em "Aguardando ... do Aluno"** — quando o aluno cria diretamente (via "Enviar Novo Documento") uma submissão de Concepção, Objetivos, Introdução, Materiais e Métodos, Resultados, Discussão ou Conclusão de TCC já com o conteúdo obrigatório preenchido, o status inicial nascia como "aguardando o aluno" (só `update()` fazia essa transição, e criação nunca passa por lá), ficando preso para sempre fora de "Aguardando Meu Despacho" e visível só no widget de dashboard. Agora o status inicial já nasce como pendência de correção do professor quando é o próprio aluno autenticado quem cria o registro; criação automática/placeholder (backfill) continua igual. Inclui comando `app:fix-stuck-awaiting-student-statuses` para remediar os processos já presos em produção. Spec: [`docs/specs/2026-09-01-etapas-tcc-criadas-pelo-aluno-presas-aguardando-aluno.md`](docs/specs/2026-09-01-etapas-tcc-criadas-pelo-aluno-presas-aguardando-aluno.md)

## 2026-08-31

- [ ] **Documento tipo "Outro" habilitado fica preso em `pending`** — os 3 `match($document_type)` que decidem o status pós-habilitação/reenvio não cobriam `'Outro'` (opção real do formulário), caindo no fallback `pending` (status de auditoria de IA, sem job que o processe para esse tipo). Como `pending` está em `STUDENT_STATUSES`, o processo ficava preso e fora de "Aguardando Meu Despacho", aparecendo só no widget de dashboard sem lugar de fato para o professor resolver. `'Outro'` agora vai para `Aguardando Revisão do Professor`, e os 2 dropdowns de despacho manual passam a oferecer esse status. Spec: [`docs/specs/2026-08-31-documento-tipo-outro-preso-em-pending.md`](docs/specs/2026-08-31-documento-tipo-outro-preso-em-pending.md)
- [ ] **Etapas de redação do TCC fora da whitelist, caindo em "Não Classificado"** — os 10 status do pipeline paralelo de TCC (Introdução/M&M/Resultados/Discussão/Conclusão, lado aluno e lado professor) nunca tinham entrado nas whitelists `STUDENT_STATUSES`/`PROFESSOR_STATUSES` nem na query do "Ações Urgentes". Com a rede de segurança "Não Classificado" adicionada em 28/08, isso passou a sequestrar a aba padrão do painel sempre que algum aluno tinha uma etapa de TCC pendente em qualquer lugar do banco — dando a impressão de que processos recém-habilitados (ex: termos de aceite) tinham sumido. Spec: [`docs/specs/2026-08-31-etapas-tcc-fora-whitelist-nao-classificado.md`](docs/specs/2026-08-31-etapas-tcc-fora-whitelist-nao-classificado.md)

## 2026-08-28

- [ ] **Status órfão sumindo da lista de trabalhos + rede de segurança** — corrige `'reviewed'`/`'auditing'` (status internos da auditoria de IA) que nunca apareciam em "Aguardando Meu Despacho" nem no widget "Ações Urgentes", ficando invisíveis para o professor. Adiciona aba "⚠️ Não Classificado" e widget de dashboard que só aparecem quando algum status escapa de todos os buckets conhecidos, e SLA visual (âmbar/vermelho) por dias parado no "Ações Urgentes". Spec: [`docs/specs/2026-08-28-status-orfao-invisivel-lista-trabalhos.md`](docs/specs/2026-08-28-status-orfao-invisivel-lista-trabalhos.md)

## 2026-08-26

- [ ] **Agendamento de consulta + Blog + Na Mídia + SEO** — formulário de solicitação de agendamento de consulta na home (imunologia veterinária e clínica de animais não convencionais), com notificação síncrona por e-mail+Telegram e ticket gerenciável no admin; infraestrutura de blog (sem posts ainda) e página "Na Mídia" com 10 matérias reais já publicadas; melhorias de SEO (JSON-LD, sitemap, robots.txt). Requer `php artisan storage:link` no deploy (capa de post do blog). Spec: [`docs/specs/2026-08-26-agendamento-consulta-blog-midia-seo.md`](docs/specs/2026-08-26-agendamento-consulta-blog-midia-seo.md)

- [ ] **Notificação síncrona (e-mail) na Central de Dúvidas** — troca `->queue()` por `->send()` no e-mail de notificação de nova mensagem de chamado, para não depender do cron da fila; separa os try/catch de e-mail e Telegram. Spec: [`docs/specs/2026-08-26-notificacao-sincrona-central-duvidas.md`](docs/specs/2026-08-26-notificacao-sincrona-central-duvidas.md)
- [ ] **Cache do access_token do Google Drive + download em streaming** — corrige lentidão/travamento no envio e download de arquivos (relatado por alunas ao enviar o Termo de Aceite de Orientação): elimina o round-trip de refresh de token em toda operação de Drive, e troca o download de carregar o arquivo inteiro em memória para streaming. **Ainda não deployado em produção.** Spec: [`docs/specs/2026-08-26-cache-token-google-drive.md`](docs/specs/2026-08-26-cache-token-google-drive.md)
- [ ] **Anexo (via link de download, múltiplos arquivos) no envio de e-mail em massa** — a página "Enviar E-mail" ganha um campo opcional de anexo (até 5 arquivos, ex: PDF de edital); em vez de anexar os arquivos brutos em cada mensagem da fila, o e-mail traz um botão de download por arquivo, com link assinado válido por 60 dias, evitando multiplicar o peso do envio por destinatário. Spec: [`docs/specs/2026-08-26-anexo-link-email-massa.md`](docs/specs/2026-08-26-anexo-link-email-massa.md)

## 2026-08-24

- [x] **Indicador de carregamento ao enviar formulário** (anti-clique-duplo) — overlay de tela cheia durante o processamento do envio (não só do upload do arquivo), para o usuário não clicar várias vezes achando que travou. Spec: [`docs/specs/2026-08-24-loading-indicator-envio.md`](docs/specs/2026-08-24-loading-indicator-envio.md) — portado em 2026-08-25.
- [x] **Etapas de redação do TCC em paralelo** — Introdução/Materiais e Métodos/Resultados/Discussão/Conclusão deixam de ter ordem obrigatória entre si; o aluno envia o segmento que quiser, o orientador corrige em paralelo, e a versão final do TCC só libera quando todos os segmentos obrigatórios estiverem aprovados. Spec: [`docs/specs/2026-08-24-tcc-etapas-paralelas.md`](docs/specs/2026-08-24-tcc-etapas-paralelas.md) — portado em 2026-08-25.

<!--
Formato para novas entradas (mais recente no topo, agrupado por data):

## AAAA-MM-DD

- [ ] **Título curto da mudança** — resumo de 1-2 linhas do que mudou e por quê. Spec: [`docs/specs/AAAA-MM-DD-slug.md`](docs/specs/AAAA-MM-DD-slug.md)
-->
