#!/usr/bin/env python3
"""
Pipeline Executável Unificado: Pesquisa de Artigos, Metadados e Descoberta de Lacunas Científicas.
Executa o ciclo completo:
1. Coleta e deduplicação em bases indexadas (SciELO, PubMed, OpenAlex, CABI, arXiv, Scopus)
2. Mineração linguística de lacunas e matriz de espaços em branco (co-ocorrência)
3. Geração do dossiê em Markdown, CSV de metadados e arquivo BibTeX
"""

import sys
import os
import argparse
import subprocess

SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))


def main():
    parser = argparse.ArgumentParser(description="Executa o pipeline completo de prospecção de lacunas científicas.")
    parser.add_argument("-q", "--query", required=True, help="Termo de pesquisa ou string booleana")
    parser.add_argument("-d", "--databases", default="openalex,pubmed,scielo,cabi,arxiv", help="Bases de dados separadas por vírgula")
    parser.add_argument("-n", "--limit", type=int, default=30, help="Limite de artigos por base (padrão: 30)")
    parser.add_argument("--start-year", type=int, default=None, help="Ano inicial (ex: 2021)")
    parser.add_argument("--end-year", type=int, default=None, help="Ano final")
    parser.add_argument("-o", "--output-dir", default="./output_gap_analysis", help="Diretório onde os relatórios serão salvos")
    parser.add_argument("--scopus-key", default=None, help="Chave de API do Scopus (opcional)")
    parser.add_argument("--ncbi-key", default=None, help="Chave de API do NCBI PubMed (opcional)")

    args = parser.parse_args()

    os.makedirs(args.output_dir, exist_ok=True)
    raw_articles_file = os.path.join(args.output_dir, "articles_raw.json")
    analysis_file = os.path.join(args.output_dir, "analysis_gaps.json")
    report_md = os.path.join(args.output_dir, "relatorio_lacunas.md")
    report_csv = os.path.join(args.output_dir, "metadados_artigos.csv")
    report_bib = os.path.join(args.output_dir, "referencias.bib")

    print("\n" + "="*70)
    print(" PIPELINE DE IDENTIFICAÇÃO DE LACUNAS E TEMAS CIENTÍFICOS INÉDITOS")
    print("="*70)

    # 1. Search
    search_cmd = [
        sys.executable, os.path.join(SCRIPT_DIR, "search_academic.py"),
        "-q", args.query,
        "-d", args.databases,
        "-n", str(args.limit),
        "-o", raw_articles_file
    ]
    if args.start_year:
        search_cmd.extend(["--start-year", str(args.start_year)])
    if args.end_year:
        search_cmd.extend(["--end-year", str(args.end_year)])
    if args.scopus_key:
        search_cmd.extend(["--scopus-key", args.scopus_key])
    if args.ncbi_key:
        search_cmd.extend(["--ncbi-key", args.ncbi_key])

    print("\n>>> ETAPA 1: Coleta de Metadados nas Bases Indexadas...")
    res1 = subprocess.run(search_cmd)
    if res1.returncode != 0:
        print("[!] Erro durante a etapa de busca.", file=sys.stderr)
        sys.exit(res1.returncode)

    # 2. Analyze
    analyze_cmd = [
        sys.executable, os.path.join(SCRIPT_DIR, "analyze_gaps.py"),
        "-i", raw_articles_file,
        "-q", args.query,
        "-o", analysis_file
    ]
    print("\n>>> ETAPA 2: Mineração de Lacunas e Espaços em Branco...")
    res2 = subprocess.run(analyze_cmd)
    if res2.returncode != 0:
        print("[!] Erro durante a análise de lacunas.", file=sys.stderr)
        sys.exit(res2.returncode)

    # 3. Export
    export_cmd = [
        sys.executable, os.path.join(SCRIPT_DIR, "export_report.py"),
        "--analysis", analysis_file,
        "--articles", raw_articles_file,
        "--md", report_md,
        "--csv", report_csv,
        "--bib", report_bib
    ]
    print("\n>>> ETAPA 3: Geração dos Relatórios Finais...")
    res3 = subprocess.run(export_cmd)
    if res3.returncode != 0:
        print("[!] Erro durante a exportação.", file=sys.stderr)
        sys.exit(res3.returncode)

    print("\n" + "="*70)
    print(f"[✓] PIPELINE CONCLUÍDO COM SUCESSO!")
    print(f"Arquivos gerados em: {os.path.abspath(args.output_dir)}/")
    print(f"  ├─ Dossiê Completo (Markdown): {report_md}")
    print(f"  ├─ Metadados dos Artigos (CSV): {report_csv}")
    print(f"  ├─ Referências para Zotero/Mendeley (BibTeX): {report_bib}")
    print(f"  └─ Dados Analíticos Estruturados (JSON): {analysis_file}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
