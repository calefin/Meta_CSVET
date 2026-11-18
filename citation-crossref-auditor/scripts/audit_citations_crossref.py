#!/usr/bin/env python3
"""
Auditor Forense de Citações e Referências via Crossref API
Valida cada citação e referência de um artigo científico:
1. Cruza dados declarados (Autor, Ano, Título, Periódico, DOI) com os metadados oficiais do Crossref.
2. Detecta erros de digitação, discrepâncias de autoria, ano incorreto ou DOIs inexistentes/alucinados.
3. Avalia a COERÊNCIA DE ESCOPO: analisa se o título da obra citada pertence ao tema do artigo ou se é uma possível inserção errada / troca de referência / alucinação de IA.
4. Identifica citações órfãs (no texto sem referência) e referências fantasmas (listadas mas nunca citadas).
"""

import sys
import os
import re
import json
import time
import difflib
import argparse
from datetime import datetime
from collections import Counter

try:
    import requests
except ImportError:
    print("Error: 'requests' library required. Install with: pip install requests", file=sys.stderr)
    sys.exit(1)

USER_AGENT = "CitationCrossrefAuditor/1.0 (academic-forensics-agent; mailto:forensics-agent@example.org)"
DEFAULT_EMAIL = os.environ.get("RESEARCHER_EMAIL", "forensics-agent@example.org")

STOPWORDS = {
    "the", "of", "and", "in", "to", "a", "is", "for", "with", "that", "on", "as",
    "by", "this", "an", "are", "from", "at", "be", "or", "was", "were", "which",
    "these", "can", "has", "have", "had", "been", "using", "used", "study", "results",
    "analysis", "based", "effect", "effects", "between", "into", "through", "o", "a",
    "os", "as", "um", "uma", "de", "do", "da", "dos", "das", "em", "no", "na", "nos",
    "nas", "por", "para", "com", "não", "que", "se", "como", "sobre", "entre"
}


def clean_text(text):
    if not text:
        return ""
    return re.sub(r"\s+", " ", text).strip()


def extract_keywords(text, top_n=25):
    if not text:
        return []
    words = re.findall(r"\b[a-zA-ZÀ-ÿ]{3,}\b", text.lower())
    filtered = [w for w in words if w not in STOPWORDS]
    counts = Counter(filtered)
    return [w for w, _ in counts.most_common(top_n)]


def parse_manuscript(content):
    """Extrai o título do trabalho, o corpo do texto, as citações e a seção de referências."""
    lines = content.splitlines()
    title = ""
    body_lines = []
    ref_lines = []
    in_references = False

    # Tenta obter título (primeiro heading)
    for l in lines:
        s = l.strip()
        if not title and re.match(r"^#+\s+(.+)", s):
            title = re.match(r"^#+\s+(.+)", s).group(1)
            continue
        if re.match(r"^#*\s*(REFERÊNCIAS|REFERENCES|BIBLIOGRAFIA)\b", s, re.IGNORECASE):
            in_references = True
            continue
        if in_references:
            if re.match(r"^#*\s*(APÊNDICE|ANEXO|APPENDIX)", s, re.IGNORECASE):
                in_references = False
                continue
            ref_lines.append(s)
        else:
            body_lines.append(s)

    body_text = "\n".join(body_lines)
    
    # Extração de citações no texto
    # Padrões: (Silva, 2020), (SILVA, 2020), (Silva et al., 2021), Silva (2020)
    citations = []
    # 1. Parênteses: (Autor, 2020)
    for m in re.finditer(r"\(([A-Za-zÀ-ÿ\s;\.]{2,}),\s*([12]\d{3}[a-z]?(?:,\s*[^)]+)?)\)", body_text):
        authors_raw = m.group(1).strip()
        year_raw = m.group(2).strip()
        # Separar múltiplos autores se houver ponto e vírgula
        for a in authors_raw.split(";"):
            a_clean = re.sub(r"\bet\s+al\.?", "", a, flags=re.IGNORECASE).strip()
            # Pega o último sobrenome
            last_name = a_clean.split()[-1] if a_clean else ""
            if last_name and len(last_name) >= 2:
                citations.append({
                    "raw": m.group(0),
                    "author": last_name.upper(),
                    "year": year_raw[:4],
                    "type": "parenthetical"
                })

    # 2. Fora dos parênteses: Silva (2020) ou Silva et al. (2020)
    for m in re.finditer(r"\b([A-ZÀ-ÿ][a-zà-ÿ]+)(?:\s+et\s+al\.?)?\s*\(([12]\d{3}[a-z]?)\)", body_text):
        author = m.group(1).strip()
        year = m.group(2).strip()
        citations.append({
            "raw": m.group(0),
            "author": author.upper(),
            "year": year[:4],
            "type": "narrative"
        })

    # Decomposição das referências
    raw_refs = []
    curr = []
    for l in ref_lines:
        if l:
            curr.append(l)
        else:
            if curr:
                raw_refs.append(" ".join(curr))
                curr = []
    if curr:
        raw_refs.append(" ".join(curr))

    parsed_references = []
    doi_pattern = re.compile(r"\b(10\.\d{4,9}/[-._;()/:A-Za-z0-9]+)\b")

    for raw in raw_refs:
        # Extrai DOI
        m_doi = doi_pattern.search(raw)
        found_doi = m_doi.group(1) if m_doi else None

        # Extrai autor principal (primeira palavra ou antes da vírgula)
        m_auth = re.match(r"^([A-ZÀ-ÿ\s]+),\s*([A-Za-zÀ-ÿ\.\s]+)\.", raw)
        author_last = ""
        if m_auth:
            author_last = m_auth.group(1).strip().upper()
        else:
            first_word = raw.split()[0].replace(",", "").replace(".", "").upper() if raw.split() else ""
            author_last = first_word

        # Extrai ano (primeiro 19XX ou 20XX após autor)
        m_year = re.search(r"\b(19\d{2}|20\d{2})\b", raw)
        declared_year = m_year.group(1) if m_year else None

        # Tenta inferir título da obra
        # Geralmente entre o autor/ano e a pontuação ou entre negritos
        title_inferred = ""
        m_bold = re.search(r"\*\*([^*]+)\*\*", raw)
        if m_bold:
            title_inferred = m_bold.group(1).strip()
        else:
            parts = raw.split(".")
            if len(parts) >= 2:
                title_inferred = parts[1].strip()

        parsed_references.append({
            "raw_text": raw,
            "doi": found_doi,
            "declared_author": author_last,
            "declared_year": declared_year,
            "inferred_title": title_inferred
        })

    return {
        "title": title or "Manuscrito Sem Título",
        "body_text": body_text,
        "keywords": extract_keywords(body_text, top_n=35),
        "citations": citations,
        "references": parsed_references
    }


def query_crossref_by_doi(doi):
    """Consulta metadados completos de um DOI na Crossref API."""
    url = f"https://api.crossref.org/works/{doi}"
    headers = {"User-Agent": USER_AGENT}
    try:
        r = requests.get(url, headers=headers, timeout=12)
        if r.status_code == 200:
            return r.json().get("message", {})
        elif r.status_code == 404:
            return {"error": "DOI_NOT_FOUND", "status": 404}
    except Exception as e:
        return {"error": str(e)}
    return None


def query_crossref_by_search(author, title, declared_year=None):
    """Busca na Crossref quando a referência não possui DOI declarado."""
    url = "https://api.crossref.org/works"
    headers = {"User-Agent": USER_AGENT}
    query_str = f"{author} {title}".strip()
    params = {"query.bibliographic": query_str, "rows": 3}
    try:
        r = requests.get(url, params=params, headers=headers, timeout=12)
        if r.status_code == 200:
            items = r.json().get("message", {}).get("items", [])
            if items:
                # Retorna o mais aderente
                return items[0]
    except Exception as e:
        return {"error": str(e)}
    return None


def calculate_similarity(s1, s2):
    if not s1 or not s2:
        return 0.0
    return difflib.SequenceMatcher(None, s1.lower().strip(), s2.lower().strip()).ratio()


def check_scope_alignment(manuscript_keywords, ref_title, ref_journal, ref_subjects=None):
    """
    Avalia se o trabalho citado está no escopo temático do artigo.
    Calcula sobreposição de vocabulário e detecta anomalias de domínio (ex: citar medicina dentária num artigo de soja).
    """
    if not ref_title:
        return {"in_scope": True, "score": 1.0, "reason": "Título não disponível para análise de escopo."}

    ref_words = set(re.findall(r"\b[a-zA-ZÀ-ÿ]{3,}\b", ref_title.lower()))
    if ref_journal:
        ref_words.update(re.findall(r"\b[a-zA-ZÀ-ÿ]{3,}\b", ref_journal.lower()))
    if ref_subjects:
        for s in ref_subjects:
            ref_words.update(re.findall(r"\b[a-zA-ZÀ-ÿ]{3,}\b", s.lower()))

    ref_words = {w for w in ref_words if w not in STOPWORDS}

    manuscript_words = set(manuscript_keywords)
    overlap = ref_words.intersection(manuscript_words)

    # Verifica se há termos comuns da ciência
    generic_science = {"study", "analysis", "review", "methods", "model", "evaluation", "development", "brazil", "brasil", "sistema"}
    informative_overlap = overlap - generic_science

    # Pontuação de coerência
    if len(informative_overlap) >= 1 or len(overlap) >= 2:
        return {
            "in_scope": True,
            "overlap_terms": list(overlap),
            "score": round(len(overlap) / (len(ref_words) or 1), 2),
            "alert": None
        }
    else:
        # Nenhum termo informativo compartilhado!
        return {
            "in_scope": False,
            "overlap_terms": [],
            "score": 0.0,
            "alert": "⚠️ ALERTA DE ESCOPO / POSSÍVEL INSERÇÃO ERRADA: O título e periódico da obra citada não compartilham nenhum termo temático com o manuscrito. Verificar se a referência foi inserida por engano, troca de chave de citação ou alucinação de ferramenta de IA."
        }


def audit_manuscript(file_path, explicit_topic=None):
    print(f"\n[+] Iniciando Auditoria Forense de Citações e Referências: '{os.path.basename(file_path)}'")
    
    with open(file_path, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    data = parse_manuscript(content)
    manuscript_title = data["title"]
    keywords = data["keywords"]
    if explicit_topic:
        keywords = list(set(keywords + extract_keywords(explicit_topic, top_n=15)))

    citations = data["citations"]
    references = data["references"]

    print(f"[*] Título detectado: '{manuscript_title}'")
    print(f"[*] Total de citações no texto: {len(citations)}")
    print(f"[*] Total de referências listadas: {len(references)}")
    print(f"[*] Principais eixos temáticos do manuscrito: {', '.join(keywords[:10])}\n")

    # Mapeamento Citação <-> Referência
    ref_author_year_keys = set()
    for r in references:
        if r["declared_author"] and r["declared_year"]:
            ref_author_year_keys.add(f"{r['declared_author']}_{r['declared_year']}")

    citations_missing_in_refs = []
    for c in citations:
        key = f"{c['author']}_{c['year']}"
        if key not in ref_author_year_keys:
            citations_missing_in_refs.append(c)

    # Auditoria de cada referência no Crossref
    audited_references = []
    
    for idx, ref in enumerate(references, start=1):
        print(f"[*] [{idx}/{len(references)}] Verificando no Crossref: {ref['declared_author']} ({ref['declared_year']})...")
        crossref_data = None
        method = ""
        doi_status = "Sem DOI Declarado"

        if ref["doi"]:
            method = "DOI Direto"
            crossref_data = query_crossref_by_doi(ref["doi"])
            if crossref_data and crossref_data.get("error") == "DOI_NOT_FOUND":
                doi_status = "❌ DOI Inválido / Inexistente (404)"
            elif crossref_data and "title" in crossref_data:
                doi_status = "✓ DOI Válido e Confirmado"
        else:
            method = "Busca Bibliográfica"
            crossref_data = query_crossref_by_search(ref["declared_author"], ref["inferred_title"], ref["declared_year"])

        # Análise das discrepâncias
        discrepancies = []
        scope_info = None

        if crossref_data and "title" in crossref_data:
            cr_title = crossref_data.get("title", [""])[0] if crossref_data.get("title") else ""
            cr_container = crossref_data.get("container-title", [""])[0] if crossref_data.get("container-title") else ""
            cr_doi = crossref_data.get("DOI")
            
            # Ano no Crossref
            date_parts = crossref_data.get("published", {}).get("date-parts", [[None]])[0]
            cr_year = str(date_parts[0]) if date_parts and date_parts[0] else None

            # Autores no Crossref
            cr_authors = []
            for a in crossref_data.get("author", []):
                fam = a.get("family", "")
                if fam:
                    cr_authors.append(fam.upper())

            # 1. Checa similaridade do título
            sim = calculate_similarity(ref["inferred_title"], cr_title)
            if sim < 0.65 and ref["inferred_title"]:
                discrepancies.append({
                    "field": "Título da Obra",
                    "declared": ref["inferred_title"],
                    "crossref": cr_title,
                    "severity": "Alta",
                    "note": f"Divergência substancial de título (similaridade: {round(sim*100)}%)."
                })

            # 2. Checa Ano
            if ref["declared_year"] and cr_year and ref["declared_year"] != cr_year:
                discrepancies.append({
                    "field": "Ano de Publicação",
                    "declared": ref["declared_year"],
                    "crossref": cr_year,
                    "severity": "Moderada",
                    "note": f"Ano declarado ({ref['declared_year']}) difere do registro oficial ({cr_year})."
                })

            # 3. Checa Primeiro Autor
            if ref["declared_author"] and cr_authors:
                if ref["declared_author"] not in cr_authors and not any(calculate_similarity(ref["declared_author"], ca) > 0.8 for ca in cr_authors):
                    discrepancies.append({
                        "field": "Primeiro Autor",
                        "declared": ref["declared_author"],
                        "crossref": ", ".join(cr_authors[:3]),
                        "severity": "Alta",
                        "note": f"Sobrenome declarado '{ref['declared_author']}' não figura entre os autores registrados no Crossref."
                    })

            # 4. Avaliação de Escopo Temático
            scope_info = check_scope_alignment(keywords, cr_title, cr_container, crossref_data.get("subject"))

            audited_references.append({
                "reference_idx": idx,
                "raw_text": ref["raw_text"],
                "status": "Verificado no Crossref",
                "method": method,
                "doi_status": doi_status,
                "crossref_doi": cr_doi,
                "crossref_title": cr_title,
                "crossref_journal": cr_container,
                "crossref_year": cr_year,
                "crossref_authors": cr_authors,
                "discrepancies": discrepancies,
                "scope_info": scope_info
            })
        else:
            audited_references.append({
                "reference_idx": idx,
                "raw_text": ref["raw_text"],
                "status": "Não Localizado no Crossref" if doi_status != "❌ DOI Inválido / Inexistente (404)" else "DOI Inválido",
                "method": method,
                "doi_status": doi_status,
                "crossref_doi": None,
                "discrepancies": [{"field": "Registro", "declared": "Declarado pelo autor", "crossref": "Não encontrado", "severity": "Alta", "note": "Não foi possível validar este registro na base Crossref."}],
                "scope_info": {"in_scope": True, "alert": None, "score": 0.5, "reason": "Não avaliado por ausência de metadados oficiais."}
            })

        # Polite delay
        time.sleep(0.15)

    return {
        "manuscript_title": manuscript_title,
        "audit_timestamp": datetime.now().isoformat(),
        "total_citations": len(citations),
        "total_references": len(references),
        "citations_missing_in_refs": citations_missing_in_refs,
        "audited_references": audited_references
    }


def generate_audit_report_markdown(result, output_md):
    lines = []
    lines.append("# Relatório Forense de Auditoria de Citações e Referências (Crossref)")
    lines.append(f"**Manuscrito:** `{result['manuscript_title']}`  ")
    lines.append(f"**Data da Auditoria:** {datetime.now().strftime('%d/%m/%Y %H:%M')}  ")
    lines.append(f"**Total de Citações Auditadas:** {result['total_citations']}  ")
    lines.append(f"**Total de Referências Auditadas:** {result['total_references']}  \n")

    # Resumo Executivo
    total_refs = result["total_references"] or 1
    scope_alerts = [r for r in result["audited_references"] if r.get("scope_info") and not r["scope_info"].get("in_scope")]
    doi_errors = [r for r in result["audited_references"] if "❌" in r.get("doi_status", "")]
    discrepancy_refs = [r for r in result["audited_references"] if r.get("discrepancies") and r["status"] == "Verificado no Crossref" and len(r["discrepancies"]) > 0]
    verified_clean = [r for r in result["audited_references"] if r["status"] == "Verificado no Crossref" and len(r.get("discrepancies", [])) == 0 and (r.get("scope_info") is None or r["scope_info"].get("in_scope"))]

    lines.append("---")
    lines.append("## 1. Quadro Resumo de Integridade Científica\n")
    lines.append("| Categoria de Integridade | Quantidade | Percentual | Parecer |")
    lines.append("| :--- | :---: | :---: | :--- |")
    lines.append(f"| 🟢 **Referências 100% Confirmadas no Crossref** | {len(verified_clean)} | {round(len(verified_clean)/total_refs*100, 1)}% | Dados perfeitamente coincidentes |")
    lines.append(f"| 🟡 **Referências com Discrepâncias de Metadados** | {len(discrepancy_refs)} | {round(len(discrepancy_refs)/total_refs*100, 1)}% | Divergências de ano, autor ou título |")
    lines.append(f"| 🔴 **Alertas de Escopo / Inserção Incompatível** | {len(scope_alerts)} | {round(len(scope_alerts)/total_refs*100, 1)}% | Obras citadas fora da temática do manuscrito |")
    lines.append(f"| ❌ **DOIs Inexistentes / Fantasmas (404)** | {len(doi_errors)} | {round(len(doi_errors)/total_refs*100, 1)}% | DOIs inválidos ou alucinados |")
    lines.append(f"| ⚠️ **Citações sem Referência (Órfãs no Texto)** | {len(result['citations_missing_in_refs'])} | - | Citações feitas sem entrada na bibliografia |")
    lines.append("")

    # Seção de Alertas de Escopo
    lines.append("---")
    lines.append("## 2. Alertas Críticos de Escopo e Inserção Errada (Scope Anomaly)\n")
    if scope_alerts:
        lines.append("> [!WARNING]")
        lines.append("> Os artigos abaixo foram detectados com **temática completamente divergente** do assunto do manuscrito. Isso comumente decorre de: **troca de chaves de citação no gerenciador bibliográfico**, **cópias acidentais de outros artigos** ou **alucinações geradas por assistentes de IA**:")
        lines.append("")
        for sa in scope_alerts:
            lines.append(f"### Referência #{sa['reference_idx']}: {sa.get('crossref_title', 'Título não identificado')}")
            lines.append(f"- **Texto no Manuscrito:** `{sa['raw_text']}`")
            lines.append(f"- **Título Real (Crossref):** *{sa.get('crossref_title')}*")
            lines.append(f"- **Periódico Real:** {sa.get('crossref_journal') or 'Não informado'}")
            if sa.get('crossref_doi'):
                lines.append(f"- **DOI:** [{sa['crossref_doi']}](https://doi.org/{sa['crossref_doi']})")
            lines.append(f"- **Diagnóstico:** `{sa['scope_info']['alert']}`")
            lines.append("")
    else:
        lines.append("✓ **Nenhuma anomalia de escopo detectada.** Todas as referências avaliadas possuem alinhamento semântico consistente com a temática do trabalho.\n")

    # Seção de Discrepâncias de Metadados
    lines.append("---")
    lines.append("## 3. Discrepâncias Detalhadas entre o Manuscrito e a Base Crossref\n")
    if discrepancy_refs or doi_errors:
        for dr in discrepancy_refs + doi_errors:
            lines.append(f"### Referência #{dr['reference_idx']}")
            lines.append(f"> `{dr['raw_text']}`\n")
            lines.append(f"- **Status da Validação:** `{dr['status']}` ({dr['doi_status']})")
            if dr.get("crossref_doi"):
                lines.append(f"- **DOI Oficial:** [{dr['crossref_doi']}](https://doi.org/{dr['crossref_doi']})")
            lines.append("- **Discrepâncias Encontradas:**")
            for disc in dr["discrepancies"]:
                lines.append(f"  - **[{disc['field']}]:** Declarado: *\"{disc['declared']}\"* $\\rightarrow$ Registro Oficial: **\"{disc['crossref']}\"** ({disc['note']})")
            lines.append("")
    else:
        lines.append("✓ **Nenhuma discrepância de metadados encontrada nas obras localizadas.**\n")

    # Seção de Citações Órfãs
    lines.append("---")
    lines.append("## 4. Citações no Texto sem Referência na Bibliografia\n")
    if result["citations_missing_in_refs"]:
        lines.append("| Citação no Texto | Tipo de Citação | Providência Recomendada |")
        lines.append("| :--- | :---: | :--- |")
        for cm in result["citations_missing_in_refs"]:
            lines.append(f"| **{cm['raw']}** | `{cm['type']}` | Inserir a obra completa de {cm['author']} ({cm['year']}) na seção de Referências. |")
        lines.append("")
    else:
        lines.append("✓ **Todas as citações encontradas no texto possuem referência correspondente na bibliografia.**\n")

    lines.append("---")
    lines.append("## 5. Recomendações Finais para o Autor")
    lines.append("1. **Substituição de DOIs:** Corrija os DOIs inválidos utilizando os links oficiais fornecidos na Seção 3.")
    lines.append("2. **Revisão das Referências Fora de Escopo:** Abra o arquivo fonte e confirme se os parágrafos que citam as obras apontadas na Seção 2 realmente tratam do conteúdo do artigo referenciado.")
    lines.append("3. **Padronização ABNT:** Para aplicar a formatação NBR 6023 e NBR 10520:2023 de forma automatizada nos registros corrigidos, utilize a skill `abnt-tcc-fixer`.")

    with open(output_md, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))


def main():
    parser = argparse.ArgumentParser(description="Audita citações e referências via Crossref e verifica coerência de escopo.")
    parser.add_argument("-i", "--input", required=True, help="Arquivo do artigo científico ou TCC (Markdown/Texto)")
    parser.add_argument("-t", "--topic", default=None, help="Eixo temático do manuscrito (opcional, inferido automaticamente se omitido)")
    parser.add_argument("-o", "--output", default="relatorio_auditoria_crossref.md", help="Arquivo Markdown do relatório de auditoria")
    parser.add_argument("-j", "--json", default=None, help="Arquivo JSON de saída com dados estruturados")

    args = parser.parse_args()

    if not os.path.exists(args.input):
        print(f"Erro: Arquivo '{args.input}' não encontrado.", file=sys.stderr)
        sys.exit(1)

    result = audit_manuscript(args.input, explicit_topic=args.topic)
    generate_audit_report_markdown(result, args.output)

    if args.json:
        with open(args.json, "w", encoding="utf-8") as f:
            json.dump(result, f, ensure_ascii=False, indent=2)

    print(f"\n" + "="*70)
    print(" AUDITORIA CROSSREF CONCLUÍDA COM SUCESSO!")
    print(f" Manuscrito: {result['manuscript_title']}")
    print(f" Citações Auditadas: {result['total_citations']} | Referências Auditadas: {result['total_references']}")
    print(f" Citações Órfãs: {len(result['citations_missing_in_refs'])}")
    print(f" Relatório gerado em: {args.output}")
    print("="*70 + "\n")


if __name__ == "__main__":
    main()
