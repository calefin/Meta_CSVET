# Repositório de Skills Antigravity

Este repositório centraliza e documenta as **skills especializadas** desenvolvidas para potencializar o agente de IA em fluxos de trabalho acadêmicos avançados, integridade científica, normatização ABNT, tradução editorial de periódicos e redação científica humanizada nas áreas de **Medicina Veterinária**, **Ciências Biológicas** e **Filosofia / Bioética**.

---

## 📚 Catálogo Completo de Skills

Acesse o documento central para consultar todas as 13 skills registradas, suas finalidades e instruções detalhadas:

👉 **[Ver Catálogo Oficial de Skills (CATALOGO_SKILLS.md)](./CATALOGO_SKILLS.md)**

---

## ⚡ Sumário das Skills Disponíveis

### 1. Pesquisa Bibliométrica, Lacunas & Integridade
- **`research-gap-finder`**: Busca multi-base (SciELO, PubMed, CABI, Scopus, OpenAlex, arXiv) e mineração de lacunas inéditas gerais.
- **`systematic-review-gap-finder`**: Auditoria de viabilidade e acúmulo de ensaios primários para Revisões Sistemáticas e Meta-Análises (PRISMA/PROSPERO).
- **`citation-crossref-auditor`**: Auditoria forense de citações e referências no Crossref com detecção de obras fora do escopo do manuscrito.

### 2. Normatização ABNT de Trabalhos Acadêmicos (Normas 2026)
- **`abnt-tcc-verifier`**: Auditoria e notas de conformidade para TCCs e teses (NBR 14724, NBR 10520:2023, NBR 6023).
- **`abnt-tcc-fixer`**: Correção automática de citações em caixa baixa, reordenação de referências e seções.

### 3. Tradução Editorial Fiel de Periódicos (PT $\rightarrow$ EN)
- **`academic-translator-en`**: Tradução científica com preservação estrita do sentido original, fidelidade numérica, conversão decimal, sem clichês de IA e com controle de qualidade em Inglês Britânico ou Americano.

### 4. Redação Científica Humanizada & Anti-IA (Veterinária, Biologia e Filosofia)
- **`vet-review-writer`**: Artigos de revisão sistemática e narrativa em Medicina Veterinária ("responsável legal" / "owner").
- **`vet-meta-analysis-writer`**: Redação e síntese narrativa de metanálises veterinárias (PRISMA 2020, forest plots em prosa, GRADE).
- **`vet-experimental-writer`**: Artigos experimentais, ensaios clínicos, patologia comparada e relatos de caso veterinários (ARRIVE 2.0, CARE).
- **`bio-review-writer`**: Artigos de revisão e sínteses ecológicas/evolutivas em Ciências Biológicas (PRISMA / ROSES, taxonomia).
- **`bio-experimental-writer`**: Artigos experimentais de bancada, campo e bioinformática em Biologia (réplicas biológicas, GenBank).
- **`philosophy-essay-writer`**: Ensaios filosóficos e análise conceitual (método analítico de A. P. Martinich, rigor lógico, sem name-dropping).
- **`bioethics-writer`**: Artigos e ensaios de Bioética, Ética Animal (3Rs, senciência) e Ética Ambiental, sem moralismo panfletário.

---

## 🚀 Como Utilizar Globalmente no Antigravity

Para disponibilizar qualquer skill deste repositório globalmente em sua máquina (em qualquer pasta ou projeto):

```bash
mkdir -p ~/.gemini/config/skills
cp -r "academic-translator-en" ~/.gemini/config/skills/
cp -r "vet-review-writer" ~/.gemini/config/skills/
```
