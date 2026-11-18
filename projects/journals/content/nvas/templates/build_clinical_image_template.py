"""Gera o modelo de Imagem Clínica da NVAS (.docx), bilíngue (inglês e português).

Submissão em um só idioma (inglês ou português); a revista prepara a outra versão após o aceite.

Uso:  python content/nvas/templates/build_clinical_image_template.py
Saída: plugins/themes/csvet/docs/NVAS-clinical-image-template.docx (link nas normas,
na política da seção Imagens Clínicas e na página /nvas/<idioma>/clinical-images).
Regras: Anexo B das normas (content/nvas/author-guidelines.<idioma>.html).
"""
from pathlib import Path

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

from build_manuscript_templates import KIND_IMAGE, journal_header_footer

ROOT = Path(__file__).resolve().parents[3]
OUT = ROOT / "plugins" / "themes" / "csvet" / "docs" / "NVAS-clinical-image-template.docx"

NAVY = RGBColor(0x00, 0x00, 0x80)
GRAY = RGBColor(0x5A, 0x60, 0x80)
HINT = RGBColor(0x8A, 0x8F, 0xA8)


def shade(cell, hex_fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hex_fill)
    tc_pr.append(shd)


def line_numbering(section):
    ln = OxmlElement("w:lnNumType")
    ln.set(qn("w:countBy"), "1")
    ln.set(qn("w:restart"), "continuous")
    section._sectPr.append(ln)


def heading(doc, text, size=14):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(18)
    p.paragraph_format.keep_with_next = True
    r = p.add_run(text)
    r.bold = True
    r.font.size = Pt(size)
    r.font.color.rgb = NAVY
    return p


def field(doc, label, hint):
    """Rótulo em negrito seguido do texto-guia em cinza (a ser substituído pelo autor)."""
    p = doc.add_paragraph()
    r = p.add_run(label + " ")
    r.bold = True
    h = p.add_run(hint)
    h.italic = True
    h.font.color.rgb = HINT
    return p


def figure_box(doc, text):
    t = doc.add_table(rows=1, cols=1)
    t.style = "Table Grid"
    c = t.rows[0].cells[0]
    shade(c, "F4F6FB")
    p = c.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(36)
    p.paragraph_format.space_after = Pt(36)
    r = p.add_run(text)
    r.italic = True
    r.font.color.rgb = GRAY


def version(doc, L):
    heading(doc, L["version"], 15)
    field(doc, L["title"], L["title_hint"])
    field(doc, L["abstract"], L["abstract_hint"])
    field(doc, L["keywords"], L["keywords_hint"])
    heading(doc, L["text"], 12)
    for label, hint in L["sections"]:
        field(doc, label, hint)
    heading(doc, L["figure"], 12)
    figure_box(doc, L["figure_box"])
    field(doc, L["legend"], L["legend_hint"])
    p = doc.add_paragraph()
    r = p.add_run(L["count"])
    r.bold = True
    r.font.color.rgb = GRAY


EN = {
    "version": "ENGLISH VERSION",
    "title": "Title:",
    "title_hint": "[Up to 10 words, stating the finding or diagnosis and the species. E.g. Hypertrophic osteopathy in a dog with a pulmonary mass]",
    "abstract": "Abstract:",
    "abstract_hint": "[One or two sentences, up to 60 words, summarizing the case, the finding and the diagnosis.]",
    "keywords": "Keywords:",
    "keywords_hint": "[3 to 5, separated by semicolons, necessarily including species; body system or organ; modality. E.g. dog; respiratory system; radiography]",
    "text": "Text",
    "sections": [
        ("Signalment and history.", "[Species, breed, age, sex; reason for presentation and relevant history, in two or three sentences.]"),
        ("Image findings.", "[What the image shows, citing every panel and every arrow, e.g. \"Lateral thoracic radiograph (Figure 1A) showing ... (arrow)\".]"),
        ("Diagnosis and confirmation.", "[The diagnosis and the method that confirmed it: histopathology, cytology, culture, PCR, serology, advanced imaging, surgery, necropsy or unequivocal follow-up.]"),
        ("Outcome.", "[One sentence: treatment and outcome.]"),
        ("Teaching point.", "[One or two sentences: what the reader should remember when seeing this image.]"),
    ],
    "figure": "Figure 1",
    "figure_box": "Insert Figure 1 here (labelled version), for review.\nUpload the labelled and the unlabelled versions also as separate files (original JPEG/TIFF/PNG, at least 2,000 pixels wide). Any words on the figure must be in English.\nIf this is the version added after acceptance, do not repeat the figure: write only the translated legend.",
    "legend": "Figure 1.",
    "legend_hint": "[Self-explanatory legend describing every panel: (A) ... (B) ... Arrow: ...; arrowhead: ...; asterisk: .... For micrographs: staining, magnification and scale bar value. For diagnostic images: view or plane, orientation, sequence or transducer frequency. State any masking of identifiers.] (A second figure, if needed: Figure 2, same format; at most 4 panels in total.)",
    "count": "Word count (text + legends, excluding title, abstract and references): ____ / 500",
}

PT = {
    "version": "VERSÃO EM PORTUGUÊS",
    "title": "Título:",
    "title_hint": "[Até 10 palavras, com o achado ou diagnóstico e a espécie. Ex.: Osteopatia hipertrófica em cão com massa pulmonar]",
    "abstract": "Resumo:",
    "abstract_hint": "[Uma ou duas frases, até 60 palavras, resumindo o caso, o achado e o diagnóstico.]",
    "keywords": "Palavras-chave:",
    "keywords_hint": "[3 a 5, separadas por ponto e vírgula, incluindo obrigatoriamente espécie; sistema ou órgão; modalidade. Ex.: cão; sistema respiratório; radiografia]",
    "text": "Texto",
    "sections": [
        ("Resenha e histórico.", "[Espécie, raça, idade, sexo; motivo do atendimento e histórico relevante, em duas ou três frases.]"),
        ("Achados de imagem.", "[O que a imagem mostra, citando cada painel e cada seta, ex.: \"Radiografia torácica laterolateral (Figura 1A) com ... (seta)\".]"),
        ("Diagnóstico e confirmação.", "[O diagnóstico e o método que o confirmou: histopatologia, citologia, cultura, PCR, sorologia, imagem avançada, cirurgia, necropsia ou evolução inequívoca.]"),
        ("Desfecho.", "[Uma frase: tratamento e evolução.]"),
        ("Mensagem para a prática.", "[Uma ou duas frases: o que o leitor deve lembrar ao ver esta imagem.]"),
    ],
    "figure": "Figura 1",
    "figure_box": "Insira aqui a Figura 1 (versão com marcações), para a avaliação.\nEnvie também as versões com e sem marcações em arquivos separados (JPEG/TIFF/PNG original, ao menos 2.000 pixels de largura). Palavras na figura, se houver, devem estar em inglês.\nSe esta for a versão feita após o aceite, não repita a figura: escreva só a legenda traduzida.",
    "legend": "Figura 1.",
    "legend_hint": "[Legenda autoexplicativa descrevendo cada painel: (A) ... (B) ... Seta: ...; ponta de seta: ...; asterisco: .... Em micrografias: coloração, aumento e valor da barra de escala. Em imagens diagnósticas: projeção ou plano, orientação, sequência ou frequência do transdutor. Informe identificadores ocultados.] (Segunda figura, se necessária: Figura 2, mesmo formato; no máximo 4 painéis no total.)",
    "count": "Contagem de palavras (texto + legendas, sem título, resumo e referências): ____ / 500",
}

DECLARATIONS = [
    ("Consent / Consentimento:", "[Choose one. Not required: the images and text do not allow the animal or its legal guardian to be identified. OR The legal guardian gave written consent for publication (or the institutional admission form authorizes use for teaching and research). / Escolha uma. Dispensado: as imagens e o texto não permitem identificar o animal nem o seu responsável legal. OU O responsável legal deu consentimento por escrito para a publicação (ou o termo institucional de atendimento autoriza o uso em ensino e pesquisa).]"),
    ("Ethics / Ética:", "[Routine clinical care: no ethics committee approval required. If part of a study: committee, protocol number and date. / Atendimento clínico de rotina: dispensa aprovação de comissão de ética. Se fizer parte de um estudo: comissão, número do protocolo e data.]"),
    ("Image integrity / Integridade da imagem:", "[The images are original and were only cropped, rotated and adjusted for brightness, contrast or colour over the whole image; no element was added, removed or moved and no AI tool was used to create or enhance them. Masked identifiers, if any, are stated in the legend. / As imagens são originais e foram apenas recortadas, giradas e ajustadas em brilho, contraste ou cor na imagem inteira; nenhum elemento foi acrescentado, removido ou movido e nenhuma ferramenta de IA foi usada para criá-las ou melhorá-las. Identificadores ocultados, se houver, constam da legenda.]"),
    ("Prior disclosure / Divulgação prévia:", "[None, or where the case was shown (meeting, social media). / Nenhuma, ou onde o caso foi apresentado (congresso, redes sociais).]"),
    ("Use of generative AI / Uso de IA generativa:", "[None, or the tool and how it was used in the text. / Nenhum, ou a ferramenta e como foi usada no texto.]"),
    ("Conflicts of interest / Conflito de interesses:", "[The authors declare no conflict of interest. / Os autores declaram não haver conflito de interesses.]"),
    ("Funding / Financiamento:", "[None, or funder and grant number. / Nenhum, ou financiador e número do processo.]"),
]


def build():
    doc = Document()
    st = doc.styles["Normal"]
    st.font.name = "Arial"
    st.font.size = Pt(12)
    st.paragraph_format.line_spacing = 2.0
    st.paragraph_format.space_after = Pt(0)

    sec = doc.sections[0]
    sec.page_width, sec.page_height = Cm(21), Cm(29.7)
    for side in ("left_margin", "right_margin", "top_margin", "bottom_margin"):
        setattr(sec, side, Cm(2.5))
    line_numbering(sec)
    # Logo, tipo de artigo e rodapé com o DOI: os mesmos dos outros modelos (avisos nos dois idiomas).
    journal_header_footer(sec, lambda pt, en: f"{en} / {pt}", KIND_IMAGE)

    # Cabeçalho do modelo
    p = doc.add_paragraph()
    r = p.add_run("Clinical Image template / Modelo de Imagem Clínica  ·  Clinical Images section / Seção Imagens Clínicas")
    r.font.color.rgb = GRAY

    # Instruções (apagar antes de enviar)
    t = doc.add_table(rows=1, cols=1)
    t.style = "Table Grid"
    c = t.rows[0].cells[0]
    shade(c, "EAF7FF")
    c.paragraphs[0].add_run("Before you start / Antes de começar  (delete this box before submitting / apague este quadro antes de enviar)").bold = True
    for line in [
        "• Anonymized file: no author names, affiliations or acknowledgements here; they go on the title page. / Arquivo anonimizado: sem nomes dos autores, afiliações ou agradecimentos; eles vão na folha de rosto.",
        "• At submission, fill in ONE version only, English or Portuguese, and delete the other; up to 500 words (text + legends). After acceptance, the journal prepares the other version and you review and approve it: both are published. / Na submissão, preencha UMA só versão, em inglês ou em português, e apague a outra; até 500 palavras (texto + legendas). Após o aceite, a revista prepara a outra versão e você a revisa e aprova: as duas são publicadas.",
        "• One or two figures, at most 4 panels (A–D). Arrows and labels are allowed, explained in the legend; upload a labelled AND an unlabelled version. / Uma ou duas figuras, no máximo 4 painéis (A–D). Setas e marcações são permitidas, explicadas na legenda; envie a versão com E a versão sem marcações.",
        "• Original files (camera, phone or DICOM export), never screenshots or images forwarded by messaging apps; at least 300 dpi / 2,000 pixels wide. / Arquivos originais (câmera, celular ou exportação DICOM), nunca captura de tela nem imagem repassada por aplicativo de mensagens; mínimo 300 dpi / 2.000 pixels de largura.",
        "• Only whole-image adjustments; no AI enhancement; remove names, dates, record numbers and logos. / Só ajustes na imagem inteira; sem melhoria por IA; retire nomes, datas, prontuários e logotipos.",
        "• Up to 4 authors (including the graduate author) and up to 5 references (Vancouver). Submit to the Clinical Images section. / Até 4 autores (incluindo o autor graduado) e até 5 referências (Vancouver). Envie na seção Imagens Clínicas.",
    ]:
        q = c.add_paragraph(line)
        q.paragraph_format.line_spacing = 1.15
        for run in q.runs:
            run.font.size = Pt(10)

    version(doc, EN)
    doc.add_page_break()
    version(doc, PT)

    heading(doc, "Declarations / Declarações", 12)
    for label, hint in DECLARATIONS:
        field(doc, label, hint)

    heading(doc, "References / Referências (up to 5 / até 5)", 12)
    field(doc, "1.", "[Smith AB, Silva CD. Title of the article. Abbreviated Journal Title. 2024;12(3):123-30. doi:10.xxxx/xxxxx]")
    for n in range(2, 6):
        field(doc, f"{n}.", "[...]")

    OUT.parent.mkdir(parents=True, exist_ok=True)
    doc.save(OUT)
    print(OUT)


if __name__ == "__main__":
    build()
