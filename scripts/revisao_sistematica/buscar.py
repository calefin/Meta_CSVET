#!/usr/bin/env python3
"""
Padronizador de buscas para revisão sistemática / metanálise — profatilio.com.br

Gera o arquivo que alimenta a triagem cega do sistema (formato "profatilio-sr/1").
Só usa a biblioteca padrão do Python 3.9+ (nada para instalar).

O que faz:
  1. Busca direto no PubMed (E-utilities) e/ou Europe PMC, se você passar a sintaxe.
  2. Lê as exportações das outras bases: Scopus (.ris/.csv), Web of Science (.ris/.txt),
     Embase/CAB (.ris), PubMed (.nbib) e Zotero/Mendeley (.ris).
  3. Padroniza todos os campos (DOI, PMID, PMCID, ISSN, volume, fascículo, páginas,
     idioma, tipo de publicação, autores, ano, revista, resumo, palavras-chave).
  4. Completa DOIs ausentes pelo Crossref (busca por título + conferência de ano).
  5. Remove duplicatas (DOI > PMID > título normalizado + ano), juntando as bases de origem.
  6. Valida: todo registro precisa de título e de um identificador (DOI, PMID ou URL).
     O que não passar vai para <saida>_rejeitados.csv com o motivo.
  7. Registra cada busca no padrão PRISMA-S (base, plataforma, sintaxe, data, nº de resultados).

Exemplos:
  # Busca no PubMed + arquivos exportados do Scopus e do WoS, completando DOI:
  python3 buscar.py --pubmed "(meloxicam) AND (rabbit)" \\
      --arquivo scopus.ris:Scopus --arquivo savedrecs.txt:"Web of Science" \\
      --email seu@email.com --completar-doi --saida 01_meloxicam.json

  # Só padronizar arquivos já baixados:
  python3 buscar.py --arquivo pubmed.nbib --arquivo scopus.csv --saida triagem.json

  # Gerar os arquivos-modelo (JSON e CSV) com o formato esperado:
  python3 buscar.py --modelo

Depois: no painel do professor → Revisões Sistemáticas → projeto → "Importar registros".
"""

import argparse
import csv
import datetime as dt
import difflib
import io
import json
import re
import sys
import time
import unicodedata
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

SCHEMA = "profatilio-sr/1"
USER_AGENT = "profatilio-sr/1.0 (revisao sistematica; mailto:{email})"

# Campos de cada registro (ordem do modelo). Obrigatórios: title + (doi | pmid | url).
RECORD_FIELDS = [
    "record_id", "title", "abstract", "authors", "year", "journal", "volume", "issue", "pages",
    "issn", "doi", "pmid", "pmcid", "url", "language", "publication_type", "keywords", "sources",
]


# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------

def clean(value):
    if value is None:
        return None
    if isinstance(value, (list, tuple)):
        value = "; ".join(str(v) for v in value if v)
    value = re.sub(r"<[^>]+>", "", str(value))
    value = re.sub(r"\s+", " ", value).strip()
    return value or None


def norm_doi(doi):
    doi = clean(doi)
    if not doi:
        return None
    doi = doi.lower()
    doi = re.sub(r"^(https?://)?(dx\.)?doi\.org/", "", doi)
    doi = re.sub(r"^doi:\s*", "", doi)
    m = re.search(r"10\.\d{4,9}/\S+", doi)
    return m.group(0).rstrip(".;,") if m else None


def norm_title(title):
    t = unicodedata.normalize("NFKD", title or "").encode("ascii", "ignore").decode().lower()
    return re.sub(r"[^a-z0-9]+", "", t)


def year_of(value):
    m = re.search(r"(1[89]\d{2}|20\d{2})", str(value or ""))
    return m.group(1) if m else None


def http_get(url, email, timeout=60):
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT.format(email=email or "anon")})
    for attempt in range(4):
        try:
            with urllib.request.urlopen(req, timeout=timeout) as r:
                return r.read()
        except Exception as exc:  # rede instável / limite de requisições
            if attempt == 3:
                raise
            print(f"  … tentando de novo ({exc})", file=sys.stderr)
            time.sleep(2 * (attempt + 1))


def empty_record(**kw):
    r = {f: None for f in RECORD_FIELDS}
    r["sources"] = []
    r.update({k: v for k, v in kw.items() if v is not None})
    return r


def finalize(r):
    """Normaliza os campos de um registro."""
    for k in RECORD_FIELDS:
        if k != "sources":
            r[k] = clean(r.get(k))
    r["title"] = (r["title"] or "").rstrip(" .") or None
    r["doi"] = norm_doi(r["doi"])
    r["year"] = year_of(r["year"])
    if r["pmid"] and not re.fullmatch(r"\d{1,10}", r["pmid"]):
        r["pmid"] = None
    if r["url"] and not re.match(r"^https?://", r["url"], re.I):
        r["url"] = None
    if r["abstract"] and r["abstract"].lower().strip("[]. ") == "no abstract available":
        r["abstract"] = None
    r["sources"] = sorted(set(s for s in r.get("sources") or [] if s))
    return r


# ---------------------------------------------------------------------------
# Leitores de arquivos
# ---------------------------------------------------------------------------

def read_text(path):
    raw = open(path, "rb").read()
    for enc in ("utf-8-sig", "utf-16", "cp1252"):
        try:
            return raw.decode(enc)
        except UnicodeDecodeError:
            continue
    return raw.decode("utf-8", "ignore")


def parse_ris(text, source):
    records, tags, last = [], {}, None

    def flush():
        if not tags:
            return
        g = lambda *keys: next((tags[k][0] for k in keys if tags.get(k)), None)
        doi = g("DO")
        if not doi:
            for u in tags.get("UR", []) + tags.get("L3", []):
                doi = norm_doi(u) or doi
        pmid = None
        for v in tags.get("AN", []) + tags.get("C2", []) + tags.get("M2", []):
            m = re.fullmatch(r"(?:PMID:?\s*)?(\d{5,9})", v.strip())
            if m:
                pmid = m.group(1)
                break
        sp, ep = g("SP"), g("EP")
        records.append(empty_record(
            title=g("TI", "T1", "CT", "BT"), abstract=g("AB", "N2"),
            authors="; ".join(tags.get("AU", []) + tags.get("A1", [])), year=g("PY", "Y1", "DA"),
            journal=g("T2", "JO", "JF", "JA", "J2"), volume=g("VL"), issue=g("IS"),
            pages=f"{sp}-{ep}" if sp and ep else sp, issn=g("SN"), doi=doi, pmid=pmid,
            url=g("UR"), language=g("LA"), publication_type=g("M3", "TY"),
            keywords="; ".join(tags.get("KW", [])), sources=[source]))

    for line in text.splitlines():
        m = re.match(r"^([A-Z][A-Z0-9])  -\s?(.*)$", line)
        if not m:
            if last and line.strip() and tags.get(last):
                tags[last][-1] += " " + line.strip()
            continue
        tag, value = m.group(1), m.group(2).strip()
        if tag == "ER":
            flush()
            tags, last = {}, None
            continue
        if tag == "TY":
            tags = {}
        tags.setdefault(tag, []).append(value)
        last = tag
    flush()
    return records


def parse_medline(text, source):
    records = []
    for block in re.split(r"\n\s*\n(?=PMID- )", text.strip()):
        tags, last = {}, None
        for line in block.splitlines():
            m = re.match(r"^([A-Z]{2,4})\s*- (.*)$", line)
            if m:
                last = m.group(1)
                tags.setdefault(last, []).append(m.group(2).strip())
            elif last and line.startswith("      "):
                tags[last][-1] += " " + line.strip()
        doi = next((v.replace("[doi]", "").strip() for v in tags.get("LID", []) + tags.get("AID", []) if "[doi]" in v), None)
        g = lambda k: tags.get(k, [None])[0]
        records.append(empty_record(
            title=g("TI") or g("BTI"), abstract=g("AB"), authors="; ".join(tags.get("FAU") or tags.get("AU", [])),
            year=g("DP"), journal=g("JT") or g("TA"), volume=g("VI"), issue=g("IP"), pages=g("PG"),
            issn=g("IS"), doi=doi, pmid=g("PMID"), pmcid=g("PMC"), language=g("LA"),
            publication_type="; ".join(tags.get("PT", [])), keywords="; ".join(tags.get("OT", []) + tags.get("MH", [])),
            url=f"https://pubmed.ncbi.nlm.nih.gov/{g('PMID')}/" if g("PMID") else None, sources=[source]))
    return records


CSV_MAP = {
    "title": ["title", "ti", "article title", "document title"],
    "abstract": ["abstract", "ab"],
    "authors": ["author full names", "authors", "af", "au", "author"],
    "year": ["year", "py", "publication year"],
    "journal": ["source title", "so", "journal", "publication title"],
    "volume": ["volume", "vl"], "issue": ["issue", "is"],
    "pages": ["pages", "page start"], "issn": ["issn", "sn"],
    "doi": ["doi", "di"], "pmid": ["pubmed id", "pm", "pmid"], "pmcid": ["pmcid"],
    "url": ["link", "url"], "language": ["language of original document", "la", "language"],
    "publication_type": ["document type", "dt", "publication_type"],
    "keywords": ["author keywords", "de", "keywords"],
    "record_id": ["record_id", "eid", "ut", "ut (unique wos id)"],
}


def parse_csv(text, source):
    first = text.splitlines()[0] if text else ""
    delim = "\t" if first.count("\t") > first.count(",") else (";" if first.count(";") > first.count(",") else ",")
    reader = csv.reader(io.StringIO(text), delimiter=delim)
    header = [h.strip().strip('"').lower() for h in next(reader, [])]
    idx = {}
    for field, names in CSV_MAP.items():
        for n in names:
            if n in header:
                idx[field] = header.index(n)
                break
    if "title" not in idx:
        raise SystemExit(f"CSV sem coluna de título: {header[:10]}")
    records = []
    for row in reader:
        get = lambda f: row[idx[f]] if f in idx and idx[f] < len(row) else None
        rec = empty_record(sources=[source])
        for f in idx:
            rec[f] = get(f)
        if rec.get("authors"):
            rec["authors"] = "; ".join(a.strip() for a in re.split(r";", rec["authors"]) if a.strip())
        if "sources" in header:  # modelo do sistema
            rec["sources"] = [s.strip() for s in (row[header.index("sources")] or source).split(";") if s.strip()]
        records.append(rec)
    return records


def parse_json(text, source):
    data = json.loads(text)
    items = data if isinstance(data, list) else data.get("records", [])
    out = []
    for it in items:
        rec = empty_record()
        for f in RECORD_FIELDS:
            rec[f] = it.get(f)
        src = it.get("sources") or source
        rec["sources"] = src if isinstance(src, list) else [src]
        out.append(rec)
    return out


def load_file(spec):
    path, _, source = spec.partition(":")
    if len(path) == 1 and source:  # C:\caminho no Windows
        path, _, source = spec[2:].partition(":")
        path = spec[:2] + path
    text = read_text(path)
    low = path.lower()
    if low.endswith(".json"):
        recs = parse_json(text, source or "Arquivo JSON")
    elif re.search(r"^PMID- ", text, re.M):
        recs = parse_medline(text, source or "PubMed")
    elif re.search(r"^TY  - ", text, re.M):
        guess = "Scopus" if "scopus.com" in text else ("Web of Science" if "webofscience" in text.lower() else "Arquivo RIS")
        recs = parse_ris(text, source or guess)
    else:
        guess = "Scopus" if "Source title" in text[:3000] else ("Web of Science" if re.match(r"^\ufeff?PT\t", text) else "Arquivo CSV")
        recs = parse_csv(text, source or guess)
    name = source or (recs[0]["sources"][0] if recs and recs[0]["sources"] else path)
    print(f"  {path}: {len(recs)} registros ({name})")
    return name, recs


# ---------------------------------------------------------------------------
# Buscas on-line
# ---------------------------------------------------------------------------

def search_pubmed(query, email, api_key=None, limit=10000):
    base = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/"
    extra = f"&email={urllib.parse.quote(email or '')}&tool=profatilio-sr" + (f"&api_key={api_key}" if api_key else "")
    q = urllib.parse.quote(query)
    res = json.loads(http_get(f"{base}esearch.fcgi?db=pubmed&retmode=json&retmax={limit}&term={q}{extra}", email))
    ids = res["esearchresult"]["idlist"]
    total = int(res["esearchresult"]["count"])
    print(f"  PubMed: {total} resultados")
    if total > limit:
        print(f"  ⚠ PubMed retornou {total}; só os primeiros {limit} foram baixados. Refine a busca.", file=sys.stderr)
    records = []
    for i in range(0, len(ids), 200):
        chunk = ",".join(ids[i:i + 200])
        text = http_get(f"{base}efetch.fcgi?db=pubmed&rettype=medline&retmode=text&id={chunk}{extra}", email).decode("utf-8", "ignore")
        records += parse_medline(text, "PubMed")
        time.sleep(0.12 if api_key else 0.4)
    return total, records


def search_europepmc(query, email, limit=10000):
    records, cursor, total = [], "*", 0
    while len(records) < limit:
        url = ("https://www.ebi.ac.uk/europepmc/webservices/rest/search?format=json&resultType=core&pageSize=1000"
               f"&cursorMark={urllib.parse.quote(cursor)}&query={urllib.parse.quote(query)}")
        data = json.loads(http_get(url, email))
        total = int(data.get("hitCount", 0))
        for it in data.get("resultList", {}).get("result", []):
            authors = it.get("authorString")
            journal = (it.get("journalInfo") or {}).get("journal", {}).get("title")
            records.append(empty_record(
                title=it.get("title"), abstract=it.get("abstractText"), authors=authors and authors.replace(", ", "; "),
                year=it.get("pubYear"), journal=journal, volume=(it.get("journalInfo") or {}).get("volume"),
                issue=(it.get("journalInfo") or {}).get("issue"), pages=it.get("pageInfo"), doi=it.get("doi"),
                pmid=it.get("pmid"), pmcid=it.get("pmcid"), language=it.get("language"),
                publication_type="; ".join((it.get("pubTypeList") or {}).get("pubType", [])),
                url=f"https://europepmc.org/article/{it.get('source')}/{it.get('id')}", sources=["Europe PMC"]))
        nxt = data.get("nextCursorMark")
        if not nxt or nxt == cursor or not data.get("resultList", {}).get("result"):
            break
        cursor = nxt
    print(f"  Europe PMC: {total} resultados")
    return total, records[:limit]


def crossref_doi(rec, email):
    """Procura o DOI pelo título; só aceita se o título bater ≥ 92% e o ano conferir (±1)."""
    q = urllib.parse.urlencode({"query.bibliographic": rec["title"], "rows": 3, "mailto": email or ""})
    try:
        items = json.loads(http_get(f"https://api.crossref.org/works?{q}", email, timeout=30))["message"]["items"]
    except Exception:
        return None
    for it in items:
        title = (it.get("title") or [""])[0]
        ratio = difflib.SequenceMatcher(None, norm_title(title), norm_title(rec["title"])).ratio()
        year = (it.get("issued", {}).get("date-parts") or [[None]])[0][0]
        if ratio >= 0.92 and (not rec["year"] or not year or abs(int(rec["year"]) - int(year)) <= 1):
            return norm_doi(it.get("DOI"))
    return None


# ---------------------------------------------------------------------------
# Desduplicação e validação
# ---------------------------------------------------------------------------

def dedupe(records):
    by_key, out = {}, []
    for r in records:
        keys = [k for k in (
            r["doi"] and "doi:" + r["doi"],
            r["pmid"] and "pmid:" + r["pmid"],
            "t:" + norm_title(r["title"]) + "|" + (r["year"] or ""),
        ) if k]
        hit = next((by_key[k] for k in keys if k in by_key), None)
        if hit is None:
            out.append(r)
            hit = r
        else:
            hit["sources"] = sorted(set(hit["sources"]) | set(r["sources"]))
            for f in RECORD_FIELDS:
                if f != "sources" and not hit.get(f) and r.get(f):
                    hit[f] = r[f]
        for k in keys:
            by_key[k] = hit
    return out


def validate(r):
    if not r["title"] or len(r["title"]) < 5:
        return "sem título"
    if not (r["doi"] or r["pmid"] or r["url"]):
        return "sem identificador (DOI, PMID ou URL)"
    return None


# ---------------------------------------------------------------------------
# Modelos
# ---------------------------------------------------------------------------

EXAMPLE = {
    "schema": SCHEMA,
    "project": "01_Meloxicam_Lagomorfos",
    "generated_at": "2026-10-15T10:00:00",
    "search": {
        "date": "2026-10-15",
        "query": "(\"meloxicam\" OR \"metacam\") AND (\"rabbit\" OR \"Oryctolagus cuniculus\")",
        "searches": [
            {"database": "PubMed/MEDLINE", "platform": "NCBI E-utilities", "query": "(\"meloxicam\" OR \"metacam\") AND (\"rabbit\" OR \"Oryctolagus cuniculus\")",
             "date": "2026-10-15", "results": 152, "filters": "nenhum", "notes": ""},
            {"database": "Scopus", "platform": "Elsevier", "query": "TITLE-ABS-KEY((meloxicam OR metacam) AND (rabbit*))",
             "date": "2026-10-15", "results": 201, "filters": "nenhum", "notes": "exportado em RIS"},
        ],
        "sources": {"PubMed/MEDLINE": 152, "Scopus": 201},
        "duplicates_removed": 98,
        "doi_completed_crossref": 7,
        "rejected": 1,
    },
    "records": [
        {
            "record_id": "PMID:12345678",
            "title": "Analgesic efficacy of meloxicam in rabbits after ovariohysterectomy",
            "abstract": "Background: ... Methods: ... Results: ... Conclusions: ...",
            "authors": "Silva, Ana B; Souza, Carlos D",
            "year": "2021",
            "journal": "Journal of Exotic Pet Medicine",
            "volume": "38", "issue": "2", "pages": "45-52",
            "issn": "1557-5063",
            "doi": "10.1016/j.jepm.2021.01.001",
            "pmid": "12345678",
            "pmcid": None,
            "url": "https://pubmed.ncbi.nlm.nih.gov/12345678/",
            "language": "eng",
            "publication_type": "Journal Article; Randomized Controlled Trial",
            "keywords": "rabbit; analgesia; NSAID; grimace scale",
            "sources": ["PubMed/MEDLINE", "Scopus"],
        }
    ],
}


def write_templates(prefix="modelo_importacao"):
    with open(f"{prefix}.json", "w", encoding="utf-8") as f:
        json.dump(EXAMPLE, f, ensure_ascii=False, indent=2)
    with open(f"{prefix}.csv", "w", encoding="utf-8-sig", newline="") as f:
        w = csv.writer(f)
        w.writerow(RECORD_FIELDS)
        rec = EXAMPLE["records"][0]
        w.writerow([("; ".join(rec[k]) if isinstance(rec[k], list) else (rec[k] or "")) for k in RECORD_FIELDS])
    print(f"Modelos gravados: {prefix}.json e {prefix}.csv")


# ---------------------------------------------------------------------------
# Principal
# ---------------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--pubmed", help="sintaxe de busca para o PubMed")
    ap.add_argument("--europepmc", help="sintaxe de busca para o Europe PMC")
    ap.add_argument("--arquivo", action="append", default=[], metavar="CAMINHO[:BASE]",
                    help="exportação de outra base (.ris .nbib .csv .txt .json); repita para cada arquivo")
    ap.add_argument("--consulta", action="append", default=[], metavar="BASE=SINTAXE",
                    help="sintaxe usada em cada arquivo exportado (para o registro PRISMA-S)")
    ap.add_argument("--email", help="seu e-mail (exigido pela NCBI/Crossref para uso educado)")
    ap.add_argument("--ncbi-api-key", help="chave da NCBI (opcional, deixa o PubMed mais rápido)")
    ap.add_argument("--completar-doi", action="store_true", help="procurar no Crossref o DOI dos registros sem DOI")
    ap.add_argument("--projeto", help="nome do projeto (ex.: 01_Meloxicam_Lagomorfos)")
    ap.add_argument("--saida", default="triagem.json", help="arquivo JSON de saída")
    ap.add_argument("--modelo", action="store_true", help="só gravar os arquivos-modelo e sair")
    a = ap.parse_args()

    if a.modelo:
        write_templates()
        return
    if not (a.pubmed or a.europepmc or a.arquivo):
        ap.error("informe --pubmed, --europepmc ou pelo menos um --arquivo")
    if (a.pubmed or a.europepmc or a.completar_doi) and not a.email:
        ap.error("--email é obrigatório para buscas on-line e Crossref")

    today = dt.date.today().isoformat()
    queries = dict(q.split("=", 1) for q in a.consulta if "=" in q)
    all_records, sources, searches = [], {}, []

    print("Lendo fontes…")
    if a.pubmed:
        total, recs = search_pubmed(a.pubmed, a.email, a.ncbi_api_key)
        all_records += recs
        sources["PubMed"] = len(recs)
        searches.append({"database": "PubMed/MEDLINE", "platform": "NCBI E-utilities", "query": a.pubmed,
                         "date": today, "results": total, "filters": "", "notes": ""})
    if a.europepmc:
        total, recs = search_europepmc(a.europepmc, a.email)
        all_records += recs
        sources["Europe PMC"] = len(recs)
        searches.append({"database": "Europe PMC", "platform": "EBI REST API", "query": a.europepmc,
                         "date": today, "results": total, "filters": "", "notes": ""})
    for spec in a.arquivo:
        name, recs = load_file(spec)
        all_records += recs
        sources[name] = sources.get(name, 0) + len(recs)
        searches.append({"database": name, "platform": "exportação manual", "query": queries.get(name, ""),
                         "date": today, "results": len(recs), "filters": "", "notes": spec})

    all_records = [finalize(r) for r in all_records]
    raw = len(all_records)

    completed = 0
    if a.completar_doi:
        missing = [r for r in all_records if not r["doi"] and r["title"]]
        print(f"Completando DOI no Crossref para {len(missing)} registros…")
        for i, r in enumerate(missing, 1):
            doi = crossref_doi(r, a.email)
            if doi:
                r["doi"] = doi
                completed += 1
            if i % 25 == 0:
                print(f"  {i}/{len(missing)} ({completed} encontrados)")
            time.sleep(0.2)

    unique = dedupe(all_records)
    accepted, rejected = [], []
    for r in unique:
        problem = validate(r)
        (rejected if problem else accepted).append((r, problem))
    accepted = [r for r, _ in accepted]
    for i, r in enumerate(accepted, 1):
        r["record_id"] = r["record_id"] or (f"DOI:{r['doi']}" if r["doi"] else f"PMID:{r['pmid']}" if r["pmid"] else f"R{i:05d}")

    out = {
        "schema": SCHEMA,
        "project": a.projeto,
        "generated_at": dt.datetime.now().isoformat(timespec="seconds"),
        "search": {
            "date": today,
            "query": a.pubmed or a.europepmc or next(iter(queries.values()), None),
            "searches": searches,
            "sources": sources,
            "duplicates_removed": raw - len(unique),
            "doi_completed_crossref": completed,
            "rejected": len(rejected),
        },
        "records": accepted,
    }
    with open(a.saida, "w", encoding="utf-8") as f:
        json.dump(out, f, ensure_ascii=False, indent=1)

    if rejected:
        rej = re.sub(r"\.json$", "", a.saida) + "_rejeitados.csv"
        with open(rej, "w", encoding="utf-8-sig", newline="") as f:
            w = csv.writer(f)
            w.writerow(["motivo"] + RECORD_FIELDS)
            for r, problem in rejected:
                w.writerow([problem] + [("; ".join(r[k]) if isinstance(r[k], list) else (r[k] or "")) for k in RECORD_FIELDS])
        print(f"⚠ {len(rejected)} registro(s) rejeitado(s) — veja {rej} (corrija e importe de novo, se for o caso)")

    no_doi = sum(1 for r in accepted if not r["doi"])
    no_abs = sum(1 for r in accepted if not r["abstract"])
    print("\nResumo (PRISMA — identificação)")
    for name, n in sources.items():
        print(f"  {name}: {n}")
    print(f"  Total bruto: {raw}\n  Duplicatas removidas: {raw - len(unique)}\n  DOIs completados (Crossref): {completed}")
    print(f"  Registros únicos para triagem: {len(accepted)}  (sem DOI: {no_doi} · sem resumo: {no_abs})")
    print(f"\nArquivo pronto: {a.saida}  → importe no painel do professor.")


if __name__ == "__main__":
    main()
