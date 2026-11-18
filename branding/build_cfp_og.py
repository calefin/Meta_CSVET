"""Imagens de compartilhamento (1200x630) das chamadas (artigos e imagens clínicas), em português e inglês.

Uso:  python branding/build_cfp_og.py
Saída: plugins/themes/csvet/images/og/nvas-<página>.<idioma>.png, que o tema usa
como og:image da página /nvas/<idioma>/<página> (prévia no WhatsApp, LinkedIn, X).
Mesmas fontes e selo de build_logo.py.
"""
from matplotlib.patches import Circle, FancyBboxPatch, Rectangle

from build_logo import ACCENT, NAME_MAIN, NAVY, SERIF_B, SERIF_R, SOFT, WHITE, figure, seal, text, width, plt, OG_PATH

TEXTS = {
    "pt_BR": {
        "kicker": "CHAMADA DE ARTIGOS  ·  1ª EDIÇÃO",
        "headline": "Publique sua pesquisa na NVAS",
        "deadline_label": "ENVIE ATÉ",
        "deadline": "15 de novembro de 2026",
        "chips": ["Sem custo de publicação", "DOI em todo artigo", "Revisão por pares"],
    },
    "en": {
        "kicker": "CALL FOR PAPERS  ·  FIRST ISSUE",
        "headline": "Publish your research in NVAS",
        "deadline_label": "SUBMIT BY",
        "deadline": "15 November 2026",
        "chips": ["No publication fees", "DOI for every article", "Peer reviewed"],
    },
}

CLINICAL_IMAGES = {
    "pt_BR": {
        "kicker": "CHAMADA DE IMAGENS CLÍNICAS  ·  1ª EDIÇÃO",
        "headline": "Sua imagem clínica vira artigo",
        "deadline_label": "ENVIE ATÉ",
        "deadline": "15 de novembro de 2026",
        "chips": ["Foto clínica e radiologia", "Envio em português", "DOI e revisão por pares"],
    },
    "en": {
        "kicker": "CALL FOR CLINICAL IMAGES  ·  FIRST ISSUE",
        "headline": "Your clinical image, published",
        "deadline_label": "SUBMIT BY",
        "deadline": "15 November 2026",
        "chips": ["Clinical photo and radiology", "Published in EN and PT", "DOI and peer review"],
    },
}

PUBLISH = {
    "pt_BR": {
        "kicker": "QUERO PUBLICAR  ·  ORIENTAÇÃO GRATUITA",
        "headline": "Seu primeiro artigo, com orientação",
        "deadline_label": "1ª EDIÇÃO  ·  ENVIE ATÉ",
        "deadline": "15 de novembro de 2026",
        "chips": ["Modelos passo a passo", "Estudantes com orientador", "Sem custo"],
    },
    "en": {
        "kicker": "PUBLISH WITH US  ·  FREE GUIDANCE",
        "headline": "Your first paper, with guidance",
        "deadline_label": "FIRST ISSUE  ·  SUBMIT BY",
        "deadline": "15 November 2026",
        "chips": ["Step-by-step templates", "Students welcome", "No fees"],
    },
}

# página (caminho em /nvas/<idioma>/<página>) -> textos por idioma
PAGES = {"call-for-papers": TEXTS, "clinical-images": CLINICAL_IMAGES, "publish-with-us": PUBLISH}

W, H = 1200, 630
X0 = 80


def rounded(ax, x, y, w, h, r, **kw):
    ax.add_patch(FancyBboxPatch((x + r, y + r), w - 2 * r, h - 2 * r, boxstyle=f"round,pad={r}", **kw))


def build(page, locale, t):
    fig, ax = figure(W, H)
    ax.add_patch(Rectangle((0, 0), W, H, fc=NAVY, ec="none"))
    # brilho azul no canto, como no destaque da página
    for r, a in ((520, 0.10), (380, 0.10), (240, 0.10)):
        ax.add_patch(Circle((W + 40, H + 40), r, fc=ACCENT, ec="none", alpha=a))
    ax.add_patch(Rectangle((0, 0), W, 10, fc=ACCENT, ec="none"))

    # selo + nome da revista
    seal(ax, X0 + 46, 528, 92, inverse=True)
    text(ax, "NEOTROPICAL", X0 + 116, 540, 15, SERIF_R, SOFT, tracking=0.3)
    text(ax, NAME_MAIN, X0 + 114, 508, 25, SERIF_B, WHITE)

    # chapéu
    kw = width(t["kicker"], 19, "Roboto-700", 0.08)
    rounded(ax, X0, 404, kw + 36, 40, 20, fc=(0, 0.667, 1, 0.18), ec=ACCENT, lw=1.5)
    text(ax, t["kicker"], X0 + 18, 417, 19, "Roboto-700", "#bfe9ff", tracking=0.08)

    # título
    size = 62
    while width(t["headline"], size, SERIF_B) > W - 2 * X0:
        size -= 2
    text(ax, t["headline"], X0, 318, size, SERIF_B, WHITE)

    # prazo em destaque
    dl = 46
    dw = width(t["deadline"], dl, "Roboto-700")
    rounded(ax, X0, 168, dw + 64, 112, 14, fc=WHITE, ec="none")
    ax.add_patch(Rectangle((X0, 168), 10, 112, fc=ACCENT, ec="none"))
    text(ax, t["deadline_label"], X0 + 36, 242, 16, "Roboto-700", "#5a6080", tracking=0.12)
    text(ax, t["deadline"], X0 + 34, 190, dl, "Roboto-700", NAVY)

    # vantagens
    x = X0
    for chip in t["chips"]:
        cw = width(chip, 22, "Roboto-500")
        rounded(ax, x, 62, cw + 58, 50, 25, fc="none", ec=SOFT, lw=1.5)
        ax.add_patch(Circle((x + 22, 87), 5, fc=ACCENT, ec="none"))
        text(ax, chip, x + 34, 79, 22, "Roboto-500", WHITE)
        x += cw + 58 + 16

    out = OG_PATH.parent / f"nvas-{page}.{locale}.png"
    fig.savefig(out, dpi=100)
    plt.close(fig)
    print(out)


if __name__ == "__main__":
    for page, texts in PAGES.items():
        for locale, t in texts.items():
            build(page, locale, t)
