# Extração em duplicata, trava da base, análise, GRADE e submissão (P7–P14)

**Data:** 2026-10-15
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com
**Base:** `PIPELINE_SISTEMA_METANALISES.md` seções 3, 4 (P7–P14), 6, 7, 9, 10, 12 e 13; `GUIA_OSF_E_EQUIPE_DE_ALUNOS.pdf` seções 6, 9 e 11.

## Fluxo

1. **Abrir extração (P7)** — "Extração e análise → Abrir extração": exige triagem encerrada e dois revisores; cria um estudo (`STU-0001`) por registro incluído e libera o piloto (padrão 3 estudos). Relatos do mesmo estudo podem ser mesclados (bloqueado se o relato já tem extração enviada); rótulos renomeáveis ("Silva 2019a").
2. **Piloto (P7a)** — os alunos só veem os estudos-piloto; o professor compara as divergências e libera a extração completa registrando o que foi combinado (campos registrados não mudam: isso seria emenda).
3. **Extração e RoB em duplicata (P7b)** — portal do aluno, página "Extração e Risco de Viés" (`/portal/extracao`, link direto `?estudo=ID`):
   - formulário gerado de `extraction.fields` (text/integer/number/category) com "onde está" por campo, `extraction.outcomes` (linhas desfecho × grupo × momento: n, eventos, média, DP, proporção, efeito, IC, origem, "lido de gráfico") e `risk_of_bias.tools[].domains` (julgamento + justificativa por domínio + geral);
   - validações do pipeline: inteiro/número, categoria válida, origem obrigatória para números, eventos ≤ n, DP > 0, proporção 0–1; RoB completo para enviar;
   - rascunho e envio; enviado só o professor reabre; cego (ninguém vê a extração do outro);
   - guia da etapa (o que fazer, por quê, faça/não faça, materiais + ferramenta de RoB do projeto); barra de ações fixa no celular.
4. **Reconciliação (P7c)** — "Estudos → Reconciliar": divergências de campo, de desfecho (inclusive linha que só um extraiu) e de RoB, lado a lado com o nome de cada revisor; uma escolha por divergência; notas; aviso ao professor quando a dupla termina o piloto e quando termina tudo.
5. **Trava da base** — exige todos os estudos com 2 extrações e reconciliados, checagens automáticas OK e rótulos únicos (alerta de amostra sobreposta). Gera `PNN_EXTRACAO_travada_vN.csv` (formato longo do modelo) e `PNN_RoB_vN.csv`, com SHA-256, imutáveis. Nova versão só se algo mudou e com justificativa.
6. **Análise (P8)** — "Análises (R) → Registrar análise": script, saídas e sessionInfo guardados com SHA-256 e ligados à versão travada; post hoc gera desvio automático. "Confirmar reexecução" por outra pessoa (bloqueia a mesma pessoa).
7. **GRADE (P9)** — linhas criadas a partir dos desfechos do protocolo; k, n, efeito, IC, intervalo de predição, certeza e razões por domínio; exporta `PNN_GRADE_SoF.csv`.
8. **Manuscrito e submissão (P10–P14)** — checklist de submissão (guia, seção 11), revista, datas, decisão e DOI do artigo.
9. **Contatos com autores** (envio, lembretes em 2 e 4 semanas com alerta de vencido, resposta) e **atas de reunião** (decisões, pendências, próxima reunião).

## Fase atual e avisos

- `SystematicReviewExtractionService::phase()` calcula P0–P14; coluna "Fase" na lista de revisões; o "Guia do professor" e a trilha do aluno seguem a fase.
- Pendências do OSF ganham: base travada (04), análise reexecutada (05: script, saídas, sessionInfo) e depósito público após a submissão. O pacote .zip inclui 04 e 05.

## Arquivos

- Migration `2026_10_15_000006_create_systematic_review_extraction_tables` (`sr_studies`, `sr_extractions` com `field_values`, `sr_dataset_locks`, `sr_analysis_runs`, `sr_grade_rows`, `sr_author_contacts`, `sr_meetings`; colunas de extração/manuscrito em `systematic_reviews`). Testada em MariaDB.
- Models `Sr*`; `app/Services/SystematicReviewExtractionService.php`.
- `app/Filament/Pages/ExtracaoRob.php` + `resources/views/filament/pages/extracao-rob.blade.php` (portal).
- Relation managers: `StudiesRelationManager`, `AnalysisRunsRelationManager`, `GradeRowsRelationManager`, `AuthorContactsRelationManager`, `MeetingsRelationManager`.
- `ViewSystematicReview`: menu "Extração e análise" e downloads; `SystematicReviewExportController`: `extracao-travada|rob-travado (?v=N)|extracao-duplicata|contatos-autores|grade|atas` e `analysisFile`.
- `StageGuides`: guia do aluno "extraction" e guia do professor P7–P14.
- `tests/Feature/SystematicReviewExtractionTest.php`.

## Fora do escopo (evoluções possíveis)

Execução do R no servidor (hoje o script roda na máquina do professor e o resultado é registrado), integração automática com a API do OSF, lembretes automáticos de prazos por pessoa.
