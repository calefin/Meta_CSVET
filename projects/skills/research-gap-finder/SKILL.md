---
name: research-gap-finder
description: >-
  Use this skill when the user wants to search for scientific articles, extract academic metadata,
  and identify unpublished or unexplored research gaps and topics for producing scientific papers
  by analyzing indexed databases such as SciELO, CABI, PubMed, Scopus, OpenAlex, Crossref, and arXiv.
---

# Research Gap Finder: Prospecção de Temas Inéditos em Bases Indexadas

Esta skill capacita o agente a realizar buscas bibliométricas sistemáticas, extrair metadados completos de artigos científicos e minerar **lacunas de pesquisa não publicadas (research gaps)** para a produção de artigos científicos originais, revisões sistemáticas e comunicações de alto impacto.

---

## Bases Indexadas Suportadas

O pipeline integrado interroga as seguintes fontes indexadas:
- **SciELO (via Crossref prefix `10.1590` & OpenAlex):** Ampla cobertura da América Latina, Caribe, Portugal, com foco em periódicos com Qualis-CAPES.
- **PubMed / MEDLINE (NCBI E-utilities):** Literatura biomédica, ciências da saúde, toxicologia e biotecnologia.
- **CABI (CAB Direct):** Agronomia, sanidade vegetal, medicina veterinária, silvicultura e meio ambiente.
- **OpenAlex:** Mais de 250 milhões de trabalhos científicos multidisciplinares mundiais com grafos de citação e conceitos.
- **Scopus (Elsevier):** Base de alto impacto (requer `SCOPUS_API_KEY` ou consulta via OpenAlex que indexa periódicos Scopus).
- **arXiv:** Preprints e fronteiras emergentes de ciência da computação, inteligência artificial e física.

Para detalhes das APIs e políticas de cota, consulte [Guia de Bases de Dados](./references/databases_guide.md).

---

## Fluxo de Execução Recomendado

### Passo 1: Definição da Estratégia de Busca
Antes de rodar a busca, estruture os termos no modelo PICO/PECO e operadores booleanos.
Consulte o guia de formulação: [Formulação de Strings de Busca](./references/query_formulation.md).

### Passo 2: Execução do Pipeline Unificado
Execute o script orquestrador a partir da raiz da skill:

```bash
python3 scripts/run_pipeline.py \
  -q "seu tema ou string booleana" \
  -d "openalex,pubmed,scielo,cabi,arxiv" \
  -n 30 \
  --start-year 2021 \
  -o "./output_pesquisa"
```

#### Parâmetros Principais:
- `-q, --query`: Termo de pesquisa (ex: `"microplastics AND soil"`, `"soja AND sustentabilidade"`).
- `-d, --databases`: Bases separadas por vírgula (`openalex`, `pubmed`, `scielo`, `cabi`, `arxiv`, `scopus`).
- `-n, --limit`: Número de artigos por base (padrão: 30).
- `--start-year`: Filtro de ano inicial (útil para focar em publicações recentes dos últimos 3-5 anos).
- `-o, --output-dir`: Diretório onde serão gravados os relatórios e metadados.
- `--scopus-key`: Chave da API Scopus (se o usuário possuir acesso institucional).

### Passo 3: Análise e Identificação de Lacunas
O script realiza automaticamente:
1. **Deduplicação Inteligente:** Remove duplicatas com base em DOI e títulos normalizados.
2. **Mineração Linguística de Lacunas:** Rastreia frases nos resumos que declaram limitações, incertezas e chamadas para pesquisas futuras (ex: *"further studies are needed"*, *"remains poorly understood"*, *"pouco explorado"*).
3. **Matriz de Espaços em Branco (White Spaces):** Identifica conceitos proeminentes que nunca foram correlacionados na literatura.
4. **Análise de Velocidade e Saturação:** Avalia se o tema está saturado ou se é uma fronteira emergente.

Consulte os fundamentos teóricos em [Metodologia de Lacunas de Pesquisa](./references/gap_methodology.md).

### Passo 4: Geração de Propostas e Entrega dos Resultados
O pipeline gera três saídas prontas no diretório de saída:
- `relatorio_lacunas.md`: Dossiê executivo em Markdown com diagnósticos, tabelas e propostas de artigos estruturadas no padrão PICO.
- `metadados_artigos.csv`: Planilha completa com título, autores, ano, periódico, DOI, citações e resumo de cada artigo coletado.
- `referencias.bib`: Arquivo BibTeX para importação direta no Zotero, Mendeley ou Overleaf.

### Passo 5: Enquadramento em Revistas-Alvo
Utilize o relatório gerado para sugerir ao usuário os periódicos ideais, alinhados aos estratos Qualis-CAPES (A1, A2, etc.) e métricas internacionais (JCR/Scopus CiteScore).
Consulte a matriz em [Seleção de Periódicos e Qualis](./references/journal_qualis_matrix.md).

---

## Execução Modular (Scripts Individuais)

Caso seja necessário rodar apenas uma etapa:
- **Apenas Busca:** `python3 scripts/search_academic.py -q "tema" -o artigos.json`
- **Apenas Análise:** `python3 scripts/analyze_gaps.py -i artigos.json -q "tema" -o analise.json`
- **Apenas Exportação:** `python3 scripts/export_report.py --analysis analise.json --articles artigos.json --md relatorio.md --csv artigos.csv --bib referencias.bib`

---

## Exemplo de Aplicação
Veja um exemplo completo de relatório gerado para o tema de solos tropicais em [Exemplo de Dossiê](./examples/sample_report_microplastics.md).
