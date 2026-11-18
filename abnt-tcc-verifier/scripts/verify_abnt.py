#!/usr/bin/env python3
"""
Auditor e Verificador de Normas ABNT para TCC (Versão 2026 / NBR 10520:2023, NBR 14724, NBR 6023)
Analisa arquivos de texto / Markdown / TXT e gera relatório minucioso de não-conformidades.
"""

import sys
import os
import re
import json
import argparse
from datetime import datetime
from collections import defaultdict


ABNT_MONTHS = ["jan.", "fev.", "mar.", "abr.", "maio", "jun.", "jul.", "ago.", "set.", "out.", "nov.", "dez."]


class ABNTLinter:
    def __init__(self, content):
        self.content = content
        self.lines = content.splitlines()
        self.issues = []
        self.citations_found = []
        self.references_found = []

    def add_issue(self, code, severity, rule_nbr, message, line_num=None, snippet=None, fix_suggestion=None):
        self.issues.append({
            "code": code,
            "severity": severity,  # "Crítica", "Moderada", "Leve"
            "rule": rule_nbr,
            "message": message,
            "line": line_num,
            "snippet": snippet.strip() if snippet else None,
            "fix_suggestion": fix_suggestion
        })

    def check_citations_nbr10520(self):
        """
        NBR 10520:2023 / 2026:
        - Autores entre parênteses NÃO são mais em caixa alta total! Ex: (Silva, 2020), não (SILVA, 2020).
        - Abreviatura de página: p. 15 (não pg., pág., P.).
        - Separador de autores entre parênteses: ponto e vírgula (Silva; Santos, 2021).
        - Pontuação final: sempre fora dos parênteses da citação (...fato (Silva, 2020). e NÃO ...fato. (Silva, 2020)).
        """
        # 1. Ponto final antes do parêntese da citação
        punc_before_pattern = re.compile(r"(\.\s*\([A-ZÀ-ÿ][a-zA-ZÀ-ÿ\s;,0-9\.]+\))")
        
        # 2. Caixa alta obsoleta dentro de parênteses: (SILVA, 2020) ou (SILVA; SOUZA, 2021)
        # NBR 10520:2023 exige (Silva, 2020)
        old_caps_pattern = re.compile(r"\(([A-ZÀ-ÿ]{2,}(?:\s*;\s*[A-ZÀ-ÿ]{2,})*),\s*([12]\d{3}[a-z]?(?:,\s*[^)]+)?)\)")

        # 3. Formato de página inválido (pg., pág., P., pag.)
        invalid_page_pattern = re.compile(r"\([^)]*,\s*(?:[12]\d{3}[a-z]?),\s*(pg\.?|pág\.?|P\.?|pag\.?)\s*(\d+)[^)]*\)", re.IGNORECASE)

        # 4. Uso de vírgula ou 'e' em vez de ponto e vírgula entre parênteses para múltiplos autores
        multi_author_comma = re.compile(r"\(([A-ZÀ-ÿ][a-zà-ÿ]+)\s+(?:e|and)\s+([A-ZÀ-ÿ][a-zà-ÿ]+),\s*([12]\d{3})\)")

        # 5. Citações gerais no texto
        citation_general = re.compile(r"\(([A-ZÀ-ÿ][a-zA-ZÀ-ÿ\s;,\.]+),\s*([12]\d{3}[a-z]?)(?:,\s*p\.\s*\d+)?\)")

        in_references_block = False

        for idx, line in enumerate(self.lines, start=1):
            line_str = line.strip()
            if re.match(r"^#+\s*REFERÊNCIAS", line_str, re.IGNORECASE):
                in_references_block = True
                continue

            if in_references_block:
                continue

            # Ponto antes do parêntese
            m_punc = punc_before_pattern.search(line_str)
            if m_punc:
                snippet = m_punc.group(1)
                fixed = snippet.replace(".", "").strip() + "."
                self.add_issue(
                    code="CIT-001",
                    severity="Moderada",
                    rule_nbr="NBR 10520",
                    message="Ponto final posicionado antes da citação. Na ABNT, a pontuação que encerra o período fica APÓS os parênteses da citação.",
                    line_num=idx,
                    snippet=snippet,
                    fix_suggestion=f"Mover o ponto final para o fim da citação: '... {fixed}'"
                )

            # Caixa alta obsoleta (mudança fundamental da NBR 10520:2023)
            for m in old_caps_pattern.finditer(line_str):
                snippet = m.group(0)
                authors_raw = m.group(1)
                year_rest = m.group(2)
                
                # Transform to capitalized lowercase
                parts = [p.strip().title() for p in authors_raw.split(";")]
                new_authors = "; ".join(parts)
                fixed = f"({new_authors}, {year_rest})"

                self.add_issue(
                    code="CIT-002",
                    severity="Crítica",
                    rule_nbr="NBR 10520:2023",
                    message="Uso de CAIXA ALTA no sobrenome dentro dos parênteses da citação. A norma NBR 10520 atualizada determina o uso de inicial maiúscula e restante minúsculo tanto fora quanto dentro dos parênteses.",
                    line_num=idx,
                    snippet=snippet,
                    fix_suggestion=fixed
                )

            # Abreviatura errônea de página
            for m in invalid_page_pattern.finditer(line_str):
                snippet = m.group(0)
                err_page = m.group(1)
                p_num = m.group(2)
                if err_page != "p.":
                    fixed = re.sub(r"(?:pg\.?|pág\.?|P\.?|pag\.?)\s*" + p_num, f"p. {p_num}", snippet, flags=re.IGNORECASE)
                    self.add_issue(
                        code="CIT-003",
                        severity="Leve",
                        rule_nbr="NBR 10520",
                        message=f"Abreviatura de página incorreta ('{err_page}'). A norma ABNT prescreve 'p.' para página ou 'f.' para folha.",
                        line_num=idx,
                        snippet=snippet,
                        fix_suggestion=fixed
                    )

            # Uso de 'e' em vez de ';' dentro dos parênteses
            for m in multi_author_comma.finditer(line_str):
                snippet = m.group(0)
                a1, a2, yr = m.group(1), m.group(2), m.group(3)
                fixed = f"({a1}; {a2}, {yr})"
                self.add_issue(
                    code="CIT-004",
                    severity="Moderada",
                    rule_nbr="NBR 10520",
                    message="Autores dentro de parênteses separados por 'e' em vez de ponto e vírgula (;).",
                    line_num=idx,
                    snippet=snippet,
                    fix_suggestion=fixed
                )

            # Coleta citações para cruzamento com referências
            for m in citation_general.finditer(line_str):
                raw_auth = m.group(1).strip()
                raw_yr = m.group(2).strip()
                self.citations_found.append({"author": raw_auth, "year": raw_yr, "line": idx})

    def check_sections_nbr6024(self):
        """
        NBR 6024:
        - Numeração progressiva: 1 INTRODUÇÃO, 1.1 Contexto, 1.1.1 Detalhe.
        - Não se usa ponto após o último algarismo (ex: '1.1.' é incorreto, o certo é '1.1').
        - Deve haver um espaço entre o número e o título (ex: '1INTRODUÇÃO' é incorreto).
        """
        heading_pattern = re.compile(r"^(#{1,6}|\d+(?:\.\d+)*\.?)\s*(.*)")
        
        for idx, line in enumerate(self.lines, start=1):
            line_str = line.strip()
            if not line_str:
                continue

            # Se começar com número tipo '1.' ou '1.1.'
            m_dot = re.match(r"^(\d+(?:\.\d+)*)\.(\s+[A-Za-zÀ-ÿ].*)", line_str)
            if m_dot:
                num_part = m_dot.group(1)
                rest = m_dot.group(2)
                self.add_issue(
                    code="SEC-001",
                    severity="Moderada",
                    rule_nbr="NBR 6024",
                    message=f"Ponto desnecessário após o último número da seção ('{num_part}.'). A NBR 6024 veda o ponto após o último algarismo.",
                    line_num=idx,
                    snippet=line_str[:50],
                    fix_suggestion=f"{num_part}{rest}"
                )

    def check_resumo_nbr6028(self):
        """
        NBR 6028:
        - TCC / Monografias / Teses: extensão entre 150 e 500 palavras.
        - Deve ser constituído de uma sequência de frases concisas em parágrafo único (sem parágrafos múltiplos).
        - Palavras-chave: separadas entre si por ponto e vírgula e finalizadas por ponto.
        """
        resumo_start = None
        resumo_lines = []
        in_resumo = False

        for idx, line in enumerate(self.lines, start=1):
            if re.match(r"^#*\s*RESUMO\b", line.strip(), re.IGNORECASE):
                in_resumo = True
                resumo_start = idx
                continue
            if in_resumo:
                if re.match(r"^#*\s*(ABSTRACT|SUMÁRIO|INTRODUÇÃO|\d\s+INTRODUÇÃO)", line.strip(), re.IGNORECASE):
                    break
                if line.strip():
                    resumo_lines.append(line.strip())

        if resumo_start:
            # Conta parágrafos no resumo (linhas não vazias separadas por vazias)
            full_resumo_text = " ".join([l for l in resumo_lines if not l.lower().startswith("palavras-chave")])
            words = full_resumo_text.split()
            word_count = len(words)

            if word_count < 150 or word_count > 500:
                self.add_issue(
                    code="RES-001",
                    severity="Moderada",
                    rule_nbr="NBR 6028",
                    message=f"Extensão do Resumo fora do padrão ({word_count} palavras). A NBR 6028 estipula de 150 a 500 palavras para trabalhos acadêmicos (TCC, teses, dissertações).",
                    line_num=resumo_start,
                    snippet=f"Total de palavras: {word_count}",
                    fix_suggestion="Ajustar o resumo para conter entre 150 e 500 palavras."
                )

            # Verifica palavras-chave
            kw_line = None
            for l in resumo_lines:
                if re.match(r"^palavras-chave\s*:", l, re.IGNORECASE):
                    kw_line = l
                    break

            if kw_line:
                raw_kws = re.sub(r"^palavras-chave\s*:\s*", "", kw_line, flags=re.IGNORECASE).strip()
                if not raw_kws.endswith("."):
                    self.add_issue(
                        code="RES-002",
                        severity="Leve",
                        rule_nbr="NBR 6028",
                        message="As palavras-chave devem ser finalizadas por ponto final.",
                        snippet=kw_line,
                        fix_suggestion=f"{kw_line}."
                    )
                if "," in raw_kws and ";" not in raw_kws:
                    self.add_issue(
                        code="RES-003",
                        severity="Moderada",
                        rule_nbr="NBR 6028",
                        message="Palavras-chave separadas por vírgula (,). A norma ABNT exige separação por ponto e vírgula (;).",
                        snippet=kw_line,
                        fix_suggestion="Substituir as vírgulas entre as palavras-chave por ponto e vírgula (;)."
                    )

    def check_references_nbr6023(self):
        """
        NBR 6023:
        - Alinhamento à margem esquerda.
        - Espaçamento simples e separadas por linha em branco.
        - Ordem alfabética ascendente.
        - Destaque tipográfico no título (não no subtítulo).
        - Disponível em: <URL>. Acesso em: dia mês. ano.
        """
        ref_start = None
        ref_entries = []
        current_entry = []

        for idx, line in enumerate(self.lines, start=1):
            if re.match(r"^#*\s*REFERÊNCIAS\b", line.strip(), re.IGNORECASE):
                ref_start = idx
                continue
            if ref_start and idx > ref_start:
                if re.match(r"^#*\s*(APÊNDICE|ANEXO|GLOSSÁRIO)", line.strip(), re.IGNORECASE):
                    break
                line_str = line.strip()
                if line_str:
                    current_entry.append((idx, line_str))
                else:
                    if current_entry:
                        full_txt = " ".join([t for _, t in current_entry])
                        first_line = current_entry[0][0]
                        ref_entries.append({"line": first_line, "text": full_txt})
                        current_entry = []

        if current_entry:
            full_txt = " ".join([t for _, t in current_entry])
            ref_entries.append({"line": current_entry[0][0], "text": full_txt})

        self.references_found = ref_entries

        # Verifica ordem alfabética
        authors = []
        for r in ref_entries:
            txt = r["text"]
            # Extrai autor
            m_auth = re.match(r"^([A-ZÀ-ÿ\s,]+)\.", txt)
            if m_auth:
                authors.append((m_auth.group(1).strip().upper(), r["line"], txt))

        for i in range(len(authors) - 1):
            curr_a, curr_line, _ = authors[i]
            next_a, next_line, next_txt = authors[i+1]
            if curr_a > next_a:
                self.add_issue(
                    code="REF-001",
                    severity="Moderada",
                    rule_nbr="NBR 6023",
                    message=f"Lista de referências fora da ordem alfabética estrita: '{curr_a}' aparece antes de '{next_a}'.",
                    line_num=next_line,
                    snippet=next_txt[:60] + "...",
                    fix_suggestion=f"Reordenar a entrada '{next_a}' para antes de '{curr_a}'."
                )

        # Verifica Acesso em: dia mês. ano
        for r in ref_entries:
            txt = r["text"]
            if "disponível em:" in txt.lower() or "acesso em:" in txt.lower():
                m_acc = re.search(r"acesso\s+em:\s*([^.]+)\.", txt, re.IGNORECASE)
                if not m_acc:
                    self.add_issue(
                        code="REF-002",
                        severity="Moderada",
                        rule_nbr="NBR 6023",
                        message="Referência de documento online com formato de data de acesso ausente ou sem ponto final.",
                        line_num=r["line"],
                        snippet=txt[:80] + "...",
                        fix_suggestion="Adicionar no padrão: 'Acesso em: dia mês abreviado. ano.'"
                    )
                else:
                    acc_date = m_acc.group(1).strip()
                    # Checa abreviatura de mês em português
                    month_valid = any(m in acc_date.lower() for m in ABNT_MONTHS)
                    if not month_valid and not re.search(r"\b\d{1,2}/\d{1,2}/\d{4}\b", acc_date):
                        self.add_issue(
                            code="REF-003",
                            severity="Leve",
                            rule_nbr="NBR 6023",
                            message=f"Mês da data de acesso ('{acc_date}') não segue a abreviatura oficial ABNT (jan., fev., mar., abr., maio, jun., jul., ago., set., out., nov., dez.).",
                            line_num=r["line"],
                            snippet=f"Acesso em: {acc_date}.",
                            fix_suggestion="Utilizar mês abreviado oficial (ex: 'Acesso em: 12 nov. 2026.')"
                        )

    def check_citation_reference_crossmatch(self):
        """Cruza todas as citações feitas no texto com as referências listadas no final."""
        if not self.references_found or not self.citations_found:
            return

        ref_authors_text = " ".join([r["text"].upper() for r in self.references_found])

        # Verifica se cada citação está nas referências
        for cit in self.citations_found:
            author_norm = cit["author"].upper().split(";")[0].split(",")[0].strip()
            # Ignora et al ou termos genéricos
            if author_norm in ["BRASIL", "IBGE", "OMS", "WHO"] or len(author_norm) < 3:
                continue

            if author_norm not in ref_authors_text:
                self.add_issue(
                    code="CRO-001",
                    severity="Crítica",
                    rule_nbr="NBR 14724 / NBR 6023",
                    message=f"Citação no texto sem referência correspondente: '({cit['author']}, {cit['year']})' não foi encontrada na seção de Referências.",
                    line_num=cit["line"],
                    snippet=f"({cit['author']}, {cit['year']})",
                    fix_suggestion=f"Incluir a obra de {cit['author']} ({cit['year']}) na lista de Referências ao final do trabalho."
                )

    def run_all(self):
        self.check_citations_nbr10520()
        self.check_sections_nbr6024()
        self.check_resumo_nbr6028()
        self.check_references_nbr6023()
        self.check_citation_reference_crossmatch()

        # Calcular pontuação de conformidade (Compliance Score 0-100)
        total_penalties = 0
        for iss in self.issues:
            if iss["severity"] == "Crítica":
                total_penalties += 12
            elif iss["severity"] == "Moderada":
                total_penalties += 6
            else:
                total_penalties += 2

        score = max(0, 100 - total_penalties)

        return {
            "score": score,
            "status": "Aprovado" if score >= 90 else ("Aprovado com Ressalvas" if score >= 70 else "Necessita Correções Substanciais"),
            "total_issues": len(self.issues),
            "critical_count": sum(1 for i in self.issues if i["severity"] == "Crítica"),
            "moderate_count": sum(1 for i in self.issues if i["severity"] == "Moderada"),
            "minor_count": sum(1 for i in self.issues if i["severity"] == "Leve"),
            "issues": self.issues
        }


def generate_audit_markdown(report_data, file_path, output_md):
    lines = []
    lines.append("# Relatório de Auditoria de Conformidade ABNT para TCC")
    lines.append(f"**Documento Auditado:** `{os.path.basename(file_path)}`  ")
    lines.append(f"**Data da Auditoria:** {datetime.now().strftime('%d/%m/%Y %H:%M')}  ")
    lines.append(f"**Nota de Conformidade ABNT:** `{report_data['score']}/100`  ")
    lines.append(f"**Status da Banca:** `{report_data['status']}`  \n")

    lines.append("---")
    lines.append("## 1. Resumo Quantitativo de Apontamentos\n")
    lines.append("| Categoria de Gravidade | Quantidade | Impacto na Avaliação |")
    lines.append("| :--- | :---: | :--- |")
    lines.append(f"| 🔴 **Crítica** | {report_data['critical_count']} | Violações normativas severas (ex: citação sem referência, norma desatualizada) |")
    lines.append(f"| 🟡 **Moderada** | {report_data['moderate_count']} | Inconsistências de formatação (ex: separadores, pontuação, ordem alfabética) |")
    lines.append(f"| 🟢 **Leve** | {report_data['minor_count']} | Ajustes tipográficos e abreviaturas |")
    lines.append(f"| **TOTAL** | **{report_data['total_issues']}** | |")
    lines.append("")

    lines.append("---")
    lines.append("## 2. Detalhamento dos Apontamentos e Instruções de Correção\n")

    if not report_data["issues"]:
        lines.append("🎉 **Parabéns! Nenhuma não-conformidade foi detectada nas verificações automatizadas.**")
    else:
        for idx, iss in enumerate(report_data["issues"], start=1):
            sev_icon = "🔴" if iss["severity"] == "Crítica" else ("🟡" if iss["severity"] == "Moderada" else "🟢")
            lines.append(f"### {idx}. [{iss['code']}] {sev_icon} {iss['rule']}: {iss['message']}")
            if iss.get("line"):
                lines.append(f"- **Localização:** Linha {iss['line']}")
            if iss.get("snippet"):
                lines.append(f"- **Trecho Identificado:** `{iss['snippet']}`")
            if iss.get("fix_suggestion"):
                lines.append(f"- **Como Corrigir:** {iss['fix_suggestion']}")
            lines.append("")

    lines.append("---")
    lines.append("## 3. Próximo Passo Recomendado")
    lines.append("Para efetuar a correção automática destes apontamentos, utilize a skill complementar:")
    lines.append("👉 `abnt-tcc-fixer` (disponível em `abnt-tcc-fixer/scripts/fix_abnt.py`).")

    with open(output_md, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    parser = argparse.ArgumentParser(description="Verificador de Conformidade ABNT para TCC e Monografias.")
    parser.add_argument("-i", "--input", required=True, help="Arquivo de texto ou Markdown do TCC")
    parser.add_argument("-o", "--output-report", default="relatorio_abnt.md", help="Relatório Markdown de saída")
    parser.add_argument("-j", "--json", default=None, help="Arquivo JSON de saída opcional")

    args = parser.parse_args()

    if not os.path.exists(args.input):
        print(f"Erro: Arquivo '{args.input}' não encontrado.", file=sys.stderr)
        sys.exit(1)

    with open(args.input, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    linter = ABNTLinter(content)
    report = linter.run_all()

    generate_audit_markdown(report, args.input, args.output_report)

    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump(report, f, ensure_ascii=False, indent=2)

    print(f"\n" + "="*70)
    print(f" AUDITORIA ABNT CONCLUÍDA: {args.input}")
    print(f" Nota de Conformidade: {report['score']}/100 ({report['status']})")
    print(f" Total de Apontamentos: {report['total_issues']} (Críticos: {report['critical_count']}, Moderados: {report['moderate_count']}, Leves: {report['minor_count']})")
    print(f" Relatório gerado em: {args.output_report}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
