# IA declarada no protocolo v1.1: triagem priorizada, regra de parada, checagem aleatória e pré-extração do Revisor 1

**Data:** 2026-10-22
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com
**Base:** protocolos v1.1 (21/10/2026) dos 20 projetos, campos *Software*, *Screening* e *Extraction* do OSF e seção 2.4 do PRISMA-P.

## Por quê

Os protocolos v1.1 declaram três usos de IA:
1. priorização da triagem de título e resumo por aprendizado ativo, com regra de parada e checagem aleatória;
2. pré-preenchimento da extração só para o Revisor 1, com conferência de cada valor;
3. revisão de linguagem, declarada à revista.

Até aqui, o site exigia 100% dos registros com duas decisões e o termo do aluno proibia qualquer IA. Sem esta mudança, o que o protocolo diz e o que o site faz não bateriam.

## Comportamento

### Triagem de título e resumo priorizada

Vale quando o pipeline tem `screening.stages.0.prioritisation`.

- **Calibração** (piloto de 50 aleatórios) igual à de antes. Depois de "Liberar triagem completa", a ordem de cada aluno passa a ser:
  - primeiro os já decididos;
  - depois os mais prováveis, segundo o modelo **dele**.
- **Modelo** (`ActiveLearningService`):
  - TF-IDF de título (peso 2) + resumo + palavras-chave;
  - Naive Bayes multinomial, alfa 3,822, prioris iguais;
  - retreino a cada 10 decisões, com cache por triador e pela quantidade de registros;
  - um modelo por aluno: a triagem continua cega;
  - sem exemplos das duas classes: ordem aleatória própria (HMAC);
  - sem nenhum relevante: um estudo de validação entra como relevante *a priori* e sai do critério (a).
- **Regra de parada** (`stoppingStatus`; valores do pipeline, padrão do protocolo):
  - (a) estudos de validação marcados como relevantes;
  - (b) ≥ 10% triado;
  - (c) triados ≥ 2× relevantes;
  - (d) 100 irrelevantes seguidos.

  "Concluir minha triagem" só passa com os quatro cumpridos (ou tudo lido).
- **Checagem aleatória**: quando os dois concluem, o site sorteia 10% (mín. 100, ou todos) dos registros que ninguém leu e reabre só o Revisor 2 (rótulo "Revisor…").
  - Ele vê e decide só a amostra; o servidor recusa outros registros.
  - Se achar incluir/talvez: o status vira `resume`, os dois são reabertos e avisados, e uma nova rodada abre depois.
  - Se não achar nada: `passed`.
  - Decisões da checagem não contam na sequência de irrelevantes do Revisor 2.
- **Consenso** da etapa priorizada (`applyPrioritisedConsensus`):
  - relevante/talvez para qualquer revisor → texto completo (`EITHER_RULE_NOTE`);
  - todos que leram excluíram → excluído;
  - ninguém leu, com a checagem aprovada → excluído com o motivo `STOP_RULE_REASON`.

  Na prática não há conflitos na TA; o professor só encerra a etapa.
- **PRISMA**: `ta_stop_rule` e `ta_screened_manually`.
- **Métodos (inglês)**: parágrafo do aprendizado ativo, da regra, das rodadas da checagem e dos números.
- **Exportação de decisões**: métodos "regra do protocolo (relevante para um revisor → texto completo)" e "regra de parada (não triado; aprendizado ativo + checagem aleatória)".
- **Telas**:
  - Triagem Cega: painel "Regra de parada do protocolo" com os quatro critérios, a sequência atual e o botão de concluir quando liberado; aviso de checagem aleatória para o Revisor 2; mensagem de espera para o Revisor 1.
  - Professor: coluna "Regra de parada" na lista de triadores; guia do professor com o novo portão.

### Pré-extração por modelo de linguagem (só Revisor 1)

Vale quando o pipeline tem `extraction.llm_assistance`.

- **"Modelo de IA e prompt"** (Estudos): grava modelo, versão e o **prompt fixo gerado do dicionário de extração**, com SHA-256.
- **"Pré-extração IA"** (linha do estudo): cola o JSON do modelo.
  - Só campos e desfechos do protocolo são aceitos.
  - Vai para o rascunho do **líder**, com o original em `ai_prefill`.
  - Recusa se o líder já enviou o estudo, ou se começou sem IA.
  - O Revisor 2 nunca recebe nem vê nada.
- **Portal do líder**: aviso azul e caixa obrigatória "Conferi no PDF cada valor…". No envio, grava `ai_verified_at` e `ai_changes` (propostos, mantidos, alterados, apagados e % corrigido).
- **Exportações**:
  - `PNN_IA_extracao.csv` (por estudo + total);
  - `PNN_IA_prompt_extracao.txt`;
  - os dois entram no pacote OSF (04) quando há modelo registrado.

### Termo do aluno v2026.10.6

As cláusulas 5 e 6 passam a permitir só os usos declarados no protocolo:
- a ordenação da plataforma;
- a pré-extração conferida pelo líder.

O revisor não usa IA na extração. Quem assinou a versão anterior assina de novo. Ninguém tinha assinado ainda, porque nenhuma triagem foi liberada.

### Alunos aprovados antes da importação viram triadores

`linkToCall()` passou a incluir como triadores os aprovados da seleção cujo projeto (líder ou revisor) é o código importado. São marcados com os rótulos "Líder (1º autor)" e "Revisor(a) cego(a)", sem convite novo e sem duplicar ao reimportar.

Antes, só quem era aprovado **depois** de a revisão existir entrava. Os 18 alunos de 2026 foram aprovados antes da importação.

### Segurança e isolamento

- Tudo continua amarrado a `user_id` do triador: `myAssignments` e `screener()` com `find` dentro das atribuições do usuário logado.
- O servidor revalida tudo: registro do projeto e amostra da checagem.
- As exportações (inclusive as de IA) são só do admin.
- Teste cobre: aluno de fora não vê nada, `choose` de outro triador é ignorado, aluno de outro projeto não decide registros deste e as exportações dão 403 ao aluno.

## Arquivos

- Migration `2026_10_22_000001_add_prioritised_screening_and_ai_extraction`:
  - `systematic_reviews.ta_qc`, `ai_extraction`;
  - `sr_extractions.ai_prefill`, `ai_prefilled_at`, `ai_verified_at`, `ai_changes`;
  - testada no MariaDB, com rollback.
- `app/Services/ActiveLearningService.php` (novo).
- `app/Services/SystematicReviewService.php`: `prioritisedNow`, `orderFor`, `progress`, `finish`, `decide`, `checkStageCompletion`, `applyPrioritisedConsensus`, `resumeAfterQc`, `prisma`, `methodsText` e texto do `releasePilot`.
- `app/Services/SystematicReviewExtractionService.php`: `aiPrompt`, `setAiModel`, `importAiPrefill`, `aiChanges`, `aiRows`; `save` exige a conferência.
- `app/Services/SystematicReviewProtocolService.php`: métodos novos na exportação de decisões, arquivos de IA no pacote OSF e `priority` opcional no importador.
- `app/Models/SystematicReview.php`: `leadScreener`, `secondScreener`, `prioritisedScreening`, `stoppingRule`, `qcOpen`, `qcRecordIds`, `llmAssistedExtraction`.
- `app/Models/SrExtraction.php`: colunas de IA e `aiPrefilled()`.
- `app/Models/SystematicReviewScreener.php`: termo v2026.10.6.
- `app/Support/StageGuides.php`: guias do aluno (triagem priorizada, extração com IA) e do professor (portão novo).
- `app/Filament/Pages/TriagemCega.php` + `resources/views/filament/pages/triagem-cega.blade.php` (CSS próprio `.tc-stop`).
- `app/Filament/Pages/ExtracaoRob.php`.
- `ScreenersRelationManager` (coluna Regra de parada), `StudiesRelationManager` (Modelo de IA e prompt, Pré-extração IA, coluna IA) e `ViewSystematicReview` (downloads de IA).
- `SystematicReviewExportController` + rota (`ia-extracao`, `ia-prompt`).
- `tests/Feature/SystematicReviewDeclaredAiTest.php` (4 testes, 88 asserções). Suíte completa: 101 testes passando.

## Deploy

```
git pull
php artisan migrate --force
php artisan filament:optimize-clear
php artisan optimize:clear
```

Depois, no painel: Revisões Sistemáticas → "Importar protocolos (pipeline JSON)" com os 20 `pipeline_PXX.json` **v1.1**.

## Como importar para o prof-aid.com

- Copiar a migration e os arquivos acima.
- O termo cita "Prof. Atilio": trocar pelo dono do processo.
- Os valores da regra vêm do pipeline; sem `prioritisation`, nada muda (modo de triagem completa de antes).
