#!/usr/bin/env python3
"""
Academic Report & Reference Exporter
Exports gap analysis and retrieved metadata to:
1. Executive Markdown Dossier (.md)
2. Spreadsheet-ready CSV (.csv)
3. Reference Manager BibTeX (.bib)
"""

import sys
import os
import re
import csv
import json
import argparse
from datetime import datetime


def generate_bibtex(articles, output_bib):
    """Exports articles to BibTeX format."""
    entries = []
    for idx, art in enumerate(articles, start=1):
        doi = art.get("doi")
        title = art.get("title", "Untitled").replace("{", "").replace("}", "")
        year = art.get("year") or 2024
        journal = art.get("journal", "Journal Article")
        authors = art.get("authors", [])
        auth_str = " and ".join(authors) if authors else "Unknown Author"

        # Key generation
        first_author = authors[0].split()[-1] if authors else "Article"
        key = re.sub(r"[^\w]", "", f"{first_author}_{year}_{idx}")

        entry = f"""@article{{{key},
  title = {{{{{title}}}}},
  author = {{{auth_str}}},
  journal = {{{journal}}},
  year = {{{year}}}{f",\n  doi = {{{doi}}}" if doi else ""}{f",\n  url = {{{art.get('url')}}}" if art.get('url') else ""}
}}"""
        entries.append(entry)

    with open(output_bib, "w", encoding="utf-8") as f:
        f.write("\n\n".join(entries))


def generate_csv(articles, output_csv):
    """Exports articles to CSV format."""
    fields = [
        "id", "source_database", "year", "title", "journal", "authors",
        "doi", "citations", "open_access", "keywords", "url"
    ]

    with open(output_csv, "w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        for art in articles:
            row = dict(art)
            row["authors"] = "; ".join(art.get("authors", []))
            row["keywords"] = "; ".join(art.get("keywords", []))
            writer.writerow(row)


def generate_markdown_report(analysis_data, articles, output_md):
    """Generates a detailed, publication-ready research dossier."""
    query = analysis_data.get("query", "Área de Pesquisa")
    total = analysis_data.get("total_articles", len(articles))
    db_breakdown = analysis_data.get("database_breakdown", {})
    temporal = analysis_data.get("temporal_analysis", {})
    explicit_gaps = analysis_data.get("explicit_gaps_found", [])
    concept_data = analysis_data.get("concept_analysis", {})
    proposals = analysis_data.get("research_proposals", [])

    lines = []
    lines.append(f"# Dossiê de Prospecção Bibliométrica e Lacunas de Pesquisa")
    lines.append(f"**Tema Analisado:** `{query}`  ")
    lines.append(f"**Data da Análise:** {datetime.now().strftime('%d/%m/%Y %H:%M')}  ")
    lines.append(f"**Total de Artigos Indexados Analisados:** {total} artigos deduplicados  \n")

    lines.append("---")
    lines.append("## 1. Panorama Bibliométrico e Distribuição por Base Indexada\n")
    lines.append("| Base de Dados Indexada | Artigos Recuperados | Cobertura Temática Principal |")
    lines.append("| :--- | :---: | :--- |")
    lines.append(f"| **OpenAlex** | {db_breakdown.get('OpenAlex', 0)} | Multidisciplinar global (250M+ obras, métricas de impacto) |")
    lines.append(f"| **PubMed / MEDLINE** | {db_breakdown.get('PubMed', 0)} | Biomedicina, Farmácia, Saúde Pública e Toxicologia |")
    lines.append(f"| **SciELO** | {db_breakdown.get('SciELO', 0)} | América Latina, Portugal, Caribe e periódicos nacionais |")
    lines.append(f"| **CABI (CAB Direct)** | {db_breakdown.get('CABI', 0)} | Ciências Agrárias, Veterinária, Entomologia e Meio Ambiente |")
    lines.append(f"| **arXiv (Preprints)** | {db_breakdown.get('arXiv', 0)} | Fronteira do conhecimento (artigos pré-peer-review) |")
    if db_breakdown.get('Scopus', 0) > 0:
        lines.append(f"| **Scopus (Elsevier)** | {db_breakdown.get('Scopus', 0)} | Base proprietária global de alto impacto |")
    lines.append("")

    lines.append("### Dinâmica Temporal e Maturidade do Tema")
    lines.append(f"- **Velocidade de Publicação:** `{temporal.get('trend_velocity', 'N/A')}`")
    lines.append(f"- **Volume Recente (últimos 3 anos):** {temporal.get('recent_articles_count', 0)} artigos ({temporal.get('recent_ratio_pct', 0)}% do corpus)")
    lines.append("")

    lines.append("---")
    lines.append("## 2. Lacunas Explícitas Declaradas na Literatura (Research Gaps)\n")
    if explicit_gaps:
        lines.append("> [!IMPORTANT]")
        lines.append("> Estas afirmações foram extraídas textualmente dos artigos indexados, onde os próprios autores apontam limitações e necessidades de pesquisas futuras:")
        lines.append("")
        for idx, g in enumerate(explicit_gaps[:8], 1):
            lines.append(f"**{idx}. Declaração de Lacuna:**")
            lines.append(f"> *\"{g['gap_sentence']}\"*")
            lines.append(f"- **Fonte:** {g['paper_title']} ({g['journal']}, {g['year']})")
            if g.get('doi'):
                lines.append(f"- **DOI:** [{g['doi']}](https://doi.org/{g['doi']})")
            lines.append("")
    else:
        lines.append("*Nenhuma lacuna formulada com frases padronizadas explícitas nos resumos coletados. Análise baseada na matriz de conceitos e espaços em branco abaixo.*  \n")

    lines.append("---")
    lines.append("## 3. Matriz de Co-ocorrência e Espaços em Branco (White Spaces)\n")
    lines.append("Identifica conceitos que aparecem com frequência na literatura isoladamente, mas que **quase nunca foram investigados de forma cruzada ou simultânea** (altíssimo potencial de ineditismo):  \n")

    white_spaces = concept_data.get("white_spaces", [])
    if white_spaces:
        lines.append("| Conceito A | Frequência A | Conceito B | Frequência B | Ocorrência Conjunta | Pontuação de Oportunidade |")
        lines.append("| :--- | :---: | :--- | :---: | :---: | :---: |")
        for ws in white_spaces[:8]:
            lines.append(f"| **{ws['concept_a']}** | {ws['frequency_a']} | **{ws['concept_b']}** | {ws['frequency_b']} | **{ws['co_occurrence']}** | `{ws['opportunity_score']}` |")
        lines.append("")
    else:
        lines.append("*Dados de conceitos insuficientes para matriz de co-ocorrência combinada.*  \n")

    lines.append("---")
    lines.append("## 4. Propostas de Artigos Científicos Prontos para Produção\n")
    for idx, prop in enumerate(proposals, 1):
        lines.append(f"### Proposta {idx}: {prop.get('type')}")
        lines.append(f"- **Título Sugerido (EN):** `{prop.get('title_suggestion')}`")
        lines.append(f"- **Título Sugerido (PT):** `{prop.get('title_pt')}`")
        lines.append(f"- **Nível de Ineditismo:** `{prop.get('novelty_level')}`")
        lines.append(f"- **Justificativa da Lacuna:** {prop.get('gap_justification')}")
        lines.append(f"- **Pergunta de Pesquisa (PICO):** {prop.get('research_question_pico')}")
        lines.append(f"- **Periódicos-Alvo Recomendados:** {prop.get('target_journals')}")
        lines.append("")

    lines.append("---")
    lines.append("## 5. Próximos Passos Recomendados para o Autor\n")
    lines.append("1. **Validação de Protocolo:** Submeter o protocolo da revisão sistemática no [PROSPERO](https://www.crd.york.ac.uk/prospero/) ou [Open Science Framework (OSF)](https://osf.io/).")
    lines.append("2. **Exportação de Citações:** O arquivo `.bib` correspondente já está gerado e pode ser importado diretamente no Zotero, Mendeley ou Overleaf.")
    lines.append("3. **Conformidade Qualis/CAPES:** Priorizar submissão aos periódicos listados com estrato Qualis A1 ou A2 e indexação simultânea Scopus/SciELO.")

    with open(output_md, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    parser = argparse.ArgumentParser(description="Exporta relatórios e metadados de pesquisa.")
    parser.add_argument("--analysis", required=True, help="JSON de análise gerado pelo analyze_gaps.py")
    parser.add_argument("--articles", required=True, help="JSON de artigos gerado pelo search_academic.py")
    parser.add_argument("--md", default="relatorio_lacunas.md", help="Arquivo Markdown de saída")
    parser.add_argument("--csv", default="artigos_metadados.csv", help="Arquivo CSV de saída")
    parser.add_argument("--bib", default="referencias.bib", help="Arquivo BibTeX de saída")

    args = parser.parse_args()

    with open(args.analysis, "r", encoding="utf-8") as f:
        analysis_data = json.load(f)
    with open(args.articles, "r", encoding="utf-8") as f:
        articles_data = json.load(f)

    generate_markdown_report(analysis_data, articles_data, args.md)
    generate_csv(articles_data, args.csv)
    generate_bibtex(articles_data, args.bib)

    print(f"[✓] Exportação completa realizada com sucesso:")
    print(f"    - Relatório Markdown: {args.md}")
    print(f"    - Metadados CSV: {args.csv}")
    print(f"    - Referências BibTeX: {args.bib}")


if __name__ == "__main__":
    main()
