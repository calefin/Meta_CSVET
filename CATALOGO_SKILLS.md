# Catálogo Central de Skills do Repositório

Este documento serve como o **índice oficial e registro vivo** de todas as skills desenvolvidas neste repositório. Conforme novas skills forem concebidas e implementadas, elas devem ser registradas aqui com seus metadados, objetivo, procedimentos e escopo de atuação.

---

## Índice Geral das 13 Skills Registradas

### 🔍 Prospecção de Lacunas, Metadados & Integridade Científica
| Skill | Finalidade Principal | Status | Localização |
| :--- | :--- | :---: | :--- |
| [`research-gap-finder`](#1-research-gap-finder) | Prospecção multi-base de artigos e mineração de lacunas inéditas gerais (SciELO, PubMed, CABI, Scopus, OpenAlex, arXiv). | Ativa | [`research-gap-finder/`](./research-gap-finder/) |
| [`systematic-review-gap-finder`](#2-systematic-review-gap-finder) | Auditoria de viabilidade e acúmulo de ensaios primários para condução de **Revisões Sistemáticas e Meta-Análises** (PRISMA/PROSPERO). | Ativa | [`systematic-review-gap-finder/`](./systematic-review-gap-finder/) |
| [`citation-crossref-auditor`](#3-citation-crossref-auditor) | Auditoria forense de citações e referências com validação de metadados no **Crossref** e detecção de obras fora do escopo do manuscrito. | Ativa | [`citation-crossref-auditor/`](./citation-crossref-auditor/) |

### 📐 Normatização ABNT para TCC e Monografias (Normas 2026)
| Skill | Finalidade Principal | Status | Localização |
| :--- | :--- | :---: | :--- |
| [`abnt-tcc-verifier`](#4-abnt-tcc-verifier) | Auditoria, verificação e emissão de notas de conformidade para TCCs segundo as normas ABNT vigentes em 2026 (NBR 14724, NBR 10520:2023, NBR 6023). | Ativa | [`abnt-tcc-verifier/`](./abnt-tcc-verifier/) |
| [`abnt-tcc-fixer`](#5-abnt-tcc-fixer) | Correção automatizada de citações autor-data (NBR 10520:2023 em caixa baixa), reordenação alfabética de referências (NBR 6023) e numeração de seções (NBR 6024). | Ativa | [`abnt-tcc-fixer/`](./abnt-tcc-fixer/) |

### 🌐 Tradução Editorial e Produção Final de Periódicos (PT $\rightarrow$ EN)
| Skill | Finalidade Principal | Status | Localização |
| :--- | :--- | :---: | :--- |
| [`academic-translator-en`](#13-academic-translator-en) | **Tradução científica fiel** de artigos aprovados do Português para o Inglês, sem alterar o sentido do documento original, com preservação numérica estrita, sem clichês de IA e com auditor de QA pós-tradução. | Ativa | [`academic-translator-en/`](./academic-translator-en/) |

### 🐾 Medicina Veterinária (Redação Humanizada & Anti-IA)
| Skill | Finalidade Principal | Status | Localização |
| :--- | :--- | :---: | :--- |
| [`vet-review-writer`](#6-vet-review-writer) | Redação humanizada de **Artigos de Revisão** (sistemáticas, de escopo e narrativas) em Medicina Veterinária, com terminologia "responsável legal" e filtro anti-IA. | Ativa | [`vet-review-writer/`](./vet-review-writer/) |
| [`vet-meta-analysis-writer`](#7-vet-meta-analysis-writer) | Redação e síntese narrativa de **Metanálises** veterinárias (Forest plots em prosa, pooled effect, IC 95%, $I^2$, heterogeneidade e GRADE). | Ativa | [`vet-meta-analysis-writer/`](./vet-meta-analysis-writer/) |
| [`vet-experimental-writer`](#8-vet-experimental-writer) | Redação de **Artigos Experimentais, Ensaios Clínicos, Patologia e Relatos de Caso** veterinários (ARRIVE 2.0, CARE, REFLECT, narrativa patogênica). | Ativa | [`vet-experimental-writer/`](./vet-experimental-writer/) |

### 🌿 Ciências Biológicas (Redação Humanizada & Anti-IA)
| Skill | Finalidade Principal | Status | Localização |
| :--- | :--- | :---: | :--- |
| [`bio-review-writer`](#9-bio-review-writer) | Redação de **Artigos de Revisão e Sínteses** em Ciências Biológicas (ecologia, evolução, biologia celular e molecular, genética, zoologia e botânica). | Ativa | [`bio-review-writer/`](./bio-review-writer/) |
| [`bio-experimental-writer`](#10-bio-experimental-writer) | Redação de **Artigos Experimentais de Bancada, Campo e Bioinformática** em Biologia (ARRIVE, réplicas biológicas, sequências GenBank, licenças SISBIO). | Ativa | [`bio-experimental-writer/`](./bio-experimental-writer/) |

### 🏛️ Filosofia & Bioética (Redação Humanizada & Rigor Analítico)
| Skill | Finalidade Principal | Status | Localização |
| :--- | :--- | :---: | :--- |
| [`philosophy-essay-writer`](#11-philosophy-essay-writer) | Redação de **Ensaios Filosóficos e Análise Conceitual** (método A. P. Martinich, premissas dedutivas, paradoxo da audiência, sem name-dropping). | Ativa | [`philosophy-essay-writer/`](./philosophy-essay-writer/) |
| [`bioethics-writer`](#12-bioethics-writer) | Redação de artigos de **Bioética, Ética Animal, Princípio dos 3Rs e Ética Ambiental**, integrando filosofia moral rigorosa à prática veterinária e biológica. | Ativa | [`bioethics-writer/`](./bioethics-writer/) |

---

## Detalhamento da Skill de Tradução Acadêmica

### 13. `academic-translator-en`

- **Identificador Único:** `academic-translator-en`
- **Categoria:** Produção Editorial de Periódicos, Tradução Científica Fiel e Controle de Qualidade
- **Caminho da Skill:** [`academic-translator-en/`](./academic-translator-en/) | Arquivo de Regras: [`SKILL.md`](./academic-translator-en/SKILL.md)

#### Para que serve:
Traduz manuscritos científicos aprovados e corrigidos do Português para o Inglês mantendo **100% de fidelidade ao sentido original**, preservando a exata força das afirmações (*hedging*), todos os números, valores de p, intervalos de confiança e tabelas. Aplica conversão de pontuação decimal (vírgula para ponto), impõe terminologia jurídica obrigatória (*owner* e nunca *tutor*), elimina clichês de tradução automática por IA (*delve, pivotal, crucial, landscape*) e audita a consistência da variante da revista (**British English** ou **American English**).

#### Quando usar:
- Na etapa final de produção editorial de periódicos científicos para publicar artigos em inglês.
- Para traduzir seções completas de artigos aceitos (Resumo, Introdução, Métodos, Resultados, Discussão, Conclusão, Tabelas).
- Para rodar auditoria pós-tradução verificando se algum número, porcentagem ou unidade foi alterado ou omitido.

#### Regras Mandatórias de Tradução:
1. **Zero Distorção e Zero Alucinação:** Proibido acrescentar interpretações que não constam no português ou suavizar limitações relatadas.
2. **Preservação Numérica Estrita:** Todos os valores numéricos são preservados e checados pelo auditor `check_translation.py`.
3. **Conversão Decimal:** Vírgula vira ponto ($88,4\% \rightarrow 88.4\%$; $15,0\text{ mg} \rightarrow 15.0\text{ mg}$).
4. **Terminologia Veterinária:** *"responsável legal"* traduz-se exclusivamente como **"owner"**. Proibido usar *"tutor"* ou *"pet parent"*.
5. **Variante da Revista:** Definição explícita de **British English** (UK) ou **American English** (US).

#### Como Executar a Auditoria Pós-Tradução:
```bash
python3 "academic-translator-en/scripts/check_translation.py" \
  --pt "manuscrito_portugues.md" \
  --en "manuscrito_ingles.md" \
  --variant "british" \
  --area "veterinaria" \
  -o "relatorio_qualidade_traducao.md"
```

#### Saídas Geradas:
1. Manuscrito traduzido com fidelidade conceitual estrita em língua inglesa formal.
2. `relatorio_qualidade_traducao.md`: Relatório com nota de integridade (0 a 100), auditoria de números, conversão de separadores decimais e verificação de clichês de IA.
