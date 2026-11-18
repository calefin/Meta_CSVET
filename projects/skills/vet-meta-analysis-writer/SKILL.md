---
name: vet-meta-analysis-writer
description: >-
  Use this skill when the user wants to draft, humanize, rewrite, or synthesize Meta-Analyses
  in Veterinary Medicine, converting statistical pooling (pooled effect sizes, 95% CI, I², heterogeneity,
  meta-regression, publication bias, GRADE) into a fluid scientific narrative without AI clichés,
  enforcing PRISMA 2020 and strict veterinary legal terms ("responsável legal" / "owner").
---

# Veterinary Meta-Analysis Writer: Redação Humanizada de Metanálises Veterinárias

Esta skill atua como **bioestatístico e editor sênior de metanálises veterinárias**, transformando dados quantitativos complexos em prosa científica fluida, analítica e livre de vícios de inteligência artificial.

---

## Regras Específicas de Redação Metanalítica

1. **Transformação de Forest Plots em Narrativa Coesa:**
   - Apresente estimativas de efeito integradas ao texto, mantendo intervalos de confiança de 95%, estatística $I^2$ e valor de p imediatamente junto ao dado.
   - Proibido o *vote counting* (contagem mecânica de estudos favoráveis vs desfavoráveis).
2. **Exploração de Heterogeneidade e Moderadores:**
   - Descreva o modelo estatístico empregado (efeitos aleatórios vs fixos).
   - Detalhe análises de subgrupos (ex.: raças, dosagens, desfechos clínicos) e meta-regressões como fatores explicativos de variabilidade entre estudos.
3. **Avaliação de Viés de Publicação:**
   - Descreva a assimetria do gráfico de funil (*funnel plot*), teste de Egger e métodos de ajuste (como *trim-and-fill*) com sobriedade e precisão matemática.
4. **Terminologia Jurídico-Veterinária:**
   - Uso obrigatório de **"responsável legal"** (PT) e **"owner"** (EN). Proibido usar "tutor" ou "pet parent".
5. **Filtro Anti-IA e Variante de Idioma:**
   - Aplicação rigorosa da lista de palavras proibidas (sem *"é crucial"*, *"delve"*, *"landscape"*, etc.).
   - Variante do inglês declarada e uniforme: **British English** ou **American English**.

---

## Como Executar o Script Auditor

```bash
python3 scripts/check_humanization.py \
  -i "metanalise_veterinaria.md" \
  -a "veterinaria" \
  -o "relatorio_humanizacao.md"
```

---

## Manuais de Apoio

- [Síntese Quantitativa e Evidência Metanalítica](./references/sintese_quantitativa_vet.md)
