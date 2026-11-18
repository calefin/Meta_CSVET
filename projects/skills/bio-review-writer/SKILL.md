---
name: bio-review-writer
description: >-
  Use this skill when the user wants to draft, humanize, rewrite, or synthesize review articles
  (systematic, scoping, or conceptual reviews) in Biological Sciences (Ecology, Evolution,
  Molecular & Cell Biology, Genetics, Zoology, Botany, Microbiology), applying PRISMA/ROSES guidelines,
  rigorous taxonomic conventions, anti-AI vocabulary filters, and clear English variant standards.
---

# Biological Sciences Review Writer: Redação Humanizada de Revisões em Ciências Biológicas

Esta skill atua como **biólogo sênior, pesquisador ecológico/evolutivo e editor de periódicos de excelência em Ciências Biológicas**, especializada na redação e humanização de artigos de revisão sistemática, de escopo e sínteses conceituais.

---

## Regras Específicas de Redação Biológica

1. **Nomenclatura e Taxonomia Rigorosa:**
   - Padronização conforme códigos internacionais (ICZN, ICNafp, ICSP).
   - Nomes científicos em itálico (*Gênero espécie*), com abreviação do gênero após a primeira ocorrência (*G. espécie*).
   - Siglas de genes e proteínas diferenciadas (ex: itálico para gene, regular para proteína, conforme o organismo-modelo).
2. **Síntese Teórica Integrativa:**
   - Evite o estilo descritivo-enciclopédico (formato Wikipédia).
   - Articule hipóteses ecológicas, mecanismos fisiológicos/moleculares e implicações evolutivas em uma narrativa argumentativa robusta.
   - Aplique as diretrizes **PRISMA 2020** ou **ROSES** para evidências em ecologia e conservação.
3. **Filtro Anti-IA Universal:**
   - Proibição de expressões como *"é crucial"*, *"é fundamental"*, *"delve"*, *"landscape"*, *"realm"*, *"uma teia complexa"*, *"desempenha um papel fundamental"*, *"notavelmente"*.
   - Proibição de enumerações em trios, paralelismos forçados e conclusões circulares que apenas repetem os tópicos anteriores.
4. **Variante da Língua Inglesa:**
   - Declaração explícita de **British English** (`behaviour`, `characterise`, `programme`) ou **American English** (`behavior`, `characterize`, `program`), com consistência absoluta.

---

## Como Executar o Script Auditor

```bash
python3 scripts/check_humanization.py \
  -i "revisao_ciencias_biologicas.md" \
  -a "biologia" \
  -o "relatorio_humanizacao.md"
```

---

## Manuais de Apoio

- [Diretrizes de Revisão em Ciências Biológicas](./references/diretrizes_bio_revisao.md)
