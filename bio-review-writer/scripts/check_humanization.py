#!/usr/bin/env python3
"""
Auditor de Humanização e Filtro Anti-IA para Redação Científica
Baseado nas Diretrizes Universais e Específicas do Guia de Redação Científica (2026).
Verifica:
1. Clichês e vocabulário proibido em Português e Inglês (Parte 3.2 do Guia).
2. Estruturas sintáticas artificiais de IA (abertura reflexa com Além disso, trios, travessões excessivos).
3. Terminologia veterinária ("responsável legal" vs proibição de "tutor" / "owner" vs "pet parent").
4. Consistência entre variantes de Inglês (Britânico vs Americano).
5. Marcadores de integridade pendentes ([VERIFICAR], [CITAÇÃO NECESSÁRIA], [DADO AUSENTE]).
"""

import sys
import os
import re
import json
import argparse
from datetime import datetime


FORBIDDEN_PT = [
    (r"\bé\s+crucial\b", "é crucial", "Substitua por formulação objetiva ou remova."),
    (r"\bé\s+fundamental\b", "é fundamental", "Elimine a adjetivação ou explique a razão técnica."),
    (r"\bé\s+importante\s+(?:notar|ressaltar|destacar)\s+que\b", "é importante notar/destacar que", "Vá direto ao ponto sem preâmbulos decorativos."),
    (r"\bvale\s+(?:destacar|ressaltar|notar)\s+que\b", "vale destacar que", "Corte a expressão introdutória."),
    (r"\bdesempenha\s+um\s+papel\s+(?:significativo|fundamental|crucial|importante)\b", "desempenha um papel...", "Substitua pela ação direta do sujeito."),
    (r"\bnesse\s+contexto\b", "nesse contexto", "Transição reflexa artificial. Remova ou use nexo causal específico."),
    (r"\bno\s+cenário\s+atual\b", "no cenário atual", "Clichê genérico de abertura."),
    (r"\bcada\s+vez\s+mais\b", "cada vez mais", "Vago e informal. Apresente os dados com taxa ou número."),
    (r"\bnotavelmente\b", "notavelmente", "Adjetivação promocional de IA."),
    (r"\babordagem\s+holística\b", "abordagem holística", "Jargão vago. Descreva os componentes exatos do método."),
    (r"\bmergulhar\b", "mergulhar", "Metáfora informal não científica."),
    (r"\buma\s+teia\s+complexa\b", "uma teia complexa", "Metáfora clichê de IA."),
    (r"\bpor\s+fim,\s+mas\s+não\s+menos\s+importante\b", "por fim, mas não menos importante", "Clichê retórico."),
    (r"\blança\s+luz\s+sobre\b", "lança luz sobre", "Metáfora de IA. Use 'elucida', 'revela', 'demonstra'."),
    (r"\bdesvendar\b", "desvendar", "Tom sensacionalista. Use termos descritivos."),
    (r"\bum\s+divisor\s+de\s+águas\b", "um divisor de águas", "Hipérbole promocional."),
    (r"\bde\s+suma\s+importância\b", "de suma importância", "Preciosísmo vazio."),
    (r"\bsob\s+essa\s+ótica\b", "sob essa ótica", "Conectivo desgastado."),
    (r"\bé\s+inegável\s+que\b", "é inegável que", "Afirmação dogmática sem evidência."),
    (r"\bmultifacetado\b", "multifacetado", "Clichê comum em textos sintetizados por IA."),
    (r"\bem\s+última\s+análise\b", "em última análise", "Fecho vago de IA."),
    (r"\bé\s+imperativo\b", "é imperativo", "Juízo moral/prescritivo em vez de factual."),
    (r"\bserve\s+como\s+um\s+lembrete\b", "serve como um lembrete", "Retórica de encerramento de IA."),
    (r"\bem\s+conclusão\b", "em conclusão (no corpo)", "Desnecessário anunciar a conclusão em prosa."),
    (r"\bem\s+suma\b", "em suma", "Resumo preguiçoso de IA."),
    (r"\bem\s+síntese\b", "em síntese", "Resumo preguiçoso de IA.")
]

FORBIDDEN_EN = [
    (r"\bdelve\b", "delve", "Classic AI fingerprint word. Use 'investigate', 'examine', 'analyze'."),
    (r"\bcrucial\b", "crucial", "Overused AI adjective. Replace with technical statement."),
    (r"\bpivotal\b", "pivotal", "Overused AI adjective. State the exact finding."),
    (r"\blandscape\b", "landscape (figurative)", "Overused AI metaphor. Use 'field', 'domain', or 'literature'."),
    (r"\brealm\b", "realm", "Pretentious AI trope."),
    (r"\btapestry\b", "tapestry", "Classic AI metaphor."),
    (r"\bintricate\b", "intricate", "Overused AI descriptor."),
    (r"\bnavigate\b", "navigate (figurative)", "Use 'address', 'evaluate', 'deal with'."),
    (r"\bshed\s+light\s+on\b", "shed light on", "Overused AI metaphor. Use 'elucidate', 'clarify', 'demonstrate'."),
    (r"\ba\s+testament\s+to\b", "a testament to", "Promotional AI cliché."),
    (r"\bit\s+is\s+important\s+to\s+note\s+that\b", "it is important to note that", "Padding words. Cut and state the fact."),
    (r"\bit\s+is\s+worth\s+noting\b", "it is worth noting", "Padding words. Cut entirely."),
    (r"\bplays\s+a\s+(?:significant|crucial|pivotal|key)\s+role\b", "plays a crucial role", "State direct mechanism or action."),
    (r"\bunderscore\b", "underscore", "Overused AI verb. Use 'highlights', 'shows', or 'indicates'."),
    (r"\bgroundbreaking\b", "groundbreaking", "Hyperbolic promotional term prohibited in scientific journals."),
    (r"\bnovel\s+and\s+significant\b", "novel and significant", "Promotional cliché."),
    (r"\bfills\s+a\s+critical\s+gap\b", "fills a critical gap", "Show how the study addresses the gap without proclaiming it."),
    (r"\bpaving\s+the\s+way\b", "paving the way", "Overused metaphor."),
    (r"\bholistic\b", "holistic", "Vague buzzword."),
    (r"\bmultifaceted\b", "multifaceted", "Typical AI crutch."),
    (r"\bultimately\b", "ultimately", "Vague transitional closing word."),
    (r"\bit\s+is\s+imperative\b", "it is imperative", "Prescriptive tone."),
    (r"\bserves\s+as\s+a\s+reminder\b", "serves as a reminder", "Preachy AI closing sentence."),
    (r"\bfoster\b", "foster", "Overused AI buzzword.")
]

BRITISH_MARKERS = [
    r"\b\w+is(?:e|ed|ing|ation)\b", r"\bhaemorrhag\w+\b", r"\bcolour\b", r"\bfavour\b",
    r"\bprogramme\b", r"\bcentre\b", r"\banaesthes\w+\b", r"\boedema\b"
]

AMERICAN_MARKERS = [
    r"\b\w+iz(?:e|ed|ing|ation)\b", r"\bhemorrhag\w+\b", r"\bcolor\b", r"\bfavor\b",
    r"\bprogram\b", r"\bcenter\b", r"\banesthes\w+\b", r"\bedema\b"
]


def audit_text_humanization(text, area="veterinaria", english_variant="auto"):
    lines = text.splitlines()
    issues = []
    
    # 1. Checagem de Vocabulário Proibido PT e EN
    for idx, line in enumerate(lines, start=1):
        line_str = line.strip()
        if not line_str or line_str.startswith("#"):
            continue

        # PT
        for pat, word, tip in FORBIDDEN_PT:
            for m in re.finditer(pat, line_str, re.IGNORECASE):
                issues.append({
                    "code": "CLICHE-PT",
                    "severity": "Moderada",
                    "category": "Filtro Anti-IA (Vocabulário PT)",
                    "message": f"Expressão de IA proibida detectada: '{m.group(0)}'.",
                    "line": idx,
                    "snippet": line_str[:90],
                    "recommendation": tip
                })

        # EN
        for pat, word, tip in FORBIDDEN_EN:
            for m in re.finditer(pat, line_str, re.IGNORECASE):
                issues.append({
                    "code": "CLICHE-EN",
                    "severity": "Moderada",
                    "category": "Filtro Anti-IA (Vocabulário EN)",
                    "message": f"Expressão típica de IA detectada: '{m.group(0)}'.",
                    "line": idx,
                    "snippet": line_str[:90],
                    "recommendation": tip
                })

        # 2. Terminologia Veterinária (Regra Inviolável)
        if "vet" in area.lower():
            # Português: Proibido "tutor"
            m_tutor = re.search(r"\b(tutor|tutores|tutora|tutoras)\b", line_str, re.IGNORECASE)
            if m_tutor:
                issues.append({
                    "code": "TERMO-VET-PT",
                    "severity": "Crítica",
                    "category": "Terminologia Jurídico-Veterinária",
                    "message": f"Uso estritamente proibido do termo '{m_tutor.group(0)}'. A norma jurídica e científica exige o uso exclusivo de 'responsável legal'.",
                    "line": idx,
                    "snippet": line_str[:90],
                    "recommendation": "Substituir imediatamente por 'responsável legal' ou 'responsáveis legais'."
                })

            # Inglês: Proibido "tutor" / "pet parent"
            m_pet_parent = re.search(r"\b(tutor|tutores|pet\s+parent|pet\s+parents)\b", line_str, re.IGNORECASE)
            if m_pet_parent:
                issues.append({
                    "code": "TERMO-VET-EN",
                    "severity": "Crítica",
                    "category": "Terminologia Veterinária (Inglês)",
                    "message": f"Uso incorreto de '{m_pet_parent.group(0)}'. Em língua inglesa deve-se utilizar exclusivamente 'owner' ou 'owners'.",
                    "line": idx,
                    "snippet": line_str[:90],
                    "recommendation": "Substituir por 'owner' ou 'owners'."
                })

        # 3. Estruturas Sintáticas Artificiais
        # "não apenas X, mas também Y"
        if re.search(r"\bnão\s+apenas\b[^\n]+\bmas\s+também\b", line_str, re.IGNORECASE) or \
           re.search(r"\bnot\s+only\b[^\n]+\bbut\s+also\b", line_str, re.IGNORECASE):
            issues.append({
                "code": "STRUCT-NOT-ONLY",
                "severity": "Leve",
                "category": "Estrutura Sintática Artificial",
                "message": "Construção artificial 'não apenas... mas também' / 'not only... but also'.",
                "line": idx,
                "snippet": line_str[:90],
                "recommendation": "Prefira enunciar as informações de forma direta e sem paralelismo forçado."
            })

        # Perguntas retóricas
        if "?" in line_str and not line_str.startswith("#"):
            issues.append({
                "code": "STRUCT-RHETORICAL",
                "severity": "Leve",
                "category": "Pergunta Retórica",
                "message": "Pergunta retórica no corpo do texto científico.",
                "line": idx,
                "snippet": line_str[:90],
                "recommendation": "Textos científicos devem apresentar afirmações e dados fundamentados, eliminando perguntas retóricas decorativas."
            })

    # 4. Abertura reflexa sequencial com Além disso / Ademais / Furthermore
    for idx in range(len(lines) - 1):
        l1 = lines[idx].strip()
        l2 = lines[idx+1].strip()
        if re.match(r"^(Além disso|Ademais|Outrossim|Portanto|Entretanto|Furthermore|Moreover)\b", l1, re.IGNORECASE) and \
           re.match(r"^(Além disso|Ademais|Outrossim|Portanto|Entretanto|Furthermore|Moreover)\b", l2, re.IGNORECASE):
            issues.append({
                "code": "STRUCT-CONNECTIVE-STACK",
                "severity": "Moderada",
                "category": "Vício de Conectivos",
                "message": "Início de parágrafos consecutivos com conectivos mecânicos de transição.",
                "line": idx + 1,
                "snippet": f"P1: {l1[:40]}... | P2: {l2[:40]}...",
                "recommendation": "Elimine o conectivo mecânico e inicie pela ideia central ou pelo sujeito da oração."
            })

    # 5. Marcadores de integridade pendentes
    for idx, line in enumerate(lines, start=1):
        for tag in ["[VERIFICAR", "[CITAÇÃO NECESSÁRIA]", "[DADO AUSENTE", "[DECISÃO DO AUTOR"]:
            if tag in line:
                issues.append({
                    "code": "TAG-PENDING",
                    "severity": "Crítica",
                    "category": "Integridade Editorial",
                    "message": f"Marcador de pendência de dados encontrado no texto: '{tag}'.",
                    "line": idx,
                    "snippet": line.strip()[:90],
                    "recommendation": "Resolver a pendência ou conferir na fonte antes da submissão."
                })

    # 6. Checagem de Variante do Inglês (British vs American)
    full_text = text.lower()
    br_count = sum(len(re.findall(pat, full_text)) for pat in BRITISH_MARKERS)
    am_count = sum(len(re.findall(pat, full_text)) for pat in AMERICAN_MARKERS)

    variant_diagnostic = None
    if br_count > 0 or am_count > 0:
        if br_count > 0 and am_count > 0 and abs(br_count - am_count) < 3:
            issues.append({
                "code": "LANG-VARIANT-MIX",
                "severity": "Moderada",
                "category": "Consistência de Variante da Língua Inglesa",
                "message": f"Mistura de grafias britânicas ({br_count}) e americanas ({am_count}) detectada no manuscrito.",
                "line": 1,
                "snippet": f"Ocorrências: Britânico ({br_count}), Americano ({am_count})",
                "recommendation": "Padronize integralmente para uma única variante (Britânica ou Americana) conforme as instruções aos autores da revista-alvo."
            })
        variant_diagnostic = {
            "british_occurrences": br_count,
            "american_occurrences": am_count,
            "predominant": "Britânico" if br_count > am_count else ("Americano" if am_count > br_count else "Misto/Indefinido")
        }

    # Cálculo da pontuação de humanização (0 a 100)
    penalties = 0
    for iss in issues:
        if iss["severity"] == "Crítica":
            penalties += 15
        elif iss["severity"] == "Moderada":
            penalties += 6
        else:
            penalties += 2

    score = max(0, 100 - penalties)

    return {
        "score_humanization": score,
        "status": "Excelente (Natural / Sem Fingerprints de IA)" if score >= 90 else ("Bom (Necessita Ajustes Leves)" if score >= 75 else "Alerta Alto de Clichês de IA"),
        "total_issues": len(issues),
        "critical_issues": sum(1 for i in issues if i["severity"] == "Crítica"),
        "moderate_issues": sum(1 for i in issues if i["severity"] == "Moderada"),
        "minor_issues": sum(1 for i in issues if i["severity"] == "Leve"),
        "variant_diagnostic": variant_diagnostic,
        "issues": issues
    }


def generate_markdown_report(audit_result, file_path, output_md):
    lines = []
    lines.append("# Relatório de Humanização Científica e Filtro Anti-IA")
    lines.append(f"**Documento Auditado:** `{os.path.basename(file_path)}`  ")
    lines.append(f"**Data da Auditoria:** {datetime.now().strftime('%d/%m/%Y %H:%M')}  ")
    lines.append(f"**Índice de Humanização Científica:** `{audit_result['score_humanization']}/100`  ")
    lines.append(f"**Diagnóstico Geral:** `{audit_result['status']}`  \n")

    lines.append("---")
    lines.append("## 1. Sumário de Apontamentos Anti-IA\n")
    lines.append("| Categoria | Apontamentos | Impacto Editorial |")
    lines.append("| :--- | :---: | :--- |")
    lines.append(f"| 🔴 **Crítica** | {audit_result['critical_issues']} | Uso de 'tutor', marcadores pendentes de integridade (`[VERIFICAR]`) |")
    lines.append(f"| 🟡 **Moderada** | {audit_result['moderate_issues']} | Clichês explícitos de IA ('é crucial', 'delve', 'landscape', misturas de inglês) |")
    lines.append(f"| 🟢 **Leve** | {audit_result['minor_issues']} | Sintaxe artificial ('não apenas... mas também', perguntas retóricas) |")
    lines.append(f"| **TOTAL** | **{audit_result['total_issues']}** | |")
    lines.append("")

    if audit_result.get("variant_diagnostic"):
        vd = audit_result["variant_diagnostic"]
        lines.append("### Diagnóstico da Língua Inglesa")
        lines.append(f"- **Variante Predominante:** `{vd['predominant']}`")
        lines.append(f"- **Marcadores Britânicos Detectados:** {vd['british_occurrences']}")
        lines.append(f"- **Marcadores Americanos Detectados:** {vd['american_occurrences']}")
        lines.append("")

    lines.append("---")
    lines.append("## 2. Detalhamento dos Apontamentos para Limpeza Textual\n")

    if not audit_result["issues"]:
        lines.append("🎉 **Texto limpo e humanizado! Nenhuma marca evidente de texto sintetizado por IA foi encontrada.**")
    else:
        for idx, iss in enumerate(audit_result["issues"], start=1):
            sev_icon = "🔴" if iss["severity"] == "Crítica" else ("🟡" if iss["severity"] == "Moderada" else "🟢")
            lines.append(f"### {idx}. [{iss['code']}] {sev_icon} {iss['category']}: {iss['message']}")
            lines.append(f"- **Localização:** Linha {iss['line']}")
            lines.append(f"- **Trecho no Manuscrito:** `{iss['snippet']}`")
            lines.append(f"- **Recomendação de Correção:** {iss['recommendation']}")
            lines.append("")

    with open(output_md, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    parser = argparse.ArgumentParser(description="Audita manuscritos científicos contra clichês de IA e terminologias inadequadas.")
    parser.add_argument("-i", "--input", required=True, help="Arquivo do manuscrito (Markdown ou TXT)")
    parser.add_argument("-a", "--area", default="veterinaria", help="Área temática (veterinaria, biologia, filosofia)")
    parser.add_argument("-o", "--output", default="relatorio_humanizacao.md", help="Arquivo Markdown de saída")
    parser.add_argument("-j", "--json", default=None, help="Arquivo JSON de saída opcional")

    args = parser.parse_args()

    if not os.path.exists(args.input):
        print(f"Erro: Arquivo '{args.input}' não encontrado.", file=sys.stderr)
        sys.exit(1)

    with open(args.input, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    res = audit_text_humanization(content, area=args.area)
    generate_markdown_report(res, args.input, args.output)

    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump(res, f, ensure_ascii=False, indent=2)

    print(f"\n" + "="*70)
    print(" AUDITORIA DE HUMANIZAÇÃO CONCLUÍDA")
    print(f" Arquivo: {args.input}")
    print(f" Índice de Humanização: {res['score_humanization']}/100 ({res['status']})")
    print(f" Apontamentos: {res['total_issues']} (Críticos: {res['critical_issues']}, Moderados: {res['moderate_issues']}, Leves: {res['minor_issues']})")
    print(f" Relatório gerado em: {args.output}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
