"""Gera o logo da revista (SVG + PNG) com texto convertido em curvas.

Uso:  python branding/build_logo.py [pasta-das-fontes]
Fontes (Source Serif 4: OFL, ver SourceSerif4-OFL.txt; Roboto: Apache 2.0) esperadas na pasta: SourceSerif4-<estilo>.ttf e Roboto-<peso>.ttf.
Para trocar o nome da revista, edite NAME_TOP / NAME_MAIN / MONOGRAM abaixo.

Identidade: selo circular com monograma serifado (padrão de periódicos científicos)
+ título em serifa, filete e editora em caixa-alta espaçada.
"""
import sys
from pathlib import Path

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.font_manager import FontProperties
from matplotlib.patches import Circle, PathPatch, Polygon, Rectangle, Wedge
from matplotlib.textpath import TextPath

NAME_TOP = "NEOTROPICAL"
NAME_MAIN = "Veterinary and Animal Science"
MONOGRAM = "NVAS"
PUBLISHER = "CSVET EDITORA"

NAVY = "#000080"
GRAY = "#6b6b7b"       # texto secundário sobre branco
ACCENT = "#00aaff"
WHITE = "#ffffff"
SOFT = "#c9cdf0"       # texto secundário sobre navy

HERE = Path(__file__).parent
OUT = HERE / "out"
FONTS = Path(sys.argv[1]) if len(sys.argv) > 1 else HERE / "fonts"


def font(name):
    return FontProperties(fname=str(FONTS / f"{name}.ttf"))


SERIF_B = "SourceSerif4-Semibold"
SERIF_R = "SourceSerif4-Regular"
SANS = "Roboto-500"


def advance(ch, size, fp):
    bar = TextPath((0, 0), "|", size=size, prop=fp).get_extents().width
    return TextPath((0, 0), ch + "|", size=size, prop=fp).get_extents().width - bar


def width(s, size, fname, tracking=0.0):
    fp = font(fname)
    if not tracking:
        return TextPath((0, 0), s, size=size, prop=fp).get_extents().width
    w = 0.0
    for ch in s:
        w += (size * 0.25 if ch == " " else advance(ch, size, fp)) + tracking * size
    return w - tracking * size


def text(ax, s, x, y, size, fname, color, tracking=0.0, center=False):
    """Desenha texto como curvas; tracking em fração do tamanho."""
    if center:
        x -= width(s, size, fname, tracking) / 2
    fp = font(fname)
    if not tracking:
        tp = TextPath((x, y), s, size=size, prop=fp)
        ax.add_patch(PathPatch(tp, fc=color, ec="none", lw=0))
        return
    cx = x
    for ch in s:
        if ch == " ":
            cx += size * 0.25 + tracking * size
            continue
        ax.add_patch(PathPatch(TextPath((cx, y), ch, size=size, prop=fp), fc=color, ec="none", lw=0))
        cx += advance(ch, size, fp) + tracking * size


def seal(ax, cx, cy, d, inverse=False, small=False):
    """Selo circular: anel fino interno, monograma serifado e filete com losango de destaque.

    Normal: disco navy com traços brancos. inverse (cabeçalho navy): selo vazado, só traços brancos.
    small: só a inicial, legível a 32 px.
    """
    r = d / 2
    fg = WHITE
    if inverse:
        ax.add_patch(Wedge((cx, cy), r, 0, 360, width=r * 0.05, fc=fg, ec="none"))
    else:
        ax.add_patch(Circle((cx, cy), r, fc=NAVY, ec="none"))
    if small:
        size = d * 0.62
        cap = TextPath((0, 0), "N", size=size, prop=font(SERIF_B)).get_extents()
        text(ax, "N", cx, cy - cap.height / 2 - cap.y0, size, SERIF_B, fg, center=True)
        return
    ax.add_patch(Wedge((cx, cy), r * 0.88, 0, 360, width=r * 0.022, fc=fg, ec="none"))
    # monograma
    size = d * 0.25
    ext = TextPath((0, 0), MONOGRAM, size=size, prop=font(SERIF_B)).get_extents()
    base = cy - ext.height / 2 - ext.y0 + d * 0.04
    text(ax, MONOGRAM, cx, base, size, SERIF_B, fg, tracking=0.02, center=True)
    # filete sob o monograma, com losango de destaque
    y = base - d * 0.09
    half = d * 0.22
    lw = d * 0.012
    ax.add_patch(Rectangle((cx - half, y - lw / 2), 2 * half, lw, fc=fg, ec="none"))
    s = d * 0.03
    ax.add_patch(Polygon([(cx - s, y), (cx, y + s), (cx + s, y), (cx, y - s)], fc=ACCENT, ec="none"))


def figure(w, h):
    fig = plt.figure(figsize=(w / 100, h / 100), dpi=100)
    ax = fig.add_axes([0, 0, 1, 1])
    ax.set_xlim(0, w)
    ax.set_ylim(0, h)
    ax.set_aspect("equal")
    ax.axis("off")
    return fig, ax


def save(fig, name, scales=(1,)):
    OUT.mkdir(exist_ok=True)
    fig.savefig(OUT / f"{name}.svg", transparent=True)
    for k in scales:
        suffix = "" if k == 1 else f"@{k}x"
        fig.savefig(OUT / f"{name}{suffix}.png", transparent=True, dpi=100 * k)
    plt.close(fig)


def horizontal(variant):
    inverse = variant == "white"
    main = WHITE if inverse else NAVY
    sec = SOFT if inverse else GRAY
    H = 240
    main_size = 58
    x0 = 278
    tw = width(NAME_MAIN, main_size, SERIF_B)
    fig, ax = figure(int(x0 + tw + 24), H)
    seal(ax, 116, H / 2, 208, inverse=inverse)
    # divisor vertical fino
    ax.add_patch(Rectangle((246, 36), 1.6, H - 72, fc=sec, ec="none", alpha=0.7))
    text(ax, NAME_TOP, x0 + 2, 162, 26, SERIF_R, main, tracking=0.32)
    text(ax, NAME_MAIN, x0, 98, main_size, SERIF_B, main)
    # filete de destaque + editora
    ax.add_patch(Rectangle((x0 + 2, 46), 44, 3, fc=ACCENT, ec="none"))
    text(ax, PUBLISHER, x0 + 58, 40, 17, SANS, sec, tracking=0.28)
    save(fig, f"logo-{variant}", scales=(1, 2))


def icon_only():
    fig, ax = figure(512, 512)
    seal(ax, 256, 256, 512)
    save(fig, "icon")
    fig, ax = figure(180, 180)
    seal(ax, 90, 90, 180)
    fig.savefig(OUT / "icon-180.png", transparent=True)
    plt.close(fig)
    fig, ax = figure(32, 32)
    seal(ax, 16, 16, 32, small=True)
    fig.savefig(OUT / "icon-32.png", transparent=True)
    plt.close(fig)


OG_PATH = HERE.parent / "plugins" / "themes" / "csvet" / "images" / "og" / f"{MONOGRAM.lower()}.png"
OG_TAGLINE = "Peer-reviewed  ·  Open access  ·  All veterinary and animal sciences"
OG_URL = f"journals.csvet.com.br/{MONOGRAM.lower()}"


def og_image():
    """Imagem de compartilhamento 1200x630 (WhatsApp, LinkedIn, X) usada pelo tema."""
    W, H = 1200, 630
    fig, ax = figure(W, H)
    ax.add_patch(Rectangle((0, 0), W, H, fc=NAVY, ec="none"))
    ax.add_patch(Rectangle((0, 0), W, 8, fc=ACCENT, ec="none"))
    # bloco do logo (selo + divisor + título), centralizado
    main_size = 50
    gap = 34
    d = 200
    tw = width(NAME_MAIN, main_size, SERIF_B)
    total = d + gap * 2 + tw
    x = (W - total) / 2
    cy = 400
    seal(ax, x + d / 2, cy, d, inverse=True)
    dx = x + d + gap
    ax.add_patch(Rectangle((dx, cy - 78), 1.6, 156, fc=SOFT, ec="none", alpha=0.7))
    x0 = dx + gap
    text(ax, NAME_TOP, x0 + 2, cy + 34, 23, SERIF_R, WHITE, tracking=0.32)
    text(ax, NAME_MAIN, x0, cy - 22, main_size, SERIF_B, WHITE)
    ax.add_patch(Rectangle((x0 + 2, cy - 64), 40, 3, fc=ACCENT, ec="none"))
    text(ax, PUBLISHER, x0 + 54, cy - 70, 15, SANS, SOFT, tracking=0.28)
    text(ax, OG_TAGLINE, W / 2, 182, 28, "Roboto-400", SOFT, center=True)
    text(ax, OG_URL, W / 2, 84, 24, SANS, ACCENT, center=True)
    OG_PATH.parent.mkdir(parents=True, exist_ok=True)
    fig.savefig(OG_PATH, dpi=100)
    plt.close(fig)


if __name__ == "__main__":
    horizontal("color")
    horizontal("white")
    icon_only()
    og_image()
    print("Arquivos em", OUT)
