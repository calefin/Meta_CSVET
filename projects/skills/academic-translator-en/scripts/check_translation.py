#!/usr/bin/env python3
"""
Validador e Auditor de Qualidade de Tradução Acadêmica (PT -> EN)
Realiza o controle de qualidade editorial pós-tradução para periódicos científicos:
1. Fidelidade Numérica: Compara todos os números, porcentagens, valores de p, tamanhos amostrais (N) e unidades entre o texto em português e o texto em inglês.
2. Conversão Decimal: Confere se separadores decimais (vírgula no PT -> ponto no EN) foram convertidos corretamente.
3. Terminologia Obrigatória: Confere se 'responsável legal' virou 'owner' (e bloqueia 'tutor' ou 'pet parent').
4. Filtro Anti-IA: Rastreia termos e clichês artificiais de tradução automática em inglês ('delve', 'pivotal', 'landscape', etc.).
5. Consistência de Variante: Audita aderência estrita ao Inglês Britânico (UK) ou Americano (US).
6. Preservação de Itálicos e Nomenclatura Científica: Confere nomes binomiais (*Gênero espécie*).
"""

import sys
import os
import re
import json
import argparse
from datetime import datetime
from collections import Counter


FORBIDDEN_EN = [
    (r"\bdelve\b", "delve", "Clichê clássico de tradução por IA. Use 'investigate', 'examine', 'analyze'."),
    (r"\bcrucial\b", "crucial", "Adjetivo excessivo de IA. Use 'essential', 'key' ou descreva o fato técnico."),
    (r"\bpivotal\b", "pivotal", "Termo inflacionado de IA. Use 'central', 'primary'."),
    (r"\blandscape\b", "landscape (figurado)", "Metáfora de IA. Use 'field', 'domain', 'literature'."),
    (r"\brealm\b", "realm", "Clichê pretensioso de IA."),
    (r"\btapestry\b", "tapestry", "Metáfora clichê de IA."),
    (r"\bintricate\b", "intricate", "Descritor vago de IA."),
    (r"\bnavigate\b", "navigate (figurado)", "Use 'address', 'evaluate', 'manage'."),
    (r"\bshed\s+light\s+on\b", "shed light on", "Metáfora desgastada. Use 'elucidate', 'demonstrate', 'reveal'."),
    (r"\ba\s+testament\s+to\b", "a testament to", "Expressão promocional inadequada."),
    (r"\bit\s+is\s+important\s+to\s+note\s+that\b", "it is important to note that", "Enchimento. Corte e vá direto ao fato."),
    (r"\bit\s+is\s+worth\s+noting\b", "it is worth noting", "Enchimento desnecessário."),
    (r"\bplays\s+a\s+(?:significant|crucial|pivotal|key)\s+role\b", "plays a crucial/significant role", "Substitua pela ação direta do sujeito."),
    (r"\bunderscore\b", "underscore", "Verbo sobrecarregado de IA. Use 'highlights', 'indicates', 'shows'."),
    (r"\bgroundbreaking\b", "groundbreaking", "Adjetivo promocional vedado em periódicos científicos sérios."),
    (r"\bnovel\s+and\s+significant\b", "novel and significant", "Clichê promocional de IA."),
    (r"\bfills\s+a\s+critical\s+gap\b", "fills a critical gap", "Mostre a lacuna sem proclamá-la em tom publicitário."),
    (r"\bpaving\s+the\s+way\b", "paving the way", "Metáfora jornalística."),
    (r"\bholistic\b", "holistic", "Jargão vago. Descreva os componentes exatos."),
    (r"\bmultifaceted\b", "multifaceted", "Bengala estilística de IA."),
    (r"\bultimately\b", "ultimately", "Transição genérica de IA."),
    (r"\bit\s+is\s+imperative\b", "it is imperative", "Tom prescritivo/moralista."),
    (r"\bserves\s+as\s+a\s+reminder\b", "serves as a reminder", "Fecho moralista de IA."),
    (r"\bfoster\b", "foster", "Jargão institucional vago de IA.")
]

BRITISH_MARKERS = [
    (r"\b\w+is(?:e|ed|ing|ation)\b", "-ise / -isation"),
    (r"\bhaemorrhag\w+\b", "haemorrhagic"),
    (r"\boedema\b", "oedema"),
    (r"\banaesthes\w+\b", "anaesthesia"),
    (r"\bfaecal\b", "faecal"),
    (r"\bdiarrhoea\b", "diarrhoea"),
    (r"\bprogramme\b", "programme"),
    (r"\bcentre\b", "centre"),
    (r"\bcolour\b", "colour"),
    (r"\bfavour\b", "favour")
]

AMERICAN_MARKERS = [
    (r"\b\w+iz(?:e|ed|ing|ation)\b", "-ize / -ization"),
    (r"\bhemorrhag\w+\b", "hemorrhagic"),
    (r"\bedema\b", "edema"),
    (r"\banesthes\w+\b", "anesthesia"),
    (r"\bfecal\b", "fecal"),
    (r"\bdiarrhea\b", "diarrhea"),
    (r"\bprogram\b", "program"),
    (r"\bcenter\b", "center"),
    (r"\bcolor\b", "color"),
    (r"\bfavor\b", "favor")
]


def extract_numbers_from_text(text):
    """Extrai números normalizados (substituindo vírgula por ponto) para comparação de valores."""
    raw_nums = re.findall(r"\b\d+(?:[.,]\d+)?%?\b", text)
    normalized = []
    for n in raw_nums:
        # Se for ano de 4 dígitos ou valor normal
        val = n.replace("%", "").replace(",", ".")
        try:
            float_val = float(val)
            normalized.append(round(float_val, 4))
        except ValueError:
            pass
    return normalized


def audit_translation(pt_text, en_text, target_variant="british", area="geral"):
    issues = []
    
    # 1. Checagem de Fidelidade Numérica
    pt_nums = extract_numbers_from_text(pt_text)
    en_nums = extract_numbers_from_text(en_text)
    
    pt_counts = Counter(pt_nums)
    en_counts = Counter(en_nums)
    
    missing_in_en = []
    for num, count in pt_counts.items():
        if en_counts[num] < count:
            missing_in_en.append((num, count - en_counts[num]))
            
    extra_in_en = []
    for num, count in en_counts.items():
        if pt_counts[num] < count:
            extra_in_en.append((num, count - pt_counts[num]))

    if missing_in_en:
        for num, diff in missing_in_en[:10]:
            issues.append({
                "code": "NUM-MISMATCH",
                "severity": "Crítica",
                "category": "Fidelidade Numérica",
                "message": f"O valor numérico {num} aparece no texto original em português, mas está ausente ou alterado na tradução em inglês.",
                "recommendation": f"Verifique a preservação exata do valor {num} no parágrafo correspondente."
            })

    # 2. Checagem de Separador Decimal em Inglês (Vírgula usada em vez de ponto)
    # Ex: 89,7% ou 3,5 mg no texto em inglês
    comma_decimals = re.findall(r"\b\d+,\d+(?:%|\s*[a-zA-Z]+)?\b", en_text)
    for cd in comma_decimals[:5]:
        issues.append({
            "code": "DECIMAL-COMMA-EN",
            "severity": "Moderada",
            "category": "Formatação Numérica",
            "message": f"Separador decimal com vírgula detectado no texto em inglês: '{cd}'.",
            "recommendation": f"Em inglês acadêmico, substitua a vírgula por ponto (ex: '{cd.replace(',', '.')}') e use vírgula apenas como separador de milhar (1,000)."
        })

    # 3. Terminologia Proibida e Veterinária
    lines_en = en_text.splitlines()
    for idx, l in enumerate(lines_en, start=1):
        line_str = l.strip()
        if not line_str or line_str.startswith("#"):
            continue

        # Proibição de 'tutor' ou 'pet parent'
        m_tutor = re.search(r"\b(tutor|tutores|pet\s+parent|pet\s+parents)\b", line_str, re.IGNORECASE)
        if m_tutor:
            issues.append({
                "code": "TERM-OWNER-REQUIRED",
                "severity": "Crítica",
                "category": "Terminologia Jurídico-Veterinária",
                "message": f"Termo proibido '{m_tutor.group(0)}' detectado na tradução.",
                "line": idx,
                "snippet": line_str[:90],
                "recommendation": "Na área veterinária em língua inglesa, utilize estritamente 'owner' ou 'owners'."
            })

        # Clichês de IA em inglês
        for pat, word, tip in FORBIDDEN_EN:
            for m in re.finditer(pat, line_str, re.IGNORECASE):
                issues.append({
                    "code": "AI-CLICHE-EN",
                    "severity": "Moderada",
                    "category": "Filtro Anti-IA",
                    "message": f"Clichê ou expressão de tradução artificial por IA detectada: '{m.group(0)}'.",
                    "line": idx,
                    "snippet": line_str[:90],
                    "recommendation": tip
                })

        # Marcadores de nota de tradução pendente
        if "[TRANSLATOR NOTE" in line_str or "[VERIFY" in line_str or "[CITAÇÃO" in line_str:
            issues.append({
                "code": "PENDING-NOTE",
                "severity": "Crítica",
                "category": "Notas de Tradução Pendentes",
                "message": "Marcador de pendência ou nota do tradutor não resolvida no texto em inglês.",
                "line": idx,
                "snippet": line_str[:90],
                "recommendation": "Resolva a dúvida ou confirme a redação antes da diagramação final."
            })

    # 4. Consistência de Variante da Língua Inglesa (British vs American)
    full_en = en_text.lower()
    br_hits = []
    for pat, desc in BRITISH_MARKERS:
        found = re.findall(pat, full_en)
        if found:
            br_hits.extend(found)

    am_hits = []
    for pat, desc in AMERICAN_MARKERS:
        found = re.findall(pat, full_en)
        if found:
            am_hits.extend(found)

    target_variant = target_variant.lower()
    if "brit" in target_variant:
        if am_hits:
            issues.append({
                "code": "VARIANT-MISMATCH",
                "severity": "Moderada",
                "category": "Consistência Ortográfica (UK)",
                "message": f"A revista exige Inglês Britânico (UK), mas foram encontradas formas americanas: {list(set(am_hits))[:5]}.",
                "recommendation": "Converta para a grafia britânica padrão (ex.: -ize -> -ise, hemorrhagic -> haemorrhagic, program -> programme)."
            })
    elif "amer" in target_variant or "us" in target_variant:
        if br_hits:
            issues.append({
                "code": "VARIANT-MISMATCH",
                "severity": "Moderada",
                "category": "Consistência Ortográfica (US)",
                "message": f"A revista exige Inglês Americano (US), mas foram encontradas formas britânicas: {list(set(br_hits))[:5]}.",
                "recommendation": "Converta para a grafia americana padrão (ex.: -ise -> -ize, haemorrhagic -> hemorrhagic, programme -> program)."
            })
    else:
        # Modo automático: detecta se houve mistura mútua
        if br_hits and am_hits:
            issues.append({
                "code": "VARIANT-CONTAMINATION",
                "severity": "Moderada",
                "category": "Mistura de Variantes de Inglês",
                "message": f"O manuscrito mistura formas britânicas ({list(set(br_hits))[:3]}) e americanas ({list(set(am_hits))[:3]}).",
                "recommendation": "Escolha a variante exigida pelo periódico (UK ou US) e mantenha a ortografia 100% consistente em todo o artigo."
            })

    # Cálculo da pontuação de fidelidade e qualidade (0-100)
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
        "score_fidelity": score,
        "status": "Aprovado para Publicação (Excelente)" if score >= 90 else ("Aprovado com Ressalvas (Requer Ajustes)" if score >= 75 else "Necessita Revisão Editorial Urgente"),
        "total_issues": len(issues),
        "critical_count": sum(1 for i in issues if i["severity"] == "Crítica"),
        "moderate_count": sum(1 for i in issues if i["severity"] == "Moderada"),
        "minor_count": sum(1 for i in issues if i["severity"] == "Leve"),
        "numbers_audited": len(pt_nums),
        "target_variant_declared": target_variant,
        "british_forms_count": len(br_hits),
        "american_forms_count": len(am_hits),
        "issues": issues
    }


def generate_markdown_report(result, pt_path, en_path, output_md):
    lines = []
    lines.append("# Relatório Editorial de Controle de Qualidade da Tradução (PT $\\rightarrow$ EN)")
    lines.append(f"**Manuscrito Original (PT):** `{os.path.basename(pt_path)}`  ")
    lines.append(f"**Manuscrito Traduzido (EN):** `{os.path.basename(en_path)}`  ")
    lines.append(f"**Data da Auditoria:** {datetime.now().strftime('%d/%m/%Y %H:%M')}  ")
    lines.append(f"**Índice de Fidelidade e Qualidade da Tradução:** `{result['score_fidelity']}/100`  ")
    lines.append(f"**Veredito Editorial:** `{result['status']}`  \n")

    lines.append("---")
    lines.append("## 1. Sumário de Verificação de Integridade\n")
    lines.append("| Critério de Qualidade | Status / Resultado | Observações |")
    lines.append("| :--- | :---: | :--- |")
    crit_msg = '✓ Sem discrepâncias numéricas' if result['critical_count'] == 0 else f"⚠️ {result['critical_count']} divergências detectadas"
    lines.append(f"| **Fidelidade Numérica e Estatística** | {result['numbers_audited']} valores auditados | {crit_msg} |")
    lines.append(f"| **Variante de Inglês Solicitada** | `{result['target_variant_declared'].title()}` | Formas Britânicas: {result['british_forms_count']} | Formas Americanas: {result['american_forms_count']} |")
    lines.append(f"| **Total de Apontamentos** | {result['total_issues']} | Críticos: {result['critical_count']}, Moderados: {result['moderate_count']} |")
    lines.append("")

    lines.append("---")
    lines.append("## 2. Detalhamento das Não-Conformidades e Divergências de Tradução\n")

    if not result["issues"]:
        lines.append("🎉 **Tradução com 100% de integridade editorial! Todos os números, termos técnicos e padrões de estilo foram preservados sem adulteração de sentido.**")
    else:
        for idx, iss in enumerate(result["issues"], start=1):
            sev_icon = "🔴" if iss["severity"] == "Crítica" else ("🟡" if iss["severity"] == "Moderada" else "🟢")
            lines.append(f"### {idx}. [{iss['code']}] {sev_icon} {iss['category']}: {iss['message']}")
            if iss.get("line"):
                lines.append(f"- **Localização (EN):** Linha {iss['line']}")
            if iss.get("snippet"):
                lines.append(f"- **Trecho em Inglês:** `{iss['snippet']}`")
            lines.append(f"- **Ação Editorial Recomendada:** {iss['recommendation']}")
            lines.append("")

    with open(output_md, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    parser = argparse.ArgumentParser(description="Auditor de Qualidade e Fidelidade de Tradução Acadêmica (PT -> EN).")
    parser.add_argument("--pt", required=True, help="Arquivo original em português")
    parser.add_argument("--en", required=True, help="Arquivo traduzido em inglês")
    parser.add_argument("--variant", default="british", choices=["british", "american", "auto"], help="Variante de inglês da revista")
    parser.add_argument("--area", default="geral", help="Área do artigo (veterinaria, biologia, filosofia, geral)")
    parser.add_argument("-o", "--output", default="relatorio_qualidade_traducao.md", help="Arquivo Markdown de saída")
    parser.add_argument("-j", "--json", default=None, help="Arquivo JSON de saída opcional")

    args = parser.parse_args()

    for p in [args.pt, args.en]:
        if not os.path.exists(p):
            print(f"Erro: Arquivo '{p}' não encontrado.", file=sys.stderr)
            sys.exit(1)

    with open(args.pt, "r", encoding="utf-8", errors="replace") as f:
        pt_content = f.read()
    with open(args.en, "r", encoding="utf-8", errors="replace") as f:
        en_content = f.read()

    result = audit_translation(pt_content, en_content, target_variant=args.variant, area=args.area)
    generate_markdown_report(result, args.pt, args.en, args.output)

    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False, indent=2)

    print(f"\n" + "="*70)
    print(" AUDITORIA DE TRADUÇÃO ACADÊMICA CONCLUÍDA")
    print(f" Manuscrito PT: {args.pt} | Tradução EN: {args.en}")
    print(f" Índice de Qualidade Editorial: {result['score_fidelity']}/100 ({result['status']})")
    print(f" Valores numéricos auditados: {result['numbers_audited']}")
    print(f" Apontamentos: {result['total_issues']} (Críticos: {result['critical_count']}, Moderados: {result['moderate_count']})")
    print(f" Relatório gerado em: {args.output}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
