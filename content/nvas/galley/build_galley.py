"""Monta o PDF de um artigo da NVAS (galé) no layout da revista, a partir do manuscrito .docx.

O manuscrito segue os modelos da revista (content/nvas/templates): título, Abstract + Keywords,
Resumo + Palavras-chave, corpo com títulos em negrito (subtítulos em negrito e itálico),
Declarations, References e, no fim, tabelas e figuras com legendas. O que não está no manuscrito
anonimizado (autores, ORCID, filiações, contribuições, conflitos) vem de uma ficha JSON com os
dados da folha de rosto. Exemplo comentado: content/nvas/galley/ficha-exemplo.json.

Posição de tabelas e figuras (a 1ª regra que valer):
  1. "floats" da ficha › "after": logo depois do parágrafo ou título que contém o trecho (editor);
  2. onde o autor deixou no .docx (os modelos pedem: logo depois do parágrafo que cita), ou um
     parágrafo-marcador "[Figure 1 here]" / "[Figura 1 aqui]";
  3. logo depois do 1º parágrafo que a cita;
  4. no fim do texto, com aviso (não é citada).
Largura ("floats" › "width"): full (duas colunas, padrão), column (uma coluna; só figura pequena,
texto dentro da figura fica menor) ou page (página só para ela); "scale": % da largura.
O relatório no fim diz onde cada uma ficou e por qual regra. Citações [n] e DOIs viram links.

Fluxo da produção: --proof gera a prova do autor (final proof: dados reais, faixa e marca d'água
AUTHOR PROOF, <slug>_PROOF.pdf) para mandar pela discussão da etapa Produção do OJS; aprovada,
--final gera da MESMA ficha o PDF que sobe como galé (Publicação › Galés). O OJS não gera PDF.

Uso:  python content/nvas/galley/build_galley.py FICHA.json [--out PASTA] [--proof | --final]
      sem opção: amostra de layout (marca d'água SPECIMEN, DOI e datas "a definir");
      --proof exige volume, article_number, doi, received, accepted (proof_deadline opcional);
      --final exige também published.
Saída: PASTA/<slug>.pdf (ou _PROOF.pdf) e o .html (padrão: a pasta da ficha).
Requer pandoc, Google Chrome e pypdf (metadados; pip install pypdf). A hifenização do texto
justificado é a do próprio Chrome, com o dicionário que o Chrome normal já baixou (hyphen-data). Logo: branding/out/logo-color.svg (python branding/build_logo.py).
"""
import argparse
import html
import json
import re
import shutil
import subprocess
import sys
import tempfile
from html.parser import HTMLParser
from pathlib import Path

from pypdf import PdfWriter
from pypdf.generic import NameObject, TextStringObject

ROOT = Path(__file__).resolve().parents[3]
HERE = Path(__file__).resolve().parent
CSS = HERE / "galley.css"
LOGO = ROOT / "branding" / "out" / "logo-color.svg"
CHROME_PATHS = [
    Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe"),
    Path(r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"),
    Path("/usr/bin/google-chrome"),
    Path("/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"),
]

JOURNAL = "Neotropical Veterinary and Animal Science"
JOURNAL_ABBR = "Neotrop Vet Anim Sci"
JOURNAL_URL = "journals.csvet.com.br/nvas"
PUBLISHER = "CSVET Editora"
TBD = "to be assigned"
DOI_PREFIX = "10.54547"  # prefixo da revista; sufixo nvas.<id do artigo> (doiSuffixType no journal.json)

# Seções da revista (journal.json) -> rótulo da tarja e cor (cor cheia, fundo claro).
SECTION_STYLE = {
    "Original Research Articles": ("Original research", "#000080", "#e6e8f7"),
    "Short Communications": ("Short communication", "#5b3cc4", "#eeeafb"),
    "Case Reports and Case Series": ("Case report", "#c2410c", "#fdeee6"),
    "Clinical Images": ("Clinical image", "#be185d", "#fbe8f1"),
    "Reviews": ("Review", "#00796b", "#e3f3f1"),
    "Methods and Technical Notes": ("Methods", "#0369a1", "#e4f1f9"),
    "Data Articles": ("Data article", "#4d7c0f", "#eef5e3"),
    "Perspectives and Commentaries": ("Perspective", "#7c4a03", "#f6efe3"),
    "Letters to the Editor": ("Letter", "#4b5563", "#eef0f3"),
    "Editorials": ("Editorial", "#4b5563", "#eef0f3"),
    "Proceedings and Supplements": ("Proceedings", "#4b5563", "#eef0f3"),
}

ORCID_SVG = (
    '<svg class="orcid" viewBox="0 0 24 24" aria-label="ORCID"><circle cx="12" cy="12" r="12" fill="#A6CE39"/>'
    '<path fill="#fff" d="M7.6 17.3H6.1V7h1.5zM6.85 4.6a1 1 0 1 1 0 2 1 1 0 0 1 0-2zM9.6 7h4c3.8 0 5.5 2.7 '
    '5.5 5.15 0 2.67-2.1 5.15-5.48 5.15H9.6zm1.5 8.95h2.4c3.43 0 4.2-2.6 4.2-3.8 0-1.95-1.24-3.8-4.27-3.8H11.1z"/></svg>'
)
LOCK_SVG = (
    '<svg viewBox="0 0 10 14" aria-hidden="true"><path d="M2.2 6V4a2.8 2.8 0 0 1 5.6 0" fill="none" '
    'stroke="currentColor" stroke-width="1.3"/><rect x="0.6" y="6" width="8.8" height="7.4" rx="1.2" '
    'fill="currentColor"/><circle cx="5" cy="9.6" r="1.1" fill="#fff"/></svg>'
)


# ---------------------------------------------------------------- docx -> blocos HTML

class _Blocks(HTMLParser):
    """Separa o HTML do pandoc nos blocos de primeiro nível (p, table, figure, h1-h6...)."""

    VOID = {"img", "br", "hr", "col", "meta", "link", "input", "wbr", "source"}

    def __init__(self, text):
        super().__init__(convert_charrefs=False)
        self.text = text
        self.lines = [0]
        for line in text.splitlines(keepends=True):
            self.lines.append(self.lines[-1] + len(line))
        self.depth = 0
        self.start = None
        self.blocks = []

    def _offset(self):
        line, col = self.getpos()
        return self.lines[line - 1] + col

    def handle_starttag(self, tag, attrs):
        if self.depth == 0:
            self.start = self._offset()
        if tag not in self.VOID:
            self.depth += 1
        elif self.depth == 0:
            self.blocks.append(self.text[self.start:self._offset() + len(self.get_starttag_text())])

    def handle_startendtag(self, tag, attrs):
        if self.depth == 0:
            start = self._offset()
            self.blocks.append(self.text[start:start + len(self.get_starttag_text())])

    def handle_endtag(self, tag):
        if tag in self.VOID:
            return
        self.depth -= 1
        if self.depth == 0:
            end = self.text.index(">", self._offset()) + 1
            self.blocks.append(self.text[self.start:end])


def docx_blocks(docx, workdir, media_dir):
    out = subprocess.run(
        ["pandoc", str(docx), "-f", "docx", "-t", "html5", "--wrap=none", f"--extract-media={media_dir}"],
        cwd=workdir, capture_output=True, text=True, encoding="utf-8", check=True,
    ).stdout
    parser = _Blocks(out)
    parser.feed(out)
    return [b.strip() for b in parser.blocks if b.strip()]


def plain(block):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", block))).strip()


def inner(block):
    m = re.match(r"<(\w+)[^>]*>(.*)</\1>$", block, re.S)
    return m.group(2).strip() if m else block


def heading_level(block):
    """2 = seção (negrito), 3 = subseção (negrito + itálico), 0 = não é título."""
    m = re.match(r"<h([1-6])[^>]*>", block)
    if m:
        return 2 if m.group(1) in "12" else 3
    if not block.startswith("<p"):
        return 0
    body = inner(block)
    if re.fullmatch(r"<strong>\s*<em>.+</em>\s*</strong>|<em>\s*<strong>.+</strong>\s*</em>", body, re.S):
        return 3
    if re.fullmatch(r"<strong>(?:(?!</strong>).)+</strong>", body, re.S) and len(plain(body)) < 140:
        return 2
    return 0


def is_image(block):
    return "<img" in block and len(plain(block)) == 0


CAPTION_RE = re.compile(r"^(Table|Figure)\s+(\d+)\.?\s*")


def caption_of(block):
    m = CAPTION_RE.match(plain(block))
    return (m.group(1), int(m.group(2))) if m and block.startswith("<p") else None


def caption_html(block):
    """Tira o rótulo 'Table 1.' do começo da legenda e o devolve à parte."""
    body = inner(block)
    body = re.sub(r"^\s*<strong>\s*((?:Table|Figure)\s+\d+\.?)\s*</strong>\s*", "", body)
    body = re.sub(r"^\s*(?:Table|Figure)\s+\d+\.?\s*", "", body)
    return body.strip()


# ---------------------------------------------------------------- leitura do manuscrito

def parse_manuscript(blocks):
    doc = {"abstract": [], "keywords": [], "resumo": [], "palavras": [], "body": [],
           "declarations": [], "references": [], "floats": {}, "legends": {}}
    state = "pre"
    tail = []
    for b in blocks:
        text = plain(b)
        level = heading_level(b)
        low = text.lower().rstrip(":")
        if level == 2 and low == "abstract":
            state = "abstract"; continue
        if level == 2 and low == "resumo":
            state = "resumo"; continue
        if level == 2 and low in ("declarations", "statements and declarations"):
            state = "declarations"; continue
        if level == 2 and low in ("references", "referências"):
            state = "references"; continue
        if re.match(r"keywords?\s*:", text, re.I):
            doc["keywords"] = split_keywords(b); state = "between"; continue
        if re.match(r"palavras-chave\s*:", text, re.I):
            doc["palavras"] = split_keywords(b); state = "body"; continue
        if state == "between" and level == 2:
            state = "body"
        if state == "abstract":
            doc["abstract"].append(b)
        elif state == "resumo":
            doc["resumo"].append(b)
        elif state == "body":
            doc["body"].append(b)
        elif state == "declarations":
            doc["declarations"].append(b)
        elif state == "references":
            if re.match(r"^\d+\.\s", text):
                doc["references"].append(b)
            else:
                state = "tail"; tail.append(b)
        elif state == "tail":
            tail.append(b)
    doc["body"] = extract_floats(doc["body"], doc, inline=True)
    extract_floats(tail, doc, inline=False)
    for (kind, n), fl in doc["floats"].items():
        if kind == "Figure" and not fl.get("caption"):
            fl["caption"] = doc["legends"].get(n, "")
    return doc


def split_keywords(block):
    body = re.sub(r"^\s*(?:<strong>)?\s*(?:Keywords?|Palavras-chave)\s*:?\s*(?:</strong>)?\s*:?\s*", "",
                  inner(block), flags=re.I)
    return [k.strip() for k in re.split(r";", body.rstrip(". ")) if k.strip()]


def extract_floats(blocks, doc, inline):
    """Tira tabelas e figuras da sequência. No corpo, deixa um marcador no lugar."""
    out = []
    i = 0
    in_legends = False
    while i < len(blocks):
        b = blocks[i]
        cap = caption_of(b)
        nxt = blocks[i + 1] if i + 1 < len(blocks) else ""
        if heading_level(b) == 2 and plain(b).lower().startswith("figure legend"):
            in_legends = True; i += 1; continue
        if in_legends and cap and cap[0] == "Figure":
            doc["legends"][cap[1]] = caption_html(b); i += 1; continue
        if cap and cap[0] == "Table" and nxt.startswith("<table"):
            fl = {"kind": "Table", "n": cap[1], "caption": caption_html(b), "content": nxt, "note": ""}
            j = i + 2
            if j < len(blocks):
                after = blocks[j]
                if (after.startswith("<p") and not heading_level(after) and not caption_of(after)
                        and len(plain(after)) < 800 and not is_image(after)):
                    fl["note"] = inner(after); j += 1
            doc["floats"][("Table", cap[1])] = fl
            if inline:
                out.append(("float", ("Table", cap[1])))
            i = j; continue
        if is_image(b):
            label = caption_of(nxt) if nxt else None
            if label and label[0] == "Figure":
                is_full_caption = len(plain(nxt)) > len(f"Figure {label[1]}") + 2
                doc["floats"][("Figure", label[1])] = {
                    "kind": "Figure", "n": label[1], "content": b,
                    "caption": caption_html(nxt) if is_full_caption else "",
                }
                if inline:
                    out.append(("float", ("Figure", label[1])))
                i += 2; continue
        out.append(b)
        i += 1
    return out


# ---------------------------------------------------------------- montagem

CITE_RE = re.compile(r"\[(\d+(?:\s*[,\u2013\u2014-]\s*\d+)*)\]")


def link_citations(fragment):
    def repl(m):
        first = re.match(r"\d+", m.group(1)).group(0)
        return f'<a class="xref" href="#ref-{first}">[{m.group(1)}]</a>'
    parts = re.split(r"(<[^>]+>)", fragment)
    return "".join(p if p.startswith("<") else CITE_RE.sub(repl, p) for p in parts)


def link_reference(fragment):
    fragment = re.sub(r"(?<![\"/>])doi:\s*(10\.\d{4,9}/[^\s<]+)",
                      lambda m: f'doi:<a href="https://doi.org/{m.group(1)}">{m.group(1)}</a>', fragment)
    fragment = re.sub(r"(?<![\"'=>])(https?://[^\s<]+)",
                      lambda m: f'<a href="{m.group(1)}">{m.group(1)}</a>', fragment)
    return fragment


def float_html(fl):
    kind = fl["kind"]
    label = f'<span class="label">{kind} {fl["n"]}.</span>'
    cap = link_citations(fl.get("caption", ""))
    fid = f'{kind.lower()}-{fl["n"]}'
    # Largura: full (as duas colunas, padrão), column (uma coluna) ou page (página só para ela).
    width = f' w-{fl.get("width", "full")}'
    if kind == "Table":
        note = f'<p class="tnote">{link_citations(fl["note"])}</p>' if fl.get("note") else ""
        size = width + (" long" if fl["content"].count("<tr") > 22 else "")
        # Larguras do Word (colgroup do pandoc) saem ruins em A4 de duas colunas: o navegador distribui.
        table = re.sub(r"<colgroup>.*?</colgroup>", "", fl["content"], flags=re.S)
        return (f'<div class="float table{size}" id="{fid}"><p class="caption">{label} {cap}</p>'
                f'{table}{note}</div>')
    img = re.search(r"<img[^>]*>", fl["content"]).group(0)
    img = re.sub(r'\s(?:style|width|height)="[^"]*"', "", img)
    # Texto alternativo para leitor de tela: sem alt no Word, vale a legenda.
    if not re.search(r'alt="[^"]+"', img):
        alt = esc(f'{kind} {fl["n"]}. {plain(fl.get("caption", ""))}')
        img = re.sub(r'\salt="[^"]*"', "", img).replace("<img", f'<img alt="{alt}"', 1)
    if fl.get("src"):
        img = re.sub(r'src="[^"]*"', f'src="{fl["src"]}"', img)
    if fl.get("scale"):  # % da largura disponível
        img = img.replace("<img", f'<img style="width: {int(fl["scale"])}%"', 1)
    return f'<div class="float figure{width}" id="{fid}">{img}<p class="caption">{label} {cap}</p></div>'


# Marcador de posição no manuscrito: "[Figure 1 here]", "Insert Table 2 here", "[Figura 1 aqui]".
MARKER_RE = re.compile(
    r"^\[?\s*(?:insert|inserir|place|colocar)?\s*(fig(?:ure|ura)?\.?|tab(?:le|ela)?\.?)\s*(\d+)"
    r"\s*(?:near\s+here|about\s+here|here|aqui)?\s*\]?\.?$", re.I)


def float_key(label):
    """'Figure 1', 'Fig. 1', 'Figura 1', 'Table 2', 'Tabela 2' -> ('Figure', 1) / ('Table', 2)."""
    m = re.match(r"\s*(fig|tab)\D*(\d+)\s*$", label, re.I)
    if not m:
        sys.exit(f"Ficha: rótulo inválido em 'floats': {label!r} (use 'Figure 1' ou 'Table 2')")
    return ("Figure" if m.group(1).lower() == "fig" else "Table", int(m.group(2)))


def anchor_text(item):
    text = plain(item).replace("­", "")
    return text[:70] + ("…" if len(text) > 70 else "")


def place_floats(body, floats, rules):
    """Decide onde entra cada tabela e figura, nesta ordem:
    1. 'after' da ficha: depois do parágrafo ou título que contém o trecho indicado (decisão do editor);
    2. onde o autor deixou no .docx, ou um marcador "[Figure 1 here]" / "[Figura 1 aqui]";
    3. logo depois do 1º parágrafo que a cita;
    4. no fim do texto (aviso: não é citada).
    Devolve o corpo e o relatório {chave: (regra, trecho de referência)}."""
    report = {}
    # 1. Decisão do editor: sai de onde o autor deixou e entra depois do trecho indicado.
    for key, rule in rules.items():
        if key not in floats or not rule.get("after"):
            continue
        body = [i for i in body if i != ("float", key)]
        want = re.sub(r"\s+", " ", rule["after"]).strip().lower()
        for idx, item in enumerate(body):
            if isinstance(item, str) and want in plain(item).lower():
                body.insert(idx + 1, ("float", key))
                report[key] = ("ficha (after)", anchor_text(item))
                break
        else:
            sys.exit(f"Ficha: {key[0]} {key[1]}: trecho de 'after' não encontrado no texto: {rule['after']!r}")
    # 2. Posição do autor: o marcador vira o float; o que já está no corpo fica onde está.
    for idx, item in enumerate(body):
        if isinstance(item, str) and item.startswith("<p"):
            m = MARKER_RE.match(plain(item))
            if m:
                key = ("Figure" if m.group(1).lower().startswith("fig") else "Table", int(m.group(2)))
                if key in floats and key not in report:
                    prev = next((b for b in reversed(body[:idx]) if isinstance(b, str)), "")
                    body[idx] = ("float", key)
                    report[key] = ("marcador do autor", anchor_text(prev))
                else:
                    body[idx] = None
    body = [i for i in body if i is not None]
    for idx, item in enumerate(body):
        if isinstance(item, tuple) and item[1] not in report:
            prev = next((b for b in reversed(body[:idx]) if isinstance(b, str)), "")
            report[item[1]] = ("posição do autor no .docx", anchor_text(prev))
    # 3 e 4. Primeira citação; sem citação, no fim do texto.
    for key in sorted(floats):
        if key in report:
            continue
        kind, n = key
        cite = re.compile(rf"\b{kind}s?\s+(?:\d+\s*(?:,|and|&|–|-)\s*)*{n}\b")
        for idx, item in enumerate(body):
            if isinstance(item, str) and item.startswith("<p") and not heading_level(item) and cite.search(plain(item)):
                body.insert(idx + 1, ("float", key))
                report[key] = ("1ª citação", anchor_text(item))
                break
        else:
            body.append(("float", key))
            report[key] = ("fim do texto (NÃO É CITADA no texto)", "")
    return body, report


def render_body(body, floats):
    out = []
    num = 0
    for item in body:
        if isinstance(item, tuple):
            out.append(float_html(floats[item[1]])); continue
        level = heading_level(item)
        if level == 2:
            num += 1
            out.append(f'<h2><span class="num">{num}</span>{html.escape(plain(item))}</h2>')
        elif level == 3:
            out.append(f"<h3>{plain_inline(inner(item))}</h3>")
        else:
            out.append(link_citations(item))
    return "\n".join(out)


def plain_inline(fragment):
    """Título de subseção sem o negrito/itálico externos, mantendo itálico interno (espécies)."""
    fragment = re.sub(r"^<(strong|em)>\s*<(strong|em)>(.*)</\2>\s*</\1>$", r"\3", fragment.strip(), flags=re.S)
    return fragment


def declarations_html(decl_blocks, overrides):
    """Declarações do manuscrito, com as que estavam ocultas (anonimização) trocadas pela ficha."""
    items = []
    for b in decl_blocks:
        m = re.match(r"\s*<strong>([^<]+?)\.?\s*</strong>\s*(.*)", inner(b), re.S)
        if m:
            items.append([m.group(1).strip().rstrip("."), m.group(2).strip()])
        elif items:
            items[-1][1] += " " + inner(b)
    for label, text in overrides:
        key = label.lower().split(" (")[0]
        for it in items:
            if it[0].lower().split(" (")[0].startswith(key):
                it[0], it[1] = label, text; break
        else:
            items.insert(0, [label, text]) if key.startswith("acknowledg") else items.append([label, text])
    return "\n".join(f"<p><strong>{html.escape(l)}.</strong> {t}</p>" for l, t in items)


def esc(s):
    return html.escape(s, quote=True)


def css_string(s):
    return '"' + s.replace("\\", "\\\\").replace('"', '\\"') + '"'


def float_rules(meta, base):
    """'floats' da ficha ({"Figure 1": {"src", "after", "width", "scale"}}); aceita também o formato
    antigo "figures": {"1": "arquivo.png"}."""
    rules = {}
    for n, v in (meta.get("figures") or {}).items():
        rules[("Figure", int(n))] = v if isinstance(v, dict) else {"src": v}
    for label, v in (meta.get("floats") or {}).items():
        rules[float_key(label)] = v
    for key, rule in rules.items():
        if rule.get("width", "full") not in ("full", "column", "page"):
            sys.exit(f"Ficha: {key[0]} {key[1]}: width deve ser full, column ou page")
        if rule.get("src"):
            rule["src"] = (base / rule["src"]).resolve().as_uri()
    return rules


def build(ficha_path, out_dir, mode):
    """mode: 'specimen' (amostra de layout, sem dados de publicação), 'proof' (prova para o autor
    aprovar, com os dados reais) ou 'final' (o PDF que sobe como galé no OJS)."""
    ficha_path = Path(ficha_path).resolve()
    meta = json.loads(ficha_path.read_text(encoding="utf-8"))
    base = ficha_path.parent
    docx = (base / meta["manuscript"]).resolve()
    slug = meta["slug"]
    out_dir = Path(out_dir).resolve() if out_dir else base
    out_dir.mkdir(parents=True, exist_ok=True)
    specimen, proof = mode == "specimen", mode == "proof"
    if not specimen:
        needed = ("volume", "article_number", "doi", "received", "accepted") + (("published",) if mode == "final" else ())
        missing = [k for k in needed if not meta.get(k)]
        if missing:
            sys.exit(f"--{mode} exige na ficha: {', '.join(missing)}")
    if not LOGO.exists():
        sys.exit(f"Falta o logo {LOGO}: rode python branding/build_logo.py")

    media = f"{slug}_files"
    doc = parse_manuscript(docx_blocks(docx, out_dir, media))
    rules = float_rules(meta, base)
    for key, rule in rules.items():
        if key not in doc["floats"]:
            sys.exit(f"Ficha: {key[0]} {key[1]} não existe no manuscrito "
                     f"(há: {', '.join(f'{k} {n}' for k, n in sorted(doc['floats']))})")
        doc["floats"][key].update({k: rule[k] for k in ("src", "width", "scale") if rule.get(k)})
    body, placement = place_floats(doc["body"], doc["floats"], rules)

    # --- autores
    affs = meta["affiliations"]
    authors = []
    for a in meta["authors"]:
        sup = ",".join(str(i) for i in a.get("affiliations", [1])) if len(affs) > 1 else ""
        mark = "*" if a.get("corresponding") else ""
        orcid = (f'<a href="https://orcid.org/{a["orcid"]}">{ORCID_SVG}</a>' if a.get("orcid") else "")
        sup_html = f"<sup>{sup}{mark}</sup>" if (sup or mark) else ""
        authors.append(f'{esc(a["name"])}{sup_html}{orcid}')
    authors_html = ", ".join(authors[:-1]) + (" and " if len(authors) > 1 else "") + authors[-1]
    aff_html = "".join(
        f"<li>{f'<sup>{i}</sup>' if len(affs) > 1 else ''}{esc(t)}</li>" for i, t in enumerate(affs, 1))
    corr = meta["corresponding"]
    corr_html = (f'<p class="correspondence">* Corresponding author: {esc(corr["name"])}, '
                 f'<a href="mailto:{esc(corr["email"])}">{esc(corr["email"])}</a></p>')

    # --- dados do fascículo
    year = meta.get("year", "2026")
    volume = meta.get("volume") or "1"
    art_no = meta.get("article_number") or ("e[n]" if specimen else "")
    doi = meta.get("doi") or f"{DOI_PREFIX}/nvas.[ano].[n]"
    doi_txt = f"https://doi.org/{doi}"
    surnames = [a["surname"] for a in meta["authors"]]
    running_left = (surnames[0] + (" and " + surnames[1] if len(surnames) == 2 else " et al." if len(surnames) > 2 else ""))
    cite_names = ", ".join(a["cite_name"] for a in meta["authors"])
    title_plain = plain(meta["title"])
    cite = (f"{cite_names}. {meta['title']}. <em>{JOURNAL_ABBR}</em>. {year};{volume}:{art_no}. "
            f"{doi_txt}")

    def date(k):
        return esc(meta[k]) if meta.get(k) else '<span class="pending">—</span>'

    status = ('<dt>Status</dt><dd><span class="pending">Manuscript in preparation. Layout specimen; '
              'not peer reviewed or published.</span></dd>') if specimen else ""
    info = f"""
      <h2>Article info</h2>
      <dl>
        <dt>Article history</dt>
        <dd>Received {date('received')}<br>Accepted {date('accepted')}<br>Published {date('published')}</dd>
        {status}
        <dt>Section</dt><dd>{esc(meta['section'])}</dd>
        {f"<dt>Handling editor</dt><dd>{esc(meta['handling_editor'])}</dd>" if meta.get('handling_editor') else ''}
      </dl>
      <h2>Keywords</h2>
      <ul class="kw">{''.join(f'<li>{k}</li>' for k in doc['keywords'])}</ul>
    """
    citebar = f"""<section class="citebar">
      <p class="howtocite"><strong>How to cite:</strong> {cite}</p>
      <p class="licence"><span class="cc">CC BY 4.0</span> © {esc(year)} The Authors. Open access article
        under the Creative Commons Attribution 4.0 International licence, which permits use, distribution and
        reproduction in any medium, provided the original work is properly cited. Published by {PUBLISHER}.</p>
    </section>"""
    abstract = "".join(link_citations(p) for p in doc["abstract"])
    resumo = ""
    if doc["resumo"]:
        resumo = (f'<section class="resumo" lang="pt-BR"><h2>Resumo</h2>{"".join(doc["resumo"])}'
                  f'<p class="kw"><strong>Palavras-chave:</strong> {"; ".join(doc["palavras"])}</p></section>')

    refs = []
    for r in doc["references"]:
        m = re.match(r"\s*(\d+)\\?\.\s*(.*)", inner(r), re.S)
        n, text = m.group(1), m.group(2)
        refs.append(f'<li id="ref-{n}"><span class="n">{n}.</span>{link_reference(text)}</li>')

    banner = mark = ""
    if proof:
        mark = '<div class="specimen-mark">AUTHOR PROOF</div>'
        deadline = (f" and reply by <strong>{esc(meta['proof_deadline'])}</strong>"
                    if meta.get("proof_deadline") else "")
        banner = ('<div class="specimen-banner"><strong>AUTHOR PROOF.</strong> Please check every page of this '
                  f'proof{deadline}, listing any corrections with page and line. Only errors can be corrected at '
                  'this stage. Once you approve it, this file, without this notice and the watermark, is the '
                  'version that will be published.</div>')
    if specimen:
        mark = '<div class="specimen-mark">SPECIMEN · NOT PUBLISHED</div>'
        banner = ('<div class="specimen-banner"><strong>LAYOUT SPECIMEN.</strong> This manuscript is in preparation '
                  f'for submission to <em>{JOURNAL}</em>. It has not been peer reviewed, accepted or published, and no '
                  'DOI has been assigned. Values marked [n] and the dates are assigned on publication; the rest '
                  'shows the format of the journal\'s published articles.</div>')

    type_label, type_color, type_tint = SECTION_STYLE.get(meta["section"], (meta["section"], "#000080", "#e6e8f7"))
    subtype = meta.get("article_type", "")
    show_subtype = subtype and subtype.lower() != type_label.lower()
    # Bandeira do tipo de artigo, encostada na borda direita da 1ª página.
    flag = (f'<div class="flag"><div class="flag-inner"><span class="type">{esc(type_label)}</span>'
            + (f'<span class="subtype">{esc(subtype)}</span>' if show_subtype else "")
            + f'<span class="oa" title="Open access">{LOCK_SVG}</span></div></div>')
    foot = (f"{JOURNAL_ABBR}. {year};{volume}:{art_no} · {doi_txt}"
            + ("  ·  SPECIMEN, NOT PUBLISHED" if specimen else "  ·  AUTHOR PROOF" if proof else ""))
    page_css = f"""
    @page {{ @top-left {{ content: {css_string(type_label.upper() + "  ·  " + running_left)};
                         color: {type_color}; }}
             @top-right {{ content: {css_string(meta['short_title'])}; }}
             @bottom-left {{ content: {css_string(foot)}; }} }}
    @page :first {{ @top-left {{ content: none; }} @top-right {{ content: none; }} }}
    """

    page = f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{esc(title_plain)} — NVAS</title>
<link rel="stylesheet" href="{CSS.as_uri()}">
<style>{page_css}</style>
</head>
<body style="--type: {type_color}; --type-tint: {type_tint};">
{mark}
{flag}
<header class="masthead">
  <img src="{LOGO.as_uri()}" alt="{JOURNAL}">
  <div class="issue">
    <div class="jname">{JOURNAL}</div>
    <div>Volume {esc(volume)} · {esc(year)} · {f"Article {esc(art_no)}" if meta.get("article_number") else f"Article number {TBD}"}</div>
    <div>{esc(doi_txt)}</div>
    <div>{JOURNAL_URL}</div>
  </div>
</header>
{banner}
<h1 class="title">{meta['title']}</h1>
<p class="authors">{authors_html}</p>
<ul class="affiliations">{aff_html}</ul>
{corr_html}
<section class="front">
  <div class="info">{info}</div>
  <div class="abstract"><h2>Abstract</h2>{abstract}</div>
</section>
{citebar}
{resumo}
<main class="body">
{render_body(body, doc['floats'])}
</main>
<section class="backmatter">
  <h2>Declarations</h2>
  <div class="decl">{declarations_html(doc['declarations'], meta.get('declarations', []))}</div>
</section>
<section class="refs">
  <h2>References</h2>
  <ol>{''.join(refs)}</ol>
</section>
</body>
</html>
"""
    name = slug + ("_PROOF" if proof else "")
    html_path = out_dir / f"{name}.html"
    html_path.write_text(page, encoding="utf-8")
    pdf_path = out_dir / f"{name}.pdf"
    chrome = next((p for p in CHROME_PATHS if p.exists()), None)
    if not chrome:
        sys.exit(f"Chrome não encontrado; o HTML está em {html_path}")
    # Marcadores a partir dos títulos e PDF com tags (leitor de tela).
    subprocess.run([str(chrome), "--headless=new", "--disable-gpu", "--allow-file-access-from-files",
                    f"--user-data-dir={chrome_profile()}",
                    "--no-pdf-header-footer", "--virtual-time-budget=20000",
                    "--generate-pdf-document-outline", "--export-tagged-pdf",
                    f"--print-to-pdf={pdf_path}", html_path.as_uri()],
                   check=True, capture_output=True)
    set_pdf_metadata(pdf_path, {
        "/Title": title_plain,
        "/Author": "; ".join(a["name"] for a in meta["authors"]),
        "/Subject": f"{JOURNAL}. {year};{volume}:{art_no}. {doi_txt}"
                    + (" (layout specimen, not published)" if specimen else " (author proof)" if proof else ""),
        "/Keywords": "; ".join(plain(k) for k in doc["keywords"]),
        "/Creator": f"{JOURNAL} (build_galley.py)",
    })
    print(f"{pdf_path}\n  {len(doc['references'])} referências. Posição das tabelas e figuras:")
    for key in sorted(doc["floats"]):
        how, where = placement[key]
        width = doc["floats"][key].get("width", "full")
        print(f"  {key[0]} {key[1]} [{width}] {how}" + (f": depois de “{where}”" if where else ""))
    return pdf_path


HYPHEN_DATA = [
    Path.home() / "AppData" / "Local" / "Google" / "Chrome" / "User Data" / "hyphen-data",
    Path.home() / "Library" / "Application Support" / "Google" / "Chrome" / "hyphen-data",
    Path.home() / ".config" / "google-chrome" / "hyphen-data",
]


def chrome_profile():
    """Perfil próprio do Chrome headless com o dicionário de hifenização (hyphen-data) copiado do
    Chrome normal: sem ele o headless não hifeniza. Hífens inseridos à mão (&shy;) não servem:
    o Chrome quebra a camada de texto do PDF em cada um ("fol lowed") e a busca no PDF falha."""
    profile = Path(tempfile.gettempdir()) / "nvas-galley-chrome"
    target = profile / "hyphen-data"
    if not target.exists():
        source = next((p for p in HYPHEN_DATA if p.exists()), None)
        if source:
            shutil.copytree(source, target)
        else:
            print("  aviso: dicionário de hifenização do Chrome não encontrado; o texto sai sem hifenização "
                  "(abra o Chrome normal uma vez e gere de novo)")
    return profile


def set_pdf_metadata(pdf_path, info):
    """O Chrome só grava o título; autor, assunto e palavras-chave (lidos por Zotero, Mendeley e
    indexadores) entram aqui, sem perder marcadores nem tags. Também acerta o texto dos marcadores:
    o Chrome cola as palavras onde o título quebra de linha e copia a caixa alta do CSS."""
    writer = PdfWriter(clone_from=str(pdf_path))
    writer.add_metadata(info)
    outlines = writer._root_object.get("/Outlines")
    first = outlines.get_object().get("/First") if outlines else None
    if first is not None:
        top = first.get_object()
        top[NameObject("/Title")] = TextStringObject(info["/Title"])

        def fix(node):
            while node is not None:
                item = node.get_object()
                title = str(item.get("/Title", ""))
                title = re.sub(r"^(\d+)(?=\S)", r"\1 ", title)
                if title.isupper():
                    title = title.capitalize()
                item[NameObject("/Title")] = TextStringObject(title)
                fix(item.get("/First"))
                node = item.get("/Next")
        fix(top.get("/First"))
    tmp = pdf_path.with_suffix(".tmp.pdf")
    with open(tmp, "wb") as fh:
        writer.write(fh)
    tmp.replace(pdf_path)


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("ficha", nargs="+")
    ap.add_argument("--out")
    group = ap.add_mutually_exclusive_group()
    group.add_argument("--proof", action="store_true", help="prova para o autor aprovar (final proof)")
    group.add_argument("--final", action="store_true", help="PDF para publicar (galé no OJS)")
    args = ap.parse_args()
    sys.stdout.reconfigure(encoding="utf-8")  # relatório com acentos no terminal do Windows
    for f in args.ficha:
        build(f, args.out, "proof" if args.proof else "final" if args.final else "specimen")
