# Revisão de Artigos (fluxo sequencial sem login) + Painel de Avaliações de Estágio

Data: 2026-10-06

## 1. Avaliações de Estágio

### Problema
O "Relatório de Estágios" só mostrava fichas **homologadas** e não deixava abrir as fichas
de cada aluno. O professor precisa ver todas as fichas (com nota e CH), por aluno e por
semestre, com a **média até o momento**, e lançar fichas recebidas fora do sistema. O aluno
deve ver só locais e CH homologada, **nunca a nota**.

### O que mudou
- `app/Filament/Pages/RelatorioEstagios.php` + `resources/views/filament/pages/relatorio-estagios.blade.php`
  - Menu renomeado para **"Avaliações de Estágio"** (grupo Orientação Acadêmica, só admin).
  - Inclui todas as fichas, exceto `Cancelado`. Semestre = data de envio (`created_at`), que é
    estável (antes usava `updated_at`, que mudava a cada edição).
  - Filtro de semestre com opção "Todos os semestres" (seções por semestre) + busca por nome/RGM.
  - Por aluno: fichas (e quantas homologadas), CH total, média até o momento (e nº de notas).
  - "Ver fichas" expande a lista de cada ficha (situação, disciplina, locais, CH, nota, data, link).
  - Botão **"Lançar avaliação manual"**: aluno, disciplina, data (define o semestre), locais, CH,
    nota, homologada sim/não, observação. Cria `DocumentSubmission` `Avaliações de Estágio`
    com `metadata.manual_entry = true`, **sem eventos** (não notifica o aluno).
- `app/Filament/Pages/MeuEstagio.php` + `resources/views/filament/pages/meu-estagio.blade.php`
  - Nova página do portal do aluno ("Meus Trabalhos → Meu Estágio"): por semestre, soma de
    locais e CH **só de fichas homologadas**, sem nota. Registrada em `StudentPanelProvider`.
- A ficha de envio **não mudou**: o aluno continua digitando a nota ao enviar o PDF; o professor
  valida e homologa. Os registros já existentes no banco seguem válidos sem migração.
  A nota só não aparece na página "Meu Estágio".

## 2. Revisão de Artigo Científico

### Fluxo
1. Professor cadastra o artigo (título, nome curto p/ arquivo, revista-alvo, instruções, .docx original,
   prazo padrão) e a fila ordenada de revisores (nome, e-mail, prazo opcional).
2. "Iniciar revisão": o 1º revisor recebe e-mail com link pessoal (sem login).
3. O revisor baixa a versão atual, devolve o .docx revisado, comentários, **mensagem de sugestão**, e
   aceita a **declaração de participação** (obrigatória). Recebe comprovante por e-mail (SHA-256).
4. O sistema nomeia a nova versão: `{NomeCurto}_v{N}_rev-{INICIAIS cumulativas}.docx`
   (ex.: `Imuno_Repteis_v3_rev-JS-MO.docx`) e passa automaticamente ao próximo.
5. Após o último, status "Revisões Concluídas" e e-mail + Telegram ao professor. Ele registra a versão
   final e a data de submissão à revista.

### Painel (admin → "Pesquisa & Publicações → Revisão de Artigos")
- Lista: situação, progresso (x/y), com quem está, prazo/atraso; ações **Avisar**, **Reenviar link**, versão atual.
- Visualização: cadeia de revisores com datas (enviado, abriu, prazo, devolveu), arquivos de cada versão,
  comentários, sugestões, declaração (data, IP, SHA-256) e **Registro de acompanhamento** (linha do tempo
  de todos os eventos).
- Ações: Iniciar, **Enviar aviso** (mensagem opcional), **Reenviar link**, Prorrogar prazo, Pular revisor,
  Adicionar revisor (inclusive após concluir — reabre a rodada), Registrar versão final/submissão, Cancelar.
- Badge no menu com artigos que voltaram para o professor.

### Segurança
- Token de 64 caracteres; no banco só o **hash SHA-256** (`token_hash`). Reenvio/aviso geram link novo e invalidam o anterior.
- Só o revisor da vez baixa/envia; outros veem "aguardando" ou o comprovante. Cancelar invalida todos os links.
- Rotas com `throttle` (POST 6/min), regex no token, honeypot, CSRF, `noindex`, `no-referrer`, `no-store`; página sem scripts de terceiros.
- Arquivos no disco `local` privado, com nome UUID; download do admin por rota que exige login de admin.
- Validação do documento sem extrair: extensão .docx/.odt, assinatura ZIP, estrutura mínima, **recusa macros** (vbaProject, macroEnabled, ActiveX), limite 30MB.
- Envio protegido por transação com `lockForUpdate` (duplo clique não gera duas versões).
- Excluir o artigo apaga todos os arquivos.

### Arquivos
- Migration `2026_10_06_000001_create_article_reviews_tables.php` (`article_reviews`, `article_reviewers`)
- Models `ArticleReview`, `ArticleReviewer`; Service `ArticleReviewService`
- `ArticleReviewController` + `resources/views/article-review/show.blade.php`
- `ArticleReviewResource` (+ páginas List/Create/Edit/View)
- Rotas em `routes/web.php`: `article-review.show|download|store`, `article-review.admin.download`
- Lembretes **manuais**: no painel, clicar no nome do revisor → "Enviar lembrete" (mensagem opcional). Revisor da vez recebe link novo; quem está na fila recebe aviso prévio sem link. O comando `app:lembrar-revisoes-artigo` existe mas **não é agendado** (preferência do professor).

### Importação no prof-aid.com
1. Copiar arquivos acima e rodar `php artisan migrate`.
2. Trocar "Prof. Atílio" nos textos de e-mail/página pelo nome do dono da conta.
