"""Gera os documentos e modelos da Editora CSVET (Word e Excel).

Uso:  python branding/build_docs.py          (requer python-docx e openpyxl)

Saídas
  plugins/themes/csvet/docs/        PÚBLICOS (download nas Instruções aos Autores)
    CSVET-proposta-livro.docx            proposta de obra autoral, manual, monografia, dissertação ou tese
    CSVET-proposta-coletanea.docx        proposta de coletânea (organizadores)
    CSVET-convites-capitulos.xlsx        planilha do organizador: autores convidados e capítulos
    CSVET-modelo-livro.docx              modelo de manuscrito (obra inteira), com estilos
    CSVET-modelo-capitulo.docx           modelo de capítulo de coletânea, com estilos
    CSVET-declaracao-autores.docx        originalidade, autoria, direitos e licença (autores/organizadores)
    CSVET-termo-autor-capitulo.docx      termo de cada autor de capítulo
    CSVET-anuencia-orientador.docx       anuência do orientador (monografias, dissertações, teses)
  content/editora/documentos/       INTERNOS (enviados pela editora; bloqueados na web)
    contrato-edicao-minuta.docx          minuta de contrato de edição: aberto, comercial ou híbrido (revisão jurídica)
    declaracao-avaliacao-pares.docx      declaração de avaliação por pares e de publicação (CAPES/pós-graduação)
    termo-adesao-voluntario.docx         Conselho Editorial e editores de coleção (Lei 9.608/1998)
    conselho-editorial-modelo.xlsx       planilha de convites do Conselho/pareceristas (scripts/invite.php)
    controle-isbn.xlsx                   controle dos ISBN atribuídos pela CBL
  content/editora/producao/         PADRÃO GRÁFICO (diagramação no Word, exportar PDF/A)
    modelo-diagramacao-16x23.docx        livros, monografias, coletâneas (formato padrão)
    modelo-diagramacao-21x28.docx        manuais e atlas com muitas imagens

Os textos jurídicos são modelos de referência: revise com advogado antes do primeiro uso.
"""
from datetime import date
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Mm, Pt, RGBColor
from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.worksheet.datavalidation import DataValidation

ROOT = Path(__file__).resolve().parent.parent
PUBLIC = ROOT / "plugins" / "themes" / "csvet" / "docs"
INTERNAL = ROOT / "content" / "editora" / "documentos"
PRODUCTION = ROOT / "content" / "editora" / "producao"
LOGO = ROOT / "branding" / "assets" / "logo-csvet.png"

NAVY = RGBColor(0x00, 0x00, 0x80)
ACCENT = RGBColor(0x00, 0x88, 0xCC)
GRAY = RGBColor(0x55, 0x55, 0x66)

PUBLISHER = "Editora CSVET"
COMPANY = "CSVET Editora, Cursos e Treinamentos"
CNPJ = "32.645.724/0001-89"
CITY = "Americana, SP"
SITE = "https://books.csvet.com.br"
GUIDELINES = "https://books.csvet.com.br/editora/about/submissions"
EMAIL = "contato@csvet.com.br"
DIRECTOR = "Atilio Sersun Calefi"
LINE = "_" * 62
SERIES = [
    "Livros Técnico-Científicos",
    "Coletâneas",
    "Manuais e Guias Práticos",
    "Monografias",
    "Dissertações e Teses",
    "Anais e Resumos de Eventos",
]


# Elementos que o esquema OOXML exige DEPOIS dos que inserimos (o Word recusa a ordem errada)
PPR_AFTER_PBDR = ("w:shd", "w:tabs", "w:suppressAutoHyphens", "w:kinsoku", "w:wordWrap", "w:overflowPunct", "w:topLinePunct",
                  "w:autoSpaceDE", "w:autoSpaceDN", "w:bidi", "w:adjustRightInd", "w:snapToGrid", "w:spacing", "w:ind",
                  "w:contextualSpacing", "w:mirrorIndents", "w:suppressOverlap", "w:jc", "w:textDirection", "w:textAlignment",
                  "w:textboxTightWrap", "w:outlineLvl", "w:divId", "w:cnfStyle", "w:rPr", "w:sectPr", "w:pPrChange")
SETTINGS_AFTER_MIRROR = ("w:alignBordersAndEdges", "w:bordersDoNotSurroundHeader", "w:bordersDoNotSurroundFooter", "w:gutterAtTop",
                         "w:hideSpellingErrors", "w:hideGrammaticalErrors", "w:activeWritingStyle", "w:proofState", "w:formsDesign",
                         "w:attachedTemplate", "w:linkStyles", "w:stylePaneFormatFilter", "w:stylePaneSortMethod", "w:documentType",
                         "w:mailMerge", "w:revisionView", "w:trackRevisions", "w:doNotTrackMoves", "w:doNotTrackFormatting",
                         "w:documentProtection", "w:autoFormatOverride", "w:styleLockTheme", "w:styleLockQFSet", "w:defaultTabStop",
                         "w:autoHyphenation", "w:consecutiveHyphenLimit", "w:hyphenationZone", "w:doNotHyphenateCaps",
                         "w:showEnvelope", "w:summaryLength", "w:clickAndTypeStyle", "w:defaultTableStyle", "w:evenAndOddHeaders",
                         "w:bookFoldRevPrinting", "w:bookFoldPrinting", "w:bookFoldPrintingSheets", "w:drawingGridHorizontalSpacing",
                         "w:drawingGridVerticalSpacing", "w:displayHorizontalDrawingGridEvery", "w:displayVerticalDrawingGridEvery",
                         "w:doNotUseMarginsForDrawingGridOrigin", "w:drawingGridHorizontalOrigin", "w:drawingGridVerticalOrigin",
                         "w:doNotShadeFormData", "w:noPunctuationKerning", "w:characterSpacingControl", "w:printTwoOnOne",
                         "w:strictFirstAndLastChars", "w:noLineBreaksAfter", "w:noLineBreaksBefore", "w:savePreviewPicture",
                         "w:doNotValidateAgainstSchema", "w:saveInvalidXml", "w:ignoreMixedContent", "w:alwaysShowPlaceholderText",
                         "w:doNotDemarcateInvalidXml", "w:saveXmlDataOnly", "w:useXSLTWhenSaving", "w:saveThroughXslt",
                         "w:showXMLTags", "w:alwaysMergeEmptyNamespace", "w:updateFields", "w:hdrShapeDefaults", "w:footnotePr",
                         "w:endnotePr", "w:compat", "w:docVars", "w:rsids", "m:mathPr", "w:attachedSchema", "w:themeFontLang",
                         "w:clrSchemeMapping", "w:doNotIncludeSubdocsInStats", "w:doNotAutoCompressPictures", "w:forceUpgrade",
                         "w:captions", "w:readModeInkLockDown", "w:smartTagType", "sl:schemaLibrary", "w:shapeDefaults",
                         "w:doNotEmbedSmartTags", "w:decimalSymbol", "w:listSeparator")


# =====================================================================  utilidades Word
def new_doc(size="A4", body_font="Calibri", body_size=11, heading_font=None, margins=(2.5, 2.5, 2.5, 2.5)):
    doc = Document()
    sec = doc.sections[0]
    if size == "A4":
        sec.page_width, sec.page_height = Mm(210), Mm(297)
    top, bottom, left, right = margins
    sec.top_margin, sec.bottom_margin, sec.left_margin, sec.right_margin = Cm(top), Cm(bottom), Cm(left), Cm(right)
    st = doc.styles["Normal"]
    st.font.name = body_font
    st.element.rPr.rFonts.set(qn("w:eastAsia"), body_font)
    st.font.size = Pt(body_size)
    st.paragraph_format.space_after = Pt(6)
    st.paragraph_format.line_spacing = 1.15
    heading_font = heading_font or body_font
    for i, (size_pt, color) in enumerate([(16, NAVY), (13, NAVY), (11.5, NAVY), (11, GRAY)], start=1):
        h = doc.styles[f"Heading {i}"]
        h.font.name = heading_font
        _font(h, heading_font)
        h.font.size = Pt(size_pt)
        h.font.bold = True
        h.font.italic = False
        h.font.color.rgb = color
        h.paragraph_format.space_before = Pt(14 if i < 3 else 10)
        h.paragraph_format.space_after = Pt(6)
        h.paragraph_format.keep_with_next = True
    return doc


def _font(style, name):
    """Fixa a fonte em todos os scripts (o Word ignora font.name nos títulos com tema)."""
    rpr = style.element.get_or_add_rPr()
    rfonts = rpr.find(qn("w:rFonts"))
    if rfonts is None:
        rfonts = OxmlElement("w:rFonts")
        rpr.insert(0, rfonts)
    for attr in ("w:ascii", "w:hAnsi", "w:cs", "w:eastAsia"):
        rfonts.set(qn(attr), name)
    for attr in ("w:asciiTheme", "w:hAnsiTheme", "w:cstheme", "w:eastAsiaTheme"):
        if rfonts.get(qn(attr)) is not None:
            del rfonts.attrib[qn(attr)]


def para(doc, text="", bold=False, italic=False, size=None, color=None, align=None, style=None, space_after=None):
    p = doc.add_paragraph(style=style)
    if text:
        add_runs(p, text, bold=bold, italic=italic, size=size, color=color)
    if align == "center":
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    elif align == "right":
        p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    elif align == "justify":
        p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    if space_after is not None:
        p.paragraph_format.space_after = Pt(space_after)
    return p


def add_runs(p, text, bold=False, italic=False, size=None, color=None):
    """Texto com **negrito** inline."""
    parts = text.split("**")
    for i, part in enumerate(parts):
        if not part:
            continue
        r = p.add_run(part)
        r.bold = bold or (i % 2 == 1)
        r.italic = italic
        if size:
            r.font.size = Pt(size)
        if color is not None:
            r.font.color.rgb = color
    return p


def header_block(doc, title, subtitle=None):
    if LOGO.exists():
        doc.add_picture(str(LOGO), width=Cm(5))
        doc.paragraphs[-1].alignment = WD_ALIGN_PARAGRAPH.LEFT
    para(doc, title, bold=True, size=16, color=NAVY, space_after=2)
    if subtitle:
        para(doc, subtitle, italic=True, size=10, color=GRAY)
    rule(doc)


def rule(doc):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(8)
    ppr = p._p.get_or_add_pPr()
    bdr = OxmlElement("w:pBdr")
    bottom = OxmlElement("w:bottom")
    for k, v in (("w:val", "single"), ("w:sz", "12"), ("w:space", "1"), ("w:color", "00AAFF")):
        bottom.set(qn(k), v)
    bdr.append(bottom)
    # Ordem exigida pelo esquema do Word (pBdr vem antes de shd, spacing, ind, jc...)
    ppr.insert_element_before(bdr, *PPR_AFTER_PBDR)


def field_line(doc, label, lines=1):
    p = para(doc, f"**{label}:** ", space_after=2)
    p.add_run(LINE[: max(10, 62 - len(label))])
    for _ in range(lines - 1):
        para(doc, LINE + "______", space_after=2)


def checklist(doc, items):
    for item in items:
        p = para(doc, "☐  " + item, space_after=4)
        p.paragraph_format.left_indent = Cm(0.6)
        p.paragraph_format.first_line_indent = Cm(-0.6)


def numbered(doc, items, prefix=""):
    for i, item in enumerate(items, start=1):
        p = para(doc, f"{prefix}{i}. " + item, space_after=4, align="justify")
        p.paragraph_format.left_indent = Cm(0.6)
        p.paragraph_format.first_line_indent = Cm(-0.6)


def signature(doc, who, note=None):
    para(doc, f"Local e data: {LINE[:40]}", space_after=18)
    for w in ([who] if isinstance(who, str) else who):
        para(doc, "_" * 45, space_after=0)
        para(doc, w, size=9.5, color=GRAY, space_after=14)
    para(doc, note or "Aceita-se assinatura eletrônica gratuita do gov.br (assinador.iti.br), certificado digital ou assinatura digitalizada.",
         italic=True, size=8.5, color=GRAY)


def table(doc, rows, widths=None, header=True):
    t = doc.add_table(rows=len(rows), cols=len(rows[0]))
    t.style = "Table Grid"
    t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, row in enumerate(rows):
        for j, value in enumerate(row):
            cell = t.cell(i, j)
            cell.text = ""
            add_runs(cell.paragraphs[0], str(value), bold=header and i == 0, size=9.5)
            if header and i == 0:
                shade(cell, "E8ECF8")
            if widths:
                cell.width = Cm(widths[j])
    doc.add_paragraph()
    return t


def shade(cell, hex_color):
    tcpr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_color)
    tcpr.insert_element_before(shd, "w:noWrap", "w:tcMar", "w:textDirection", "w:tcFitText", "w:vAlign", "w:hideMark", "w:headers", "w:cellIns", "w:cellDel", "w:cellMerge", "w:tcPrChange")


def footer_note(doc, text):
    f = doc.sections[0].footer.paragraphs[0]
    f.text = ""
    add_runs(f, text, size=8, color=GRAY)


def field(paragraph, instr, placeholder=""):
    """Campo do Word (PAGE, STYLEREF, TOC...). O Word atualiza ao abrir/imprimir (F9)."""
    r = paragraph.add_run()
    b = OxmlElement("w:fldChar")
    b.set(qn("w:fldCharType"), "begin")
    r._r.append(b)
    r = paragraph.add_run()
    t = OxmlElement("w:instrText")
    t.set(qn("xml:space"), "preserve")
    t.text = f" {instr} "
    r._r.append(t)
    r = paragraph.add_run()
    s = OxmlElement("w:fldChar")
    s.set(qn("w:fldCharType"), "separate")
    r._r.append(s)
    paragraph.add_run(placeholder)
    r = paragraph.add_run()
    e = OxmlElement("w:fldChar")
    e.set(qn("w:fldCharType"), "end")
    r._r.append(e)


def save(doc, path):
    path.parent.mkdir(parents=True, exist_ok=True)
    doc.core_properties.author = PUBLISHER
    doc.core_properties.title = path.stem
    doc.save(path)
    print("  ", path.relative_to(ROOT))


# =====================================================================  propostas
def proposta_livro():
    doc = new_doc()
    header_block(doc, "Proposta editorial de livro",
                 "Obra autoral, manual, monografia, dissertação ou tese. Envie este arquivo preenchido junto com o texto completo. Até 3 páginas.")
    doc.add_heading("1. Identificação da obra", 2)
    field_line(doc, "Título")
    field_line(doc, "Subtítulo")
    para(doc, "**Coleção pretendida** (marque uma):")
    checklist(doc, [s for s in SERIES if s != "Coletâneas" and not s.startswith("Anais")])
    para(doc, "**Origem do texto:**")
    checklist(doc, ["Obra inédita", "TCC de graduação (já concluído)", "Monografia de especialização ou residência",
                    "Dissertação de mestrado", "Tese de doutorado", "Outra: ________________________________"])
    field_line(doc, "Se for trabalho acadêmico: instituição, programa/curso, ano da aprovação e link do repositório", 2)
    field_line(doc, "Partes já publicadas como artigos (referência e DOI), se houver", 2)
    field_line(doc, "Idioma do texto")
    field_line(doc, "Extensão aproximada (páginas A4 ou caracteres com espaços)")
    field_line(doc, "Número de figuras e tabelas (e quantas são de terceiros)")

    doc.add_heading("2. Conteúdo", 2)
    field_line(doc, "Resumo (até 250 palavras)", 6)
    field_line(doc, "Palavras-chave (3 a 6)")
    field_line(doc, "Público-alvo (estudantes, clínicos, produtores, pesquisadores...)", 2)
    field_line(doc, "Justificativa: por que este livro é necessário e como se diferencia das obras existentes (cite 2 ou 3)", 5)
    para(doc, "**Sumário comentado:** liste os capítulos com 2 ou 3 linhas sobre cada um.")
    table(doc, [["Nº", "Título do capítulo", "Conteúdo (2 a 3 linhas)", "Páginas (aprox.)"]] + [[str(i), "", "", ""] for i in range(1, 9)],
          widths=[1, 5, 8, 2.5])

    doc.add_heading("3. Autores", 2)
    para(doc, "Uma linha por autor, na ordem da capa. Pelo menos um autor deve ter graduação concluída em medicina veterinária, zootecnia ou área afim.")
    table(doc, [["Nome completo", "Titulação", "Instituição", "ORCID", "Lattes / CRMV", "E-mail"]] + [[""] * 6 for _ in range(4)],
          widths=[3.6, 2.2, 3, 2.6, 2.6, 3])
    field_line(doc, "Orientador(a), se for trabalho acadêmico (nome, titulação, instituição, e-mail)", 2)
    field_line(doc, "Minicurrículo dos autores (até 5 linhas cada, para a orelha/contracapa)", 5)

    doc.add_heading("4. Declarações", 2)
    checklist(doc, [
        "O texto é original e não foi publicado como livro nem está em avaliação em outra editora.",
        "O texto completo foi enviado sem identificação dos autores (avaliação duplo-anônima).",
        "Há autorização por escrito para todas as imagens, tabelas e trechos de terceiros, ou eles são de domínio público/licença aberta.",
        "Estudos com animais ou pessoas têm aprovação ética (CEUA/CEP) e as licenças exigidas (SISBIO, SisGen).",
        "Qualquer uso de IA generativa está descrito abaixo; todas as referências foram conferidas pelos autores.",
    ])
    field_line(doc, "Uso de IA generativa (ferramenta, finalidade, partes do texto) ou \"não utilizada\"", 2)
    field_line(doc, "Prazo desejado ou data relevante (evento, lançamento)")
    field_line(doc, "Interesse em exemplares impressos (quantidade estimada)")
    footer_note(doc, f"{PUBLISHER} · {SITE} · {EMAIL}")
    save(doc, PUBLIC / "CSVET-proposta-livro.docx")


def proposta_coletanea():
    doc = new_doc()
    header_block(doc, "Proposta editorial de coletânea",
                 "Para organizadores(as) de obras com capítulos de diferentes autores. Envie junto com a planilha de convites de capítulos.")
    doc.add_heading("1. A obra", 2)
    field_line(doc, "Título")
    field_line(doc, "Subtítulo")
    field_line(doc, "Tema e ementa: o que o livro cobre e o que fica de fora (até 300 palavras)", 6)
    field_line(doc, "Público-alvo", 2)
    field_line(doc, "Justificativa e diferencial em relação às obras existentes", 4)
    field_line(doc, "Palavras-chave (3 a 6)")
    field_line(doc, "Idioma(s) dos capítulos")

    doc.add_heading("2. Organizadores(as)", 2)
    para(doc, "Até 3 organizadores. Pelo menos um deve ter doutorado ou experiência reconhecida no tema; ao menos um deve ter graduação concluída em medicina veterinária, zootecnia ou área afim.")
    table(doc, [["Nome completo", "Titulação", "Instituição", "ORCID", "E-mail"]] + [[""] * 5 for _ in range(3)],
          widths=[4, 2.5, 4, 3, 3.5])

    doc.add_heading("3. Capítulos previstos", 2)
    para(doc, "Mínimo de 6 capítulos. Os autores convidados também vão na planilha CSVET-convites-capitulos.xlsx, usada pela editora para enviar os convites pela plataforma.")
    table(doc, [["Nº", "Título provisório do capítulo", "Autor(es) convidado(s) e instituição", "Situação"]] + [[str(i), "", "", "convidar / confirmado"] for i in range(1, 11)],
          widths=[1, 6.5, 6.5, 3])

    doc.add_heading("4. Cronograma", 2)
    table(doc, [["Etapa", "Data prevista"],
                ["Aprovação da proposta pelo Conselho Editorial", ""],
                ["Envio dos convites aos autores de capítulo", ""],
                ["Entrega dos capítulos completos (prazo dos autores)", ""],
                ["Revisão prévia pelos organizadores", ""],
                ["Avaliação por pares (até 30 dias)", ""],
                ["Versões finais dos autores", ""],
                ["Produção (revisão, diagramação, ISBN) e publicação", ""]], widths=[11, 5])

    doc.add_heading("5. Responsabilidades dos organizadores", 2)
    checklist(doc, [
        "Convidar os autores, acompanhar os prazos e fazer a revisão prévia de conteúdo de cada capítulo antes da avaliação por pares.",
        "Garantir que todos os capítulos sigam o modelo de capítulo e que cada autor envie o Termo de Autor de Capítulo assinado.",
        "Escrever a Apresentação do livro e sugerir pareceristas (sem conflito de interesses com os autores).",
        "Não incluir capítulo próprio sem avaliação por pares independente, conduzida pela editora.",
        "Manter a confidencialidade dos pareceres e seguir as diretrizes do COPE.",
    ])
    footer_note(doc, f"{PUBLISHER} · {SITE} · {EMAIL}")
    save(doc, PUBLIC / "CSVET-proposta-coletanea.docx")


# =====================================================================  modelos de manuscrito (autores)
def manuscript_styles(doc):
    styles = doc.styles

    def style(name, base="Normal", size=12, bold=False, italic=False, align=None, before=0, after=6, indent_left=0, color=None, spacing=1.5):
        try:
            st = styles[name]
        except KeyError:
            st = styles.add_style(name, WD_STYLE_TYPE.PARAGRAPH)
        st.base_style = styles[base]
        st.font.size = Pt(size)
        st.font.bold = bold
        st.font.italic = italic
        if color is not None:
            st.font.color.rgb = color
        pf = st.paragraph_format
        pf.space_before, pf.space_after = Pt(before), Pt(after)
        pf.line_spacing = spacing
        if indent_left:
            pf.left_indent = Cm(indent_left)
        if align:
            pf.alignment = align
        st.quick_style = True
        return st

    style("Título do livro", size=20, bold=True, align=WD_ALIGN_PARAGRAPH.CENTER, before=24, after=6, color=NAVY, spacing=1.15)
    style("Subtítulo do livro", size=14, italic=True, align=WD_ALIGN_PARAGRAPH.CENTER, after=24, color=GRAY, spacing=1.15)
    style("Autores do capítulo", size=11, italic=True, after=12, spacing=1.15)
    style("Resumo", size=11, after=6, spacing=1.15, align=WD_ALIGN_PARAGRAPH.JUSTIFY)
    style("Citação longa", size=10, indent_left=4, after=12, spacing=1.0, align=WD_ALIGN_PARAGRAPH.JUSTIFY)
    style("Legenda", size=10, before=6, after=2, spacing=1.0, bold=False)
    style("Fonte da figura", size=9.5, after=12, spacing=1.0)
    style("Referência", size=11, after=8, spacing=1.0)
    style("Nota para o autor", size=9.5, italic=True, color=ACCENT, after=8, spacing=1.0)
    for i, size in ((1, 16), (2, 13), (3, 12), (4, 12)):
        h = styles[f"Heading {i}"]
        h.font.size = Pt(size)
        h.paragraph_format.line_spacing = 1.15
    body = styles["Normal"]
    body.paragraph_format.first_line_indent = Cm(1.25)
    body.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    body.paragraph_format.line_spacing = 1.5


def note(doc, text):
    p = para(doc, text, style="Nota para o autor")
    p.paragraph_format.first_line_indent = Cm(0)
    return p


def plain(doc, text, style=None):
    p = para(doc, text, style=style)
    p.paragraph_format.first_line_indent = Cm(0)
    return p


def modelo_livro():
    doc = new_doc(body_font="Times New Roman", body_size=12, heading_font="Arial", margins=(3, 2, 3, 2))
    manuscript_styles(doc)
    note(doc, "MODELO DE LIVRO DA EDITORA CSVET. Escreva por cima dos exemplos e use os ESTILOS do Word (guia Página Inicial › Estilos): "
              "Título 1 para capítulos, Título 2/3/4 para seções, Legenda, Citação longa e Referência. Não diagrame: a editora faz a diagramação "
              "no formato 16×23 cm. Apague todas as notas azuis antes de enviar. Não coloque seu nome em lugar nenhum deste arquivo (avaliação duplo-anônima) "
              "e limpe os metadados (Arquivo › Informações › Verificar se há problemas › Inspecionar documento).")
    para(doc, "Título do livro", style="Título do livro")
    para(doc, "Subtítulo do livro", style="Subtítulo do livro")
    note(doc, "A identificação dos autores, a dedicatória e os agradecimentos entram depois do aceite.")
    doc.add_heading("Apresentação", 1)
    para(doc, "Texto de apresentação do livro (1 a 3 páginas): objetivo, público, organização dos capítulos e como usar a obra. "
              "Parágrafos com recuo de 1,25 cm, espaçamento 1,5 e alinhamento justificado já estão configurados no estilo Normal.")
    doc.add_heading("1 Título do primeiro capítulo", 1)
    para(doc, "Texto do capítulo. Cite as fontes no sistema autor-data (Silva; Souza, 2024) ou numérico [1], conforme o padrão de referências escolhido, "
              "de forma consistente em todo o livro. Nomes científicos em itálico (por exemplo, Leptospira interrogans) e fármacos pelo princípio ativo.")
    doc.add_heading("1.1 Seção secundária", 2)
    para(doc, "Texto da seção. Use no máximo quatro níveis de título (1, 1.1, 1.1.1, 1.1.1.1).")
    doc.add_heading("1.1.1 Seção terciária", 3)
    para(doc, "Citação direta com mais de três linhas vai no estilo Citação longa:")
    plain(doc, "Texto citado com mais de três linhas, recuado 4 cm, em fonte menor e sem aspas, seguido da indicação da fonte com a página "
               "(Autor, ano, p. 00).", style="Citação longa")
    plain(doc, "Figura 1 – Legenda descritiva da figura, que permita entendê-la sem ler o texto", style="Legenda")
    note(doc, "[Insira aqui a figura em boa resolução e envie também o arquivo original separado: fotos ≥ 300 dpi, gráficos em vetor ou 600 dpi.]")
    plain(doc, "Fonte: os autores (2026). / Fonte: Autor (ano), com autorização.", style="Fonte da figura")
    plain(doc, "Tabela 1 – Título da tabela acima dela", style="Legenda")
    table(doc, [["Variável", "Grupo A", "Grupo B"], ["Exemplo", "0,0 ± 0,0", "0,0 ± 0,0"]], widths=[6, 4, 4])
    plain(doc, "Fonte: os autores (2026).", style="Fonte da figura")
    doc.add_heading("Referências", 2)
    note(doc, "Referências ao fim de cada capítulo, em ordem alfabética (ABNT NBR 6023:2018) ou numérica (Vancouver), com DOI quando houver.")
    plain(doc, "SOBRENOME, Nome; SOBRENOME, Nome. Título do artigo. Nome da Revista, v. 0, n. 0, p. 00-00, 2024. DOI: https://doi.org/10.0000/xxxx.", style="Referência")
    doc.add_heading("2 Título do segundo capítulo", 1)
    para(doc, "Comece cada capítulo com Título 1. A editora abre cada capítulo em página ímpar na diagramação.")
    footer = doc.sections[0].footer.paragraphs[0]
    footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
    field(footer, "PAGE", "1")
    save(doc, PUBLIC / "CSVET-modelo-livro.docx")


def modelo_capitulo():
    doc = new_doc(body_font="Times New Roman", body_size=12, heading_font="Arial", margins=(3, 2, 3, 2))
    manuscript_styles(doc)
    note(doc, "MODELO DE CAPÍTULO DE COLETÂNEA DA EDITORA CSVET. Use os estilos do Word e escreva por cima dos exemplos. Extensão orientativa: "
              "15 a 30 páginas neste modelo, incluindo referências. Na versão para avaliação, NÃO identifique os autores (deixe a linha de autores como está); "
              "nome, titulação, instituição e ORCID ficam no cadastro da plataforma. Apague as notas azuis antes de enviar.")
    doc.add_heading("Título do capítulo", 1)
    plain(doc, "[Autores omitidos para avaliação]", style="Autores do capítulo")
    plain(doc, "**Resumo:** até 150 palavras, sem citações, apresentando o objetivo, o conteúdo principal e a contribuição do capítulo.", style="Resumo")
    plain(doc, "**Palavras-chave:** termo 1; termo 2; termo 3.", style="Resumo")
    doc.add_heading("1 Introdução", 2)
    para(doc, "Apresente o tema, a importância e o objetivo do capítulo. Parágrafos com recuo de 1,25 cm e espaçamento 1,5.")
    doc.add_heading("2 Desenvolvimento", 2)
    para(doc, "Organize o conteúdo em seções numeradas (2, 2.1, 2.1.1). Figuras e tabelas numeradas por capítulo, com legenda e fonte, como no modelo de livro.")
    doc.add_heading("2.1 Seção secundária", 3)
    para(doc, "Texto da seção.")
    plain(doc, "Figura 1 – Legenda descritiva da figura", style="Legenda")
    note(doc, "[Insira a figura e envie o arquivo original separado, em alta resolução.]")
    plain(doc, "Fonte: os autores (2026).", style="Fonte da figura")
    doc.add_heading("3 Considerações finais", 2)
    para(doc, "Síntese e aplicações práticas do conteúdo.")
    doc.add_heading("Referências", 2)
    plain(doc, "SOBRENOME, Nome. Título. Edição. Cidade: Editora, ano.", style="Referência")
    footer = doc.sections[0].footer.paragraphs[0]
    footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
    field(footer, "PAGE", "1")
    save(doc, PUBLIC / "CSVET-modelo-capitulo.docx")


# =====================================================================  declarações e termos
def declaracao_autores():
    doc = new_doc()
    header_block(doc, "Declaração de originalidade, autoria, direitos e licença",
                 "Assinada por todos os autores (ou organizadores, nas coletâneas) e enviada na submissão.")
    field_line(doc, "Título da obra")
    field_line(doc, "Coleção")
    field_line(doc, "Autores/organizadores, na ordem da capa", 2)
    para(doc, "Os signatários declaram, sob as penas da lei, que:")
    checklist(doc, [
        "A obra é original, de sua autoria, e não foi publicada como livro nem está em avaliação em outra editora. Trabalhos acadêmicos depositados em repositório e partes publicadas como artigos estão declarados na proposta.",
        "Todos os signatários contribuíram substancialmente para a obra, aprovaram a versão submetida e concordam com a submissão; ninguém que mereça autoria foi omitido.",
        "Obras derivadas de trabalho acadêmico: orientador(a), coorientador(a) e banca foram cadastrados com os papéis próprios (não como autores), salvo coautoria efetiva na versão em livro, conforme a página Publicar TCC, dissertação ou tese como livro.",
        "A obra não viola direitos de terceiros: há autorização por escrito para imagens, tabelas e trechos reproduzidos, e para imagens de pessoas identificáveis, ou eles são de domínio público/licença aberta, com a fonte indicada.",
        "Estudos com animais ou com pessoas foram aprovados pelas comissões de ética competentes (CEUA/CEP) e têm as licenças exigidas; os tutores consentiram com o uso de dados e imagens.",
        "Os dados e as imagens são autênticos; qualquer uso de IA generativa está declarado; a IA não é autora; todas as referências foram conferidas.",
        "Conflitos de interesse e fontes de financiamento estão declarados na obra.",
        "Concordam que erros ou má conduta identificados após a publicação serão tratados por errata, manifestação de preocupação ou retratação, conforme o COPE.",
    ])
    para(doc, "**Direitos e licença.** Os autores mantêm os direitos autorais e, se a obra for aceita, concederão à Editora CSVET o direito de primeira publicação "
              "e de distribuição nas versões digital e impressa, nos termos do contrato de edição. A obra será publicada em acesso aberto sob a licença "
              "Creative Commons Atribuição 4.0 Internacional (CC BY 4.0), salvo opção abaixo:", align="justify")
    checklist(doc, ["CC BY 4.0 (padrão: permite reutilização com crédito, inclusive comercial)",
                    "CC BY-NC-ND 4.0 (somente se justificado: proíbe uso comercial e obras derivadas por terceiros)"])
    para(doc, "**Modelo de publicação preferido** (definido no contrato de edição, após o aceite; não influencia a avaliação):")
    checklist(doc, ["Acesso aberto (e-book gratuito; impresso opcional sob demanda)",
                    "Comercial (e-book e impresso pagos; cessão ou licença exclusiva por prazo determinado, com direitos autorais)",
                    "Híbrido (pago no lançamento e aberto após o embargo)"])
    para(doc, "**Custos.** Os autores conhecem a política de custos da editora (sem taxa de submissão nem de avaliação) e sabem que a avaliação e as decisões "
              "editoriais independem de qualquer pagamento.", align="justify")
    signature(doc, ["Autor(a)/Organizador(a) 1", "Autor(a)/Organizador(a) 2", "Autor(a)/Organizador(a) 3"])
    footer_note(doc, f"{PUBLISHER} · {COMPANY} · CNPJ {CNPJ}")
    save(doc, PUBLIC / "CSVET-declaracao-autores.docx")


def termo_capitulo():
    doc = new_doc()
    header_block(doc, "Termo de Autor de Capítulo",
                 "Um termo por capítulo, assinado por todos os autores do capítulo e enviado com o texto (pela área de discussões do livro).")
    field_line(doc, "Título do livro (coletânea)")
    field_line(doc, "Organizador(es)")
    field_line(doc, "Título do capítulo")
    table(doc, [["Autor(a) do capítulo", "Titulação e instituição", "ORCID", "E-mail"]] + [[""] * 4 for _ in range(4)], widths=[4.5, 5, 3, 4])
    para(doc, "Os autores do capítulo declaram que:")
    checklist(doc, [
        "O capítulo é original e inédito, de sua autoria, e não foi publicado nem submetido a outro livro ou revista.",
        "Todos os autores contribuíram para o capítulo, aprovaram a versão enviada e concordam com sua publicação na coletânea.",
        "Há autorização por escrito para imagens, tabelas e trechos de terceiros, ou eles são de domínio público/licença aberta, com a fonte indicada.",
        "Estudos com animais ou pessoas têm aprovação ética e licenças; os tutores consentiram com o uso de dados e imagens.",
        "Qualquer uso de IA generativa está declarado; todas as referências foram conferidas; conflitos de interesse e financiamento estão declarados no capítulo.",
        "Concordam que o capítulo passe por avaliação por pares duplo-anônima e pela revisão dos organizadores, podendo ser recusado.",
    ])
    para(doc, "**Direitos e licença.** Os autores mantêm os direitos autorais sobre o capítulo e autorizam, gratuitamente, a Editora CSVET a publicá-lo e "
              "distribuí-lo na coletânea, nas versões digital e impressa, em acesso aberto sob a licença Creative Commons Atribuição 4.0 Internacional "
              "(CC BY 4.0), inclusive em página própria do capítulo, com DOI quando houver. Não há remuneração nem taxa para os autores de capítulo. "
              "Os autores podem depositar a versão publicada em repositórios, com link para a página do livro.", align="justify")
    signature(doc, ["Autor(a) 1 do capítulo", "Autor(a) 2 do capítulo", "Autor(a) 3 do capítulo"])
    footer_note(doc, f"{PUBLISHER} · {COMPANY} · CNPJ {CNPJ}")
    save(doc, PUBLIC / "CSVET-termo-autor-capitulo.docx")


def anuencia():
    doc = new_doc()
    header_block(doc, "Anuência do(a) orientador(a)",
                 "Para monografias (TCC, especialização, residência), dissertações e teses publicadas como livro.")
    para(doc, f"Eu, {LINE[:45]}, (titulação) {LINE[:20]}, "
              f"vinculado(a) a {LINE[:40]}, orientador(a) do trabalho "
              f"\"{LINE[:50]}\", de autoria de {LINE[:40]}, aprovado em ____/____/______ no curso/programa "
              f"{LINE[:40]}, declaro que:", align="justify")
    checklist(doc, [
        "Concordo com a publicação do trabalho como livro pela Editora CSVET, na versão adaptada pelo(a) autor(a).",
        "Fui consultado(a) sobre a forma de crédito na obra e opto por constar como:",
    ])
    checklist(doc, ["   Orientador(a): crédito de orientação na folha de rosto, na nota de origem e na página do livro (não sou autor(a) do livro)",
                    "   Coautor(a): além de orientar, escrevi ou revisei criticamente a versão em livro, aprovei a versão final e respondo pelo conteúdo (critérios COPE/ICMJE)",
                    "   Não desejo constar na obra"])
    para(doc, "**Banca examinadora** do trabalho original (nome, titulação e instituição). A editora confirma por e-mail a concordância de cada membro em ser citado na nota de origem; membros da banca não são autores do livro.")
    table(doc, [["Nome", "Titulação", "Instituição", "E-mail"]] + [[""] * 4 for _ in range(3)], widths=[5, 2.5, 5, 4])
    field_line(doc, "Coorientador(a), se houver (nome, titulação, instituição, e-mail)")
    checklist(doc, ["Não tenho conhecimento de plágio, fabricação de dados ou outra irregularidade no trabalho."])
    signature(doc, "Orientador(a)")
    footer_note(doc, f"{PUBLISHER} · {COMPANY} · CNPJ {CNPJ}")
    save(doc, PUBLIC / "CSVET-anuencia-orientador.docx")


def contrato():
    doc = new_doc()
    header_block(doc, "Contrato de edição (minuta)",
                 "Modelo de referência para obras autorais e coletâneas, nas modalidades aberta, comercial e híbrida. "
                 "Revisar com advogado antes do primeiro uso; adaptar os campos entre colchetes.")
    para(doc, f"**EDITORA:** {COMPANY}, inscrita no CNPJ sob o nº {CNPJ}, com sede em {CITY}, que publica sob a marca {PUBLISHER}, "
              f"neste ato representada por seu diretor, {DIRECTOR}.", align="justify")
    para(doc, f"**AUTOR(ES)/ORGANIZADOR(ES):** {LINE[:40]}, CPF {LINE[:16]}, endereço {LINE[:40]}, e-mail {LINE[:25]} "
              "(repetir para cada autor ou organizador).", align="justify")
    para(doc, "As partes celebram este contrato de edição, nos termos dos arts. 49 a 67 da Lei nº 9.610/1998 (Lei de Direitos Autorais), mediante as cláusulas seguintes:", align="justify")
    para(doc, "**MODALIDADE DE PUBLICAÇÃO** (marque uma):")
    checklist(doc, [
        "**A. Acesso aberto:** licença não exclusiva à EDITORA; versão digital gratuita sob CC BY 4.0; impresso opcional sob demanda.",
        "**B. Comercial:** [cessão / licença exclusiva] dos direitos patrimoniais de edição à EDITORA pelo prazo de [5] anos; e-book e impresso pagos; direitos autorais aos AUTORES.",
        "**C. Híbrida:** modalidade B por [12/24] meses a contar da publicação; depois, a versão digital passa à modalidade A (CC BY 4.0).",
    ])
    clauses = [
        "**Objeto.** O(s) AUTOR(ES) autoriza(m) a EDITORA a editar, reproduzir e distribuir a obra intitulada \"[título]\" (a OBRA), aprovada pelo Conselho Editorial após avaliação por pares, nas versões digital (PDF/EPUB) e impressa, na modalidade marcada acima.",
        "**Direitos morais.** Os direitos morais (autoria, integridade, inéditos) pertencem aos AUTORES e são inalienáveis (art. 27 da Lei nº 9.610/1998).",
        "**Modalidade A (acesso aberto).** Os AUTORES mantêm os direitos patrimoniais e concedem à EDITORA licença não exclusiva, gratuita e por prazo indeterminado de primeira publicação e de distribuição. A versão digital é publicada sob a licença Creative Commons Atribuição 4.0 Internacional (CC BY 4.0), irrevogável para as cópias distribuídas. A EDITORA pode vender exemplares impressos sob demanda, repassando aos AUTORES [10]% do valor líquido.",
        "**Modalidade B (comercial).** Os AUTORES [cedem / licenciam com exclusividade] à EDITORA, pelo prazo de [5] anos, os direitos patrimoniais de edição, reprodução, distribuição e comercialização da OBRA em formato digital e impresso, no Brasil e no exterior, inclusive por lojas digitais e impressão sob demanda. Findo o prazo sem renovação, os direitos revertem integralmente aos AUTORES.",
        "**Preço e direitos autorais (modalidades B e C).** O preço é definido pela EDITORA. Os AUTORES recebem [20]% do valor líquido recebido pela EDITORA em cada venda de e-book e [10]% em cada exemplar impresso, divididos entre os AUTORES na proporção [igual/indicada], com prestação de contas e pagamento [semestrais], por Pix ou transferência.",
        "**Coletâneas.** Os ORGANIZADORES coordenam a obra e apresentam os Termos de Autor de Capítulo assinados; cada autor mantém os direitos morais sobre o seu capítulo, e os direitos patrimoniais seguem a modalidade deste contrato.",
        "**Proteção da OBRA.** Nas modalidades B e C, a OBRA traz aviso de todos os direitos reservados e de proibição de uso para treinamento de sistemas de inteligência artificial e de mineração de texto e dados sem autorização. A EDITORA pode adotar medidas extrajudiciais e judiciais contra reprodução, venda ou distribuição não autorizadas, mantendo os AUTORES informados.",
        "**Obrigações da EDITORA.** Conduzir a avaliação por pares; realizar normalização, diagramação, capa e provas; obter ISBN e ficha catalográfica junto à Câmara Brasileira do Livro (CBL); fazer o depósito legal na Biblioteca Nacional (Lei nº 10.994/2004); publicar a OBRA no portal books.csvet.com.br e divulgar seus metadados a indexadores e lojas.",
        "**Obrigações dos AUTORES.** Entregar a versão final nos prazos combinados; revisar as provas em até [10] dias; garantir a originalidade da OBRA e a autorização de uso de conteúdo de terceiros, respondendo por eventual violação de direitos; informar conflitos de interesse.",
        "**Custos.** Não há taxa de submissão, avaliação ou publicação. Serviços opcionais solicitados pelos AUTORES (revisão de linguagem, tradução, exemplares para uso próprio) constam de orçamento anexo, aprovado antes da execução.",
        "**Repositórios.** Os AUTORES podem depositar a versão aceita em repositório institucional: na modalidade A, imediatamente; nas modalidades B e C, após [12] meses da publicação ou ao fim do embargo.",
        "**Prazo de publicação.** A EDITORA publicará a OBRA em até [6] meses após a entrega da versão final aprovada, salvo atraso causado pelos AUTORES.",
        "**Correções e retratação.** Erros identificados após a publicação serão corrigidos por errata; casos de má conduta seguirão as diretrizes do COPE, podendo levar à retratação da OBRA, mantido o registro público.",
        "**Dados pessoais.** Os dados pessoais das partes são usados somente para a execução deste contrato, nos termos da Lei nº 13.709/2018 (LGPD); nome, afiliação, ORCID e minicurrículo constarão da OBRA e do portal.",
        "**Assinatura eletrônica.** As partes reconhecem a validade da assinatura eletrônica deste contrato (gov.br, certificado ICP-Brasil ou plataforma equivalente), nos termos da MP nº 2.200-2/2001 e da Lei nº 14.063/2020. O contrato assinado é anexado à submissão na plataforma da EDITORA.",
        "**Rescisão.** O contrato pode ser rescindido por descumprimento, mediante notificação com prazo de 30 dias para correção. A licença aberta das cópias já publicadas permanece válida.",
        f"**Foro.** Fica eleito o foro da Comarca de {CITY.split(',')[0]}/SP.",
    ]
    numbered(doc, clauses, prefix="Cláusula ")
    signature(doc, [f"{COMPANY} – {DIRECTOR}, Diretor", "Autor(a)/Organizador(a)", "Testemunha 1", "Testemunha 2"])
    footer_note(doc, "Minuta de referência – revisão jurídica obrigatória antes do uso.")
    save(doc, INTERNAL / "contrato-edicao-minuta.docx")


def carta_avaliacao():
    """Declaração emitida pela editora para cada livro publicado (pedida pela CAPES e pelos programas de pós-graduação)."""
    doc = new_doc()
    header_block(doc, "Declaração de avaliação por pares e de publicação", "Emitida pela Editora CSVET para cada obra publicada")
    para(doc, "Declaramos, para os devidos fins, que a obra abaixo foi publicada pela Editora CSVET (CSVET Editora, Cursos e Treinamentos, "
              f"CNPJ {CNPJ}), após o seguinte processo editorial:", align="justify")
    table(doc, [["Dado", "Informação"],
                ["Título", "[título completo]"],
                ["Autores / organizadores", "[nomes, na ordem da capa]"],
                ["Coleção", "[coleção]"],
                ["ISBN", "[ISBN-13 de cada formato]"],
                ["Ficha catalográfica", "Emitida pela Câmara Brasileira do Livro (CBL)"],
                ["Número de páginas", "[nº]"],
                ["Data de submissão", "[dd/mm/aaaa]"],
                ["Avaliação interna", "Conselho Editorial, em [dd/mm/aaaa]"],
                ["Avaliação externa", "Duplo-anônima, por [2] pareceristas doutores externos às instituições dos autores, em [dd/mm/aaaa]"],
                ["Aprovação final", "Conselho Editorial, em [dd/mm/aaaa]"],
                ["Data de publicação", "[dd/mm/aaaa]"],
                ["Acesso", "[Acesso aberto (CC BY 4.0) / Comercial]"],
                ["Endereço da obra", "https://books.csvet.com.br/editora/catalog/book/[id]"]], widths=[5, 11])
    para(doc, "O Conselho Editorial da editora é composto por doutores de diferentes instituições, listados em "
              "https://books.csvet.com.br/editora/about/editorialMasthead. Os pareceres ficam arquivados na plataforma editorial e podem "
              "ser apresentados, sem identificação dos pareceristas, mediante solicitação de comissão de avaliação.", align="justify")
    signature(doc, "Prof. Dr. Atilio Sersun Calefi – Editor-chefe, Editora CSVET",
              note="Assinatura eletrônica (gov.br ou certificado digital). A autenticidade pode ser confirmada em contato@csvet.com.br.")
    footer_note(doc, f"{PUBLISHER} · {COMPANY} · CNPJ {CNPJ} · {SITE}")
    save(doc, INTERNAL / "declaracao-avaliacao-pares.docx")


def termo_voluntario():
    doc = new_doc()
    header_block(doc, "Termo de Adesão ao Serviço Voluntário", "Conselho Editorial e editores de coleção da Editora CSVET")
    para(doc, f"**ENTIDADE:** {COMPANY}, inscrita no CNPJ sob o nº {CNPJ}, com sede em {CITY}, que publica sob a marca {PUBLISHER}, neste ato representada por seu diretor, {DIRECTOR}.", align="justify")
    para(doc, f"**VOLUNTÁRIO(A):** {LINE[:45]}, CPF nº {LINE[:16]}, residente em {LINE[:40]}, e-mail {LINE[:25]}, ORCID {LINE[:22]}.", align="justify")
    para(doc, "As partes celebram este Termo de Adesão ao Serviço Voluntário, com fundamento na Lei nº 9.608/1998, mediante as cláusulas seguintes:", align="justify")
    numbered(doc, [
        "**Objeto.** O(A) VOLUNTÁRIO(A) prestará serviço voluntário à ENTIDADE na função de ______________________ (Membro do Conselho Editorial ou Editor(a) de coleção), área de ______________________, compreendendo a avaliação interna de propostas, a indicação e o acompanhamento de pareceristas e a orientação da política editorial descritas em https://books.csvet.com.br/editora/about.",
        "**Natureza voluntária e gratuita.** O serviço é espontâneo, gratuito e não gera vínculo empregatício, funcional, societário, previdenciário ou de prestação de serviços (art. 1º, parágrafo único, da Lei nº 9.608/1998).",
        "**Despesas.** Despesas só serão ressarcidas se previamente autorizadas por escrito (art. 3º da Lei nº 9.608/1998); o ressarcimento não caracteriza remuneração.",
        "**Forma.** Não há jornada, horário ou subordinação. As atividades são remotas, pela plataforma da editora, e o(a) VOLUNTÁRIO(A) pode recusar qualquer tarefa.",
        "**Confidencialidade e integridade.** O(A) VOLUNTÁRIO(A) manterá sigilo sobre as obras e pareceres, não os inserirá em ferramentas de IA generativa, declarará conflitos de interesse e seguirá as diretrizes do COPE.",
        "**Dados e LGPD.** O(A) VOLUNTÁRIO(A) autoriza a divulgação de nome, afiliação, país, função e ORCID na página da equipe editorial e nas obras, podendo revogar a autorização; CPF e endereço servem só para formalizar este termo (Lei nº 13.709/2018).",
        "**Vigência.** Prazo indeterminado; encerramento a qualquer tempo, por qualquer das partes, por simples comunicação escrita (inclusive e-mail).",
        f"**Foro.** Comarca de {CITY.split(',')[0]}/SP.",
    ], prefix="Cláusula ")
    signature(doc, [f"{COMPANY} – {DIRECTOR}, Diretor", "VOLUNTÁRIO(A)"])
    save(doc, INTERNAL / "termo-adesao-voluntario.docx")


# =====================================================================  diagramação (padrão gráfico)
FORMATS = {
    "16x23": dict(w=160, h=230, inner=22, outer=16, top=20, bottom=22, body=10.5, lead=14.5, h1=20, h2=13, h3=11, photo=28),
    "21x28": dict(w=210, h=280, inner=25, outer=18, top=22, bottom=24, body=10.5, lead=14.5, h1=24, h2=14, h3=11.5, photo=32),
}
SERIF = "Source Serif 4"
SANS = "Roboto"
PHOTO = ROOT / "branding" / "assets" / "foto-autor-exemplo.png"
FIGURE = ROOT / "branding" / "assets" / "figura-exemplo.png"
UPDATE_FIELDS_AFTER = ("w:hdrShapeDefaults", "w:footnotePr", "w:endnotePr", "w:compat", "w:docVars", "w:rsids", "m:mathPr",
                       "w:attachedSchema", "w:themeFontLang", "w:clrSchemeMapping", "w:doNotIncludeSubdocsInStats",
                       "w:doNotAutoCompressPictures", "w:forceUpgrade", "w:captions", "w:readModeInkLockDown", "w:smartTagType",
                       "sl:schemaLibrary", "w:shapeDefaults", "w:doNotEmbedSmartTags", "w:decimalSymbol", "w:listSeparator")


def example_images():
    """Foto 3x4 e figura de exemplo (marcadores de posição, substituídos pelas imagens reais)."""
    from PIL import Image, ImageDraw, ImageFont
    sans = ROOT / "branding" / "fonts" / "Roboto-500.ttf"
    if not PHOTO.exists():
        img = Image.new("RGB", (600, 800), (228, 231, 240))
        d = ImageDraw.Draw(img)
        d.ellipse([200, 170, 400, 370], fill=(176, 182, 204))
        d.rounded_rectangle([110, 400, 490, 800], radius=150, fill=(176, 182, 204))
        d.text((300, 740), "FOTO 3x4", font=ImageFont.truetype(str(sans), 44), fill=(90, 96, 128), anchor="ms")
        img.save(PHOTO, optimize=True)
    if not FIGURE.exists():
        img = Image.new("RGB", (1600, 900), (238, 241, 248))
        d = ImageDraw.Draw(img)
        d.rectangle([0, 0, 1599, 899], outline=(0, 136, 204), width=8)
        d.line([(0, 0), (1600, 900)], fill=(196, 202, 222), width=4)
        d.line([(0, 900), (1600, 0)], fill=(196, 202, 222), width=4)
        d.text((800, 470), "Figura (300 dpi no tamanho de impressão)", font=ImageFont.truetype(str(sans), 56), fill=(0, 0, 128), anchor="mm")
        img.save(FIGURE, optimize=True)


def page_break(doc, odd=True):
    """Nova seção começando em página ímpar (padrão editorial: capítulos à direita)."""
    return doc.add_section(WD_SECTION.ODD_PAGE if odd else WD_SECTION.NEW_PAGE)


def field_block(doc, instr, lines, style):
    """Campo do Word (TOC, INDEX) que ocupa vários parágrafos, já com um resultado de exemplo.
    O Word recalcula o conteúdo ao abrir (updateFields) ou com F9."""
    paragraphs = []
    for i, line in enumerate(lines):
        p = doc.add_paragraph(style=style)
        if i == 0:
            r = p.add_run()
            b = OxmlElement("w:fldChar")
            b.set(qn("w:fldCharType"), "begin")
            b.set(qn("w:dirty"), "true")
            r._r.append(b)
            r = p.add_run()
            t = OxmlElement("w:instrText")
            t.set(qn("xml:space"), "preserve")
            t.text = f" {instr} "
            r._r.append(t)
            r = p.add_run()
            s = OxmlElement("w:fldChar")
            s.set(qn("w:fldCharType"), "separate")
            r._r.append(s)
        p.add_run(line)
        paragraphs.append(p)
    r = paragraphs[-1].add_run()
    e = OxmlElement("w:fldChar")
    e.set(qn("w:fldCharType"), "end")
    r._r.append(e)
    return paragraphs


def index_term(paragraph, term):
    """Marca um termo para o índice remissivo (campo XE, invisível na impressão)."""
    field(paragraph, f'XE "{term}"', "")


def diagramacao(fmt):
    from docx.enum.text import WD_TAB_ALIGNMENT, WD_TAB_LEADER

    f = FORMATS[fmt]
    text_width = Mm(f["w"] - f["inner"] - f["outer"])
    doc = Document()
    settings = doc.settings.element
    settings.insert_element_before(OxmlElement("w:mirrorMargins"), *SETTINGS_AFTER_MIRROR)
    update = OxmlElement("w:updateFields")
    update.set(qn("w:val"), "true")
    settings.insert_element_before(update, *UPDATE_FIELDS_AFTER)  # o Word oferece atualizar sumário e índice ao abrir
    doc.settings.odd_and_even_pages_header_footer = True
    sec = doc.sections[0]
    sec.page_width, sec.page_height = Mm(f["w"]), Mm(f["h"])
    sec.left_margin, sec.right_margin = Mm(f["inner"]), Mm(f["outer"])
    sec.top_margin, sec.bottom_margin = Mm(f["top"]), Mm(f["bottom"])
    sec.header_distance, sec.footer_distance = Mm(10), Mm(10)

    st = doc.styles["Normal"]
    _font(st, SERIF)
    st.font.size = Pt(f["body"])
    pf = st.paragraph_format
    pf.line_spacing = Pt(f["lead"])  # entrelinha exata no texto corrido (mancha regular)
    pf.space_after = Pt(0)
    pf.first_line_indent = Mm(5)
    pf.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
    pf.widow_control = True
    for i, size in ((1, f["h1"]), (2, f["h2"]), (3, f["h3"])):
        h = doc.styles[f"Heading {i}"]
        _font(h, SANS)
        h.font.size = Pt(size)
        h.font.bold = i > 1
        h.font.color.rgb = NAVY
        h.paragraph_format.first_line_indent = Mm(0)
        h.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.LEFT
        h.paragraph_format.space_before = Pt(0 if i == 1 else 14)
        h.paragraph_format.space_after = Pt(28 if i == 1 else 6)
        h.paragraph_format.keep_with_next = True
        h.paragraph_format.line_spacing = 1.1
    extra = {}
    for name, size, italic, bold, align, color in [
        ("Rótulo do capítulo", 10, False, True, "left", ACCENT),
        ("Autores do capítulo", 10, True, False, "left", GRAY),
        ("Resumo", 9.5, False, False, "justify", None),
        ("Legenda", 9, False, False, "left", None),
        ("Fonte da figura", 8.5, False, False, "left", GRAY),
        ("Citação longa", 9.5, False, False, "justify", None),
        ("Referência", 9, False, False, "left", None),
        ("Créditos", 8.5, False, False, "left", None),
        ("Expediente", 8.5, False, False, "left", None),
        ("Folha de rosto", 12, False, False, "center", None),
        ("Figura", 10, False, False, "center", None),
        ("Bio", 9.5, False, False, "justify", None),
        ("toc 1", 10, False, True, "left", None),
        ("toc 2", 9.5, False, False, "left", None),
        ("index 1", 9, False, False, "left", None),
    ]:
        s = doc.styles.add_style(name, WD_STYLE_TYPE.PARAGRAPH)
        s.base_style = doc.styles["Normal"]
        s.font.size = Pt(size)
        s.font.italic = italic
        s.font.bold = bold
        if color is not None:
            s.font.color.rgb = color
        p = s.paragraph_format
        p.first_line_indent = Mm(0)
        p.alignment = {"left": WD_ALIGN_PARAGRAPH.LEFT, "justify": WD_ALIGN_PARAGRAPH.JUSTIFY, "center": WD_ALIGN_PARAGRAPH.CENTER}[align]
        p.line_spacing = Pt(size * 1.35)
        p.space_after = Pt(4)
        if name in ("Rótulo do capítulo", "Autores do capítulo", "Legenda", "Expediente", "toc 1"):
            _font(s, SANS)
        if name == "Citação longa":
            p.left_indent = Mm(12)
            p.space_before, p.space_after = Pt(6), Pt(8)
        if name == "Referência":
            p.left_indent, p.first_line_indent = Mm(5), Mm(-5)
        if name == "Figura":
            # Entrelinha simples (múltipla): com entrelinha exata o Word corta imagens e logos
            p.line_spacing = 1.0
            p.space_before, p.space_after = Pt(8), Pt(4)
            p.keep_with_next = True
        if name in ("toc 1", "toc 2"):
            p.tab_stops.add_tab_stop(text_width, WD_TAB_ALIGNMENT.RIGHT, WD_TAB_LEADER.DOTS)
            p.space_before = Pt(6 if name == "toc 1" else 0)
            p.space_after = Pt(2)
        if name == "toc 2":
            p.left_indent = Mm(6)
        if name == "index 1":
            p.left_indent, p.first_line_indent = Mm(4), Mm(-4)
        extra[name] = s

    def blank(n=1, style="Folha de rosto"):
        for _ in range(n):
            para(doc, "", style=style)

    def picture(path, width):
        doc.add_picture(str(path), width=width)
        p = doc.paragraphs[-1]
        p.style = extra["Figura"]
        return p

    def no_borders(t):
        tblpr = t._tbl.tblPr
        borders = OxmlElement("w:tblBorders")
        for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
            el = OxmlElement(f"w:{edge}")
            el.set(qn("w:val"), "nil")
            borders.append(el)
        tblpr.append(borders)

    def cell_text(cell, text, style, bold=False):
        cell.text = ""
        p = cell.paragraphs[0]
        p.style = extra[style]
        add_runs(p, text, bold=bold)
        return p

    # ---- falsa folha de rosto (p. i)
    blank(8)
    para(doc, "TÍTULO DO LIVRO", style="Folha de rosto", bold=True, size=16, color=NAVY)

    # ---- verso: expediente com titulação (p. ii)
    doc.paragraphs[-1].runs[-1].add_break(WD_BREAK.PAGE)
    para(doc, PUBLISHER.upper(), style="Expediente", bold=True, color=NAVY)
    rows = [
        ("Editor-chefe", "Prof. Dr. Atilio Sersun Calefi – Universidade Cruzeiro do Sul, Brasil"),
        ("Conselho Editorial", "Prof.ª Dr.ª Nome Sobrenome – Instituição, País\nProf. Dr. Nome Sobrenome – Instituição, País\n"
                               "Prof.ª Dr.ª Nome Sobrenome – Instituição, País\nProf. Dr. Nome Sobrenome – Instituição, País"),
        ("Pareceristas desta obra", "Prof. Dr. Nome Sobrenome – Instituição, País\nProf.ª Dr.ª Nome Sobrenome – Instituição, País\n"
                                    "(avaliação duplo-anônima; nomes publicados após a aprovação, com autorização dos pareceristas)"),
        ("Revisão de texto", "Me. Nome Sobrenome – Letras, Instituição"),
        ("Normalização", "Nome Sobrenome – Bibliotecário(a), CRB-0/0000"),
        ("Diagramação", "Nome Sobrenome"),
        ("Capa", "Nome Sobrenome (imagem: fonte e licença)"),
    ]
    t = doc.add_table(rows=len(rows), cols=2)
    no_borders(t)
    for i, (role, names) in enumerate(rows):
        cell_text(t.cell(i, 0), role, "Expediente", bold=True)
        cell_text(t.cell(i, 1), names, "Expediente")
        t.cell(i, 0).width, t.cell(i, 1).width = Mm(32), text_width - Mm(32)

    # ---- folha de rosto (p. iii)
    page = doc.add_paragraph(style=extra["Folha de rosto"])
    page.add_run().add_break(WD_BREAK.PAGE)
    blank(1)
    para(doc, "Nome do Autor · Nome da Autora", style="Folha de rosto", size=12)
    para(doc, "(Organizadores), nas coletâneas", style="Folha de rosto", italic=True, size=9, color=GRAY)
    blank(4)
    para(doc, "TÍTULO DO LIVRO", style="Folha de rosto", bold=True, size=f["h1"], color=NAVY)
    para(doc, "Subtítulo do livro", style="Folha de rosto", italic=True, size=13)
    blank(1)
    para(doc, "Orientação: Prof. Dr. Nome do Orientador (somente monografias, dissertações e teses)", style="Folha de rosto", italic=True, size=9, color=GRAY)
    blank(1)
    para(doc, "1ª edição", style="Folha de rosto", size=10)
    blank(6 if fmt == "16x23" else 10)
    if LOGO.exists():
        picture(LOGO, Mm(42))
    para(doc, f"{CITY.replace(', ', ' – ')}  ·  {date.today().year}", style="Folha de rosto", size=9.5)

    # ---- verso da folha de rosto: direitos, nota de origem, ficha CBL, ISBN (p. iv)
    doc.paragraphs[-1].runs[-1].add_break(WD_BREAK.PAGE)
    para(doc, f"© {date.today().year} os autores. Publicado por {PUBLISHER} ({COMPANY}, CNPJ {CNPJ}).", style="Créditos")
    para(doc, "Esta obra está licenciada sob a licença Creative Commons Atribuição 4.0 Internacional (CC BY 4.0): "
              "https://creativecommons.org/licenses/by/4.0/deed.pt-br. O reuso exige a citação dos autores, da obra e da Editora CSVET; "
              "a venda desta edição por terceiros como se fosse própria, a omissão da autoria e o plágio violam a licença e a Lei nº 9.610/1998.", style="Créditos")
    para(doc, "Avaliação por pares duplo-anônima. Esta obra foi aprovada pelo Conselho Editorial após parecer de especialistas externos.", style="Créditos")
    para(doc, "Nota de origem (obras derivadas de trabalho acadêmico): Esta obra é uma versão revista e adaptada do [Trabalho de Conclusão de Curso / "
              "Monografia de Especialização / Dissertação / Tese] ([curso ou programa]) de [Autor], aprovado em [data] na [instituição], sob orientação "
              "de [Prof. Dr. Nome] [e coorientação de ...]. Banca examinadora: [nomes e instituições, com a concordância de cada membro]. "
              "Orientação e banca não são autoras desta edição.", style="Créditos", italic=True)
    para(doc, "Nota editorial (somente obras de membros da editora): Os autores integram a Editora CSVET. A avaliação desta obra foi conduzida "
              "de forma independente por [Prof.ª Dr.ª Nome] (Conselho Editorial), sem participação dos autores na escolha dos pareceristas nem na decisão.",
         style="Créditos", italic=True)
    para(doc, "[Obras comerciais, em vez da licença CC BY acima:] Todos os direitos reservados. Nenhuma parte desta obra pode ser reproduzida, "
              "distribuída, vendida, transmitida ou usada para treinamento de sistemas de inteligência artificial sem autorização prévia e por escrito "
              "da editora, exceto citações com indicação da fonte (Lei nº 9.610/1998). A violação de direitos autorais é crime (Código Penal, art. 184).",
         style="Créditos", italic=True)
    blank(1, "Créditos")
    t = doc.add_table(rows=1, cols=1)
    t.style = "Table Grid"
    c = t.cell(0, 0)
    cell_text(c, "Dados Internacionais de Catalogação na Publicação (CIP)", "Créditos", bold=True).alignment = WD_ALIGN_PARAGRAPH.CENTER
    for line in ["[Colar aqui a ficha catalográfica emitida pela CBL, sem alterar]", "", "", "", ""]:
        c.add_paragraph(line, style=extra["Créditos"])
    blank(1, "Créditos")
    para(doc, "ISBN 978-65-00-00000-0 (PDF)  ·  ISBN 978-65-00-00000-0 (impresso)", style="Créditos", bold=True)
    para(doc, "DOI https://doi.org/10.00000/xxxx (quando houver)", style="Créditos")
    para(doc, f"{PUBLISHER} · {CITY} · {EMAIL} · {SITE}", style="Créditos")

    # ---- sumário (ímpar), já preenchido com um exemplo; F9 recalcula
    page_break(doc)
    doc.add_heading("Sumário", 1)
    field_block(doc, 'TOC \\o "1-2" \\h \\z \\u', [
        "Apresentação\tvii",
        "Título do capítulo 1\t1",
        "1.1 Seção\t2",
        "Referências\t4",
        "Título do capítulo 2\t5",
        "2.1 Seção\t6",
        "Referências\t8",
        "Sobre os autores\t9",
        "Índice remissivo\t11",
    ], extra["toc 2"])
    page_break(doc)
    doc.add_heading("Apresentação", 1)
    para(doc, "Texto da apresentação (organizadores) ou do prefácio (convidado): objetivo do livro, público, organização dos capítulos "
              "e como usar a obra.")

    # ---- miolo: capítulos (ímpar), numeração arábica a partir de 1
    main = page_break(doc)
    pg = OxmlElement("w:pgNumType")
    pg.set(qn("w:start"), "1")
    main._sectPr.insert_element_before(pg, "w:cols", "w:formProt", "w:vAlign", "w:noEndnote", "w:titlePg", "w:textDirection", "w:bidi", "w:rtlGutter", "w:docGrid", "w:printerSettings", "w:sectPrChange")
    for sect_header, sect_footer in ((main.header, main.footer), (main.even_page_header, main.even_page_footer)):
        sect_header.is_linked_to_previous = False
        sect_footer.is_linked_to_previous = False
    hp = main.header.paragraphs[0]  # ímpar: título do capítulo + número no canto externo
    hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    hp.style = extra["Legenda"]
    field(hp, 'STYLEREF "Heading 1"', "Título do capítulo")
    hp.add_run("    ")
    field(hp, "PAGE", "1")
    ep = main.even_page_header.paragraphs[0]  # par: número + título do livro
    ep.alignment = WD_ALIGN_PARAGRAPH.LEFT
    ep.style = extra["Legenda"]
    field(ep, "PAGE", "2")
    ep.add_run("    TÍTULO DO LIVRO")
    main.different_first_page_header_footer = True  # abertura de capítulo sem cabeçalho
    fp = main.first_page_footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    fp.style = extra["Legenda"]
    field(fp, "PAGE", "1")

    terms = [("hemogasometria", "Hemogasometria"), ("cães", "Cães"), ("gatos", "Gatos"), ("acidose metabólica", "Acidose metabólica")]
    for n in (1, 2):
        if n > 1:
            page_break(doc).different_first_page_header_footer = True
        blank(4, "Créditos")
        para(doc, f"CAPÍTULO {n}", style="Rótulo do capítulo")
        doc.add_heading(f"Título do capítulo {n}", 1)
        para(doc, "Nome do Autor · Nome da Autora (somente em coletâneas)", style="Autores do capítulo")
        para(doc, "**Resumo:** até 150 palavras (coletâneas). **Palavras-chave:** termo; termo; termo.", style="Resumo")
        doc.add_heading(f"{n}.1 Seção", 2)
        p = para(doc, "Texto corrido em Source Serif 4, corpo 10,5 pt sobre 14,5 pt, justificado, com recuo de 5 mm na primeira linha. "
                      "Exemplo de termo marcado para o índice remissivo: a hemogasometria")
        index_term(p, terms[(n - 1) * 2][1])
        p.add_run(" é usada em cães")
        index_term(p, terms[(n - 1) * 2 + 1][1])
        p.add_run(" e gatos. Para marcar um termo, selecione-o e use Referências › Marcar Entrada (Alt+Shift+X).")
        for _ in range(2):
            para(doc, "Esta mancha gráfica e esta entrelinha dão de 2.000 a 2.200 caracteres por página no formato 16×23 cm, com boa leitura "
                      "na tela e no papel. Títulos em Roboto, na cor da marca. Capítulos sempre abrem em página ímpar, sem cabeçalho.")
        para(doc, "Citação direta longa, recuada 12 mm, corpo 9,5 pt (Autor, ano, p. 00).", style="Citação longa")
        picture(FIGURE, text_width)
        para(doc, f"Figura {n}.1 – Legenda da figura (numeração por capítulo). Imagens sempre no estilo Figura.", style="Legenda")
        para(doc, "Fonte: os autores (ano).", style="Fonte da figura")
        doc.add_heading("Referências", 2)
        para(doc, "SOBRENOME, Nome. Título da obra. Cidade: Editora, ano. DOI quando houver.", style="Referência")

    # ---- sobre os autores, com foto
    page_break(doc).different_first_page_header_footer = True
    doc.add_heading("Sobre os autores", 1)
    for name in ("Nome Completo do Autor", "Nome Completo da Autora"):
        t = doc.add_table(rows=1, cols=2)
        no_borders(t)
        left, right = t.cell(0, 0), t.cell(0, 1)
        left.width, right.width = Mm(f["photo"] + 4), text_width - Mm(f["photo"] + 4)
        left.text = ""
        left.paragraphs[0].style = extra["Figura"]
        left.paragraphs[0].add_run().add_picture(str(PHOTO), width=Mm(f["photo"]))
        cell_text(right, name, "Bio", bold=True)
        right.add_paragraph("Médico(a)-veterinário(a), Doutor(a) em [área] pela [instituição]. Professor(a) da [instituição], "
                            "com atuação em [temas]. Minicurrículo de até 6 linhas, em terceira pessoa.", style=extra["Bio"])
        right.add_paragraph("ORCID: https://orcid.org/0000-0000-0000-0000 · Lattes: http://lattes.cnpq.br/0000000000000000",
                            style=extra["Créditos"])
        blank(1, "Créditos")

    # ---- índice remissivo, já com exemplo; F9 recalcula a partir das marcações XE
    page_break(doc).different_first_page_header_footer = True
    doc.add_heading("Índice remissivo", 1)
    field_block(doc, 'INDEX \\c "2" \\z "1046"', [
        "Acidose metabólica, 5",
        "Cães, 2, 6",
        "Gatos, 2, 6",
        "Hemogasometria, 2, 5",
    ], extra["index 1"])

    page_break(doc, odd=False)
    blank(20 if fmt == "16x23" else 28, "Créditos")
    para(doc, f"Este livro foi composto em Source Serif 4 e Roboto e publicado pela {PUBLISHER} em {date.today().year}.", style="Créditos", italic=True)
    save(doc, PRODUCTION / f"modelo-diagramacao-{fmt}.docx")


# =====================================================================  planilhas
HEAD = Font(bold=True, color="FFFFFF")
FILL = PatternFill("solid", fgColor="000080")
THIN = Border(bottom=Side(style="thin", color="C6C6C6"))


def sheet_header(ws, headers, widths):
    ws.append(headers)
    for i, w in enumerate(widths, start=1):
        cell = ws.cell(row=1, column=i)
        cell.font, cell.fill = HEAD, FILL
        cell.alignment = Alignment(vertical="center", wrap_text=True)
        ws.column_dimensions[cell.column_letter].width = w
    ws.row_dimensions[1].height = 30
    ws.freeze_panes = "A2"


def how_to(ws, lines):
    ws.column_dimensions["A"].width = 120
    for i, line in enumerate(lines, start=1):
        ws.cell(row=i, column=1, value=line).alignment = Alignment(wrap_text=True, vertical="top")
        if i == 1:
            ws.cell(row=i, column=1).font = Font(bold=True, size=14, color="000080")


def convites_capitulos():
    wb = Workbook()
    ws = wb.active
    ws.title = "Como usar"
    how_to(ws, [
        "Convites de capítulos – Editora CSVET",
        "1. Preencha a aba Convidados: uma linha por autor de capítulo. Se um capítulo tiver vários autores, repita o título do capítulo em cada linha.",
        "2. Use exatamente o mesmo título do livro em todas as linhas (coluna livro) e o seu nome como organizador(a) (coluna organizador).",
        "3. O prazo é a data de entrega do texto completo (ex.: 30/11/2026). Deixe a coluna status vazia: a editora a preenche.",
        "4. Envie esta planilha para contato@csvet.com.br junto com a proposta da coletânea (ou depois da aprovação).",
        "5. A editora envia os convites pela plataforma: cada autor recebe um e-mail em seu nome, cria a conta e aceita. "
        "Quem aceitar é incluído no livro com o capítulo já cadastrado e entrega o texto pela área de discussões do livro.",
        "6. Você acompanha tudo no painel da plataforma (Minhas submissões › o livro), onde também conversa com os autores.",
        "Dados pessoais: use esta planilha só para os convites e não a compartilhe com terceiros (LGPD).",
    ])
    ws = wb.create_sheet("Convidados")
    headers = ["nome", "sobrenome", "email", "funcao", "capitulo", "prazo", "livro", "organizador", "instituicao", "pais", "idioma", "status", "observacoes"]
    sheet_header(ws, headers, [16, 18, 30, 18, 45, 12, 35, 25, 28, 6, 8, 14, 30])
    ws.append(["Maria", "Silva", "maria.silva@exemplo.br", "Autor de capítulo", "Doença renal crônica em gatos", "30/11/2026",
               "Clínica de Felinos: Atualidades", "Prof. Dr. Nome do Organizador", "Universidade Exemplo", "BR", "pt", "", "exemplo: apague esta linha"])
    for r in range(3, 60):
        ws.cell(row=r, column=4, value="Autor de capítulo")
    dv = DataValidation(type="list", formula1='"Autor de capítulo"', allow_blank=True)
    ws.add_data_validation(dv)
    dv.add("D2:D500")
    dv2 = DataValidation(type="list", formula1='"pt,en,es"', allow_blank=True)
    ws.add_data_validation(dv2)
    dv2.add("K2:K500")
    wb.save(PUBLIC / "CSVET-convites-capitulos.xlsx")
    print("  ", (PUBLIC / "CSVET-convites-capitulos.xlsx").relative_to(ROOT))


def conselho():
    wb = Workbook()
    ws = wb.active
    ws.title = "Como usar"
    how_to(ws, [
        "Conselho Editorial, editores de coleção e pareceristas – Editora CSVET",
        "1. Preencha a aba Convidados (uma linha por pessoa). funcao: Conselho editorial, Editor de coleção ou Parecerista (veja a aba Funções).",
        "2. Salve a aba Convidados como CSV UTF-8 (Arquivo › Salvar como › CSV UTF-8) e envie ao servidor, FORA da pasta do site: ~/convites/conselho.csv",
        "3. Simule:  php scripts/invite.php ~/convites/conselho.csv --user=SEU_USUARIO",
        "4. Envie:   php scripts/invite.php ~/convites/conselho.csv --user=SEU_USUARIO --send",
        "5. Atualize a coluna status com o arquivo conselho.resultado.csv gerado pelo script. Após cada aceite do Conselho ou de editor de coleção, "
        "envie o Termo de Adesão ao Serviço Voluntário (content/editora/documentos/termo-adesao-voluntario.docx).",
        "Requisito do script (uma vez): api_key_secret no config.inc.php e chave de API gerada no seu perfil (veja o README).",
        "Meta: 6 a 12 membros do Conselho, doutores, de instituições diferentes (no máximo 1/3 da mesma instituição; ao menos 1 do exterior).",
        "Dados pessoais (CPF etc.): esta planilha preenchida NUNCA vai para o Git nem para a pasta pública.",
    ])
    ws = wb.create_sheet("Funções")
    sheet_header(ws, ["Função (use na planilha)", "Papel no OMP", "Aparece na equipe editorial", "O que faz", "Carga estimada", "Perfil desejado"],
                 [22, 24, 14, 60, 24, 40])
    for row in [
        ["Conselho editorial", "Membro do Conselho Editorial", "sim", "Avaliação interna das propostas da sua área, política editorial, sugestão de pareceristas", "2 a 4 propostas/semestre", "Doutor(a) com produção na área"],
        ["Editor de coleção", "Editor de série", "sim", "Conduz as propostas de uma coleção: avaliação interna, convite a pareceristas, recomendação de decisão", "2 a 4 obras/semestre", "Doutor(a) com experiência editorial"],
        ["Parecerista", "Avaliador Externo", "não", "Avaliação duplo-anônima de livros e capítulos", "sob convite, 30 dias por obra", "Mestre ou doutor(a) na área"],
    ]:
        ws.append(row)
    ws = wb.create_sheet("Convidados")
    sheet_header(ws, ["nome", "sobrenome", "email", "funcao", "area", "instituicao", "pais", "idioma", "data_inicio", "status", "orcid", "observacoes"],
                 [16, 18, 30, 20, 30, 30, 6, 8, 12, 14, 22, 30])
    dv = DataValidation(type="list", formula1='"Conselho editorial,Editor de coleção,Parecerista"', allow_blank=True)
    ws.add_data_validation(dv)
    dv.add("D2:D500")
    INTERNAL.mkdir(parents=True, exist_ok=True)
    wb.save(INTERNAL / "conselho-editorial-modelo.xlsx")
    print("  ", (INTERNAL / "conselho-editorial-modelo.xlsx").relative_to(ROOT))


def controle_isbn():
    wb = Workbook()
    ws = wb.active
    ws.title = "ISBN"
    sheet_header(ws, ["ISBN-13", "Formato", "Título", "Autores/organizadores", "Coleção", "Edição", "Data de atribuição (CBL)", "Ficha CBL recebida",
                      "Data de publicação", "ID no OMP", "URL", "DOI", "Depósito legal BN", "Observações"],
                 [18, 12, 40, 30, 22, 8, 14, 12, 14, 9, 40, 22, 14, 30])
    dv = DataValidation(type="list", formula1='"PDF,EPUB,Impresso"', allow_blank=True)
    ws.add_data_validation(dv)
    dv.add("B2:B1000")
    dv2 = DataValidation(type="list", formula1='"' + ",".join(SERIES) + '"', allow_blank=True)
    ws.add_data_validation(dv2)
    dv2.add("E2:E1000")
    ws2 = wb.create_sheet("Regras")
    how_to(ws2, [
        "Regras do ISBN (CBL)",
        "Um ISBN por formato: PDF, EPUB e impresso são produtos diferentes.",
        "Nova edição (conteúdo alterado) = novo ISBN. Reimpressão sem alteração = mesmo ISBN.",
        "Coletânea: o ISBN é do livro; capítulos não têm ISBN (podem ter DOI).",
        "No OMP: Publicação › Formatos de publicação › (formato) › Códigos de identificação › ISBN-13 (15).",
        "A ficha catalográfica é pedida com o arquivo final: título, autores, edição, ano e páginas não podem mudar depois.",
        "Depósito legal: enviar a obra à Biblioteca Nacional (Lei nº 10.994/2004).",
    ])
    wb.save(INTERNAL / "controle-isbn.xlsx")
    print("  ", (INTERNAL / "controle-isbn.xlsx").relative_to(ROOT))


if __name__ == "__main__":
    print("Documentos públicos, internos e de produção:")
    proposta_livro()
    proposta_coletanea()
    modelo_livro()
    modelo_capitulo()
    declaracao_autores()
    termo_capitulo()
    anuencia()
    convites_capitulos()
    contrato()
    carta_avaliacao()
    termo_voluntario()
    conselho()
    controle_isbn()
    example_images()
    for fmt in FORMATS:
        diagramacao(fmt)
