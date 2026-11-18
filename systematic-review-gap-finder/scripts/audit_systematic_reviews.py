#!/usr/bin/env python3
"""
Systematic Review & Meta-Analysis Gap Auditor
Audits the existing literature landscape to determine if a new Systematic Review or
Meta-Analysis is needed, viable, and publishable.
Compares existing reviews vs primary studies accumulation, detects temporal obsolescence,
and discovers subgroup/moderator voids.
"""

import sys
import os
import re
import json
import argparse
from datetime import datetime
from collections import Counter

try:
    import requests
except ImportError:
    print("Error: 'requests' is required.", file=sys.stderr)
    sys.exit(1)

USER_AGENT = "SystematicReviewGapAuditor/2.0 (academic-evidence-agent; mailto:evidence@example.org)"

STOPWORDS = {
    "the", "of", "and", "in", "to", "a", "is", "for", "with", "that", "on", "as",
    "by", "this", "an", "are", "from", "at", "be", "or", "was", "were", "which",
    "these", "can", "has", "have", "had", "been", "using", "used", "study", "results",
    "analysis", "based", "effect", "effects", "between", "into", "through", "o", "a",
    "os", "as", "um", "uma", "de", "do", "da", "dos", "das", "em", "no", "na", "nos",
    "nas", "por", "para", "com", "não", "que", "se", "como", "sobre", "entre"
}


def normalize_title(t):
    if not t:
        return ""
    return " ".join(re.sub(r"[^\w\s]", "", t.lower()).split())


def extract_topic_keywords(query):
    words = re.findall(r"\b[a-zA-ZÀ-ÿ]{3,}\b", query.lower())
    return [w for w in words if w not in STOPWORDS]


def is_relevant_to_topic(title, abstract, query_keywords):
    if not query_keywords:
        return True
    text = (title + " " + abstract).lower()
    matches = sum(1 for kw in query_keywords if kw in text)
    # Match at least 40% of query keywords or at least 2 key terms if query is long
    min_required = 1 if len(query_keywords) <= 2 else max(2, int(len(query_keywords) * 0.4))
    return matches >= min_required


def search_pubmed_typed(query, is_review=True, limit=25):
    """Searches PubMed distinguishing systematic reviews/meta-analyses vs primary studies."""
    results = []
    base_esearch = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi"
    base_efetch = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi"
    query_keywords = extract_topic_keywords(query)

    if is_review:
        full_term = f"({query}) AND (\"systematic review\"[pt] OR \"meta-analysis\"[pt] OR \"systematic review\"[ti] OR \"meta-analysis\"[ti])"
    else:
        full_term = f"({query}) AND (\"randomized controlled trial\"[pt] OR \"clinical trial\"[pt] OR \"trial\"[tiab] OR \"experiment\"[tiab] OR \"cohort\"[tiab]) NOT (\"systematic review\"[tiab] OR \"meta-analysis\"[tiab] OR \"review\"[pt])"

    params = {
        "db": "pubmed",
        "term": full_term,
        "retmode": "json",
        "retmax": limit,
        "sort": "pub_date"
    }

    try:
        r = requests.get(base_esearch, params=params, headers={"User-Agent": USER_AGENT}, timeout=15)
        if r.status_code != 200:
            return results
        id_list = r.json().get("esearchresult", {}).get("idlist", [])
        if not id_list:
            return results

        fetch_params = {"db": "pubmed", "id": ",".join(id_list), "retmode": "xml"}
        fr = requests.get(base_efetch, params=fetch_params, headers={"User-Agent": USER_AGENT}, timeout=20)
        if fr.status_code != 200:
            return results

        import xml.etree.ElementTree as ET
        root = ET.fromstring(fr.content)
        for article in root.findall(".//PubmedArticle"):
            medline = article.find("MedlineCitation")
            if medline is None:
                continue
            art_el = medline.find("Article")
            if art_el is None:
                continue

            title_el = art_el.find("ArticleTitle")
            title = "".join(title_el.itertext()).strip() if title_el is not None else ""

            year = None
            year_el = art_el.find(".//Journal/JournalIssue/PubDate/Year")
            if year_el is not None and year_el.text:
                year = year_el.text
            else:
                m_date = art_el.find(".//Journal/JournalIssue/PubDate/MedlineDate")
                if m_date is not None and m_date.text:
                    m = re.search(r"\b(19\d\d|20\d\d)\b", m_date.text)
                    if m:
                        year = m.group(1)

            journal_el = art_el.find(".//Journal/Title")
            journal = journal_el.text if journal_el is not None else ""

            doi = None
            for id_node in article.findall(".//PubmedData/ArticleIdList/ArticleId"):
                if id_node.get("IdType") == "doi":
                    doi = id_node.text

            abstract_parts = []
            for ab in art_el.findall(".//AbstractText"):
                lbl = ab.get("Label")
                txt = "".join(ab.itertext()).strip()
                if lbl:
                    abstract_parts.append(f"{lbl}: {txt}")
                elif txt:
                    abstract_parts.append(txt)
            abstract = " \n".join(abstract_parts)

            if not is_relevant_to_topic(title, abstract, query_keywords):
                continue

            results.append({
                "source": "PubMed",
                "title": title,
                "year": int(year) if year and year.isdigit() else None,
                "journal": journal,
                "doi": doi,
                "abstract": abstract,
                "is_review": is_review
            })
    except Exception as e:
        print(f"[PubMed] Error: {e}", file=sys.stderr)

    return results


def search_openalex_typed(query, is_review=True, limit=30):
    """Searches OpenAlex with semantic verification against topic keywords."""
    results = []
    base_url = "https://api.openalex.org/works"
    headers = {"User-Agent": USER_AGENT}
    query_keywords = extract_topic_keywords(query)

    params = {
        "search": query,
        "per_page": min(limit * 2, 60),
        "sort": "publication_year:desc"
    }
    if is_review:
        params["filter"] = "title_and_abstract.search:systematic review|meta-analysis"

    try:
        r = requests.get(base_url, params=params, headers=headers, timeout=15)
        if r.status_code != 200:
            return results

        data = r.json()
        for w in data.get("results", []):
            title = w.get("title") or ""
            title_lower = title.lower()

            has_rev_keyword = any(k in title_lower for k in [
                "systematic review", "meta-analysis", "meta analysis", "meta-analytic",
                "revisão sistemática", "meta-análise", "scoping review"
            ])

            if is_review and not has_rev_keyword:
                continue
            if not is_review and has_rev_keyword:
                continue

            inv = w.get("abstract_inverted_index")
            abstract = ""
            if inv:
                wpos = []
                for wd, positions in inv.items():
                    for p in positions:
                        wpos.append((p, wd))
                wpos.sort(key=lambda x: x[0])
                abstract = " ".join(item[1] for item in wpos)

            if not is_relevant_to_topic(title, abstract, query_keywords):
                continue

            doi_val = w.get("doi")
            doi_clean = doi_val.replace("https://doi.org/", "") if doi_val else None

            location = w.get("primary_location") or {}
            source_info = location.get("source") or {}
            journal = source_info.get("display_name") or ""

            results.append({
                "source": "OpenAlex",
                "title": title,
                "year": w.get("publication_year"),
                "journal": journal,
                "doi": doi_clean,
                "citations": w.get("cited_by_count", 0),
                "abstract": abstract,
                "is_review": is_review
            })
            if len(results) >= limit:
                break
    except Exception as e:
        print(f"[OpenAlex] Error: {e}", file=sys.stderr)

    return results


def search_scielo_typed(query, is_review=True, limit=20):
    """Searches SciELO indexed articles via Crossref with semantic verification."""
    results = []
    base_url = "https://api.crossref.org/works"
    headers = {"User-Agent": USER_AGENT}
    query_keywords = extract_topic_keywords(query)

    params = {
        "query": query,
        "filter": "prefix:10.1590",
        "rows": limit * 2,
        "sort": "published",
        "order": "desc"
    }

    try:
        r = requests.get(base_url, params=params, headers=headers, timeout=15)
        if r.status_code != 200:
            return results

        items = r.json().get("message", {}).get("items", [])
        for it in items:
            title_list = it.get("title", [])
            title = title_list[0] if title_list else ""
            if not title:
                continue

            title_lower = title.lower()
            has_rev_keyword = any(k in title_lower for k in [
                "revisão sistemática", "meta-análise", "metaanálise", "systematic review", "meta-analysis"
            ])

            if is_review and not has_rev_keyword:
                continue
            if not is_review and has_rev_keyword:
                continue

            abstract_raw = it.get("abstract", "")
            abstract = re.sub(r"<[^>]+>", " ", abstract_raw).strip() if abstract_raw else ""

            if not is_relevant_to_topic(title, abstract, query_keywords):
                continue

            date_parts = it.get("published", {}).get("date-parts", [[None]])[0]
            year = date_parts[0] if date_parts else None
            doi = it.get("DOI")
            container = it.get("container-title", [])
            journal = container[0] if container else ""

            results.append({
                "source": "SciELO",
                "title": title,
                "year": year,
                "journal": journal,
                "doi": doi,
                "abstract": abstract,
                "is_review": is_review
            })
            if len(results) >= limit:
                break
    except Exception as e:
        print(f"[SciELO] Error: {e}", file=sys.stderr)

    return results


def deduplicate(items):
    seen = set()
    res = []
    for it in items:
        doi = it.get("doi")
        norm = normalize_title(it.get("title", ""))
        key = doi.lower() if doi else norm
        if key and key not in seen:
            seen.add(key)
            res.append(it)
    return res


def extract_review_limitations(reviews):
    """Extracts explicit limitations and calls for future meta-analyses from existing review abstracts."""
    limits = []
    patterns = [
        r"(?:high|substantial|considerable)\s+heterogeneity[^.?!]+[.?!]",
        r"(?:subgroup|moderator|meta-regression)\s+(?:analysis\s+was\s+not\s+possible|was\s+limited|could\s+not\s+be\s+performed)[^.?!]+[.?!]",
        r"(?:lack|scarcity|dearth|limited\s+number)\s+of\s+(?:high-quality|randomized|eligible|long-term)\s+studies[^.?!]+[.?!]",
        r"(?:future|further)\s+(?:systematic\s+reviews|meta-analyses|studies)\s+(?:should|are\s+warranted|needed)[^.?!]+[.?!]",
        r"(?:risk\s+of\s+bias|methodological\s+limitations)[^.?!]+[.?!]",
        r"(?:poucos\s+estudos|elevada\s+heterogeneidade|análise\s+de\s+subgrupos\s+não\s+foi\s+possível)[^.?!]+[.?!]"
    ]
    compiled = [re.compile(p, re.IGNORECASE) for p in patterns]

    for rev in reviews:
        abstract = rev.get("abstract", "")
        if not abstract:
            continue
        for cp in compiled:
            matches = cp.findall(abstract)
            for m in matches:
                clean = re.sub(r"\s+", " ", m).strip()
                if 25 < len(clean) < 350:
                    limits.append({
                        "limitation_statement": clean,
                        "review_title": rev.get("title"),
                        "year": rev.get("year"),
                        "journal": rev.get("journal"),
                        "doi": rev.get("doi")
                    })
    return limits


def compute_gap_verdict(query, reviews, primary_studies, current_year):
    total_reviews = len(reviews)
    total_primary = len(primary_studies)

    rev_years = [r.get("year") for r in reviews if r.get("year")]
    latest_rev_year = max(rev_years) if rev_years else None

    if latest_rev_year:
        primary_after_latest = len([p for p in primary_studies if p.get("year") and p.get("year") > latest_rev_year])
        years_since_latest = current_year - latest_rev_year
    else:
        primary_after_latest = total_primary
        years_since_latest = 99

    score = 0
    verdict = ""
    rationale = []

    if total_reviews == 0:
        if total_primary >= 5:
            score = 95
            verdict = "OPORTUNIDADE INÉDITA (Primeira Meta-Análise do Tema)"
            rationale.append(f"Inexistência de revisões sistemáticas ou meta-análises publicadas para '{query}'.")
            rationale.append(f"Identificados {total_primary} estudos primários/experimentais com potencial imediato para síntese quantitativa.")
        else:
            score = 45
            verdict = "ESTUDOS PRIMÁRIOS INSUFICIENTES"
            rationale.append("Não há revisões sistemáticas prévias, mas o volume de ensaios/estudos primários ainda é incipiente (< 5 estudos). Recomenda-se Scoping Review.")
    elif years_since_latest >= 3 and primary_after_latest >= 5:
        score = 90
        verdict = "ATUALIZAÇÃO URGENTE NECESSÁRIA (Revisão Desatualizada)"
        rationale.append(f"A última revisão foi publicada em {latest_rev_year} (há {years_since_latest} anos).")
        rationale.append(f"Foram identificados pelo menos {primary_after_latest} novos estudos primários publicados após a última síntese.")
    elif total_reviews <= 2 and total_primary >= 12:
        score = 80
        verdict = "GAP DE SUBGRUPO / MODERADORES E META-REGRESSÃO"
        rationale.append(f"Existem revisões prévias pontuais ({total_reviews}), mas o grande volume de estudos primários ({total_primary}) viabiliza análise aprofundada de moderadores, subgrupos e meta-regressão.")
    elif latest_rev_year and (current_year - latest_rev_year) <= 1 and total_reviews >= 3:
        score = 25
        verdict = "TEMA SATURADO (Revisões Recentes Disponíveis)"
        rationale.append(f"Existem {total_reviews} revisões recentes, a última publicada em {latest_rev_year}. Uma nova revisão só se justifica se houver recorte inovador.")
    else:
        score = 65
        verdict = "OPORTUNIDADE CONDICIONAL DE REVISÃO SISTEMÁTICA"
        rationale.append("Existe espaço para síntese crítica, preferencialmente focada em recortes metodológicos ou populacionais específicos.")

    return {
        "score_iro": score,
        "verdict": verdict,
        "rationale": rationale,
        "total_reviews": total_reviews,
        "latest_review_year": latest_rev_year,
        "years_since_latest": years_since_latest if latest_rev_year else None,
        "total_primary_studies": total_primary,
        "primary_studies_after_latest_review": primary_after_latest
    }


def audit_systematic_review_landscape(query, limit_reviews=25, limit_primary=50):
    current_year = datetime.now().year
    print(f"\n[+] Auditando o ecossistema de Revisões Sistemáticas e Meta-Análises para: '{query}'")

    print("[*] Mapeando Revisões Sistemáticas e Meta-Análises existentes (PubMed, OpenAlex, SciELO)...")
    revs_pm = search_pubmed_typed(query, is_review=True, limit=limit_reviews)
    revs_oa = search_openalex_typed(query, is_review=True, limit=limit_reviews)
    revs_sc = search_scielo_typed(query, is_review=True, limit=limit_reviews)
    all_reviews = deduplicate(revs_pm + revs_oa + revs_sc)
    print(f"    -> Encontradas {len(all_reviews)} revisões sistemáticas / meta-análises validadas.")

    print("[*] Mapeando Estudos Primários, Ensaios Clínicos e Experimentais...")
    prim_pm = search_pubmed_typed(query, is_review=False, limit=limit_primary)
    prim_oa = search_openalex_typed(query, is_review=False, limit=limit_primary)
    prim_sc = search_scielo_typed(query, is_review=False, limit=limit_primary)
    all_primary = deduplicate(prim_pm + prim_oa + prim_sc)
    print(f"    -> Encontrados {len(all_primary)} estudos primários/experimentais únicos.")

    limitations = extract_review_limitations(all_reviews)
    verdict_info = compute_gap_verdict(query, all_reviews, all_primary, current_year)

    return {
        "query": query,
        "audit_timestamp": datetime.now().isoformat(),
        "verdict_metrics": verdict_info,
        "existing_reviews": all_reviews,
        "primary_studies_sample": all_primary,
        "extracted_review_limitations": limitations
    }


def main():
    parser = argparse.ArgumentParser(description="Audita a necessidade de uma nova Revisão Sistemática ou Meta-Análise.")
    parser.add_argument("-q", "--query", required=True, help="Tema de pesquisa para a revisão sistemática")
    parser.add_argument("-o", "--output", default="audit_review.json", help="Arquivo JSON de saída")
    parser.add_argument("--limit-reviews", type=int, default=25, help="Limite de busca para revisões")
    parser.add_argument("--limit-primary", type=int, default=50, help="Limite de busca para estudos primários")

    args = parser.parse_args()

    results = audit_systematic_review_landscape(
        query=args.query,
        limit_reviews=args.limit_reviews,
        limit_primary=args.limit_primary
    )

    with open(args.output, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)

    vm = results["verdict_metrics"]
    print(f"\n" + "="*70)
    print(f" DIAGNÓSTICO DE OPORTUNIDADE DE META-ANÁLISE (IRO: {vm['score_iro']}/100)")
    print(f" Veredito: {vm['verdict']}")
    print("="*70)
    for r in vm["rationale"]:
        print(f" • {r}")
    print(f"\n[✓] Relatório detalhado salvo em: {args.output}\n")


if __name__ == "__main__":
    main()
