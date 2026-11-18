# Metodologia de Identificação e Validação de Lacunas de Pesquisa

A detecção de temas não publicados com potencial para geração de artigos de alto impacto requer rigor metodológico. Este documento sintetiza as principais taxonomias acadêmicas utilizadas internacionalmente.

---

## 1. Tipologia de Lacunas de Pesquisa (Miles, 2017 & Robinson et al., 2011)

Ao analisar a literatura coletada, o pesquisador deve classificar o tema não publicado em uma destas 6 categorias:

### A. Lacuna Populacional / Contextual (Population Gap)
- **Definição:** Uma intervenção ou fenômeno foi testado extensivamente em uma população (ex: adultos em países temperados), mas inexistem dados em outra população (ex: crianças, idosos, pequenos agricultores do semiárido, solos tropicais).
- **Potencial de Artigo:** Artigos originais de validação contextual, adaptação de protocolos ou estudos comparativos.

### B. Lacuna Metodológica (Methodological Gap)
- **Definição:** A literatura é dominada por estudos observacionais, retrospectivos ou *in vitro*, mas carece de ensaios randomizados, estudos longitudinais ou meta-análises formais.
- **Potencial de Artigo:** Ensaios clínicos controlados, estudos de coorte prospectivos ou revisões sistemáticas PRISMA.

### C. Lacuna Empírica / Evidencial (Empirical/Evidential Gap)
- **Definição:** Existem achados conflitantes ou inconclusivos na literatura sobre a mesma relação de causa e efeito (ex: Artigo X afirma que biochar eleva produtividade em 20%; Artigo Y relata neutralidade ou toxicidade sob certas condições).
- **Potencial de Artigo:** Meta-análise com meta-regressão de moderadores ou artigo experimental de elucidação mecanística.

### D. Lacuna Teórica / Conceitual (Theoretical Gap)
- **Definição:** As teorias existentes falham em explicar anomalias observadas nos dados empíricos recentes.
- **Potencial de Artigo:** Artigos de síntese teórica, modelos conceituais ou *Perspectives*.

### E. Lacuna de Fronteira Tecnológica (Practical / Implementation Gap)
- **Definição:** Surgimento recente de uma nova ferramenta tecnológica (ex: Visão Computacional, LLMs, Sensores IoT, Edição Gênica CRISPR) que ainda não foi aplicada ao problema tradicional da área.
- **Potencial de Artigo:** Comunicações rápidas, notas técnicas ou artigos interdisciplinares de alta novidade.

---

## 2. Princípios de Mineração Bibliométrica (Lógica VOSviewer e Bibliometrix)

O pipeline analisa duas dimensões centrais:

1. **Matriz de Co-ocorrência de Termos (Keyword Co-occurrence):**
   - Quando o Termo A ("Nanopartículas de Prata") e o Termo B ("Fitopatógenos da Soja") co-ocorrem com frequência média em seus respectivos clusters, mas sua co-ocorrência bilateral é **zero**, existe um **Espaço em Branco (White Space)** com alto ineditismo.

2. **Citações por Ano (Citation Velocity):**
   - Subtemas com poucos artigos totais (< 20 nos últimos 3 anos), porém com alta média de citações por trabalho (> 5 citações/ano), indicam **Fronteiras Emergentes de Alta Demanda**, ideais para novas submissões.

---

## 3. Critérios FINER para Validação de Novas Ideias

Antes de iniciar a redação do artigo, valide o tema proposto com o checklist FINER:
- **F (Feasible / Viável):** Existem dados acessíveis, reagentes ou fontes abertas suficientes para executar?
- **I (Interesting / Interessante):** O resultado desperta o interesse de editores e revisores da área?
- **N (Novel / Inédito):** Confirma, refuta ou expande descobertas prévias? Preenche uma lacuna explícita?
- **E (Ethical / Ético):** Cumpre diretrizes de integridade acadêmica e comitês de ética?
- **R (Relevant / Relevante):** Contribui para o avanço da ciência, políticas públicas ou prática industrial?
