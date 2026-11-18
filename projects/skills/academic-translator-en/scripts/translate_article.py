#!/usr/bin/env python3
"""
Orquestrador de Tradução Acadêmica e Preparação de Manuscritos (PT -> EN)
Segmenta manuscritos em seções para tradução precisa, preservando a estrutura Markdown,
números, tabelas e referências, e executa a checagem pós-tradução.
"""

import sys
import os
import re
import argparse
import subprocess

SCRIPT_DIR = os.path.dirname(os.path.realpath(__file__))


def split_sections(content):
    """Segmenta o manuscrito científico pelas seções principais."""
    lines = content.splitlines()
    sections = []
    current_title = "Preâmbulo / Título"
    current_lines = []

    heading_pattern = re.compile(r"^#+\s+(.+)")

    for l in lines:
        m = heading_pattern.match(l)
        if m:
            if current_lines:
                sections.append((current_title, "\n".join(current_lines)))
                current_lines = []
            current_title = m.group(1).strip()
            current_lines.append(l)
        else:
            current_lines.append(l)

    if current_lines:
        sections.append((current_title, "\n".join(current_lines)))

    return sections


def main():
    parser = argparse.ArgumentParser(description="Orquestrador de tradução de artigos científicos.")
    parser.add_argument("-i", "--input", required=True, help="Arquivo do artigo em português (.md ou .txt)")
    parser.add_argument("-v", "--variant", default="british", choices=["british", "american"], help="Variante exigida pela revista (british ou american)")
    parser.add_argument("-a", "--area", default="veterinaria", choices=["veterinaria", "biologia", "filosofia", "geral"], help="Área científica")
    parser.add_argument("--check-against", default=None, help="Arquivo traduzido em inglês para rodar o controle de qualidade")
    parser.add_argument("-o", "--output-qa", default="relatorio_qualidade_traducao.md", help="Arquivo do relatório de QA")

    args = parser.parse_args()

    if not os.path.exists(args.input):
        print(f"Erro: Arquivo '{args.input}' não encontrado.", file=sys.stderr)
        sys.exit(1)

    with open(args.input, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    sections = split_sections(content)
    print(f"\n[+] Manuscrito carregado: '{args.input}'")
    print(f"[*] Total de seções detectadas: {len(sections)}")
    print(f"[*] Área: {args.area.title()} | Variante do Inglês: {args.variant.title()}")
    print("\nEstrutura identificada:")
    for idx, (title, text) in enumerate(sections, 1):
        words = len(text.split())
        print(f"  {idx}. {title} (~{words} palavras)")

    if args.check_against:
        print(f"\n[*] Executando auditoria editorial contra a tradução: '{args.check_against}'...")
        cmd = [
            sys.executable, os.path.join(SCRIPT_DIR, "check_translation.py"),
            "--pt", args.input,
            "--en", args.check_against,
            "--variant", args.variant,
            "--area", args.area,
            "-o", args.output_qa
        ]
        res = subprocess.run(cmd)
        sys.exit(res.returncode)
    else:
        print("\n[i] Para realizar a validação da tradução produzida, execute:")
        print(f"    python3 scripts/check_translation.py --pt \"{args.input}\" --en \"artigo_traduzido.md\" --variant {args.variant} --area {args.area}")


if __name__ == "__main__":
    main()
