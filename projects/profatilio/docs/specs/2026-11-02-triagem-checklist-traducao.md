# Triagem Cega: checklist PICO, travas de lógica e tradução de apoio (2026-11-02)

## Motivo
Os alunos do programa de metanálises são de graduação, com pouca familiaridade com o conteúdo e com o inglês. No treino, o kappa das duplas ficou baixo (de −0,02 a 0,71), e os erros se concentraram em comparador e desfecho. A triagem oficial precisa de concordância ≥ 0,60 no piloto de 50.

## O que muda
- **Checklist por projeto**: `systematic_reviews.screening_checklist` (json), com uma lista de `{q, h, code}`. O conteúdo vem de `database/data/screening_checklists.json` (chave = `protocol_code`, ex.: P01) e é carregado por `php artisan metanalises:checklists [--dry-run] [--file=]`. O comando avisa quando um código não corresponde a nenhum motivo do projeto.
- `SystematicReview::screeningChecklistFor($reasons)` resolve o código ("E9") para o motivo completo da etapa ("E9 — …"). O padrão exige espaço depois do código, para E1 não casar com E10.
- **Respostas**: `systematic_review_decisions.checklist` (json; índice da pergunta → sim/nao/incerto). O serviço `decide()` aceita `?array $checklist`: null mantém o que já está gravado, e índices ou valores inválidos são descartados. Na página, `$checklist` e `$checklistRecordId` são enviados de forma adiada (`$wire.$set(..., false)`) junto com a decisão, e respostas de outro trabalho (toque duplo) são ignoradas.
- **Indicação**: calculada no navegador só com as respostas do aluno. Qualquer Não → Excluir, com o motivo destacado na lista; Não dá para saber → Talvez; tudo Sim → Incluir. A indicação não usa modelo nem IA, e a decisão continua do aluno.
- **Travas** (`pass()` no Alpine): sem checklist completo, a decisão não sai e aparece um aviso. Com contradição (Incluir com Não ou Não dá para saber; Excluir sem nenhum Não; Talvez com tudo Sim), o sistema pede confirmação.
- **Tradução**: a API `Translator` do Chrome roda no aparelho. Fica ligada por padrão (localStorage `tc_tr_auto`) e guarda o cache por sessão. Quando o navegador não tem a API, aparece um link para o Google Tradutor. Nada é gravado no servidor, e o modelo de priorização usa só o texto original.
- `TriagemCega::reasonPt()` traduz os motivos genéricos do protocolo; o valor gravado continua o original.
- O e-mail da liberação (`assignmentText`) traz "ESTA É A TRIAGEM OFICIAL, NÃO É TREINO" e explica a calibração.

## Deploy
`git pull`, `php artisan migrate --force`, `php artisan metanalises:checklists`, `php artisan optimize:clear`.

## Testes
`SystematicReviewFlowTest::test_screening_checklist_suggests_from_the_students_own_answers_and_is_kept_with_the_vote`.
