# Protocolo registrado (pipeline JSON), OSF e exportações

**Data:** 2026-10-15
**Status:** implementado no profatilio.com.br · pendente no prof-aid.com
**Base:** `PIPELINE_SISTEMA_METANALISES.md` (seções 1, 4 P0–P2, 5, 8, 10, 14) e `GUIA_OSF_E_EQUIPE_DE_ALUNOS.pdf` (seções 2, 3, 6, 9, 10).

## Princípio

"O protocolo registrado manda": critérios, códigos de exclusão, decisões permitidas, campos de extração e plano de análise vêm do `pipeline_PXX.json` (schema `calefi-sr-pipeline/1.0`) e ficam somente leitura depois do congelamento. Mudança só por emenda.

## Fluxo no painel (Revisões Sistemáticas)

1. **Importar protocolos (P0)** — lista: "Importar protocolos (pipeline JSON)" aceita os 20 arquivos de uma vez (o `manifest.json` é ignorado). Na revisão: "Protocolo e OSF → Importar/Atualizar protocolo". Mapeamento:
   - `protocol_id/folder/family/title/label_pt` → código (P01…), pasta, família, título, nome curto;
   - família → tipo e framework (intervention/arm → meta-análise PICO; prevalence → prevalência CoCoPop; dta → acurácia PIRD; prognostic/correlation → meta-análise PECO);
   - `eligibility.pico` (Population; Intervention/Condition/Exposure/Index test/Prognostic factor; Comparator/Reference standard; Outcomes; Study design), inclusão, exclusão (E1…), idioma/datas;
   - `search.databases` (estratégia por base), suplementares, conjunto de validação;
   - `screening.stages`: decisões permitidas por etapa, regra do "talvez", tamanho do piloto (calibração) e **códigos de exclusão E1…** do texto completo;
   - `extraction`, `risk_of_bias`, `synthesis`, `osf_fields` (61 respostas do template Generalized SR Registration), `keywords` → destaques na triagem;
   - liga a revisão ao projeto da seleção de alunos (P01–P10 → PET-01…10; P11–P20 → PAT-01…10).
   - Validado com os 20 JSON reais (sem gravar nenhum título real no repositório).
2. **Congelar protocolo (P1)** — exige critérios e nenhuma triagem; grava `protocol_sha256` (JSON canônico do pipeline + campos + códigos) e, se enviado, o PDF (`protocol_pdf_sha256`, guardado em disco privado). Formulário fica somente leitura; reimportar é recusado.
3. **Registro no OSF (P2)** — exige protocolo congelado e nenhuma triagem; DOI validado/normalizado (`10.17605/OSF.IO/XXXXX`), link do projeto, data e embargo. Liberar a triagem exige protocolo congelado + DOI.
4. **Desvios e emendas** — tipo, seção, o que mudou, justificativa, quem pediu, referência no OSF. Emenda gera nova versão (1.0 → 1.1) e descongela (atualizar no OSF e congelar de novo).

## Regras de triagem do protocolo

- "Talvez" de qualquer revisor no título/resumo leva o registro ao texto completo (gravado com `MAYBE_RULE_NOTE`; método "regra do protocolo" na exportação).
- Texto completo: só incluir/excluir (servidor recusa "talvez"; o botão some).
- Exclusão no texto completo: um código E1… (ou "Texto completo não obtido"); no título/resumo, "Fora do tema da revisão" + códigos.
- Tela da Triagem Cega: um trabalho por vez; ao excluir, lista com o código em destaque; avisos "na dúvida, marque Talvez" (TA) e "escolha um único código" (FT).

## Pendências e avisos do OSF

`osfPending()` diz o que subir e onde: congelar; registrar (componente 01 com ficha, PDF, modelo e dicionário de extração); log das buscas e RIS (02); decisões TA/FT fechadas + contagens PRISMA (03); desvios novos (01). Aparece no painel "Protocolo e OSF" da revisão e na coluna "OSF" da lista; e-mail + Telegram ao congelar e ao encerrar cada etapa. "Marquei como enviado ao OSF" tira o item da lista (histórico).

## Exportações (nomes do guia)

`OSF_Form_PNN.txt` · `PNN_OSF_registration_responses.json` (API: `registration_schema` + `registration_responses`) · `pipeline_PNN.json` · `PNN_BUSCA_log.csv` (executadas + planejadas + validação) · `PNN_TRIAGEM_TA|FT_decisoes_<data>.csv` (registro × revisor, código, data, decisão final e método; `_PARCIAL` se a etapa está aberta) · `PNN_PRISMA_contagens.csv` (fluxograma, códigos, checagem assessed = included + excluídos, kappa com concordância e IC95%) · `PNN_DESVIOS.csv` · `PNN_EXTRACAO_modelo.csv` (formato longo estudo × desfecho com `source_location` e `digitised`) · `PNN_dicionario_extracao.csv` (campos, desfechos, domínios de RoB) · **Pacote para o OSF** `.zip` com as pastas `01_…`–`06_…`, só etapas fechadas, e `LEIA-ME_e_SHA256.txt`.

## Arquivos

- Migration `2026_10_15_000005_add_osf_protocol_to_systematic_reviews` (+ tabela `systematic_review_deviations`, FK `sr_deviations_review_fk`).
- `app/Services/SystematicReviewProtocolService.php`; `app/Models/SystematicReviewDeviation.php`; `SystematicReview` (isFrozen, allowedDecisions, maybeAdvancesToFullText, osfFields, validationSet, computeProtocolHash, filePrefix).
- `SystematicReviewService`: regra do talvez, decisões permitidas, "fora do tema", gates de início, avisos do OSF ao encerrar etapa.
- `SystematicReviewExportController` + rota (novos tipos); `ListSystematicReviews`, `ViewSystematicReview` (Protocolo e OSF, Baixar), `SystematicReviewResource` (painel OSF, coluna OSF, campos travados); `triagem-cega.blade.php`.
- `tests/Feature/SystematicReviewProtocolTest.php` (fixture sintético).

## Fora do escopo (próximas fases do pipeline)

Integração automática com a API do OSF (opcional na seção 11), fases P3–P14 como máquina de estados com gates (deduplicação manual, obtenção de PDFs, extração dupla com reconciliação e trava da base, análise R e reexecução, GRADE), atas quinzenais.

## Guias por etapa e uso no celular

- `app/Support/StageGuides.php` + `resources/views/filament/partials/stage-guide.blade.php`:
  - **Aluno** (Triagem Cega): termo, piloto de calibração, título/resumo, texto completo e espera entre etapas. Cada guia traz o que vai fazer, por que importa, ✔ faça / ✖ não faça (redução de viés), materiais de estudo com DOI conferido (PRISMA 2020, PRISMA-P, PRISMA-S, formulário generalizado do OSF, Cochrane Handbook, McHugh 2012, Polanin 2019, Waffenschmidt 2019, GRADE e a ferramenta de risco de viés do projeto: SYRCLE, RoB 2, ROBINS-I, QUADAS-2, QUIPS, JBI, NOS), as referências do próprio protocolo e a trilha do projeto ("você está aqui"). Aberto no primeiro acesso.
  - **Professor** (ficha da revisão, "Guia do professor"): fase atual (P0–P7), o que fazer agora, portão de qualidade e o que conferir (inclui o conjunto de validação, que não é mostrado aos alunos).
- Triagem Cega responsiva: no celular, Incluir/Excluir/Talvez ficam numa barra presa ao rodapé (uma linha, botões grandes); a lista de motivos abre dentro da barra com rolagem própria; textos longos quebram a linha; atalhos de teclado somem em telas de toque. Conferido em 360 e 390 px, sem estouro horizontal.
- Correção: o aluno só vê o projeto depois de "Liberar triagem" e assina o termo no primeiro acesso; o guia do professor e o e-mail de aprovação da seleção foram ajustados a esse fluxo.
