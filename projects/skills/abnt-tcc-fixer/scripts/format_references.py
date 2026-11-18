#!/usr/bin/env python3
"""
Formatador de Referências Avulsas segundo a ABNT NBR 6023 (2026)
Recebe uma lista de referências desformatadas ou dados brutos e gera a saída padronizada.
"""

import sys
import os
import re
import argparse


def clean_reference_line(raw_ref):
    ref = raw_ref.strip()
    if not ref:
        return ""

    # Ajusta data de acesso
    ref = re.sub(
        r"acesso\s+em:\s*(\d{1,2})\s+(?:de\s+)?([A-Za-zÀ-ÿ]+)\s+(?:de\s+)?(\d{4})\.?",
        r"Acesso em: \1 \2 \3.",
        ref,
        flags=re.IGNORECASE
    )

    # Converte autor inicial se estiver em minúsculas
    m_author = re.match(r"^([a-zA-ZÀ-ÿ\s]+),\s*([a-zA-ZÀ-ÿ\.\s]+)\.\s*(.*)", ref)
    if m_author:
        last = m_author.group(1).upper()
        first = m_author.group(2).strip()
        rest = m_author.group(3).strip()
        ref = f"{last}, {first}. {rest}"

    return ref


def format_references_list(input_file, output_file=None):
    with open(input_file, "r", encoding="utf-8") as f:
        lines = f.readlines()

    entries = []
    current = []
    for l in lines:
        s = l.strip()
        if s:
            current.append(s)
        else:
            if current:
                entries.append(" ".join(current))
                current = []
    if current:
        entries.append(" ".join(current))

    # Formata e ordena
    cleaned = [clean_reference_line(e) for e in entries if e]
    cleaned.sort(key=lambda x: re.sub(r"[^\w\s]", "", x.upper()))

    output_text = "\n\n".join(cleaned)

    if output_file:
        with open(output_file, "w", encoding="utf-8") as f:
            f.write(output_text + "\n")
        print(f"[✓] {len(cleaned)} referências formatadas salvas em: {output_file}")
    else:
        print(output_text)


def main():
    parser = argparse.ArgumentParser(description="Formata lista de referências avulsas no padrão ABNT NBR 6023.")
    parser.add_argument("-i", "--input", required=True, help="Arquivo com referências desformatadas")
    parser.add_argument("-o", "--output", default=None, help="Arquivo de saída opcional")

    args = parser.parse_args()
    format_references_list(args.input, args.output)


if __name__ == "__main__":
    main()
