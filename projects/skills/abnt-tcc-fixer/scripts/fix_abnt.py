#!/usr/bin/env python3
"""
Formatador e Corretor Automático de Normas ABNT para TCC (Normas 2026 / NBR 10520:2023, NBR 6023, NBR 6024)
Aplica correções automatizadas em arquivos Markdown/Texto:
1. Converte citações obsoletas em CAIXA ALTA (SILVA, 2020) para inicial maiúscula (Silva, 2020).
2. Ajusta pontuação final após o fechamento dos parênteses.
3. Padroniza abreviatura de página para 'p. XX'.
4. Corrige numeração progressiva de seções (remove ponto no final: '1.1.' -> '1.1').
5. Padroniza separação de palavras-chave por ponto e vírgula e ponto final.
6. Reordena e formata a lista de referências em ordem alfabética estrita com meses abreviados.
"""

import sys
import os
import re
import argparse
from datetime import datetime


ABNT_MONTH_MAP = {
    "janeiro": "jan.", "fevereiro": "fev.", "março": "mar.", "marco": "mar.",
    "abril": "abr.", "maio": "maio", "junho": "jun.", "julho": "jul.",
    "agosto": "ago.", "setembro": "set.", "outubro": "out.", "novembro": "nov.",
    "dezembro": "dez.",
    "jan": "jan.", "fev": "fev.", "mar": "mar.", "abr": "abr.",
    "jun": "jun.", "jul": "jul.", "ago": "ago.", "set": "set.",
    "out": "out.", "nov": "nov.", "dez": "dez."
}


def fix_citations_in_line(line):
    # 1. Ponto antes do parêntese: ". (Autor, Ano)" -> " (Autor, Ano)."
    line = re.sub(r"\.\s*\(([A-Za-zÀ-ÿ\s;,0-9\.\-]+)\)", r" (\1).", line)

    # 2. Caixa alta obsoleta dentro de parênteses: (SILVA, 2020) -> (Silva, 2020)
    def fix_caps_match(m):
        authors_raw = m.group(1)
        rest = m.group(2)
        # Separar por ';'
        authors_parts = [p.strip() for p in authors_raw.split(";")]
        formatted = []
        for a in authors_parts:
            # Manter siglas em maiúsculas (IBGE, OMS, UNESCO, MAPA, etc.)
            if a in ["IBGE", "OMS", "WHO", "MAPA", "EMBRAPA", "ANVISA", "MEC", "CAPES", "CNPQ", "ONU"]:
                formatted.append(a)
            else:
                # Title case com respeito a preposições
                words = a.split()
                w_fixed = []
                for idx, w in enumerate(words):
                    if w.lower() in ["da", "de", "do", "das", "dos", "e"] and idx > 0:
                        w_fixed.append(w.lower())
                    else:
                        w_fixed.append(w.capitalize())
                formatted.append(" ".join(w_fixed))
        return f"({'; '.join(formatted)}, {rest})"

    line = re.sub(r"\(([A-ZÀ-ÿ\s;]{2,}),\s*([12]\d{3}[a-z]?(?:,\s*[^)]+)?)\)", fix_caps_match, line)

    # 3. Separador 'e' ou 'and' entre dois autores dentro de parênteses: (Silva e Santos, 2020) -> (Silva; Santos, 2020)
    line = re.sub(r"\(([A-ZÀ-ÿ][a-zà-ÿ]+)\s+(?:e|and)\s+([A-ZÀ-ÿ][a-zà-ÿ]+),\s*([12]\d{3}[a-z]?(?:,\s*[^)]+)?)\)", r"(\1; \2, \3)", line)

    # 4. Abreviatura errônea de página: pg., pág., P. -> p.
    line = re.sub(r"\(([^)]+),\s*(?:pg\.?|pág\.?|P\.?|pag\.?)\s*(\d+)\)", r"(\1, p. \2)", line, flags=re.IGNORECASE)

    return line


def fix_headings(line):
    # Seção com ponto após o último algarismo: "1. Introdução" -> "1 Introdução"
    m = re.match(r"^(#*\s*)(\d+(?:\.\d+)*)\.(\s+[A-Za-zÀ-ÿ].*)", line)
    if m:
        prefix = m.group(1)
        num_part = m.group(2)
        rest = m.group(3)
        return f"{prefix}{num_part}{rest}"
    return line


def fix_keywords(line):
    if re.match(r"^palavras-chave\s*:", line, re.IGNORECASE):
        prefix = re.match(r"^palavras-chave\s*:\s*", line, re.IGNORECASE).group(0)
        content = line[len(prefix):].strip()
        # Se tiver vírgulas e não tiver ponto e vírgula, substitui
        if "," in content and ";" not in content:
            items = [item.strip() for item in content.split(",") if item.strip()]
            fixed_content = "; ".join(items)
        else:
            items = [item.strip() for item in content.split(";") if item.strip()]
            fixed_content = "; ".join(items)
        # Assegura ponto final
        if not fixed_content.endswith("."):
            fixed_content += "."
        return f"Palavras-chave: {fixed_content}"
    return line


def fix_access_dates(text):
    # Converte "Acesso em: 15 de Novembro de 2026" para "Acesso em: 15 nov. 2026."
    def replace_date(m):
        day = m.group(1)
        month_word = m.group(2).lower()
        year = m.group(3)
        abnt_m = ABNT_MONTH_MAP.get(month_word, month_word[:3] + ".")
        return f"Acesso em: {day} {abnt_m} {year}."

    text = re.sub(r"acesso\s+em:\s*(\d{1,2})\s+(?:de\s+)?([A-Za-zÀ-ÿ]+)\s+(?:de\s+)?(\d{4})\.?", replace_date, text, flags=re.IGNORECASE)
    return text


def sort_and_format_references(ref_block_lines):
    """Agrupa referências, reordena em ordem alfabética estrita e normaliza espaçamento."""
    entries = []
    current_entry = []

    for l in ref_block_lines:
        line_str = l.strip()
        if line_str:
            current_entry.append(line_str)
        else:
            if current_entry:
                entries.append(" ".join(current_entry))
                current_entry = []

    if current_entry:
        entries.append(" ".join(current_entry))

    # Formata cada entrada (datas de acesso, etc.)
    formatted_entries = []
    for ent in entries:
        fixed_ent = fix_access_dates(ent)
        formatted_entries.append(fixed_ent)

    # Ordena alfabeticamente
    formatted_entries.sort(key=lambda x: re.sub(r"[^\w\s]", "", x.upper()))

    # Monta bloco formatado: cada referência em 1 parágrafo com linha em branco entre elas
    return "\n\n".join(formatted_entries)


def process_document(content):
    lines = content.splitlines()
    processed_lines = []
    in_references = False
    ref_lines = []
    header_references_line = None

    for line in lines:
        if re.match(r"^#*\s*REFERÊNCIAS\b", line.strip(), re.IGNORECASE):
            in_references = True
            header_references_line = line
            continue

        if in_references:
            if re.match(r"^#*\s*(APÊNDICE|ANEXO|GLOSSÁRIO)", line.strip(), re.IGNORECASE):
                # Fim do bloco de referências
                in_references = False
                formatted_refs = sort_and_format_references(ref_lines)
                processed_lines.append(header_references_line)
                processed_lines.append("")
                processed_lines.append(formatted_refs)
                processed_lines.append("")
                processed_lines.append(line)
                ref_lines = []
                continue
            ref_lines.append(line)
        else:
            # Aplica correções nas seções normais
            l = fix_headings(line)
            l = fix_citations_in_line(l)
            l = fix_keywords(l)
            processed_lines.append(l)

    # Se o documento terminou dentro do bloco de referências
    if in_references:
        formatted_refs = sort_and_format_references(ref_lines)
        processed_lines.append(header_references_line)
        processed_lines.append("")
        processed_lines.append(formatted_refs)

    return "\n".join(processed_lines)


def main():
    parser = argparse.ArgumentParser(description="Corretor Automático de Normas ABNT para TCC (Normas 2026).")
    parser.add_argument("-i", "--input", required=True, help="Arquivo de entrada (Markdown/Texto)")
    parser.add_argument("-o", "--output", default="tcc_corrigido_abnt.md", help="Arquivo corrigido de saída")

    args = parser.parse_args()

    if not os.path.exists(args.input):
        print(f"Erro: Arquivo '{args.input}' não encontrado.", file=sys.stderr)
        sys.exit(1)

    with open(args.input, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    fixed_text = process_document(content)

    with open(args.output, "w", encoding="utf-8") as f:
        f.write(fixed_text)

    print(f"\n" + "="*70)
    print(" CORREÇÃO AUTOMÁTICA ABNT CONCLUÍDA")
    print(f" Arquivo Original: {args.input}")
    print(f" Arquivo Formatado: {args.output}")
    print(" Correções aplicadas:")
    print("  ✓ Conversão de citações autor-data para caixa baixa (NBR 10520:2023)")
    print("  ✓ Ajuste de pontuação de encerramento após parênteses")
    print("  ✓ Padronização de páginas para 'p. XX'")
    print("  ✓ Correção da numeração progressiva de títulos (NBR 6024)")
    print("  ✓ Padronização de pontuação de palavras-chave (NBR 6028)")
    print("  ✓ Reordenação alfabética estrita das referências (NBR 6023)")
    print("  ✓ Padronização das datas de acesso com meses abreviados")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
