---
name: systematic-review-gap-finder
description: >-
  Use this skill when the user wants to identify research gaps specifically for conducting
  and publishing Systematic Reviews and Meta-Analyses (PRISMA/Cochrane) in any requested scientific
  domain, auditing existing reviews vs primary studies accumulation, temporal obsolescence, and subgroup voids.
---

# Systematic Review & Meta-Analysis Gap Finder

Esta skill é especializada em diagnosticar se uma determinada área temática possui demanda científica real e viabilidade para a publicação de uma **Revisão Sistemática ou Meta-Análise** de alto impacto, em conformidade com as diretrizes internacionais **PRISMA 2020** e padrões editoriais indexados (PubMed, Scopus, SciELO).

---

## Diferencial e Metodologia de Diagnóstico

Uma revisão sistemática ou meta-análise só é justificada perante os revisores de periódicos de topo (Scopus Q1, Qualis A1) sob condições rigorosas:

1. **Oportunidade Inédita (Virgem de Sínteses):** Existem ensaios/estudos primários publicados suficientes ($\ge 5$ a $10$), mas **nenhuma** revisão sistemática ou meta-análise foi conduzida até o momento.
2. **Obsolescência Temporal (Necessidade de Atualização):** A última revisão sistemática publicada possui mais de 3 a 5 anos, e desde então foram publicados múltiplos novos ensaios/estudos primários.
3. **Lacuna de Moderadores e Subgrupos:** As revisões existentes apresentaram alta heterogeneidade ($I^2 > 50\%$) ou falharam em analisar subgrupos cruciais (ex: regiões tropicais, dosagens específicas, populações vulneráveis).
4. **Detecção de Saturação:** Caso existam múltiplas revisões recentes publicadas no último ano, o agente alerta o usuário sobre o risco de rejeição por redundância.

---

## Fluxo de Execução Recomendado

### Passo 1: Executar a Auditoria Automatizada
Execute o script orquestrador a partir da raiz da skill:

```bash
python3 scripts/run_review_gap_audit.py \
  -q "sua área ou pergunta de pesquisa" \
  -o "./output_auditoria_revisao" \
  --limit-reviews 25 \
  --limit-primary 50
```

#### Parâmetros:
- `-q, --query`: Termo, intervenção ou pergunta temática (ex: `"endophytic fungi biological control soybean white mold"`, `"biochar phosphorus tropical soil"`).
- `-o, --output-dir`: Diretório para armazenar o dossiê, arquivos JSON e BibTeX.
- `--limit-reviews`: Número de revisões existentes a rastrear (padrão: 25).
- `--limit-primary`: Número de estudos primários/ensaios a mapear (padrão: 50).

### Passo 2: Interpretação do Índice de Oportunidade de Revisão (IRO)
O script calcula um escore de 0 a 100:
- **IRO 75 - 100 (Alta Oportunidade / Urgente):** Tema inédito com estudos primários abundantes ou revisão existente defasada com muitos ensaios novos acumulados.
- **IRO 50 - 74 (Oportunidade Moderada / Condicional):** Justifica-se focando em recortes de moderadores ou metodologias específicas (Scoping Review ou Subgrupos).
- **IRO < 50 (Baixa Oportunidade / Não Recomendado):** Tema saturado por meta-análises muito recentes ou volume insuficiente de estudos primários.

### Passo 3: Geração do Protocolo PRISMA-P e Estudos para Triagem
O pipeline gera automaticamente:
1. `dossie_revisao_sistematica.md`: Relatório executivo com justificativa epistemológica, pergunta PICO estruturada, critérios de elegibilidade (Inclusão/Exclusão) e plano de análise estatística.
2. `estudos_primarios_candidatos.bib`: Arquivo com todos os ensaios e estudos primários identificados para importação direta no software de triagem (Rayyan, Covidence ou Zotero).
3. `audit_review.json`: Dados brutos completos da auditoria.

### Passo 4: Registro Prévio e Submissão
- Oriente o usuário a cadastrar o protocolo no **PROSPERO** (saúde) ou **OSF Registries** (ciências agrárias, biológicas e sociais) antes de iniciar a extração de dados.
- Consulte os manuais em [Registro PROSPERO / OSF](./references/prospero_registration_guide.md) e [Diretrizes PRISMA 2020](./references/prisma_guidelines.md).

---

## Guias e Manuais de Referência

- [Diretrizes PRISMA 2020 e PRISMA-P](./references/prisma_guidelines.md): Checklist e fluxo metodológico internacional.
- [Registro no PROSPERO e OSF](./references/prospero_registration_guide.md): Como garantir prioridade autoral e evitar plágio de protocolo.
- [Análise de Heterogeneidade e Moderadores](./references/heterogeneity_and_moderators.md): Estatística $I^2$, subgrupos, meta-regressão e viés de publicação.
