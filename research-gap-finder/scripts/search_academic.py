#!/usr/bin/env python3
"""
Academic Literature Search & Metadata Extractor
Queries indexed databases: PubMed, OpenAlex, SciELO (Crossref prefix), CABI, arXiv, and Scopus.
Normalizes metadata and deduplicates by DOI and title.
"""

import sys
import os
import re
import json
import time
import argparse
import urllib.parse
import xml.etree.ElementTree as ET
from datetime import datetime

try:
    import requests
except ImportError:
    print("Error: 'requests' library is required. Install with: pip install requests", file=sys.stderr)
    sys.exit(1)

USER_AGENT = "ResearchGapFinder/2.0 (academic-researcher-agent; mailto:research-agent@example.org)"
DEFAULT_EMAIL = os.environ.get("RESEARCHER_EMAIL", "research-agent@example.org")


def normalize_title(title):
    if not title:
        return ""
    clean = re.sub(r"[^\w\s]", "", title.lower())
    return " ".join(clean.split())


def search_pubmed(query, limit=25, start_year=None, end_year=None, api_key=None):
    """Fetch scientific articles from PubMed via NCBI Entrez E-Utilities."""
    results = []
    base_esearch = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi"
    base_efetch = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi"

    params = {
        "db": "pubmed",
        "term": query,
        "retmode": "json",
        "retmax": limit,
        "sort": "pub_date",
        "email": DEFAULT_EMAIL
    }
    if api_key:
        params["api_key"] = api_key
    if start_year or end_year:
        sy = start_year or 1900
        ey = end_year or datetime.now().year + 1
        params["term"] = f"({query}) AND ({sy}:{ey}[dp])"

    try:
        r = requests.get(base_esearch, params=params, headers={"User-Agent": USER_AGENT}, timeout=15)
        if r.status_code != 200:
            print(f"[PubMed] Search returned status code {r.status_code}", file=sys.stderr)
            return results
        
        data = r.json()
        id_list = data.get("esearchresult", {}).get("idlist", [])
        if not id_list:
            return results

        fetch_params = {
            "db": "pubmed",
            "id": ",".join(id_list),
            "retmode": "xml",
            "email": DEFAULT_EMAIL
        }
        if api_key:
            fetch_params["api_key"] = api_key

        fr = requests.get(base_efetch, params=fetch_params, headers={"User-Agent": USER_AGENT}, timeout=20)
        if fr.status_code != 200:
            return results

        root = ET.fromstring(fr.content)
        for article in root.findall(".//PubmedArticle"):
            medline = article.find("MedlineCitation")
            if medline is None:
                continue

            pmid_el = medline.find("PMID")
            pmid = pmid_el.text if pmid_el is not None else ""

            article_data = medline.find("Article")
            if article_data is None:
                continue

            title_el = article_data.find("ArticleTitle")
            title = "".join(title_el.itertext()).strip() if title_el is not None else ""

            # Extract abstract
            abstract_parts = []
            for ab in article_data.findall(".//AbstractText"):
                label = ab.get("Label")
                text = "".join(ab.itertext()).strip()
                if label:
                    abstract_parts.append(f"{label}: {text}")
                elif text:
                    abstract_parts.append(text)
            abstract = " \n".join(abstract_parts)

            # Year
            year = None
            year_el = article_data.find(".//Journal/JournalIssue/PubDate/Year")
            if year_el is not None and year_el.text:
                year = year_el.text
            else:
                medline_date = article_data.find(".//Journal/JournalIssue/PubDate/MedlineDate")
                if medline_date is not None and medline_date.text:
                    m = re.search(r"\b(19\d\d|20\d\d)\b", medline_date.text)
                    if m:
                        year = m.group(1)

            # Journal
            journal_el = article_data.find(".//Journal/Title")
            journal = journal_el.text if journal_el is not None else ""

            # Authors
            authors = []
            for auth in article_data.findall(".//AuthorList/Author"):
                last = auth.find("LastName")
                fore = auth.find("ForeName")
                if last is not None and last.text:
                    name = f"{fore.text} {last.text}" if fore is not None and fore.text else last.text
                    authors.append(name)

            # DOI
            doi = None
            for id_node in article.findall(".//PubmedData/ArticleIdList/ArticleId"):
                if id_node.get("IdType") == "doi":
                    doi = id_node.text

            # Keywords & MeSH
            keywords = []
            for kw in medline.findall(".//KeywordList/Keyword"):
                if kw.text:
                    keywords.append(kw.text.strip())
            mesh_terms = []
            for msh in medline.findall(".//MeshHeadingList/MeshHeading/DescriptorName"):
                if msh.text:
                    mesh_terms.append(msh.text.strip())

            results.append({
                "id": f"PMID:{pmid}",
                "title": title,
                "doi": doi,
                "year": int(year) if year and year.isdigit() else None,
                "journal": journal,
                "authors": authors[:10],
                "abstract": abstract,
                "keywords": keywords,
                "mesh_terms": mesh_terms,
                "source_database": "PubMed",
                "citations": None,
                "url": f"https://pubmed.ncbi.nlm.nih.gov/{pmid}/" if pmid else (f"https://doi.org/{doi}" if doi else None),
                "open_access": None
            })
    except Exception as e:
        print(f"[PubMed] Error during retrieval: {e}", file=sys.stderr)

    return results


def search_openalex(query, limit=30, start_year=None, end_year=None):
    """Fetch from OpenAlex (250M+ multidisciplinary works, citation graphs, concept tags)."""
    results = []
    base_url = "https://api.openalex.org/works"
    headers = {"User-Agent": USER_AGENT}

    params = {
        "search": query,
        "per_page": min(limit, 100),
        "sort": "publication_year:desc"
    }

    filters = []
    if start_year:
        filters.append(f"from_publication_date:{start_year}-01-01")
    if end_year:
        filters.append(f"to_publication_date:{end_year}-12-31")
    if filters:
        params["filter"] = ",".join(filters)

    try:
        r = requests.get(base_url, params=params, headers=headers, timeout=15)
        if r.status_code != 200:
            print(f"[OpenAlex] Status {r.status_code}", file=sys.stderr)
            return results

        data = r.json()
        for w in data.get("results", []):
            inv = w.get("abstract_inverted_index")
            abstract = ""
            if inv:
                word_pos = []
                for word, positions in inv.items():
                    for pos in positions:
                        word_pos.append((pos, word))
                word_pos.sort(key=lambda x: x[0])
                abstract = " ".join(item[1] for item in word_pos)

            doi_val = w.get("doi")
            doi_clean = doi_val.replace("https://doi.org/", "") if doi_val else None

            authors = []
            for auth in w.get("authorships", []):
                a_name = auth.get("author", {}).get("display_name")
                if a_name:
                    authors.append(a_name)

            concepts = [c.get("display_name") for c in w.get("concepts", []) if c.get("display_name")]
            location = w.get("primary_location") or {}
            source_info = location.get("source") or {}
            journal = source_info.get("display_name") or ""

            results.append({
                "id": w.get("id"),
                "title": w.get("title") or "",
                "doi": doi_clean,
                "year": w.get("publication_year"),
                "journal": journal,
                "authors": authors[:10],
                "abstract": abstract,
                "keywords": concepts[:10],
                "mesh_terms": [],
                "source_database": "OpenAlex",
                "citations": w.get("cited_by_count", 0),
                "url": doi_val or w.get("id"),
                "open_access": (w.get("open_access") or {}).get("is_oa", False)
            })
    except Exception as e:
        print(f"[OpenAlex] Error: {e}", file=sys.stderr)

    return results


def search_scielo_crossref(query, limit=25, start_year=None, end_year=None):
    """Fetch SciELO indexed records via Crossref prefix filter (10.1590)."""
    results = []
    base_url = "https://api.crossref.org/works"
    headers = {"User-Agent": USER_AGENT}

    params = {
        "query": query,
        "filter": "prefix:10.1590",
        "rows": limit,
        "sort": "published",
        "order": "desc"
    }

    try:
        r = requests.get(base_url, params=params, headers=headers, timeout=15)
        if r.status_code != 200:
            print(f"[SciELO/Crossref] Status {r.status_code}", file=sys.stderr)
            return results

        items = r.json().get("message", {}).get("items", [])
        for it in items:
            title_list = it.get("title", [])
            title = title_list[0] if title_list else ""
            if not title:
                continue

            doi = it.get("DOI")
            date_parts = it.get("published", {}).get("date-parts", [[None]])[0]
            year = date_parts[0] if date_parts else None

            if start_year and year and year < start_year:
                continue
            if end_year and year and year > end_year:
                continue

            abstract_raw = it.get("abstract", "")
            abstract = re.sub(r"<[^>]+>", " ", abstract_raw).strip() if abstract_raw else ""

            container = it.get("container-title", [])
            journal = container[0] if container else ""

            authors = []
            for auth in it.get("author", []):
                given = auth.get("given", "")
                family = auth.get("family", "")
                full = f"{given} {family}".strip()
                if full:
                    authors.append(full)

            results.append({
                "id": f"DOI:{doi}",
                "title": title,
                "doi": doi,
                "year": year,
                "journal": journal,
                "authors": authors[:10],
                "abstract": abstract,
                "keywords": it.get("subject", []),
                "mesh_terms": [],
                "source_database": "SciELO",
                "citations": it.get("is-referenced-by-count", 0),
                "url": f"https://doi.org/{doi}" if doi else None,
                "open_access": True
            })
    except Exception as e:
        print(f"[SciELO/Crossref] Error: {e}", file=sys.stderr)

    return results


def search_cabi_crossref(query, limit=20, start_year=None, end_year=None):
    """Fetch CABI indexed records through Crossref bibliographic query for CABI publications."""
    results = []
    base_url = "https://api.crossref.org/works"
    headers = {"User-Agent": USER_AGENT}

    params = {
        "query": query,
        "query.bibliographic": "CABI",
        "rows": limit,
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

            doi = it.get("DOI")
            date_parts = it.get("published", {}).get("date-parts", [[None]])[0]
            year = date_parts[0] if date_parts else None

            if start_year and year and year < start_year:
                continue
            if end_year and year and year > end_year:
                continue

            abstract_raw = it.get("abstract", "")
            abstract = re.sub(r"<[^>]+>", " ", abstract_raw).strip() if abstract_raw else ""
            container = it.get("container-title", [])
            journal = container[0] if container else ""

            authors = []
            for auth in it.get("author", []):
                given = auth.get("given", "")
                family = auth.get("family", "")
                full = f"{given} {family}".strip()
                if full:
                    authors.append(full)

            results.append({
                "id": f"DOI:{doi}",
                "title": title,
                "doi": doi,
                "year": year,
                "journal": journal,
                "authors": authors[:10],
                "abstract": abstract,
                "keywords": it.get("subject", []),
                "mesh_terms": [],
                "source_database": "CABI",
                "citations": it.get("is-referenced-by-count", 0),
                "url": f"https://doi.org/{doi}" if doi else None,
                "open_access": None
            })
    except Exception as e:
        print(f"[CABI] Error: {e}", file=sys.stderr)

    return results


def search_arxiv(query, limit=20):
    """Fetch preprint publications from arXiv to detect emerging, cutting-edge topics."""
    results = []
    base_url = "http://export.arxiv.org/api/query"
    params = {
        "search_query": f"all:{query}",
        "start": 0,
        "max_results": limit,
        "sortBy": "submittedDate",
        "sortOrder": "descending"
    }

    try:
        r = requests.get(base_url, params=params, headers={"User-Agent": USER_AGENT}, timeout=15)
        if r.status_code != 200:
            return results

        root = ET.fromstring(r.content)
        atom_ns = {"atom": "http://www.w3.org/2005/Atom"}

        for entry in root.findall("atom:entry", atom_ns):
            title_el = entry.find("atom:title", atom_ns)
            title = " ".join(title_el.text.split()) if title_el is not None and title_el.text else ""

            summary_el = entry.find("atom:summary", atom_ns)
            abstract = " ".join(summary_el.text.split()) if summary_el is not None and summary_el.text else ""

            published_el = entry.find("atom:published", atom_ns)
            year = None
            if published_el is not None and published_el.text:
                year = int(published_el.text[:4])

            id_el = entry.find("atom:id", atom_ns)
            arxiv_url = id_el.text if id_el is not None else ""

            authors = []
            for a in entry.findall("atom:author/atom:name", atom_ns):
                if a.text:
                    authors.append(a.text.strip())

            categories = []
            for cat in entry.findall("atom:category", atom_ns):
                term = cat.get("term")
                if term:
                    categories.append(term)

            results.append({
                "id": arxiv_url,
                "title": title,
                "doi": None,
                "year": year,
                "journal": "arXiv Preprint",
                "authors": authors[:10],
                "abstract": abstract,
                "keywords": categories,
                "mesh_terms": [],
                "source_database": "arXiv",
                "citations": None,
                "url": arxiv_url,
                "open_access": True
            })
    except Exception as e:
        print(f"[arXiv] Error: {e}", file=sys.stderr)

    return results


def search_scopus(query, limit=20, api_key=None, inst_token=None):
    """Fetch from Scopus API (Elsevier) if API key is provided."""
    if not api_key:
        return []

    results = []
    base_url = "https://api.elsevier.com/content/search/scopus"
    headers = {
        "X-ELS-APIKey": api_key,
        "Accept": "application/json",
        "User-Agent": USER_AGENT
    }
    if inst_token:
        headers["X-ELS-Insttoken"] = inst_token

    params = {
        "query": query,
        "count": min(limit, 25),
        "sort": "-pubyear"
    }

    try:
        r = requests.get(base_url, params=params, headers=headers, timeout=15)
        if r.status_code != 200:
            print(f"[Scopus] Status {r.status_code}: {r.text[:100]}", file=sys.stderr)
            return results

        entries = r.json().get("search-results", {}).get("entry", [])
        for entry in entries:
            title = entry.get("dc:title", "")
            doi = entry.get("prism:doi")
            year_str = entry.get("prism:coverDate", "")[:4]
            year = int(year_str) if year_str.isdigit() else None
            journal = entry.get("prism:publicationName", "")
            creator = entry.get("dc:creator", "")
            citations_str = entry.get("citedby-count", "0")
            citations = int(citations_str) if citations_str.isdigit() else 0

            results.append({
                "id": entry.get("dc:identifier", f"DOI:{doi}"),
                "title": title,
                "doi": doi,
                "year": year,
                "journal": journal,
                "authors": [creator] if creator else [],
                "abstract": "",  # Abstract requires separate e-fetch in Elsevier
                "keywords": [],
                "mesh_terms": [],
                "source_database": "Scopus",
                "citations": citations,
                "url": f"https://doi.org/{doi}" if doi else None,
                "open_access": entry.get("openaccess") == "1"
            })
    except Exception as e:
        print(f"[Scopus] Error: {e}", file=sys.stderr)

    return results


def deduplicate_articles(articles):
    """Deduplicates list of articles based on DOI or normalized title."""
    seen_dois = set()
    seen_titles = set()
    unique = []

    for art in articles:
        doi = art.get("doi")
        title_norm = normalize_title(art.get("title", ""))

        if doi:
            doi_lower = doi.lower().strip()
            if doi_lower in seen_dois:
                continue
            seen_dois.add(doi_lower)

        if title_norm:
            if title_norm in seen_titles:
                continue
            seen_titles.add(title_norm)

        unique.append(art)

    return unique


def run_multi_search(query, databases=None, limit_per_db=25, start_year=None, end_year=None, scopus_key=None, ncbi_key=None):
    if databases is None:
        databases = ["openalex", "pubmed", "scielo", "cabi", "arxiv"]

    scopus_key = scopus_key or os.environ.get("SCOPUS_API_KEY") or os.environ.get("ELSEVIER_API_KEY")
    if scopus_key and "scopus" not in databases:
        databases.append("scopus")

    all_articles = []

    print(f"[+] Iniciando busca acadêmica para o tema: '{query}'")
    print(f"[+] Bases selecionadas: {', '.join(databases)}")

    if "openalex" in databases:
        print("[*] Consultando OpenAlex (250M+ registros multidisciplinares)...")
        oa_results = search_openalex(query, limit=limit_per_db, start_year=start_year, end_year=end_year)
        print(f"    -> Encontrados {len(oa_results)} artigos no OpenAlex")
        all_articles.extend(oa_results)

    if "pubmed" in databases:
        print("[*] Consultando PubMed / NCBI Entrez (Biomedicina e Ciências da Saúde)...")
        pm_results = search_pubmed(query, limit=limit_per_db, start_year=start_year, end_year=end_year, api_key=ncbi_key)
        print(f"    -> Encontrados {len(pm_results)} artigos no PubMed")
        all_articles.extend(pm_results)

    if "scielo" in databases:
        print("[*] Consultando SciELO (América Latina, Caribe, Portugal, África do Sul)...")
        scielo_results = search_scielo_crossref(query, limit=limit_per_db, start_year=start_year, end_year=end_year)
        print(f"    -> Encontrados {len(scielo_results)} artigos no SciELO")
        all_articles.extend(scielo_results)

    if "cabi" in databases:
        print("[*] Consultando registros CABI (Agronomia, Meio Ambiente, Veterinária)...")
        cabi_results = search_cabi_crossref(query, limit=limit_per_db, start_year=start_year, end_year=end_year)
        print(f"    -> Encontrados {len(cabi_results)} artigos vinculados a CABI")
        all_articles.extend(cabi_results)

    if "arxiv" in databases:
        print("[*] Consultando arXiv (Preprints e Fronteiras Tecnológicas)...")
        arxiv_results = search_arxiv(query, limit=limit_per_db)
        print(f"    -> Encontrados {len(arxiv_results)} preprints no arXiv")
        all_articles.extend(arxiv_results)

    if "scopus" in databases and scopus_key:
        print("[*] Consultando Scopus API...")
        scopus_results = search_scopus(query, limit=limit_per_db, api_key=scopus_key)
        print(f"    -> Encontrados {len(scopus_results)} artigos no Scopus")
        all_articles.extend(scopus_results)
    elif "scopus" in databases and not scopus_key:
        print("[!] Aviso: Scopus selecionado mas SCOPUS_API_KEY não foi configurada. OpenAlex e Crossref indexam o catálogo Scopus gratuitamente.")

    unique_articles = deduplicate_articles(all_articles)
    print(f"\n[✓] Total coletado: {len(all_articles)} | Após deduplicação: {len(unique_articles)} artigos únicos.")
    return unique_articles


def main():
    parser = argparse.ArgumentParser(description="Busca e extrai metadados de artigos acadêmicos em múltiplas bases indexadas.")
    parser.add_argument("-q", "--query", required=True, help="Termo de busca ou string booleana")
    parser.add_argument("-d", "--databases", default="openalex,pubmed,scielo,cabi,arxiv", help="Bases separadas por vírgula")
    parser.add_argument("-n", "--limit", type=int, default=25, help="Limite de artigos por base (padrão 25)")
    parser.add_argument("--start-year", type=int, default=None, help="Ano inicial de publicação (ex: 2021)")
    parser.add_argument("--end-year", type=int, default=None, help="Ano final de publicação")
    parser.add_argument("-o", "--output", default="articles.json", help="Arquivo JSON de saída")
    parser.add_argument("--scopus-key", default=None, help="Chave de API do Scopus (Elsevier)")
    parser.add_argument("--ncbi-key", default=None, help="Chave de API do NCBI (PubMed)")

    args = parser.parse_args()
    db_list = [d.strip().lower() for d in args.databases.split(",")]

    results = run_multi_search(
        query=args.query,
        databases=db_list,
        limit_per_db=args.limit,
        start_year=args.start_year,
        end_year=args.end_year,
        scopus_key=args.scopus_key,
        ncbi_key=args.ncbi_key
    )

    with open(args.output, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)

    print(f"[✓] Resultados salvos com sucesso em: {args.output}")


if __name__ == "__main__":
    main()
