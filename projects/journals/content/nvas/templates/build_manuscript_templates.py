"""Gera os modelos de manuscrito da NVAS (.docx) com orientação passo a passo para quem nunca publicou.

Cada modelo traz quadros azuis de orientação (o que escrever, tamanho, dicas, o que evitar e um
exemplo fictício) antes de cada seção, e o texto [ESCREVA AQUI: ...] onde o autor escreve.
Os quadros são tabelas de uma célula, para o autor apagar antes de enviar.

Uso:  python content/nvas/templates/build_manuscript_templates.py
Saída: plugins/themes/csvet/docs/ (um arquivo por modelo e idioma; ver TEMPLATES no fim),
ligados na página /nvas/<idioma>/templates, nas normas (Seção 4.1) e na chamada de artigos.
Regras: content/nvas/author-guidelines.<idioma>.html. O modelo de Imagem Clínica é gerado por
build_clinical_image_template.py.
"""
from pathlib import Path

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

ROOT = Path(__file__).resolve().parents[3]
OUT_DIR = ROOT / "plugins" / "themes" / "csvet" / "docs"

NAVY = RGBColor(0x00, 0x00, 0x80)
GRAY = RGBColor(0x5A, 0x60, 0x80)
GUIDE_FILL, GUIDE_LINE = "EAF3FB", "9CC3E6"
WARN_FILL, WARN_LINE = "FFF4E0", "E8B04A"


# ---------------------------------------------------------------- utilitários de formatação

def shade(cell, hex_fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_fill)
    tc_pr.append(shd)


def cell_borders(cell, color, sides=("top", "left", "bottom", "right"), size=8):
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = OxmlElement("w:tcBorders")
    for side in ("top", "left", "bottom", "right"):
        el = OxmlElement(f"w:{side}")
        if side in sides:
            el.set(qn("w:val"), "single")
            el.set(qn("w:sz"), str(size))
            el.set(qn("w:color"), color)
        else:
            el.set(qn("w:val"), "nil")
        borders.append(el)
    tc_pr.append(borders)


def line_numbering(section):
    ln = OxmlElement("w:lnNumType")
    ln.set(qn("w:countBy"), "1")
    ln.set(qn("w:restart"), "continuous")
    section._sectPr.append(ln)


LOGO = ROOT / "branding" / "out" / "logo-color@2x.png"
DOI_PREFIX = "10.54547"

# Bandeira do tipo de artigo no cabeçalho da 1ª página: mesmas cores da galé
# (SECTION_STYLE em content/nvas/galley/build_galley.py).
KIND_CASE = ("CASE REPORT", "C2410C")
KIND_ORIGINAL = ("ORIGINAL RESEARCH", "000080")
KIND_REVIEW = ("REVIEW", "00796B")
KIND_IMAGE = ("CLINICAL IMAGE", "BE185D")


def _no_borders(tbl):
    borders = OxmlElement("w:tblBorders")
    for side in ("top", "left", "bottom", "right", "insideH", "insideV"):
        el = OxmlElement(f"w:{side}")
        el.set(qn("w:val"), "nil")
        borders.append(el)
    tbl._tbl.tblPr.append(borders)


def _field(run, code):
    for tag, text in (("begin", None), (None, code), ("end", None)):
        if tag:
            el = OxmlElement("w:fldChar")
            el.set(qn("w:fldCharType"), tag)
        else:
            el = OxmlElement("w:instrText")
            el.set(qn("xml:space"), "preserve")
            el.text = text
        run._r.append(el)


def journal_header_footer(section, t, kind=None):
    """Como a revista ethic@ (UFSC) entrega o modelo: logo e tipo de artigo no cabeçalho da 1ª página;
    em todas as páginas, rodapé com a citação e o DOI (prefixo já preenchido; o resto, a revista
    preenche após o aceite). Cabeçalho e rodapé não identificam autores (avaliação duplo-cego)."""
    section.different_first_page_header_footer = True
    width = section.page_width - section.left_margin - section.right_margin

    head = section.first_page_header
    tbl = head.add_table(rows=1, cols=2, width=width)
    _no_borders(tbl)
    left, right = tbl.rows[0].cells
    left.width, right.width = int(width * 0.6), int(width * 0.4)
    p = left.paragraphs[0]
    if LOGO.exists():
        p.add_run().add_picture(str(LOGO), height=Cm(1.15))
    else:
        r = p.add_run("Neotropical Veterinary and Animal Science")
        r.bold, r.font.color.rgb = True, NAVY
    if kind:
        label, color = kind
        shade(right, color)
        right.vertical_alignment = 1  # centro
        rp = right.paragraphs[0]
        rp.alignment = WD_ALIGN_PARAGRAPH.CENTER
        rp.paragraph_format.space_before = Pt(3)
        rp.paragraph_format.space_after = Pt(3)
        r = rp.add_run(f"{label}  ·  OPEN ACCESS")
        r.bold = True
        r.font.size = Pt(9)
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
    for cell in (left, right):
        for par in cell.paragraphs:
            par.paragraph_format.line_spacing = 1.0
    # O parágrafo vazio do cabeçalho vai para baixo da tabela e vira o respiro antes do texto.
    gap = head.paragraphs[0]
    gap._p.addprevious(tbl._tbl)
    gap.paragraph_format.line_spacing = 1.0
    gap.paragraph_format.space_after = Pt(14)

    for foot in (section.first_page_footer, section.footer):
        p = foot.paragraphs[0]
        p.paragraph_format.line_spacing = 1.0
        p.paragraph_format.tab_stops.add_tab_stop(width, alignment=2)  # direita
        r = p.add_run(f"Neotrop Vet Anim Sci. 20XX;X:eXX · https://doi.org/{DOI_PREFIX}/nvas.XXX")
        r.font.size, r.font.color.rgb = Pt(8), GRAY
        p.add_run("\t")
        num = p.add_run()
        num.bold, num.font.size, num.font.color.rgb = True, Pt(9), NAVY
        _field(num, "PAGE")
        note = foot.add_paragraph()
        note.paragraph_format.line_spacing = 1.0
        r = note.add_run(t("Ano, volume, número do artigo e DOI são preenchidos pela revista após o aceite. "
                           "Não altere o cabeçalho nem o rodapé.",
                           "Year, volume, article number and DOI are filled in by the journal after acceptance. "
                           "Do not change the header or footer."))
        r.italic, r.font.size, r.font.color.rgb = True, Pt(7), GRAY


def compact(p, size=10, after=3):
    p.paragraph_format.line_spacing = 1.15
    p.paragraph_format.space_after = Pt(after)
    for r in p.runs:
        r.font.size = Pt(size)


def new_document(t, title, numbered=True, kind=None):
    doc = Document()
    st = doc.styles["Normal"]
    st.font.name = "Arial"
    st.element.rPr.rFonts.set(qn("w:eastAsia"), "Arial")
    st.font.size = Pt(12)
    st.paragraph_format.line_spacing = 2.0
    st.paragraph_format.space_after = Pt(0)
    for name, size in (("Heading 1", 13), ("Heading 2", 12)):
        h = doc.styles[name]
        h.font.name = "Arial"
        h.element.rPr.rFonts.set(qn("w:asciiTheme"), "")
        h.font.size = Pt(size)
        h.font.bold = True
        h.font.italic = name == "Heading 2"
        h.font.color.rgb = RGBColor(0, 0, 0)
        h.paragraph_format.space_before = Pt(18 if name == "Heading 1" else 12)
        h.paragraph_format.space_after = Pt(0)
        h.paragraph_format.line_spacing = 2.0
        h.paragraph_format.keep_with_next = True

    sec = doc.sections[0]
    sec.page_width, sec.page_height = Cm(21), Cm(29.7)
    for side in ("left_margin", "right_margin", "top_margin", "bottom_margin"):
        setattr(sec, side, Cm(2.5))
    if numbered:
        line_numbering(sec)
    journal_header_footer(sec, t, kind)

    cp = doc.core_properties
    cp.title = title
    cp.author = ""
    cp.last_modified_by = ""
    cp.comments = ""
    cp.subject = "Neotropical Veterinary and Animal Science (NVAS)"
    cp.language = t("pt-BR", "en")
    return doc


# ---------------------------------------------------------------- blocos do modelo

def box(doc, head, body, fill=GUIDE_FILL, line=GUIDE_LINE):
    """Quadro de orientação: tabela de uma célula. body = lista de (tipo, texto)."""
    tbl = doc.add_table(rows=1, cols=1)
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    c = tbl.rows[0].cells[0]
    shade(c, fill)
    cell_borders(c, line, size=12)
    p = c.paragraphs[0]
    r = p.add_run(head)
    r.bold = True
    r.font.color.rgb = NAVY
    compact(p, 10.5, 4)
    for kind, text in body:
        q = c.add_paragraph()
        if kind == "label":
            q.add_run(text).bold = True
            q.paragraph_format.space_before = Pt(3)
        elif kind == "li":
            q.add_run("•\t" + text)
            q.paragraph_format.left_indent = Cm(0.6)
            q.paragraph_format.first_line_indent = Cm(-0.4)
            q.paragraph_format.tab_stops.add_tab_stop(Cm(0.6))
        elif kind == "check":
            q.add_run("☐\t" + text)
            q.paragraph_format.left_indent = Cm(0.7)
            q.paragraph_format.first_line_indent = Cm(-0.5)
            q.paragraph_format.tab_stops.add_tab_stop(Cm(0.7))
        elif kind == "ex":
            r = q.add_run(text)
            r.italic = True
            q.paragraph_format.left_indent = Cm(0.4)
        else:
            q.add_run(text)
        compact(q)
    doc.add_paragraph()  # separa o quadro do texto do autor
    return tbl


def guide(doc, t, section, what=None, size=None, tips=(), avoid=(), example=None, extra=()):
    body = []
    if what:
        body.append(("label", t("O que escrever", "What to write")))
        body += [("li", w) for w in what] if isinstance(what, list) else [("p", what)]
    if size:
        body += [("label", t("Tamanho", "Length")), ("p", size)]
    if tips:
        body.append(("label", t("Dicas", "Tips")))
        body += [("li", x) for x in tips]
    if avoid:
        body.append(("label", t("Evite", "Avoid")))
        body += [("li", x) for x in avoid]
    if example:
        body.append(("label", t("Exemplo (fictício, só para mostrar o formato)",
                                "Example (fictional, only to show the format)")))
        body += [("ex", e) for e in (example if isinstance(example, list) else [example])]
    body += list(extra)
    box(doc, t(f"Orientação · {section}  (apague este quadro antes de enviar)",
               f"Guidance · {section}  (delete this box before submitting)"), body)


def write_here(t, hint):
    return t(f"[ESCREVA AQUI: {hint}]", f"[WRITE HERE: {hint}]")


def ph(doc, text, align=None):
    p = doc.add_paragraph(text)
    if align:
        p.alignment = align
    return p


def labelled(doc, label, text):
    p = doc.add_paragraph()
    p.add_run(label + " ").bold = True
    p.add_run(text)
    return p


def h1(doc, text):
    return doc.add_heading(text, level=1)


def h2(doc, text):
    return doc.add_heading(text, level=2)


def limits_table(doc, t, rows):
    head = [t("Tipo", "Type"), t("Texto", "Text"), t("Resumo", "Abstract"),
            t("Referências", "References"), t("Figuras + tabelas", "Figures + tables")]
    tbl = doc.add_table(rows=1, cols=5)
    tbl.style = "Table Grid"
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(head):
        c = tbl.rows[0].cells[i]
        shade(c, "DCE6F5")
        c.paragraphs[0].add_run(h).bold = True
    for row in rows:
        cells = tbl.add_row().cells
        for i, v in enumerate(row):
            cells[i].paragraphs[0].add_run(v)
    for row in tbl.rows:
        for c in row.cells:
            for p in c.paragraphs:
                compact(p, 9, 0)
    note = doc.add_paragraph(t("Limites da Seção 1 das normas. O texto conta da Introdução à Conclusão "
                               "(sem resumo, tabelas, legendas e referências).",
                               "Limits from Section 1 of the guidelines. The text counts from the Introduction "
                               "to the Conclusion (excluding abstract, tables, legends and references)."))
    compact(note, 9, 0)
    note.runs[0].font.color.rgb = GRAY
    doc.add_paragraph()


def example_table(doc, t, number, head, rows):
    cap = doc.add_paragraph()
    cap.add_run(t(f"Tabela {number}. ", f"Table {number}. ")).bold = True
    cap.add_run(write_here(t, t("título autoexplicativo, acima da tabela: o quê, em quem, onde",
                                "self-explanatory title, above the table: what, in whom, where")))
    tbl = doc.add_table(rows=1, cols=len(head))
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(head):
        c = tbl.rows[0].cells[i]
        cell_borders(c, "000000", sides=("top", "bottom"))
        c.paragraphs[0].add_run(h).bold = True
    for n, row in enumerate(rows):
        cells = tbl.add_row().cells
        for i, v in enumerate(row):
            cells[i].paragraphs[0].add_run(v)
            if n == len(rows) - 1:
                cell_borders(cells[i], "000000", sides=("bottom",))
    for row in tbl.rows:
        for c in row.cells:
            for p in c.paragraphs:
                compact(p, 11, 0)
    note = doc.add_paragraph(write_here(t, t("nota da tabela: explique abreviaturas e símbolos, ex.: "
                                             "“Ht, hematócrito; ref., intervalo de referência.”",
                                             "table note: define abbreviations and symbols, e.g. "
                                             "“Ht, haematocrit; ref., reference interval.”")))
    compact(note, 10, 6)


def example_figure(doc, t, number, legend_hint):
    ph(doc, t(f"[INSIRA AQUI A FIGURA {number}: Inserir › Imagens]",
              f"[INSERT FIGURE {number} HERE: Insert › Pictures]"), WD_ALIGN_PARAGRAPH.CENTER)
    p = doc.add_paragraph()
    p.add_run(t(f"Figura {number}. ", f"Figure {number}. ")).bold = True
    p.add_run(write_here(t, legend_hint))


# ---------------------------------------------------------------- partes comuns

def table_guide(doc, t):
    guide(
        doc, t, t("Tabelas", "Tables"),
        what=t("Use tabela quando houver muitos números (exames, resultados por grupo). A tabela fica logo "
               "depois do parágrafo que a cita pela primeira vez, ex.: “(Tabela 1)”.",
               "Use a table when there are many numbers (test results, results per group). Place it right "
               "after the paragraph that first cites it, e.g. “(Table 1)”."),
        tips=[t("Faça com a ferramenta de tabela (Inserir › Tabela). Nunca cole a tabela como imagem.",
                "Build it with the table tool (Insert › Table). Never paste a table as an image."),
              t("Título acima da tabela; abreviaturas explicadas na nota abaixo; unidades no cabeçalho.",
                "Title above the table; abbreviations defined in the note below; units in the header."),
              t("Linhas horizontais só no topo, abaixo do cabeçalho e no fim; sem linhas verticais.",
                "Horizontal lines only at the top, below the header and at the bottom; no vertical lines.")],
        avoid=[t("Repetir no texto todos os números da tabela: no texto, destaque só os principais.",
                 "Repeating every number of the table in the text: highlight only the main ones."),
               t("Mostrar os mesmos dados numa tabela e numa figura.",
                 "Showing the same data in a table and in a figure.")],
    )


def figure_guide(doc, t):
    guide(
        doc, t, t("Figuras", "Figures"),
        what=t("Fotos, imagens de exames, micrografias ou gráficos. A figura fica logo depois do parágrafo que a "
               "cita pela primeira vez, ex.: “(Figura 1A)”, com a legenda embaixo.",
               "Photos, diagnostic images, micrographs or graphs. Place the figure right after the paragraph that "
               "first cites it, e.g. “(Figure 1A)”, with the legend below it."),
        tips=[t("Todo texto DENTRO da figura (eixos, rótulos, setas com palavras) em inglês, mesmo se o "
                "manuscrito for em português. A revista não refaz figuras.",
                "Any text INSIDE the figure (axes, labels, arrows with words) must be in English. "
                "The journal does not redraw figures."),
              t("Painéis com letras maiúsculas (A, B, C) e uma legenda que explique cada painel, cada seta "
                "e, nas micrografias, a coloração e a barra de escala.",
                "Panels with capital letters (A, B, C) and a legend that explains every panel, every arrow "
                "and, for micrographs, the stain and the scale bar."),
              t("Na submissão, resolução de tela basta. Os arquivos em alta resolução só são pedidos após o aceite.",
                "At submission, screen resolution is enough. High-resolution files are only requested after acceptance.")],
        avoid=[t("Foto com nome do animal, do tutor, data, número de prontuário ou logotipo do hospital.",
                 "Photos showing the animal's or owner's name, dates, record numbers or the hospital logo."),
               t("Filtros, retoques ou “melhoria” por inteligência artificial. Só são permitidos recorte e "
                 "ajustes de brilho, contraste e cor na imagem inteira.",
                 "Filters, retouching or AI “enhancement”. Only cropping and brightness, contrast or colour "
                 "adjustments to the whole image are allowed."),
               t("Captura de tela ou foto repassada por aplicativo de mensagens (chega comprimida).",
                 "Screenshots or photos forwarded by messaging apps (they arrive compressed).")],
    )


def keywords(doc, t, n="4 a 6", n_en="4 to 6", example=None):
    guide(
        doc, t, t("Palavras-chave", "Keywords"),
        what=t(f"{n} palavras-chave, separadas por ponto e vírgula, que não repitam as palavras do título. "
               "Inclua a espécie.",
               f"{n_en} keywords, separated by semicolons, that do not repeat the words of the title. "
               "Include the species."),
        tips=[t("Escolha termos que as bases de dados reconhecem: consulte o DeCS/MeSH em decs.bvsalud.org.",
                "Choose terms that databases recognise: check MeSH at meshb.nlm.nih.gov.")],
        example=example,
    )
    labelled(doc, t("Palavras-chave:", "Keywords:"), write_here(t, t("termo 1; termo 2; termo 3; termo 4",
                                                                       "term 1; term 2; term 3; term 4")))


def declarations(doc, t, ethics_body, data_example):
    h1(doc, t("Declarações", "Declarations"))
    guide(
        doc, t, t("Declarações", "Declarations"),
        what=t("Estas seis declarações são obrigatórias e também vão na folha de rosto. Aqui, no manuscrito "
               "anonimizado, não escreva nomes nem números que identifiquem vocês ou a instituição: use "
               "[blinded] no lugar.",
               "These six statements are mandatory and also go on the title page. Here, in the anonymized "
               "manuscript, do not write names or numbers that identify you or your institution: write "
               "[blinded] instead."),
        extra=[("label", t("Aprovação ética e consentimento", "Ethics approval and consent"))] + ethics_body + [
            ("label", t("Contribuição dos autores", "Author contributions")),
            ("p", t("No manuscrito anonimizado, escreva só “[blinded] (informada na folha de rosto)”. "
                    "A lista completa, com os nomes, vai na folha de rosto.",
                    "In the anonymized manuscript, write only “[blinded] (provided on the title page)”. "
                    "The full list, with names, goes on the title page.")),
            ("label", t("Conflito de interesses", "Conflict of interest")),
            ("p", t("Declare vínculos com empresas, produtos estudados, consultorias ou empregos. Se não houver: "
                    "“Os autores declaram não haver conflito de interesses.”",
                    "Declare links to companies, products studied, consultancies or jobs. If there are none: "
                    "“The authors declare no conflict of interest.”")),
            ("label", t("Financiamento", "Funding")),
            ("p", t("Bolsas e auxílios com o número do processo (CNPq, CAPES, FAPESP, iniciação científica). "
                    "Bolsa CAPES: inclua “Código de Financiamento 001”. Sem apoio: “Este trabalho não recebeu "
                    "financiamento.”",
                    "Grants and scholarships with the grant number. If there was no support: “This work received "
                    "no funding.”")),
            ("label", t("Disponibilidade de dados (sempre em inglês)", "Data availability")),
            ("p", data_example),
            ("label", t("Uso de IA generativa (sempre em inglês)", "Use of generative AI")),
            ("p", t("Se usou ChatGPT, Gemini, Claude, DeepL ou outra ferramenta para melhorar o texto ou traduzir, "
                    "declare qual e para quê: “During the preparation of this work the authors used [tool, version] "
                    "in order to [purpose]. After using this tool, the authors reviewed and edited the content as "
                    "needed and take full responsibility for the content of the publication.” Se não usou: "
                    "“The authors did not use generative AI in the preparation of this manuscript.”",
                    "If you used ChatGPT, Gemini, Claude, DeepL or another tool to improve the text or translate, "
                    "state which and why: “During the preparation of this work the authors used [tool, version] "
                    "in order to [purpose]. After using this tool, the authors reviewed and edited the content as "
                    "needed and take full responsibility for the content of the publication.” If you did not: "
                    "“The authors did not use generative AI in the preparation of this manuscript.”")),
            ("p", t("Proibido: IA escrever resultados, discussão ou conclusões no lugar de vocês, criar ou "
                    "“melhorar” imagens, ou inventar referências. Referência inexistente é má conduta.",
                    "Not allowed: AI writing results, discussion or conclusions for you, creating or “enhancing” "
                    "images, or making up references. A non-existent reference is misconduct.")),
        ],
    )
    for label, text in (
        (t("Aprovação ética e consentimento:", "Ethics approval and consent:"), write_here(t, t("a frase que se aplica ao seu trabalho", "the statement that applies to your work"))),
        (t("Contribuição dos autores:", "Author contributions:"), t("[blinded] (informada na folha de rosto)", "[blinded] (provided on the title page)")),
        (t("Conflito de interesses:", "Conflict of interest:"), t("Os autores declaram não haver conflito de interesses.", "The authors declare no conflict of interest.")),
        (t("Financiamento:", "Funding:"), write_here(t, t("financiadores e números de processo, ou “Este trabalho não recebeu financiamento.”", "funders and grant numbers, or “This work received no funding.”"))),
        ("Data availability statement:", write_here(t, t("a frase em inglês que se aplica", "the statement that applies"))),
        ("Declaration of generative AI and AI-assisted technologies in the writing process:", write_here(t, t("a frase em inglês que se aplica", "the statement that applies"))),
    ):
        labelled(doc, label, text)


def references(doc, t, limit):
    h1(doc, t("Referências", "References"))
    guide(
        doc, t, t("Referências", "References"),
        what=[t("Estilo Vancouver. No texto, números entre colchetes na ordem em que cada referência aparece pela "
                "primeira vez: [1], [2,3], [4-6]. Uma referência citada de novo mantém o número da primeira vez.",
                "Vancouver style. In the text, numbers in square brackets in the order each reference first "
                "appears: [1], [2,3], [4-6]. A reference cited again keeps its first number."),
              t("Na lista, as referências ficam na ordem numérica (não em ordem alfabética), com o DOI sempre que existir.",
                "In the list, references follow the numerical order (not alphabetical), with the DOI whenever there is one.")],
        size=limit,
        tips=[t("Use um gerenciador gratuito, como o Zotero (zotero.org), com o estilo “Vancouver”: ele numera e "
                "formata sozinho e evita erros.",
                "Use a free reference manager such as Zotero (zotero.org) with the “Vancouver” style: it numbers "
                "and formats the list for you."),
              t("Ache o DOI na página do artigo ou em search.crossref.org.",
                "Find the DOI on the article page or at search.crossref.org."),
              t("Leia cada artigo que citar e confira se ele diz mesmo o que você afirma.",
                "Read every article you cite and check that it really says what you claim."),
              t("Prefira artigos científicos revisados por pares, de preferência recentes.",
                "Prefer peer-reviewed scientific articles, preferably recent ones.")],
        avoid=[t("Sites, apostilas, TCCs, teses e resumos de congresso, salvo quando indispensável.",
                 "Websites, lecture notes, theses and conference abstracts, unless indispensable."),
               t("Citar um artigo que você não leu só porque outro autor o citou.",
                 "Citing an article you have not read just because another author cited it.")],
        example=[t("Artigo: 1. Smith AB, Silva CD. Título do artigo. Abreviatura da Revista. 2024;12(3):123-30. doi:10.xxxx/xxxxx",
                   "Article: 1. Smith AB, Silva CD. Title of the article. Abbreviated Journal Title. 2024;12(3):123-30. doi:10.xxxx/xxxxx"),
                 t("Livro: 2. Souza EF. Título do livro. 3. ed. Cidade: Editora; 2020.",
                   "Book: 2. Jones EF. Title of the book. 3rd ed. City: Publisher; 2020."),
                 t("Capítulo: 3. Lima GH. Título do capítulo. In: Costa IJ, editor. Título do livro. Cidade: Editora; 2019. p. 100-15.",
                   "Chapter: 3. Brown GH. Title of the chapter. In: White IJ, editor. Title of the book. City: Publisher; 2019. p. 100-15.")],
    )
    for n in range(1, 4):
        ph(doc, f"{n}. " + write_here(t, t("referência no estilo Vancouver", "reference in Vancouver style")))


def how_to_use(doc, t, name, order, extra=()):
    body = [
        ("li", t("Salve uma cópia com um nome seu (ex.: relato-hemangiossarcoma-cao.docx) e trabalhe nela.",
                 "Save a copy with your own file name (e.g. case-report-haemangiosarcoma-dog.docx) and work on it.")),
        ("li", t("Os quadros azuis, como este, são orientações. Leia, escreva o seu texto embaixo e apague o quadro. "
                 "Para apagar: clique dentro do quadro e use Layout › Excluir › Excluir Tabela (Word) ou botão "
                 "direito › Excluir tabela (Google Docs).",
                 "The blue boxes, like this one, are guidance. Read them, write your text below and delete the box. "
                 "To delete: click inside the box and use Layout › Delete › Delete Table (Word) or right-click › "
                 "Delete table (Google Docs).")),
        ("li", t("Onde está [ESCREVA AQUI: ...], selecione o trecho inteiro e digite por cima. No fim, procure "
                 "“ESCREVA AQUI” com Ctrl+F: não pode sobrar nenhum.",
                 "Where it says [WRITE HERE: ...], select the whole passage and type over it. At the end, search "
                 "for “WRITE HERE” with Ctrl+F: none can be left.")),
        ("li", t("Este é o manuscrito anonimizado, o arquivo que os revisores leem. Não escreva o nome dos autores, "
                 "do orientador, da instituição, do hospital, da fazenda ou da cidade, nem agradecimentos. Onde "
                 "precisar, escreva [blinded]. Os nomes vão só na folha de rosto (há um modelo próprio).",
                 "This is the anonymized manuscript, the file the reviewers read. Do not write the names of the "
                 "authors, supervisor, institution, hospital, farm or city, nor acknowledgements. Where needed, "
                 "write [blinded]. Names go only on the title page (there is a separate template).")),
        ("li", t("Escreva o manuscrito inteiro em português ou inteiro em inglês. Se for aceito em português, a "
                 "revista traduz para o inglês sem custo (aceites até 31/01/2027) e você aprova a tradução. Texto "
                 "dentro de figuras e gráficos: sempre em inglês.",
                 "Write the whole manuscript in English (American or British, consistently) or in Portuguese. "
                 "Text inside figures and graphs must always be in English.")),
        ("li", t("A formatação já está pronta (A4, Arial 12, espaço duplo, linhas e páginas numeradas). Use os "
                 "estilos Título 1 e Título 2 para as seções e não use Enter ou espaços para “empurrar” texto.",
                 "Formatting is already set (A4, Arial 12, double spacing, line and page numbers). Use the "
                 "Heading 1 and Heading 2 styles for sections and do not use Enter or spaces to “push” text.")),
        ("li", t(f"Não precisa escrever na ordem do arquivo. Uma ordem mais fácil: {order}",
                 f"You do not have to write in the order of the file. An easier order: {order}")),
        ("li", t("Todo manuscrito precisa de um autor graduado (em geral o orientador), coautor e responsável pelo "
                 "trabalho. Mostre cada versão a essa pessoa antes de enviar.",
                 "Every manuscript needs a graduate author (usually the supervisor) as co-author, responsible for "
                 "the work. Show each version to that person before submitting.")),
        ("li", t("Para contar as palavras do texto, selecione da Introdução até a Conclusão e veja a barra inferior "
                 "do Word (Google Docs: Ferramentas › Contagem de palavras).",
                 "To count the words of the text, select from the Introduction to the Conclusion and look at the "
                 "bottom bar in Word (Google Docs: Tools › Word count).")),
    ] + list(extra)
    box(doc, t(f"NVAS · {name} · Como usar este modelo (leia antes e apague este quadro antes de enviar)",
               f"NVAS · {name} · How to use this template (read first; delete this box before submitting)"), body)


def final_check(doc, t, items, section_name, guideline):
    doc.add_paragraph().add_run().add_break(WD_BREAK.PAGE)
    body = [("check", x) for x in [
        t("Apaguei todos os quadros azuis e o amarelo, inclusive este.",
          "I deleted all the blue and yellow boxes, including this one."),
        t("Procurei “ESCREVA AQUI” e “INSIRA AQUI” (Ctrl+F) e não sobrou nenhum.",
          "I searched for “WRITE HERE” and “INSERT” (Ctrl+F) and none are left."),
        t("Não há nome de autor, orientador, instituição, hospital, fazenda ou cidade no texto nem nas figuras.",
          "There are no names of authors, supervisor, institution, hospital, farm or city in the text or figures."),
        t("Tirei meu nome das propriedades do arquivo. Word: Arquivo › Informações › Verificar Problemas › "
          "Inspecionar Documento › Remover Tudo em “Propriedades do Documento e Informações Pessoais”.",
          "I removed my name from the file properties. Word: File › Info › Check for Issues › Inspect Document › "
          "Remove All under “Document Properties and Personal Information”."),
    ] + items + [
        t("Cada referência citada no texto está na lista, na mesma numeração, e vice-versa.",
          "Every reference cited in the text is in the list, with the same number, and vice versa."),
        t("Todo texto dentro das figuras e dos gráficos está em inglês.",
          "All text inside figures and graphs is in English."),
        t("O orientador (autor graduado) leu e aprovou a versão final.",
          "The supervisor (graduate author) read and approved the final version."),
    ]]
    body += [
        ("label", t("Arquivos para enviar", "Files to upload")),
        ("li", t("Este manuscrito anonimizado (tipo “Manuscrito anonimizado (sem nomes de autores)”).",
                 "This anonymized manuscript (type “Anonymized manuscript (no author names)”).")),
        ("li", t("Folha de rosto (tipo “Folha de rosto (com os autores)”) e carta de apresentação (tipo “Carta de "
                 "apresentação”), em modelos próprios: não vão para os revisores.",
                 "Title page (type “Title page (with authors)”) and cover letter (type “Cover letter”), from their own "
                 "templates: not sent to reviewers.")),
        ("li", guideline),
        ("li", t("Documentos de ética (CEUA, CEP) e licenças, quando houver (tipo “Documento de ética ou licença”), e o "
                 "termo de consentimento do responsável legal, se o caso permitir identificar o animal ou o tutor "
                 "(modelo na página Modelos para autores).",
                 "Ethics documents (animal or human ethics committee) and permits, when applicable (type “Ethics approval "
                 "or permit”), and the legal guardian's consent form if the case could identify the animal or its "
                 "guardian (template on the Templates for authors page).")),
        ("label", t("Como enviar", "How to submit")),
        ("li", t("Crie sua conta em journals.csvet.com.br (botão Cadastro) e conecte seu ORCID (grátis em orcid.org).",
                 "Create your account at journals.csvet.com.br (Register) and connect your ORCID (free at orcid.org).")),
        ("li", t(f"Entre, inicie uma nova submissão e escolha a seção “{section_name}” e o idioma do manuscrito.",
                 f"Log in, start a new submission and choose the section “{section_name}” and the manuscript language.")),
        ("li", t("Envie os arquivos e escolha o tipo de cada um; se faltar algum obrigatório, o sistema avisa. Cadastre "
                 "todos os autores (com o orientador) e copie título, resumo e palavras-chave.",
                 "Upload the files and choose the type of each one; if a required file is missing, the system tells you. "
                 "Add all authors (including the supervisor) and paste the title, abstract and keywords.")),
        ("li", t("No último passo, marque os itens da declaração dos autores, aceite a licença e conclua. "
                 "Dúvidas: contato@csvet.com.br.",
                 "In the last step, tick the items of the authors' declaration, accept the licence and submit. "
                 "Questions: contato@csvet.com.br.")),
    ]
    box(doc, t("Antes de enviar: confira (apague este quadro antes de enviar)",
               "Before submitting: check (delete this box before submitting)"), body)


# ---------------------------------------------------------------- relato de caso

def case_report(t):
    name = t("Modelo de Relato de Caso (também para Série de Casos)", "Case Report template (also for Case Series)")
    doc = new_document(t, name, kind=KIND_CASE)
    how_to_use(doc, t, name, t(
        "Apresentação do caso → Discussão → Introdução → Conclusão e Pontos de aprendizado → Resumo → Título.",
        "Case presentation → Discussion → Introduction → Conclusion and Learning points → Abstract → Title."),
        extra=[("li", t("Relato de caso é para UM caso (ou surto) que ensina algo: raro, espécie incomum, "
                        "apresentação atípica, diagnóstico difícil ou tratamento novo. Três ou mais casos parecidos "
                        "formam uma Série de Casos. Uma só imagem marcante com texto curto pode ser Imagem Clínica "
                        "(modelo próprio).",
                        "A case report is about ONE case (or outbreak) that teaches something: rare, unusual species, "
                        "atypical presentation, difficult diagnosis or new treatment. Three or more related cases form "
                        "a Case Series. A single striking image with a short text can be a Clinical Image "
                        "(separate template)."))])
    limits_table(doc, t, [
        (t("Relato de caso", "Case report"), t("2.500 palavras", "2,500 words"), t("200 palavras", "200 words"), "25", "6"),
        (t("Série de casos", "Case series"), t("3.500 palavras", "3,500 words"), t("250 palavras", "250 words"), "35", "6"),
    ])

    h1(doc, t("Título", "Title"))
    guide(
        doc, t, t("Título", "Title"),
        what=t("Diga o que o caso tem de especial: a doença ou o achado, a espécie e, se for o caso, o tratamento "
               "ou a localização incomum. Termine com “: relato de caso” (ou “: série de casos”).",
               "State what is special about the case: the disease or finding, the species and, if relevant, the "
               "treatment or unusual location. End with “: a case report” (or “: a case series”)."),
        size=t("Em geral, 10 a 20 palavras, sem abreviaturas.", "Usually 10 to 20 words, no abbreviations."),
        avoid=[t("Títulos vagos, como “Um caso interessante de neoplasia”.",
                 "Vague titles such as “An interesting case of neoplasia”."),
               t("“Primeiro relato” ou “raro” sem comprovar na Discussão.",
                 "“First report” or “rare” without proving it in the Discussion.")],
        example=t("Hemangiossarcoma cardíaco com tamponamento em cão da raça Golden Retriever: relato de caso",
                  "Cardiac haemangiosarcoma with tamponade in a Golden Retriever dog: a case report"),
    )
    ph(doc, write_here(t, t("o título", "the title")))

    h1(doc, t("Resumo", "Abstract"))
    guide(
        doc, t, t("Resumo", "Abstract"),
        what=t("Um parágrafo único, sem subtítulos, que conta o caso inteiro: por que é relevante, quem é o "
               "paciente, o que se encontrou, como se chegou ao diagnóstico, o que foi feito, como terminou e a "
               "mensagem principal.",
               "A single paragraph, without subheadings, that tells the whole case: why it matters, who the patient "
               "is, what was found, how the diagnosis was reached, what was done, the outcome and the main message."),
        size=t("Até 200 palavras (série de casos: até 250).", "Up to 200 words (case series: up to 250)."),
        tips=[t("Escreva o resumo por último, com o texto pronto.", "Write the abstract last, when the text is ready."),
              t("Frases curtas, no passado, para o que aconteceu com o animal.",
                "Short sentences, in the past tense, for what happened to the animal.")],
        avoid=[t("Referências, siglas sem explicação e informação que não está no texto.",
                 "References, unexplained abbreviations and information that is not in the text.")],
        example=t("O hemangiossarcoma cardíaco é uma neoplasia maligna de prognóstico reservado em cães. Este relato "
                  "descreve um cão Golden Retriever de nove anos atendido com fraqueza aguda e mucosas pálidas. A "
                  "ecocardiografia revelou massa no átrio direito e efusão pericárdica com tamponamento. Após a "
                  "pericardiocentese, ... O diagnóstico foi confirmado pela histopatologia. O caso reforça ...",
                  "Cardiac haemangiosarcoma is a malignant neoplasm with a guarded prognosis in dogs. This report "
                  "describes a nine-year-old Golden Retriever presented with acute weakness and pale mucous membranes. "
                  "Echocardiography revealed a right atrial mass and pericardial effusion with tamponade. After "
                  "pericardiocentesis, ... Histopathology confirmed the diagnosis. This case highlights ..."),
    )
    ph(doc, write_here(t, t("o resumo, em um parágrafo", "the abstract, in one paragraph")))
    keywords(doc, t, example=t("cão; neoplasia cardíaca; efusão pericárdica; ecocardiografia; oncologia veterinária",
                               "dog; heart neoplasms; pericardial effusion; echocardiography; veterinary oncology"))

    h1(doc, t("Introdução", "Introduction"))
    guide(
        doc, t, t("Introdução", "Introduction"),
        what=[t("1º parágrafo: o que é a doença ou condição, em poucas linhas, com referências.",
                "1st paragraph: what the disease or condition is, in a few lines, with references."),
              t("2º parágrafo: o que já se sabe e o que falta saber; por que este caso acrescenta algo "
                "(raridade, espécie, apresentação, diagnóstico, tratamento).",
                "2nd paragraph: what is known and what is missing; why this case adds something "
                "(rarity, species, presentation, diagnosis, treatment)."),
              t("Última frase: o objetivo, ex.: “Este relato descreve...”.",
                "Last sentence: the aim, e.g. “This report describes...”.")],
        size=t("Cerca de 250 a 400 palavras.", "About 250 to 400 words."),
        tips=[t("Toda informação que não é sua precisa de referência: [1], [2,3].",
                "Every statement that is not yours needs a reference: [1], [2,3].")],
        avoid=[t("Revisão de literatura longa: comparar com outros casos é tarefa da Discussão.",
                 "A long literature review: comparing with other cases belongs in the Discussion."),
               t("Copiar frases de outros textos. Todo envio passa por detector de similaridade: escreva com suas palavras.",
                 "Copying sentences from other texts. Every submission is checked for similarity: use your own words.")],
        example=t("O hemangiossarcoma é uma neoplasia maligna do endotélio vascular, mais frequente em cães de raças "
                  "grandes, que acomete principalmente o baço, o átrio direito e a pele [1,2]. ... Este relato "
                  "descreve um caso de hemangiossarcoma cardíaco em cão, com ênfase no diagnóstico ecocardiográfico "
                  "e na conduta de emergência.",
                  "Haemangiosarcoma is a malignant neoplasm of the vascular endothelium, more frequent in large-breed "
                  "dogs, that mainly affects the spleen, the right atrium and the skin [1,2]. ... This report describes "
                  "a case of cardiac haemangiosarcoma in a dog, focusing on the echocardiographic diagnosis and the "
                  "emergency management."),
    )
    ph(doc, write_here(t, t("a introdução, em 2 ou 3 parágrafos", "the introduction, in 2 or 3 paragraphs")))

    h1(doc, t("Apresentação do caso", "Case presentation"))
    guide(
        doc, t, t("Apresentação do caso", "Case presentation"),
        what=t("Conte o caso em ordem cronológica, no passado, como uma história clínica organizada, nos "
               "subtítulos abaixo. Inclua só o que ajuda a entender o diagnóstico e o tratamento.",
               "Tell the case in chronological order, in the past tense, as an organised clinical history, using "
               "the subheadings below. Include only what helps to understand the diagnosis and treatment."),
        size=t("Em geral a maior parte do texto (800 a 1.200 palavras).",
               "Usually the largest part of the text (800 to 1,200 words)."),
        tips=[t("Datas: use “dia 1”, “dia 7”, “30 dias após a cirurgia”, nunca a data exata.",
                "Dates: use “day 1”, “day 7”, “30 days after surgery”, never the exact date."),
              t("Fármacos pelo nome genérico, com dose (mg/kg), via, frequência e duração.",
                "Drugs by generic name, with dose (mg/kg), route, frequency and duration."),
              t("Série de casos: descreva os casos juntos e resuma cada animal numa tabela (espécie, idade, sexo, "
                "sinais, exames, tratamento, desfecho).",
                "Case series: describe the cases together and summarise each animal in a table (species, age, sex, "
                "signs, tests, treatment, outcome)."),
              t("Ética: atendimento de rotina não precisa de CEUA. Termo de consentimento do responsável legal só "
                "se a foto ou o texto permitir identificar o animal ou o tutor (o termo do hospital-escola também vale).",
                "Ethics: routine clinical care needs no ethics committee approval. Written consent from the legal "
                "guardian is needed only if photos or text allow the animal or owner to be identified (the teaching "
                "hospital's admission form also counts).")],
        avoid=[t("Nome do animal ou do tutor, número de prontuário, nome do hospital ou da cidade.",
                 "The animal's or owner's name, record number, hospital name or city."),
               t("Interpretar e comparar com a literatura aqui: isso vai na Discussão.",
                 "Interpreting and comparing with the literature here: that goes in the Discussion.")],
    )
    box(doc, t("Caso de patologia: biópsia, peça cirúrgica ou necropsia  (apague este quadro antes de enviar)",
               "Pathology case: biopsy, surgical specimen or necropsy  (delete this box before submitting)"), [
        ("p", t("Se o caso chegou ao laboratório só como amostra ou cadáver, você não precisa ter exame clínico, "
                "tratamento nem evolução. Troque os subtítulos abaixo por estes:",
                "If the case reached the laboratory only as a sample or a carcass, you do not need a clinical "
                "examination, treatment or outcome. Replace the subheadings below with these:")),
        ("label", t("Biópsia ou peça cirúrgica", "Biopsy or surgical specimen")),
        ("li", t("Resenha e dados da requisição: espécie, raça, idade, sexo e o que veio na ficha (suspeita, "
                 "localização, tempo de evolução, tratamentos anteriores).",
                 "Signalment and submission data: species, breed, age, sex and what came on the request form "
                 "(clinical suspicion, location, duration, previous treatments).")),
        ("li", t("Material recebido: tipo de coleta (ex.: mastectomia regional, biópsia incisional), fixação e "
                 "como foi processado e corado.",
                 "Material received: type of sampling (e.g. regional mastectomy, incisional biopsy), fixation and "
                 "how it was processed and stained.")),
        ("li", t("Exame macroscópico: tamanho, cor, consistência, superfície de corte, margens.",
                 "Gross findings: size, colour, consistency, cut surface, margins.")),
        ("li", t("Exame microscópico: padrão, células, atipias, mitoses (com a área contada, em mm²), necrose, "
                 "invasão vascular ou linfática, margens.",
                 "Microscopic findings: pattern, cells, atypia, mitoses (with the area counted, in mm²), necrosis, "
                 "vascular or lymphatic invasion, margins.")),
        ("li", t("Exames complementares, se houver: histoquímica, imuno-histoquímica, PCR.",
                 "Ancillary tests, if any: histochemistry, immunohistochemistry, PCR.")),
        ("li", t("Diagnóstico e diagnóstico diferencial: com o sistema de classificação e graduação usado, com "
                 "referência (ex.: tumores mamários caninos pela classificação de Goldschmidt et al. e graduação de "
                 "Peña et al.).",
                 "Diagnosis and differential diagnosis: with the classification and grading system used, with a "
                 "reference (e.g. canine mammary tumours by the Goldschmidt et al. classification and the Peña et "
                 "al. grading).")),
        ("label", t("Necropsia", "Necropsy")),
        ("li", t("Resenha e histórico informado (pelo tutor, pelo clínico ou pela ficha), achados macroscópicos, "
                 "achados microscópicos, exames complementares e causa da morte ou diagnóstico.",
                 "Signalment and reported history (from the owner, the clinician or the form), gross findings, "
                 "microscopic findings, ancillary tests and cause of death or diagnosis.")),
        ("label", t("O que você não tem", "What you do not have")),
        ("li", t("Escreva “não disponível” (ex.: “Não havia dados de exame físico na requisição.”) e cite isso nas "
                 "limitações da Discussão. Nunca complete com suposições.",
                 "Write “not available” (e.g. “No physical examination data were provided with the request.”) and "
                 "mention it among the limitations in the Discussion. Never fill gaps with assumptions.")),
        ("li", t("Se conseguir notícia do animal com o clínico (recidiva, metástase, óbito), inclua como "
                 "acompanhamento: valoriza o relato, mas não é obrigatório.",
                 "If you can get news of the animal from the clinician (recurrence, metastasis, death), include it as "
                 "follow-up: it adds value, but it is not required.")),
        ("li", t("Figuras: macroscopia e microscopia com aumento ou barra de escala e coloração na legenda.",
                 "Figures: gross and microscopic images with magnification or scale bar and the stain in the legend.")),
        ("li", t("Doença comum (ex.: tumor de mama em cadela) só vira relato se o caso ensinar algo novo: tipo "
                 "histológico raro, localização ou espécie incomum, diagnóstico difícil. Deixe isso claro na "
                 "Introdução e nos Pontos de aprendizado.",
                 "A common disease (e.g. mammary tumour in a bitch) only makes a case report if the case teaches "
                 "something new: rare histological type, unusual location or species, difficult diagnosis. Make "
                 "this clear in the Introduction and the Learning points.")),
    ], fill=WARN_FILL, line=WARN_LINE)
    sub = [
        (t("Resenha e histórico", "Signalment and history"),
         t("espécie, raça, idade, sexo (castrado ou não), peso; queixa principal, há quanto tempo e histórico relevante "
           "(vacinação, alimentação, ambiente, doenças e tratamentos anteriores)",
           "species, breed, age, sex (neutered or not), weight; chief complaint, for how long, and relevant history "
           "(vaccination, diet, environment, previous diseases and treatments)"),
         t("Um cão macho, não castrado, da raça Golden Retriever, de nove anos e 34 kg, foi atendido com histórico de "
           "fraqueza súbita e um episódio de síncope havia 12 horas.",
           "A nine-year-old, 34-kg, intact male Golden Retriever was presented with sudden weakness and one episode "
           "of syncope 12 hours earlier.")),
        (t("Exame clínico", "Clinical examination"),
         t("achados do exame físico, com valores e unidades; primeiro o que estava alterado",
           "physical examination findings, with values and units; abnormal findings first"),
         t("Ao exame físico, o animal apresentava mucosas pálidas, tempo de preenchimento capilar de 3 s, frequência "
           "cardíaca de 180 batimentos por minuto, pulso femoral fraco e bulhas cardíacas abafadas.",
           "On physical examination, the dog had pale mucous membranes, a capillary refill time of 3 s, a heart rate "
           "of 180 beats per minute, weak femoral pulses and muffled heart sounds.")),
        (t("Exames complementares", "Diagnostic investigation"),
         t("exames de laboratório e de imagem, com valores e intervalos de referência; muitos valores vão numa "
           "tabela (Tabela 1); cite as figuras (Figura 1)",
           "laboratory and imaging tests, with values and reference intervals; many values go in a table (Table 1); "
           "cite the figures (Figure 1)"),
         t("O hemograma revelou anemia regenerativa (hematócrito de 28%; referência: 37–55%) (Tabela 1). A "
           "ecocardiografia evidenciou massa hiperecoica no átrio direito e efusão pericárdica com colapso diastólico "
           "do átrio direito (Figura 1).",
           "Haematology revealed regenerative anaemia (haematocrit 28%; reference interval 37–55%) (Table 1). "
           "Echocardiography showed a hyperechoic right atrial mass and pericardial effusion with diastolic collapse "
           "of the right atrium (Figure 1).")),
    ]
    for title, hint, ex in sub:
        h2(doc, title)
        box(doc, t(f"Orientação · {title}  (apague este quadro antes de enviar)",
                   f"Guidance · {title}  (delete this box before submitting)"),
            [("p", hint[0].upper() + hint[1:] + "."),
             ("label", t("Exemplo (fictício)", "Example (fictional)")), ("ex", ex)])
        ph(doc, write_here(t, hint))

    table_guide(doc, t)
    example_table(doc, t, 1,
                  [t("Exame", "Test"), t("Resultado", "Result"), t("Intervalo de referência", "Reference interval")],
                  [(t("Hematócrito (%)", "Haematocrit (%)"), "28", "37–55"),
                   (t("Plaquetas (×10³/µL)", "Platelets (×10³/µL)"), "95", "200–500"),
                   (t("[exame]", "[test]"), t("[valor]", "[value]"), t("[referência]", "[reference]"))])
    figure_guide(doc, t)
    example_figure(doc, t, 1, t(
        "legenda autoexplicativa: tipo de imagem, projeção ou plano, o que mostra cada painel e cada seta. Ex.: "
        "“Ecocardiografia, corte paraesternal direito, cão. (A) Massa no átrio direito (seta). (B) Efusão "
        "pericárdica (asterisco).”",
        "self-explanatory legend: type of image, view or plane, what each panel and arrow shows. E.g. "
        "“Echocardiography, right parasternal view, dog. (A) Right atrial mass (arrow). (B) Pericardial "
        "effusion (asterisk).”"))

    for title, hint, ex in [
        (t("Diagnóstico diferencial e diagnóstico", "Differential diagnosis and diagnosis"),
         t("as doenças consideradas e por que foram descartadas; como o diagnóstico definitivo foi confirmado "
           "(histopatologia, citologia, cultura, PCR, imagem, necropsia). Sem confirmação, o caso não pode ser "
           "publicado",
           "the diseases considered and why they were ruled out; how the final diagnosis was confirmed "
           "(histopathology, cytology, culture, PCR, imaging, necropsy). Without confirmation, the case cannot "
           "be published"),
         t("Os diagnósticos diferenciais incluíram quemodectoma, mesotelioma e efusão pericárdica idiopática. O exame "
           "histopatológico da massa, obtida na necropsia, confirmou hemangiossarcoma (Figura 2).",
           "The differential diagnoses included chemodectoma, mesothelioma and idiopathic pericardial effusion. "
           "Histopathology of the mass, obtained at necropsy, confirmed haemangiosarcoma (Figure 2).")),
        (t("Tratamento e evolução", "Treatment and outcome"),
         t("o que foi feito (fármaco, dose, via, frequência, duração), como o animal respondeu e o desfecho (alta, "
           "óbito, eutanásia, tempo de acompanhamento); em casos longos, uma linha do tempo ajuda. Caso de patologia "
           "sem acompanhamento: escreva “não disponível” ou apague este subtítulo",
           "what was done (drug, dose, route, frequency, duration), how the animal responded and the outcome "
           "(discharge, death, euthanasia, follow-up time); in long cases, a timeline helps. Pathology case without "
           "follow-up: write “not available” or delete this subheading"),
         t("Foi realizada pericardiocentese guiada por ultrassom, com drenagem de 180 mL de líquido sanguinolento e "
           "melhora imediata do pulso. ... No dia 21, o animal apresentou novo tamponamento e o tutor optou pela "
           "eutanásia.",
           "Ultrasound-guided pericardiocentesis drained 180 mL of haemorrhagic fluid, with immediate improvement of "
           "the pulse. ... On day 21, tamponade recurred and the owner elected euthanasia.")),
    ]:
        h2(doc, title)
        box(doc, t(f"Orientação · {title}  (apague este quadro antes de enviar)",
                   f"Guidance · {title}  (delete this box before submitting)"),
            [("p", hint[0].upper() + hint[1:] + "."),
             ("label", t("Exemplo (fictício)", "Example (fictional)")), ("ex", ex)])
        ph(doc, write_here(t, hint.split(";")[0]))

    h1(doc, t("Discussão", "Discussion"))
    guide(
        doc, t, t("Discussão", "Discussion"),
        what=[t("A mensagem principal do caso, em uma ou duas frases.", "The main message of the case, in one or two sentences."),
              t("Compare com os casos publicados: o que foi igual e o que foi diferente (sinais, exames, tratamento, "
                "evolução), com referências.",
                "Compare with published cases: what was similar and what was different (signs, tests, treatment, "
                "outcome), with references."),
              t("Explique os achados e as decisões tomadas.", "Explain the findings and the decisions made."),
              t("Limitações: o que não foi feito ou confirmado, e por quê.",
                "Limitations: what was not done or confirmed, and why.")],
        size=t("Cerca de 600 a 900 palavras.", "About 600 to 900 words."),
        tips=[t("Comece cada parágrafo pela ideia principal e sustente com a evidência.",
                "Start each paragraph with its main idea and support it with evidence."),
              t("Ser honesto nas limitações conta a favor: os revisores valorizam isso.",
                "Being honest about limitations counts in your favour: reviewers value it.")],
        avoid=[t("Repetir a apresentação do caso.", "Repeating the case presentation."),
               t("Concluir o que um caso não permite (um caso não prova que um tratamento funciona).",
                 "Concluding what a single case cannot show (one case does not prove a treatment works)."),
               t("Dizer “primeiro relato” sem buscar em pelo menos PubMed, CAB Abstracts e SciELO; diga onde e "
                 "quando buscou.",
                 "Claiming a “first report” without searching at least PubMed, CAB Abstracts and SciELO; state "
                 "where and when you searched.")],
        example=t("Neste caso, o tamponamento cardíaco foi a primeira manifestação da neoplasia, como descrito em "
                  "outros relatos [5,6]. Diferentemente de [7], ... Uma limitação deste relato é que não foi possível "
                  "realizar tomografia para pesquisa de metástases.",
                  "In this case, cardiac tamponade was the first sign of the neoplasm, as described in other reports "
                  "[5,6]. Unlike [7], ... A limitation of this report is that computed tomography to search for "
                  "metastases could not be performed."),
    )
    ph(doc, write_here(t, t("a discussão", "the discussion")))

    h1(doc, t("Conclusão", "Conclusion"))
    guide(
        doc, t, t("Conclusão", "Conclusion"),
        what=t("Uma a três frases com a mensagem do caso, sem repetir resultados e sem ir além do que o caso mostra.",
               "One to three sentences with the message of the case, without repeating results and without going "
               "beyond what the case shows."),
        example=t("O hemangiossarcoma cardíaco deve ser incluído no diagnóstico diferencial de cães de raças grandes "
                  "com efusão pericárdica, e a ecocardiografia é decisiva para o diagnóstico e a conduta de emergência.",
                  "Cardiac haemangiosarcoma should be included in the differential diagnosis of large-breed dogs with "
                  "pericardial effusion, and echocardiography is decisive for diagnosis and emergency management."),
    )
    ph(doc, write_here(t, t("a conclusão", "the conclusion")))

    h1(doc, t("Pontos de aprendizado", "Learning points"))
    guide(
        doc, t, t("Pontos de aprendizado", "Learning points"),
        what=t("3 a 5 tópicos curtos (uma frase cada) com o que o leitor deve levar para a prática clínica ou "
               "diagnóstica.",
               "3 to 5 short bullet points (one sentence each) with what the reader should take to clinical or "
               "diagnostic practice."),
        example=t("Bulhas abafadas e pulso fraco em cão idoso de raça grande sugerem efusão pericárdica.",
                  "Muffled heart sounds and weak pulses in an older large-breed dog suggest pericardial effusion."),
    )
    for n in range(3):
        ph(doc, "• " + write_here(t, t(f"ponto de aprendizado {n + 1}", f"learning point {n + 1}")))

    declarations(doc, t, [
        ("p", t("Relato de atendimento de rotina (sem procedimento feito para a pesquisa) dispensa aprovação de comissão "
                "de ética. Escolha a frase que se aplica:",
                "A report of routine clinical care (no procedure done for research) needs no ethics committee approval. "
                "Choose the statement that applies:")),
        ("li", t("“O material não permite identificar o animal nem o seu responsável legal; por isso, o termo de "
                 "consentimento foi dispensado.”",
                 "“The material does not allow the animal or its legal guardian to be identified; therefore, consent "
                 "for publication was not required.”")),
        ("li", t("“O responsável legal deu consentimento por escrito para a publicação.”",
                 "“The legal guardian gave written consent for publication.”")),
        ("li", t("“O termo institucional assinado no atendimento autoriza o uso dos dados em ensino e pesquisa.”",
                 "“The institutional admission form signed at the visit authorises the use of the data for teaching "
                 "and research.”")),
    ], t("Para relato de caso, em geral: “The data are available from the corresponding author upon reasonable request.”",
         "For case reports, usually: “The data are available from the corresponding author upon reasonable request.”"))
    references(doc, t, t("Até 25 referências (série de casos: até 35).", "Up to 25 references (case series: up to 35)."))
    final_check(doc, t, [
        t("Relato: até 2.500 palavras, resumo até 200 e até 25 referências (série: 3.500, 250 e 35); até 6 figuras e "
          "tabelas somadas.",
          "Case report: up to 2,500 words, abstract up to 200 and up to 25 references (series: 3,500, 250 and 35); up "
          "to 6 figures and tables in total."),
        t("O diagnóstico foi confirmado por exame adequado e isso está escrito no texto.",
          "The diagnosis was confirmed by an appropriate test and this is stated in the text."),
    ], t("Relatos e Séries de Casos", "Case Reports and Case Series"),
        t("Checklist CARE preenchido, com a página ou linha de cada item (care-statement.org); em caso de patologia, "
          "“não se aplica” nos itens clínicos que o laboratório não recebeu.",
          "Completed CARE checklist, with the page or line of each item (care-statement.org); in a pathology case, "
          "“not applicable” on the clinical items the laboratory did not receive."))
    return doc


# ---------------------------------------------------------------- artigo original

def original_article(t):
    name = t("Modelo de Artigo Original (também para Comunicação Curta e Método)",
             "Original Article template (also for Short Communication and Method)")
    doc = new_document(t, name, kind=KIND_ORIGINAL)
    how_to_use(doc, t, name, t(
        "Material e Métodos → Resultados (com tabelas e figuras) → Discussão → Introdução → Conclusões → Resumo → Título.",
        "Materials and Methods → Results (with tables and figures) → Discussion → Introduction → Conclusions → "
        "Abstract → Title."),
        extra=[("li", t("Comunicação Curta e Método/Nota Técnica usam as mesmas seções, de forma mais curta, e o "
                        "resumo em um parágrafo único (sem subtítulos).",
                        "Short Communications and Methods/Technical Notes use the same sections, in a shorter form, "
                        "and a single-paragraph abstract (no subheadings)."))])
    limits_table(doc, t, [
        (t("Artigo original", "Original article"), t("6.000 palavras", "6,000 words"),
         t("estruturado, 300 palavras", "structured, 300 words"), "60", "8"),
        (t("Comunicação curta", "Short communication"), t("2.500 palavras", "2,500 words"),
         t("parágrafo único, 200", "single paragraph, 200"), "25", "3"),
        (t("Método / Nota técnica", "Method / Technical note"), t("3.500 palavras", "3,500 words"),
         t("parágrafo único, 200", "single paragraph, 200"), "30", "5"),
    ])

    h1(doc, t("Título", "Title"))
    guide(
        doc, t, t("Título", "Title"),
        what=t("Diga o que foi estudado, em qual espécie e onde; se possível, o delineamento (“: estudo transversal”) "
               "ou o principal resultado.",
               "State what was studied, in which species and where; if possible, the design (“: a cross-sectional "
               "study”) or the main finding."),
        size=t("Em geral, 12 a 20 palavras, sem abreviaturas.", "Usually 12 to 20 words, no abbreviations."),
        avoid=[t("Títulos genéricos (“Estudo sobre erliquiose”) ou que prometem mais do que o estudo mostra.",
                 "Generic titles (“A study on ehrlichiosis”) or titles that promise more than the study shows.")],
        example=t("Ocorrência de Ehrlichia canis e fatores associados em cães atendidos em um hospital veterinário do "
                  "Sudeste do Brasil: estudo transversal",
                  "Occurrence of Ehrlichia canis and associated factors in dogs attending a veterinary hospital in "
                  "south-eastern Brazil: a cross-sectional study"),
    )
    ph(doc, write_here(t, t("o título", "the title")))

    h1(doc, t("Resumo", "Abstract"))
    guide(
        doc, t, t("Resumo", "Abstract"),
        what=t("Artigo original: resumo estruturado nos cinco itens abaixo. Comunicação curta e Método: apague os "
               "rótulos e escreva um parágrafo único com as mesmas informações.",
               "Original article: structured abstract with the five items below. Short communication and Method: "
               "delete the labels and write a single paragraph with the same information."),
        size=t("Até 300 palavras (comunicação curta e método: até 200).",
               "Up to 300 words (short communication and method: up to 200)."),
        tips=[t("Resultados com números: n, %, intervalo de confiança de 95% e valor de P.",
                "Results with numbers: n, %, 95% confidence interval and P value."),
              t("Escreva por último. O resumo é lido por muito mais gente do que o artigo inteiro.",
                "Write it last. The abstract is read by many more people than the whole article.")],
        avoid=[t("Referências, siglas sem explicação e frases como “os resultados serão discutidos”.",
                 "References, unexplained abbreviations and sentences such as “the results will be discussed”.")],
        example=t("Resultados: Dos 300 cães avaliados, 54 (18,0%; IC 95%: 13,8–22,8) foram positivos na PCR. A "
                  "infestação por carrapatos esteve associada à infecção (OR 3,1; IC 95%: 1,6–5,9; P < 0,001).",
                  "Results: Of the 300 dogs tested, 54 (18.0%; 95% CI 13.8–22.8) were PCR-positive. Tick "
                  "infestation was associated with infection (OR 3.1; 95% CI 1.6–5.9; P < 0.001)."),
    )
    for label, hint in [
        (t("Contexto:", "Background:"), t("por que o tema importa, em 1 ou 2 frases", "why the topic matters, in 1 or 2 sentences")),
        (t("Objetivos:", "Objectives:"), t("o que o estudo quis responder", "what the study set out to answer")),
        (t("Métodos:", "Methods:"), t("delineamento, animais (quantos, de onde), o que foi medido e como foi analisado",
                                      "design, animals (how many, from where), what was measured and how it was analysed")),
        (t("Resultados:", "Results:"), t("os principais resultados, com números", "the main results, with numbers")),
        (t("Conclusões:", "Conclusions:"), t("a resposta ao objetivo e a importância prática",
                                             "the answer to the objective and its practical relevance")),
    ]:
        labelled(doc, label, write_here(t, hint))
    keywords(doc, t, example=t("erliquiose; cães; PCR; carrapatos; epidemiologia",
                               "ehrlichiosis; dogs; PCR; ticks; epidemiology"))

    h1(doc, t("Introdução", "Introduction"))
    guide(
        doc, t, t("Introdução", "Introduction"),
        what=[t("Do geral para o específico: o problema e sua importância (saúde animal, Saúde Única, produção).",
                "From general to specific: the problem and why it matters (animal health, One Health, production)."),
              t("O que já se sabe, com referências, e a lacuna: o que ainda não se sabe e que o seu estudo responde.",
                "What is known, with references, and the gap: what is still unknown and your study answers."),
              t("Último parágrafo: o objetivo (e a hipótese, se houver).",
                "Last paragraph: the objective (and the hypothesis, if any).")],
        size=t("Cerca de 500 a 800 palavras (comunicação curta: 200 a 400).",
               "About 500 to 800 words (short communication: 200 to 400)."),
        avoid=[t("Contar a história da doença desde a descoberta, ou listar dados sem ligação com o objetivo.",
                 "Telling the history of the disease since its discovery, or listing facts unrelated to the objective.")],
        example=t("... No entanto, não há dados recentes sobre a ocorrência da infecção em cães da região. O objetivo "
                  "deste estudo foi estimar a ocorrência de E. canis em cães atendidos em um hospital veterinário e "
                  "identificar fatores associados à infecção.",
                  "... However, there are no recent data on the occurrence of the infection in dogs in the region. "
                  "The objective of this study was to estimate the occurrence of E. canis in dogs attending a "
                  "veterinary hospital and to identify factors associated with infection."),
    )
    ph(doc, write_here(t, t("a introdução", "the introduction")))

    h1(doc, t("Material e Métodos", "Materials and Methods"))
    guide(
        doc, t, t("Material e Métodos", "Materials and Methods"),
        what=t("Tudo o que foi feito, no passado, com detalhe suficiente para outro pesquisador repetir o estudo. "
               "Use os subtítulos abaixo e acrescente outros se precisar.",
               "Everything that was done, in the past tense, in enough detail for another researcher to repeat the "
               "study. Use the subheadings below and add others if needed."),
        tips=[t("Siga a diretriz de relato do seu tipo de estudo e envie o checklist preenchido: ARRIVE 2.0 "
                "(experimentos com animais), STROBE-Vet (observacionais), CONSORT ou REFLECT (ensaios), STARD "
                "(testes diagnósticos). Estão em equator-network.org.",
                "Follow the reporting guideline for your study type and submit the completed checklist: ARRIVE 2.0 "
                "(animal experiments), STROBE-Vet (observational), CONSORT or REFLECT (trials), STARD (diagnostic "
                "tests). They are at equator-network.org."),
              t("Equipamentos e reagentes com fabricante, cidade e país; doses em mg/kg; unidades do SI.",
                "Equipment and reagents with manufacturer, city and country; doses in mg/kg; SI units."),
              t("Método já publicado: cite a referência e descreva só o que mudou.",
                "A method already published: cite it and describe only what was changed.")],
        avoid=[t("Resultados nesta seção.", "Results in this section."),
               t("Nome da instituição ou da cidade: escreva “um hospital veterinário universitário no Sudeste do Brasil”.",
                 "Name of the institution or city: write “a university veterinary hospital in south-eastern Brazil”.")],
    )
    for title, hint, ex in [
        (t("Aprovação ética", "Ethics approval"),
         t("a comissão, o número do protocolo e a data, como [blinded] neste arquivo; com pessoas (questionários a "
           "tutores ou produtores), também o comitê de ética em pesquisa (CEP/CAAE)",
           "the committee, protocol number and date, as [blinded] in this file; with people (questionnaires to "
           "owners or farmers), also the human research ethics committee"),
         t("O estudo foi aprovado pela Comissão de Ética no Uso de Animais da [blinded] (protocolo nº [blinded]). "
           "Os tutores assinaram termo de consentimento livre e esclarecido.",
           "The study was approved by the Animal Ethics Committee of [blinded] (protocol no. [blinded]). The owners "
           "signed an informed consent form.")),
        (t("Delineamento, local e período", "Study design, setting and period"),
         t("o tipo de estudo, a região e o período", "the study type, the region and the period"),
         t("Estudo transversal realizado entre março de 2024 e fevereiro de 2025 em um hospital veterinário "
           "universitário no Sudeste do Brasil.",
           "Cross-sectional study carried out between March 2024 and February 2025 at a university veterinary "
           "hospital in south-eastern Brazil.")),
        (t("Animais", "Animals"),
         t("quantos, como foram escolhidos, o cálculo do tamanho da amostra, os critérios de inclusão e exclusão; nos "
           "experimentos, alojamento, 3Rs, anestesia, analgesia e eutanásia",
           "how many, how they were selected, the sample size calculation, inclusion and exclusion criteria; in "
           "experiments, housing, 3Rs, anaesthesia, analgesia and euthanasia"),
         t("O tamanho da amostra (n = 300) foi calculado para prevalência esperada de 20%, erro de 5% e confiança de "
           "95% [8]. Foram incluídos cães com mais de seis meses ...",
           "Sample size (n = 300) was calculated for an expected prevalence of 20%, 5% error and 95% confidence [8]. "
           "Dogs older than six months were included ...")),
        (t("Procedimentos", "Procedures"),
         t("coleta de amostras, exames e técnicas, com equipamentos e reagentes",
           "sample collection, tests and techniques, with equipment and reagents"),
         t("Amostras de sangue (2 mL) foram colhidas por venopunção jugular em tubos com EDTA. O DNA foi extraído com "
           "kit comercial (nome, fabricante, cidade, país) ...",
           "Blood samples (2 mL) were collected by jugular venepuncture into EDTA tubes. DNA was extracted with a "
           "commercial kit (name, manufacturer, city, country) ...")),
        (t("Análise estatística", "Statistical analysis"),
         t("o teste usado em cada análise, o software com a versão, o nível de significância e como os pressupostos "
           "foram verificados; peça ajuda ao orientador ou a um estatístico",
           "the test used in each analysis, the software and version, the significance level and how assumptions "
           "were checked; ask your supervisor or a statistician for help"),
         t("A associação entre as variáveis e a infecção foi avaliada por regressão logística no R (versão 4.4.1). "
           "Considerou-se significativo P < 0,05.",
           "The association between variables and infection was assessed by logistic regression in R (version "
           "4.4.1). P < 0.05 was considered significant.")),
    ]:
        h2(doc, title)
        box(doc, t(f"Orientação · {title}  (apague este quadro antes de enviar)",
                   f"Guidance · {title}  (delete this box before submitting)"),
            [("p", hint[0].upper() + hint[1:] + "."),
             ("label", t("Exemplo (fictício)", "Example (fictional)")), ("ex", ex)])
        ph(doc, write_here(t, hint.split(";")[0]))

    h1(doc, t("Resultados", "Results"))
    guide(
        doc, t, t("Resultados", "Results"),
        what=t("O que foi encontrado, na mesma ordem dos Métodos, sem interpretar e sem comparar com a literatura.",
               "What was found, in the same order as the Methods, without interpreting or comparing with the literature."),
        tips=[t("Números com unidades; tamanho do efeito com intervalo de confiança de 95% e P exato (P = 0,032; "
                "P < 0,001), não só “significativo”.",
                "Numbers with units; effect size with 95% confidence interval and exact P (P = 0.032; P < 0.001), "
                "not just “significant”."),
              t("Cite cada tabela e figura no texto: (Tabela 1), (Figura 1).",
                "Cite each table and figure in the text: (Table 1), (Figure 1)."),
              t("Relate também resultados negativos ou inesperados.", "Report negative or unexpected results too.")],
        avoid=[t("Repetir no texto todos os números das tabelas.", "Repeating every number from the tables in the text."),
               t("Frases de interpretação (“isso mostra que...”): guarde para a Discussão.",
                 "Interpretation (“this shows that...”): keep it for the Discussion.")],
        example=t("Dos 300 cães incluídos, 54 (18,0%; IC 95%: 13,8–22,8) foram positivos na PCR (Tabela 1). A "
                  "positividade foi maior em cães com infestação por carrapatos (OR 3,1; IC 95%: 1,6–5,9; P < 0,001).",
                  "Of the 300 dogs included, 54 (18.0%; 95% CI 13.8–22.8) were PCR-positive (Table 1). Positivity "
                  "was higher in dogs with tick infestation (OR 3.1; 95% CI 1.6–5.9; P < 0.001)."),
    )
    ph(doc, write_here(t, t("os resultados", "the results")))
    table_guide(doc, t)
    example_table(doc, t, 1,
                  [t("Variável", "Variable"), t("Positivos/total (%)", "Positive/total (%)"),
                   t("OR (IC 95%)", "OR (95% CI)"), "P"],
                  [(t("Carrapatos: sim", "Ticks: yes"), t("38/120 (31,7)", "38/120 (31.7)"),
                    t("3,1 (1,6–5,9)", "3.1 (1.6–5.9)"), t("< 0,001", "< 0.001")),
                   (t("Carrapatos: não", "Ticks: no"), t("16/180 (8,9)", "16/180 (8.9)"),
                    t("1 (referência)", "1 (reference)"), "–"),
                   (t("[variável]", "[variable]"), t("[valor]", "[value]"), t("[valor]", "[value]"), t("[valor]", "[value]"))])
    figure_guide(doc, t)
    example_figure(doc, t, 1, t(
        "legenda autoexplicativa: o que o gráfico mostra, em quem, e o que significam barras, símbolos e asteriscos",
        "self-explanatory legend: what the graph shows, in whom, and what bars, symbols and asterisks mean"))

    h1(doc, t("Discussão", "Discussion"))
    guide(
        doc, t, t("Discussão", "Discussion"),
        what=[t("1º parágrafo: o principal resultado, respondendo ao objetivo.",
                "1st paragraph: the main result, answering the objective."),
              t("Compare com outros estudos: onde concorda, onde diverge e por quê (população, método, região).",
                "Compare with other studies: where they agree, where they differ and why (population, method, region)."),
              t("Implicações práticas: o que muda para o veterinário, o produtor ou a saúde pública.",
                "Practical implications: what changes for veterinarians, farmers or public health."),
              t("Limitações do estudo e o que estudos futuros devem fazer.",
                "Limitations of the study and what future studies should do.")],
        size=t("Cerca de 1.000 a 1.500 palavras (comunicação curta: 500 a 800).",
               "About 1,000 to 1,500 words (short communication: 500 to 800)."),
        avoid=[t("Repetir os resultados com todos os números.", "Repeating the results with all the numbers."),
               t("Conclusões que os dados não sustentam, ou associação apresentada como causa.",
                 "Conclusions the data do not support, or association presented as causation.")],
        example=t("A ocorrência de E. canis observada (18,0%) foi semelhante à relatada em outras regiões tropicais "
                  "[12,13], mas inferior à de estudos com cães de abrigo [14], o que pode refletir ...",
                  "The occurrence of E. canis observed (18.0%) was similar to that reported in other tropical regions "
                  "[12,13] but lower than in studies of shelter dogs [14], which may reflect ..."),
    )
    ph(doc, write_here(t, t("a discussão", "the discussion")))

    h1(doc, t("Conclusões", "Conclusions"))
    guide(
        doc, t, t("Conclusões", "Conclusions"),
        what=t("Respondem ao objetivo, em um parágrafo curto, sem números novos e sem exagero.",
               "Answer the objective, in one short paragraph, with no new numbers and no overstatement."),
        example=t("A infecção por E. canis é frequente nos cães atendidos e está associada à infestação por "
                  "carrapatos, o que reforça a importância do controle de ectoparasitas.",
                  "E. canis infection is frequent in the dogs attended and is associated with tick infestation, "
                  "which reinforces the importance of ectoparasite control."),
    )
    ph(doc, write_here(t, t("as conclusões", "the conclusions")))

    declarations(doc, t, [
        ("p", t("Com animais: comissão de ética (CEUA ou equivalente), protocolo e data, como [blinded] neste arquivo "
                "(números reais na folha de rosto). Com animais que têm responsável (tutor, produtor, abrigo): o "
                "consentimento por escrito. Questionários com pessoas: CEP e CAAE. Fauna silvestre: SISBIO; "
                "patrimônio genético: SisGen.",
                "With animals: ethics committee, protocol number and date, as [blinded] in this file (real numbers on "
                "the title page). With animals that have a legal guardian (owner, farmer, shelter): written consent. "
                "Questionnaires with people: human research ethics approval. Wildlife: collection permits.")),
    ], t("Exemplos: “The data that support the findings of this study are openly available in [repository] at "
         "https://doi.org/[DOI].” ou “The data are available in the supplementary material of this article.”",
         "Examples: “The data that support the findings of this study are openly available in [repository] at "
         "https://doi.org/[DOI].” or “The data are available in the supplementary material of this article.”"))
    references(doc, t, t("Até 60 referências (comunicação curta: 25; método: 30).",
                         "Up to 60 references (short communication: 25; method: 30)."))
    final_check(doc, t, [
        t("Dentro dos limites do tipo de artigo (tabela no início deste modelo).",
          "Within the limits for the article type (table at the start of this template)."),
        t("Resultados com intervalo de confiança de 95% e valores de P exatos; software estatístico com versão.",
          "Results with 95% confidence intervals and exact P values; statistical software with version."),
    ], t("Artigos Originais (ou Comunicações Curtas, ou Métodos e Notas Técnicas)",
         "Original Research Articles (or Short Communications, or Methods and Technical Notes)"),
        t("Checklist da diretriz de relato (ARRIVE 2.0, STROBE-Vet, CONSORT/REFLECT ou STARD), com a página ou linha "
          "de cada item.",
          "Reporting guideline checklist (ARRIVE 2.0, STROBE-Vet, CONSORT/REFLECT or STARD), with the page or line "
          "of each item."))
    return doc


# ---------------------------------------------------------------- revisão

def review(t):
    name = t("Modelo de Revisão (narrativa, mini-revisão, sistemática ou de escopo)",
             "Review template (narrative, mini-review, systematic or scoping)")
    doc = new_document(t, name, kind=KIND_REVIEW)
    box(doc, t("Atenção antes de começar (apague este quadro antes de enviar)",
               "Read this before you start (delete this box before submitting)"), [
        ("li", t("Revisão narrativa e mini-revisão só são aceitas por convite ou após consulta aos editores. Antes "
                 "de escrever, mande para contato@csvet.com.br: o título, um roteiro de 300 palavras, o que a sua "
                 "revisão acrescenta às revisões que já existem e a experiência dos autores no tema.",
                 "Narrative reviews and mini-reviews are accepted only by invitation or after consulting the editors. "
                 "Before writing, send to contato@csvet.com.br: the title, a 300-word outline, what your review adds "
                 "to existing reviews and the authors' experience in the topic.")),
        ("li", t("A revisão é classificada pelo método, não pelo título. Uma “revisão de literatura” sem método de "
                 "busca descrito é uma revisão narrativa.",
                 "A review is classified by its method, not its title. A “literature review” without a described "
                 "search method is a narrative review.")),
        ("li", t("Revisão sistemática exige protocolo registrado ANTES de extrair os dados (OSF Registries, PROSPERO ou "
                 "SYREAF), busca em pelo menos duas bases e dois revisores independentes. A última busca não pode ter "
                 "mais de 12 meses na submissão.",
                 "A systematic review requires a protocol registered BEFORE data extraction (OSF Registries, PROSPERO "
                 "or SYREAF), a search of at least two databases and two independent reviewers. The last search must "
                 "be less than 12 months old at submission.")),
        ("li", t("Detalhes de cada tipo: Anexo A das Instruções aos Autores.",
                 "Details for each type: Appendix A of the Guide for Authors.")),
    ], fill=WARN_FILL, line=WARN_LINE)
    how_to_use(doc, t, name, t(
        "Métodos (como buscou) → Desenvolvimento por temas → Discussão e lacunas → Introdução → Conclusões → Resumo → Título.",
        "Methods (how you searched) → Body by topics → Discussion and gaps → Introduction → Conclusions → Abstract → Title."))
    limits_table(doc, t, [
        (t("Revisão narrativa", "Narrative review"), t("6.000 palavras", "6,000 words"), t("parágrafo único, 250", "single paragraph, 250"), "100", "6"),
        (t("Mini-revisão", "Mini-review"), t("3.000 palavras", "3,000 words"), t("parágrafo único, 250", "single paragraph, 250"), "50", "3"),
        (t("Sistemática / meta-análise", "Systematic / meta-analysis"), t("8.000 palavras", "8,000 words"), t("estruturado, 300", "structured, 300"), "120", "10"),
        (t("Revisão de escopo", "Scoping review"), t("7.000 palavras", "7,000 words"), t("estruturado, 300", "structured, 300"), "150", "10"),
    ])

    h1(doc, t("Título", "Title"))
    guide(
        doc, t, t("Título", "Title"),
        what=t("O tema, a espécie (se for o caso) e o tipo de revisão no fim.",
               "The topic, the species (if relevant) and the review type at the end."),
        example=t("Leishmaniose visceral canina no Brasil: avanços no diagnóstico e no controle — revisão narrativa",
                  "Canine visceral leishmaniasis in Brazil: advances in diagnosis and control — a narrative review"),
    )
    ph(doc, write_here(t, t("o título, terminando com o tipo de revisão", "the title, ending with the review type")))

    h1(doc, t("Resumo", "Abstract"))
    guide(
        doc, t, t("Resumo", "Abstract"),
        what=[t("Narrativa e mini-revisão: um parágrafo único com o tema, por que importa, como a literatura foi "
                "buscada, os principais pontos e a conclusão.",
                "Narrative and mini-review: a single paragraph with the topic, why it matters, how the literature was "
                "searched, the main points and the conclusion."),
              t("Sistemática e escopo: estruturado em Contexto, Objetivos, Métodos (bases, data da busca, critérios, "
                "registro do protocolo), Resultados (número de estudos incluídos e achados) e Conclusões.",
                "Systematic and scoping: structured into Background, Objectives, Methods (databases, search date, "
                "criteria, protocol registration), Results (number of studies included and findings) and Conclusions.")],
        size=t("Até 250 palavras (sistemática e escopo: até 300).", "Up to 250 words (systematic and scoping: up to 300)."),
    )
    ph(doc, write_here(t, t("o resumo", "the abstract")))
    keywords(doc, t, example=t("Leishmania infantum; cães; diagnóstico; zoonoses; Saúde Única",
                               "Leishmania infantum; dogs; diagnosis; zoonoses; One Health"))

    h1(doc, t("Introdução", "Introduction"))
    guide(
        doc, t, t("Introdução", "Introduction"),
        what=[t("O tema e por que importa agora.", "The topic and why it matters now."),
              t("Obrigatório: cite as revisões recentes sobre o mesmo tema e explique o que a sua acrescenta.",
                "Mandatory: cite recent reviews on the same topic and explain what yours adds."),
              t("O objetivo. Sistemática: a pergunta no formato PICO/PECO (população, intervenção ou exposição, "
                "comparação, desfecho). Escopo: PCC (população, conceito, contexto).",
                "The objective. Systematic: the question in PICO/PECO format (population, intervention or exposure, "
                "comparator, outcome). Scoping: PCC (population, concept, context).")],
        size=t("Cerca de 400 a 700 palavras.", "About 400 to 700 words."),
    )
    ph(doc, write_here(t, t("a introdução", "the introduction")))

    h1(doc, t("Métodos (busca da literatura)", "Methods (literature search)"))
    guide(
        doc, t, t("Métodos", "Methods"),
        what=[t("Narrativa e mini-revisão: um parágrafo com as bases ou fontes (ex.: PubMed, CAB Abstracts, SciELO), o "
                "período, os termos de busca e os critérios para incluir os trabalhos.",
                "Narrative and mini-review: one paragraph with the databases or sources (e.g. PubMed, CAB Abstracts, "
                "SciELO), the period, the search terms and the criteria for including studies."),
              t("Sistemática: registro do protocolo (nome, número, data); critérios de elegibilidade (PICO/PECO); pelo "
                "menos duas bases mais literatura cinzenta; estratégia completa de cada base como material "
                "suplementar; data da última busca; seleção e extração por dois revisores independentes; ferramenta "
                "de risco de viés (RoB 2, ROBINS-I, SYRCLE, QUADAS-2); método de síntese ou meta-análise; GRADE.",
                "Systematic: protocol registration (name, number, date); eligibility criteria (PICO/PECO); at least "
                "two databases plus grey literature; full strategy for each database as supplementary material; date "
                "of the last search; selection and extraction by two independent reviewers; risk-of-bias tool (RoB 2, "
                "ROBINS-I, SYRCLE, QUADAS-2); synthesis or meta-analysis method; GRADE.")],
        example=t("A busca foi feita nas bases PubMed, CAB Abstracts e SciELO, para o período de 2015 a 2025, com os "
                  "termos (“canine visceral leishmaniasis” OR “Leishmania infantum”) AND (diagnosis OR control). Foram "
                  "incluídos artigos originais e revisões em inglês, português ou espanhol ...",
                  "PubMed, CAB Abstracts and SciELO were searched for 2015 to 2025 with the terms (“canine visceral "
                  "leishmaniasis” OR “Leishmania infantum”) AND (diagnosis OR control). Original articles and reviews "
                  "in English, Portuguese or Spanish were included ..."),
    )
    ph(doc, write_here(t, t("como a literatura foi buscada e selecionada", "how the literature was searched and selected")))

    h1(doc, t("Desenvolvimento (ou Resultados, na revisão sistemática e de escopo)",
              "Main text (or Results, in systematic and scoping reviews)"))
    guide(
        doc, t, t("Desenvolvimento", "Main text"),
        what=[t("Narrativa: organize em subtítulos por tema (Título 2), ex.: Etiologia; Epidemiologia; Patogenia; Sinais "
                "clínicos; Diagnóstico; Tratamento; Prevenção e controle.",
                "Narrative: organise into topic subheadings (Heading 2), e.g. Aetiology; Epidemiology; Pathogenesis; "
                "Clinical signs; Diagnosis; Treatment; Prevention and control."),
              t("Sistemática e escopo: fluxograma PRISMA (Figura 1), tabela das características dos estudos incluídos, "
                "risco de viés e síntese dos resultados.",
                "Systematic and scoping: PRISMA flow diagram (Figure 1), table of characteristics of included studies, "
                "risk of bias and synthesis of results.")],
        tips=[t("Analise, não só descreva: compare estudos, mostre onde discordam e por quê, e diga quão fortes são as "
                "evidências.",
                "Analyse, do not just describe: compare studies, show where they disagree and why, and say how strong "
                "the evidence is."),
              t("Tabelas comparando estudos (autor, ano, espécie, n, método, resultado) ajudam muito o leitor.",
                "Tables comparing studies (author, year, species, n, method, result) help the reader a lot.")],
        avoid=[t("Uma “colagem” de resumos de artigos, um por parágrafo.",
                 "A “patchwork” of article summaries, one per paragraph."),
               t("Copiar trechos de livros e artigos, mesmo citando: escreva com suas palavras.",
                 "Copying passages from books and articles, even when cited: use your own words.")],
    )
    for n in range(1, 3):
        h2(doc, write_here(t, t(f"subtítulo do tema {n}", f"subheading for topic {n}")))
        ph(doc, write_here(t, t(f"o texto do tema {n}", f"the text for topic {n}")))
    table_guide(doc, t)
    example_table(doc, t, 1,
                  [t("Estudo", "Study"), t("Espécie e n", "Species and n"), t("Método", "Method"), t("Principal resultado", "Main result")],
                  [(t("[autor, ano] [ref.]", "[author, year] [ref.]"), t("[espécie, n]", "[species, n]"),
                    t("[método]", "[method]"), t("[resultado]", "[result]"))] * 2)
    figure_guide(doc, t)
    example_figure(doc, t, 1, t(
        "legenda autoexplicativa (na revisão sistemática: “Fluxograma PRISMA da seleção dos estudos.”)",
        "self-explanatory legend (in a systematic review: “PRISMA flow diagram of study selection.”)"))

    h1(doc, t("Discussão e lacunas do conhecimento", "Discussion and knowledge gaps"))
    guide(
        doc, t, t("Discussão e lacunas", "Discussion and gaps"),
        what=[t("O que as evidências, em conjunto, permitem afirmar e com que segurança.",
                "What the evidence, taken together, allows us to say and with what certainty."),
              t("As lacunas: o que ainda não se sabe e quais estudos fariam falta (especialmente nos Neotrópicos).",
                "The gaps: what is still unknown and which studies are needed (especially in the Neotropics)."),
              t("Implicações para a prática e limitações da própria revisão.",
                "Implications for practice and limitations of the review itself.")],
    )
    ph(doc, write_here(t, t("a discussão e as lacunas", "the discussion and the gaps")))

    h1(doc, t("Conclusões", "Conclusions"))
    guide(
        doc, t, t("Conclusões", "Conclusions"),
        what=t("Um parágrafo curto que não vá além das evidências, com as lacunas e as implicações práticas.",
               "One short paragraph that does not go beyond the evidence, with gaps and practical implications."),
    )
    ph(doc, write_here(t, t("as conclusões", "the conclusions")))

    declarations(doc, t, [
        ("p", t("Revisão de literatura, sem animais nem pessoas: “Não se aplica.”",
                "Literature review, with no animals or people: “Not applicable.”")),
    ], t("Narrativa: “No data were generated or analysed in this study.” Sistemática: indique o repositório com os "
         "formulários e os dados extraídos.",
         "Narrative: “No data were generated or analysed in this study.” Systematic: state the repository with the "
         "extraction forms and extracted data."))
    references(doc, t, t("Narrativa: até 100; mini-revisão: 50; sistemática: 120; escopo: 150. Boa parte dos últimos "
                         "cinco anos.",
                         "Narrative: up to 100; mini-review: 50; systematic: 120; scoping: 150. A good share from "
                         "the last five years."))
    final_check(doc, t, [
        t("O tipo de revisão está no título e corresponde ao método usado.",
          "The review type is in the title and matches the method used."),
        t("Narrativa: os editores aprovaram a proposta antes. Sistemática: o protocolo está registrado e citado.",
          "Narrative: the editors approved the proposal beforehand. Systematic: the protocol is registered and cited."),
    ], t("Revisões", "Reviews"),
        t("Checklist: SANRA (narrativa e mini-revisão), PRISMA 2020 e PRISMA-S (sistemática) ou PRISMA-ScR (escopo).",
          "Checklist: SANRA (narrative and mini-review), PRISMA 2020 and PRISMA-S (systematic) or PRISMA-ScR (scoping)."))
    return doc


# ---------------------------------------------------------------- folha de rosto

def title_page(t):
    name = t("Modelo de Folha de Rosto", "Title Page template")
    doc = new_document(t, name, numbered=False)
    box(doc, t(f"NVAS · {name} · Como usar (apague este quadro antes de enviar)",
               f"NVAS · {name} · How to use (delete this box before submitting)"), [
        ("li", t("Este é o ÚNICO arquivo com os nomes dos autores. Não vai para os revisores. Serve para qualquer tipo "
                 "de artigo, inclusive Imagem Clínica.",
                 "This is the ONLY file with the authors' names. It is not sent to reviewers. It works for any article "
                 "type, including Clinical Images.")),
        ("li", t("Escreva por cima de cada [ESCREVA AQUI: ...] e apague os quadros azuis. No sistema, envie com o tipo "
                 "“Folha de rosto (com os autores)”.",
                 "Type over each [WRITE HERE: ...] and delete the blue boxes. In the system, upload it with the type "
                 "“Title page (with authors)”.")),
        ("li", t("Pode ser em português ou em inglês, no mesmo idioma do manuscrito.",
                 "It can be in English or Portuguese, in the same language as the manuscript.")),
    ])
    for label, hint in [
        (t("Tipo de artigo:", "Article type:"), t("Artigo original, Comunicação curta, Relato de caso, Série de casos, "
                                                   "Imagem clínica, Revisão (narrativa, sistemática...), Método...",
                                                   "Original article, Short communication, Case report, Case series, "
                                                   "Clinical image, Review (narrative, systematic...), Method...")),
        (t("Título:", "Title:"), t("igual ao do manuscrito", "same as in the manuscript")),
        (t("Título resumido:", "Running title:"), t("até 60 caracteres, contando os espaços", "up to 60 characters, including spaces")),
    ]:
        labelled(doc, label, write_here(t, hint))

    h1(doc, t("Autores", "Authors"))
    guide(
        doc, t, t("Autores", "Authors"),
        what=t("Todos os autores, na ordem combinada pelo grupo antes do envio. Em geral, quem mais trabalhou vem "
               "primeiro e o orientador por último.",
               "All authors, in the order agreed by the group before submission. Usually, whoever did most of the work "
               "comes first and the supervisor last."),
        tips=[t("Só é autor quem participou de verdade do trabalho e aprovou a versão final (critérios do ICMJE). Quem "
                "só ajudou vai em Agradecimentos.",
                "Only people who truly took part in the work and approved the final version are authors (ICMJE "
                "criteria). People who only helped go in the Acknowledgements."),
              t("ORCID: obrigatório para todos os autores (crie grátis em orcid.org). Depois do aceite, cada autor recebe um e-mail para confirmar o próprio ORCID; o artigo só é publicado com o ORCID de todos confirmado.",
                "ORCID: mandatory for every author (create one for free at orcid.org). After acceptance, each author receives an email to confirm their own ORCID; the article is only published when every ORCID has been confirmed."),
              t("Afiliação completa: departamento, instituição, cidade, estado, país. Estudante: inclua o curso.",
                "Full affiliation: department, institution, city, state, country. Students: include the programme.")],
        example=t("Ana Exemplo da Silva — Curso de Medicina Veterinária, Universidade Exemplo, Cidade, SP, Brasil — "
                  "ana@exemplo.br — https://orcid.org/0000-0000-0000-0000",
                  "Ana Example Silva — School of Veterinary Medicine, Example University, City, SP, Brazil — "
                  "ana@example.edu — https://orcid.org/0000-0000-0000-0000"),
    )
    tbl = doc.add_table(rows=1, cols=4)
    tbl.style = "Table Grid"
    for i, h in enumerate([t("Nome completo", "Full name"), t("Afiliação", "Affiliation"), "E-mail", "ORCID"]):
        c = tbl.rows[0].cells[i]
        shade(c, "DCE6F5")
        c.paragraphs[0].add_run(h).bold = True
    for n in range(1, 4):
        cells = tbl.add_row().cells
        cells[0].paragraphs[0].add_run(f"{n}. " + t("[nome]", "[name]"))
        cells[1].paragraphs[0].add_run(t("[departamento, instituição, cidade, estado, país]",
                                         "[department, institution, city, state, country]"))
        cells[2].paragraphs[0].add_run("[e-mail]")
        cells[3].paragraphs[0].add_run("[https://orcid.org/...]")
    for row in tbl.rows:
        for c in row.cells:
            for p in c.paragraphs:
                compact(p, 10, 0)
    note = doc.add_paragraph(t("Para mais autores: clique na última linha e use Layout › Inserir Abaixo.",
                               "For more authors: click in the last row and use Layout › Insert Below."))
    compact(note, 9, 6)
    note.runs[0].font.color.rgb = GRAY

    h1(doc, t("Autor de correspondência", "Corresponding author"))
    ph(doc, write_here(t, t("nome, endereço postal da instituição e e-mail de quem vai falar com a revista",
                            "name, institutional postal address and e-mail of the person who will deal with the journal")))

    h1(doc, t("Autor graduado responsável (guarantor)", "Responsible graduate author (guarantor)"))
    guide(
        doc, t, t("Autor graduado", "Graduate author"),
        what=t("Obrigatório: pelo menos um autor já formado em medicina veterinária, zootecnia ou área afim (em geral o "
               "orientador), responsável pela integridade do trabalho. Manuscritos só de estudantes são devolvidos "
               "sem avaliação.",
               "Mandatory: at least one author who has graduated in veterinary medicine, animal science or a related "
               "field (usually the supervisor), responsible for the integrity of the work. Manuscripts by students "
               "only are returned without review."),
        example=t("João Exemplo Santos, médico-veterinário, MSc, DSc — Professor, Universidade Exemplo — "
                  "http://lattes.cnpq.br/0000000000000000",
                  "John Example Santos, DVM, MSc, PhD — Professor, Example University — "
                  "https://orcid.org/0000-0000-0000-0000"),
    )
    ph(doc, write_here(t, t("nome, titulação, instituição e link do Currículo Lattes, do CRMV ou do ORCID",
                            "name, degrees, institution and link to ORCID, professional registration or institutional page")))

    h1(doc, t("Contagem", "Counts"))
    for label in [t("Palavras do texto (Introdução à Conclusão):", "Words in the text (Introduction to Conclusion):"),
                  t("Palavras do resumo:", "Words in the abstract:"),
                  t("Número de tabelas:", "Number of tables:"),
                  t("Número de figuras:", "Number of figures:"),
                  t("Número de referências:", "Number of references:")]:
        labelled(doc, label, "[ ]")

    h1(doc, t("Agradecimentos", "Acknowledgements"))
    guide(
        doc, t, t("Agradecimentos", "Acknowledgements"),
        what=t("Pessoas e instituições que ajudaram, mas não são autores (técnicos, laboratório, hospital, "
               "residentes). Peça permissão a quem for citado. Bolsas e auxílios vão em Financiamento.",
               "People and institutions who helped but are not authors (technicians, laboratory, hospital, residents). "
               "Ask permission from those named. Grants go in Funding."),
    )
    ph(doc, write_here(t, t("os agradecimentos, ou “Não se aplica.”", "the acknowledgements, or “Not applicable.”")))

    h1(doc, t("Contribuição dos autores (CRediT)", "Author contributions (CRediT)"))
    guide(
        doc, t, "CRediT",
        what=t("Para cada autor, os papéis que teve, escolhidos desta lista: Conceituação; Curadoria de dados; Análise "
               "formal; Obtenção de financiamento; Investigação; Metodologia; Administração do projeto; Recursos; "
               "Software; Supervisão; Validação; Visualização; Escrita – rascunho original; Escrita – revisão e edição.",
               "For each author, their roles, chosen from this list: Conceptualization; Data curation; Formal analysis; "
               "Funding acquisition; Investigation; Methodology; Project administration; Resources; Software; "
               "Supervision; Validation; Visualization; Writing – original draft; Writing – review & editing."),
        example=t("Ana Exemplo da Silva: Investigação, Escrita – rascunho original. João Exemplo Santos: Conceituação, "
                  "Supervisão, Escrita – revisão e edição.",
                  "Ana Example Silva: Investigation, Writing – original draft. John Example Santos: Conceptualization, "
                  "Supervision, Writing – review & editing."),
    )
    ph(doc, write_here(t, t("nome: papéis; nome: papéis", "name: roles; name: roles")))

    h1(doc, t("Declarações", "Declarations"))
    guide(
        doc, t, t("Declarações", "Declarations"),
        what=t("As mesmas do fim do manuscrito, mas aqui com os dados reais: nome da comissão de ética, número do "
               "protocolo e data, números de processo do financiamento etc.",
               "The same as at the end of the manuscript, but here with the real details: name of the ethics committee, "
               "protocol number and date, grant numbers, etc."),
    )
    for label, hint in [
        (t("Aprovação ética e consentimento:", "Ethics approval and consent:"),
         t("comissão, protocolo e data; ou a dispensa e o motivo", "committee, protocol and date; or the exemption and why")),
        (t("Conflito de interesses:", "Conflict of interest:"),
         t("“Os autores declaram não haver conflito de interesses.” ou os vínculos",
           "“The authors declare no conflict of interest.” or the links")),
        (t("Financiamento:", "Funding:"), t("financiadores e números de processo, ou “Não houve.”",
                                            "funders and grant numbers, or “None.”")),
        ("Data availability statement:", t("a mesma frase do manuscrito", "same statement as in the manuscript")),
        ("Declaration of generative AI:", t("a mesma frase do manuscrito", "same statement as in the manuscript")),
        (t("Preprint ou divulgação prévia:", "Preprint or prior disclosure:"),
         t("DOI do preprint, congresso ou rede social onde o trabalho foi mostrado, ou “Nenhum.”",
           "preprint DOI, meeting or social media where the work was shown, or “None.”")),
    ]:
        labelled(doc, label, write_here(t, hint))
    return doc


# ---------------------------------------------------------------- carta de apresentação

def cover_letter(t):
    name = t("Modelo de Carta de Apresentação", "Cover Letter template")
    doc = new_document(t, name, numbered=False)
    doc.styles["Normal"].paragraph_format.line_spacing = 1.15
    doc.styles["Normal"].paragraph_format.space_after = Pt(8)
    box(doc, t(f"NVAS · {name} · Como usar (apague este quadro antes de enviar)",
               f"NVAS · {name} · How to use (delete this box before submitting)"), [
        ("li", t("Carta curta (meia a uma página) ao editor-chefe. Não vai para os revisores. Serve para mostrar, em "
                 "poucas linhas, por que o trabalho cabe na revista e o que traz de novo.",
                 "A short letter (half a page to one page) to the editor-in-chief. It is not sent to reviewers. It shows, "
                 "in a few lines, why the work fits the journal and what is new about it.")),
        ("li", t("Escreva por cima de cada [ESCREVA AQUI: ...]. No sistema, envie com o tipo “Carta de apresentação”.",
                 "Type over each [WRITE HERE: ...]. In the system, upload it with the type “Cover letter”.")),
        ("li", t("Revisores sugeridos são opcionais: até três, que não sejam da sua instituição nem tenham sido seus "
                 "coautores nos últimos cinco anos.",
                 "Suggested reviewers are optional: up to three, not from your institution and not your co-authors in "
                 "the last five years.")),
    ])
    ph(doc, write_here(t, t("cidade e data", "city and date")))
    ph(doc, t("Ao Editor-Chefe", "To the Editor-in-Chief"))
    ph(doc, "Neotropical Veterinary and Animal Science (NVAS)")
    ph(doc, t("Prezados Editores,", "Dear Editors,"))
    ph(doc, t("Submetemos o manuscrito intitulado “", "We submit the manuscript entitled “")
       + write_here(t, t("título", "title")) + t("”, para avaliação como ", "” for consideration as ")
       + write_here(t, t("tipo de artigo, ex.: relato de caso", "article type, e.g. case report"))
       + t(" na Neotropical Veterinary and Animal Science.", " in Neotropical Veterinary and Animal Science."))
    ph(doc, write_here(t, t("em 2 ou 3 frases: qual problema o trabalho aborda, o que ele acrescenta (o que é novo ou "
                            "útil) e para quem interessa (clínicos, produtores, saúde pública...)",
                            "in 2 or 3 sentences: what problem the work addresses, what it adds (what is new or useful) "
                            "and who it matters to (clinicians, farmers, public health...)")))
    ph(doc, t("Declaramos que o manuscrito é original, não foi publicado e não está em avaliação em outra revista, e que "
              "todos os autores aprovaram a versão enviada. ",
              "We declare that the manuscript is original, has not been published and is not under consideration by "
              "another journal, and that all authors approved the submitted version. ")
       + write_here(t, t("se houver: preprint (DOI) ou apresentação prévia em congresso ou redes sociais",
                         "if any: preprint (DOI) or prior presentation at a meeting or on social media")))
    ph(doc, t("Revisores sugeridos (opcional): ", "Suggested reviewers (optional): ")
       + write_here(t, t("nome, instituição, e-mail e motivo (até três)", "name, institution, e-mail and reason (up to three)")))
    ph(doc, t("Revisores a excluir (opcional): ", "Reviewers to exclude (optional): ")
       + write_here(t, t("nome e justificativa", "name and reason")))
    ph(doc, t("Atenciosamente,", "Sincerely,"))
    ph(doc, write_here(t, t("nome do autor de correspondência, instituição e e-mail",
                            "corresponding author's name, institution and e-mail")))
    return doc


# ---------------------------------------------------------------- termo de consentimento

def consent_form(t):
    """Termo do responsável legal pelo animal (Seção 2.1 das normas): só quando o caso é identificável."""
    name = t("Termo de Consentimento do Responsável Legal", "Legal Guardian Consent Form")
    doc = new_document(t, name, numbered=False)
    doc.styles["Normal"].paragraph_format.line_spacing = 1.3
    doc.styles["Normal"].paragraph_format.space_after = Pt(8)
    box(doc, t(f"NVAS · {name} · Como usar (apague este quadro antes de imprimir)",
               f"NVAS · {name} · How to use (delete this box before printing)"), [
        ("label", t("Quando é preciso", "When it is needed")),
        ("li", t("Só quando o texto ou as imagens permitem identificar o animal ou o seu responsável (nome do animal ou do "
                 "tutor, fazenda, brinco ou tatuagem legível, foto com pessoas, placa, local). Caso de rotina anonimizado "
                 "dispensa o termo (Seção 2.1 das normas).",
                 "Only when the text or images could identify the animal or its guardian (animal's or owner's name, farm, "
                 "readable ear tag or tattoo, photo with people, sign, place). An anonymized routine case does not need "
                 "it (Section 2.1 of the Guide).")),
        ("li", t("Se o hospital veterinário já tem um termo institucional assinado no atendimento que autoriza o uso em "
                 "ensino e pesquisa, ele também vale: guarde uma cópia.",
                 "If the veterinary hospital already has an institutional form signed at admission that authorises use for "
                 "teaching and research, it is also valid: keep a copy.")),
        ("label", t("Como preencher e enviar", "How to fill in and submit")),
        ("li", t("Os autores preenchem os campos [ESCREVA AQUI: ...], imprimem, e o responsável legal lê e assina (assinatura "
                 "à mão ou eletrônica, como gov.br). Explique em palavras simples e tire as dúvidas antes da assinatura.",
                 "The authors fill in the [WRITE HERE: ...] fields and print the form; the legal guardian reads and signs it "
                 "(handwritten or electronic signature). Explain it in plain words and answer questions before signing.")),
        ("li", t("No envio, mande o termo assinado em PDF com o tipo “Termo de consentimento do responsável legal”. Ele fica "
                 "em sigilo com a secretaria e não vai para os revisores. No manuscrito, escreva só: “O responsável legal "
                 "deu consentimento por escrito para a publicação.”",
                 "At submission, upload the signed form as a PDF with the type “Consent form of the legal guardian”. It is "
                 "kept confidential by the editorial office and is not sent to reviewers. In the manuscript, write only: "
                 "“The legal guardian gave written consent for publication.”")),
    ])
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run(t("TERMO DE CONSENTIMENTO PARA PUBLICAÇÃO DE CASO CLÍNICO E IMAGENS",
                    "CONSENT FOR PUBLICATION OF A CLINICAL CASE AND IMAGES"))
    r.bold = True
    ph(doc, t("Revista: Neotropical Veterinary and Animal Science (NVAS), CSVET Editora — journals.csvet.com.br/nvas",
              "Journal: Neotropical Veterinary and Animal Science (NVAS), CSVET Editora — journals.csvet.com.br/nvas"))
    for label, hint in [
        (t("Título do trabalho:", "Title of the work:"), t("título do manuscrito", "title of the manuscript")),
        (t("Autor responsável (correspondência):", "Responsible author (corresponding):"),
         t("nome, instituição e e-mail", "name, institution and e-mail")),
        (t("Animal:", "Animal:"), t("espécie, raça, sexo, idade; nome ou identificação", "species, breed, sex, age; name or ID")),
        (t("Atendimento:", "Clinical care:"), t("local e período do atendimento", "place and period of care")),
    ]:
        labelled(doc, label, write_here(t, hint))
    ph(doc, t("Eu, ", "I, ") + write_here(t, t("nome completo do responsável legal", "full name of the legal guardian"))
       + t(", documento de identidade nº ", ", identity document no. ") + write_here(t, t("RG ou CPF", "ID number"))
       + t(", responsável legal pelo animal acima, declaro que:", ", legal guardian of the animal above, declare that:"))
    for item in [
        t("fui informado(a) de que os autores desejam publicar, na revista NVAS, as informações clínicas, os exames e as "
          "imagens do atendimento do animal, com finalidade científica e de ensino;",
          "I was informed that the authors wish to publish, in the journal NVAS, the clinical information, test results "
          "and images from the animal's care, for scientific and teaching purposes;"),
        t("li ou ouvi a descrição do que será publicado e pude tirar minhas dúvidas;",
          "I read or heard a description of what will be published and was able to ask questions;"),
        t("sei que meu nome, meu endereço e meus dados de contato não serão publicados, mas que, pelas imagens ou pela "
          "descrição, pessoas que conhecem o animal podem reconhecê-lo;",
          "I know that my name, address and contact details will not be published, but that people who know the animal "
          "may recognise it from the images or the description;"),
        t("sei que o artigo será de acesso aberto, na internet, para qualquer pessoa no mundo, sob a licença CC BY 4.0, "
          "e poderá ser reutilizado com citação da fonte; depois de publicado, não poderá ser retirado;",
          "I know that the article will be open access on the internet, to anyone in the world, under the CC BY 4.0 "
          "licence, and may be reused with attribution; once published, it cannot be withdrawn;"),
        t("posso desistir deste consentimento a qualquer momento antes da publicação, avisando o autor responsável, sem "
          "nenhum prejuízo ao atendimento do animal;",
          "I may withdraw this consent at any time before publication by telling the responsible author, with no effect "
          "on the animal's care;"),
        t("não receberei pagamento por esta autorização.", "I will not receive any payment for this authorisation."),
    ]:
        q = doc.add_paragraph("•\t" + item)
        q.paragraph_format.left_indent = Cm(0.6)
        q.paragraph_format.first_line_indent = Cm(-0.4)
        q.paragraph_format.tab_stops.add_tab_stop(Cm(0.6))
    ph(doc, t("Assim, AUTORIZO a publicação do caso e das imagens descritos acima.",
              "I therefore AUTHORISE the publication of the case and images described above."))
    ph(doc, write_here(t, t("cidade", "city")) + ", " + write_here(t, t("data", "date")))
    for label in [t("Assinatura do responsável legal", "Signature of the legal guardian"),
                  t("Assinatura do autor responsável", "Signature of the responsible author")]:
        sig = doc.add_paragraph("\n______________________________________________")
        sig.alignment = WD_ALIGN_PARAGRAPH.CENTER
        cap = doc.add_paragraph(label)
        cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
    return doc


# ---------------------------------------------------------------- saída

TEMPLATES = [
    (case_report, "NVAS-modelo-relato-de-caso.docx", "NVAS-case-report-template.docx"),
    (original_article, "NVAS-modelo-artigo-original.docx", "NVAS-original-article-template.docx"),
    (review, "NVAS-modelo-revisao.docx", "NVAS-review-template.docx"),
    (title_page, "NVAS-modelo-folha-de-rosto.docx", "NVAS-title-page-template.docx"),
    (cover_letter, "NVAS-modelo-carta-de-apresentacao.docx", "NVAS-cover-letter-template.docx"),
    (consent_form, "NVAS-modelo-termo-de-consentimento.docx", "NVAS-consent-form-template.docx"),
]


def build():
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for make, pt_name, en_name in TEMPLATES:
        for lang, file_name in (("pt", pt_name), ("en", en_name)):
            t = (lambda pt, en: pt) if lang == "pt" else (lambda pt, en: en)
            out = OUT_DIR / file_name
            make(t).save(out)
            print(out.relative_to(ROOT))


if __name__ == "__main__":
    build()
