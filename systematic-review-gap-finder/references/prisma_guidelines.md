# Diretrizes PRISMA 2020 e PRISMA-P para Revisões Sistemáticas e Meta-Análises

A metodologia padrão ouro internacional para condução e reporte de revisões sistemáticas é regulamentada pela declaração **PRISMA (Preferred Reporting Items for Systematic Reviews and Meta-Analyses)**.

---

## 1. As Três Etapas Formais do Fluxo PRISMA

### Etapa 1: Protocolo Prévio (PRISMA-P)
Antes de iniciar a leitura dos artigos em texto integral, é mandatório redigir e registrar publicamente um **Protocolo**.
- **Objetivo:** Prevenir viés de reporte seletivo e duplicidade de esforços na comunidade internacional.
- **Itens Essenciais:**
  1. Pergunta de pesquisa no formato PICO / PECO.
  2. Critérios explícitos de inclusão e exclusão.
  3. Bases de dados consultadas e estratégia de busca detalhada (ao menos uma string completa documentada).
  4. Métodos planejados para avaliação de Risco de Viés (*Risk of Bias*).
  5. Plano de síntese estatística (modelo de efeitos fixos vs aleatórios, $I^2$, subgrupos).

---

## 2. O Diagrama de Fluxo PRISMA 2020

O artigo final deve conter o fluxograma de quatro fases:

```text
[ IDENTIFICAÇÃO ]
  ├── Registros identificados nas bases de dados (PubMed, SciELO, Scopus, etc.)
  └── Registros removidos antes da triagem (duplicatas automáticas)

[ TRIAGEM (Screening) ]
  ├── Registros triados por Título e Resumo
  └── Registros excluídos com justificativas

[ ELEGIBILIDADE (Eligibility) ]
  ├── Artigos em texto completo avaliados para elegibilidade
  └── Artigos excluídos em texto completo (com motivos discriminados)

[ INCLUSÃO (Included) ]
  ├── Estudos incluídos na revisão sistemática qualitativa
  └── Estudos incluídos na meta-análise quantitativa
```

---

## 3. Avaliação de Risco de Viés (Risk of Bias)

A escolha da ferramenta depende do desenho dos estudos primários:
- **Ensaios Clínicos Randomizados:** Ferramenta **RoB 2** (Cochrane Risk of Bias 2).
- **Estudos Observacionais / Coortes:** Ferramenta **ROBINS-I** ou Escala de Newcastle-Ottawa.
- **Estudos Experimentais com Animais:** Ferramenta **SYRCLE's RoB tool**.
- **Estudos Agronômicos / Experimentais de Campo:** Checklists adaptados de viés experimental (alocação de parcelas, controle de bordadura, cegamento de avaliador).

---

## 4. Avaliação da Certeza da Evidência (GRADE)

O sistema **GRADE** classifica a qualidade do conjunto de evidências em:
- **Alta (High):** Muita confiança de que o efeito real está próximo do efeito estimado.
- **Moderada (Moderate):** Confiança moderada no efeito estimado.
- **Baixa (Low):** Nossa confiança no efeito é limitada.
- **Muito Baixa (Very Low):** Pouca confiança no efeito estimado.

Fatores que rebaixam a certeza: Risco de viés, Inconsistência ($I^2 > 50\%$), Indireção, Imprecisão (amostras pequenas/intervalo de confiança largo) e Viés de publicação.
