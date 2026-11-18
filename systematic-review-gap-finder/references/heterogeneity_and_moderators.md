# Análise de Heterogeneidade, Moderadores e Meta-Regressão

Um dos motivos mais fortes que justificam uma **nova meta-análise** (mesmo quando existem revisões narrativas anteriores) é a presença de **heterogeneidade estatística não explicada** ou a necessidade de avaliar **variáveis moderadoras**.

---

## 1. Métricas de Heterogeneidade Estatística

Em uma meta-análise, os estudos primários raramente possuem resultados idênticos:
- **Estatística $I^2$:**
  - $0\% \text{ a } 40\%$: Heterogeneidade irrelevante ou leve.
  - $30\% \text{ a } 60\%$: Heterogeneidade moderada.
  - $50\% \text{ a } 90\%$: Heterogeneidade substancial.
  - $75\% \text{ a } 100\%$: Heterogeneidade considerável.
- **Teste Q de Cochran:** Avalia se as diferenças entre estudos são atribuíveis ao mero acaso ($p < 0.10$ rejeita a homogeneidade).
- **Tau-quadrado ($\tau^2$):** Estima a variância entre os estudos verdadeiros em um modelo de efeitos aleatórios.

---

## 2. Estratégias para Explorar Lacunas via Moderadores

Quando a heterogeneidade for alta ($I^2 > 50\%$), a meta-análise deve obrigatoriamente prever:

### A. Análise de Subgrupos (Subgroup Analysis)
Segmentação dos estudos primários por variáveis categóricas:
- **População:** Faixa etária, sexo, gravidade clínica, tipo de solo (ex: Latossolo vs Argissolo), linhagem biológica.
- **Intervenção:** Dosagem aplicada (baixa vs alta dose), forma de formulação química, tempo de exposição.
- **Contexto Geográfico:** Países temperados vs regiões tropicais e subtropicais.
- **Qualidade Metodológica:** Baixo risco de viés vs alto risco de viés.

### B. Meta-Regressão (Meta-Regression)
Para variáveis contínuas (ex: dose do insumo em kg/ha, idade média dos participantes, duração em semanas):
- Examina se o tamanho do efeito (Effect Size) varia linearmente em função do moderador contínuo.
- Exige um número mínimo de estudos primários por covariável (regra empírica da Cochrane: ao menos 10 estudos por moderador na regressão).

---

## 3. Avaliação de Viés de Publicação

- **Funnel Plot (Gráfico de Funil):** Gráfico de dispersão do tamanho do efeito em relação ao erro padrão (tamanho amostral). Estudos menores na base e estudos maiores no topo. Assimetria sugere que estudos com resultados negativos não foram publicados.
- **Teste de Regressão de Egger:** Teste estatístico formal para detectar assimetria no gráfico de funil.
- **Método Trim-and-Fill de Duval e Tweedie:** Imputa estudos hipotéticos não publicados para recalcular o tamanho de efeito ajustado.
