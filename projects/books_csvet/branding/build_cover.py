"""Gera a capa (frente) de um livro no padrão visual da Editora CSVET.

Uso:
  python branding/build_cover.py --title "Leishmaniose Visceral Canina" --subtitle "Diagnóstico e controle" \
      --authors "Ana Pereira" --series monografias --out capa.jpg
  python branding/build_cover.py --title "Clínica de Felinos" --authors "Maria Silva; João Souza" --organizers \
      --series coletaneas --image foto.jpg --out capa.jpg

Saída: 1600 x 2300 px (proporção 16x23 cm), JPEG de alta qualidade, pronta para o OMP
(Publicação > Metadados > Imagem da capa) e para a prova da capa impressa (frente).
Cada coleção tem sua cor de destaque; título em Source Serif 4, demais textos em Roboto.
--image é opcional (foto ou ilustração com direitos de uso): ocupa a faixa central, com
degradê navy para manter a legibilidade.
"""
import argparse
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageOps

ROOT = Path(__file__).resolve().parent.parent
FONTS = ROOT / "branding" / "fonts"
LOGO = ROOT / "branding" / "assets" / "logo-csvet-branco.png"
ICON = ROOT / "branding" / "assets" / "icon-512.png"

W, H = 1600, 2300
NAVY_DARK = (0, 0, 61)
NAVY = (0, 0, 128)
WHITE = (255, 255, 255)
SOFT = (201, 205, 240)

SERIES = {
    "tecnico-cientificos": ("LIVROS TÉCNICO-CIENTÍFICOS", (0, 170, 255)),
    "coletaneas": ("COLETÂNEAS", (46, 196, 182)),
    "manuais": ("MANUAIS E GUIAS PRÁTICOS", (255, 183, 3)),
    "monografias": ("MONOGRAFIAS", (142, 202, 230)),
    "dissertacoes-teses": ("DISSERTAÇÕES E TESES", (214, 175, 55)),
    "anais": ("ANAIS E RESUMOS DE EVENTOS", (231, 111, 81)),
}


def font(name, size):
    return ImageFont.truetype(str(FONTS / f"{name}.ttf"), size)


def wrap(draw, text, fnt, width):
    lines, line = [], ""
    for word in text.split():
        test = f"{line} {word}".strip()
        if draw.textlength(test, font=fnt) <= width:
            line = test
        else:
            if line:
                lines.append(line)
            line = word
    if line:
        lines.append(line)
    return lines


def fit_title(draw, text, width, max_lines=5):
    """Maior corpo (de 150 a 70 px) em que o título cabe em até max_lines linhas."""
    for size in range(150, 69, -6):
        fnt = font("SourceSerif4-Semibold", size)
        lines = wrap(draw, text, fnt, width)
        if len(lines) <= max_lines:
            return fnt, lines
    return fnt, lines[:max_lines]


def tracked(draw, xy, text, fnt, fill, tracking):
    x, y = xy
    for ch in text:
        draw.text((x, y), ch, font=fnt, fill=fill)
        x += draw.textlength(ch, font=fnt) + tracking


def tracked_width(draw, text, fnt, tracking):
    return sum(draw.textlength(ch, font=fnt) + tracking for ch in text) - tracking


def build(title, subtitle, authors, series, organizers=False, image=None, out="capa.jpg"):
    label, accent = SERIES[series]
    img = Image.new("RGB", (W, H), NAVY_DARK)
    px = img.load()
    for y in range(H):  # degradê vertical navy profundo -> navy
        t = y / (H - 1)
        c = tuple(round(a + (b - a) * t) for a, b in zip(NAVY_DARK, NAVY))
        for x in range(W):
            px[x, y] = c
    draw = ImageDraw.Draw(img)
    margin = 150

    # Imagem opcional na faixa central, fundindo com o navy em cima e embaixo
    if image:
        band_top, band_h = 1060, 760
        photo = ImageOps.fit(Image.open(image).convert("RGB"), (W, band_h), Image.LANCZOS)
        mask = Image.new("L", (W, band_h), 255)
        mdraw = ImageDraw.Draw(mask)
        fade = 220
        for i in range(fade):
            alpha = round(255 * i / fade)
            mdraw.line([(0, i), (W, i)], fill=alpha)
            mdraw.line([(0, band_h - 1 - i), (W, band_h - 1 - i)], fill=alpha)
        tint = Image.new("RGB", (W, band_h), NAVY_DARK)
        photo = Image.blend(photo, tint, 0.25)
        img.paste(photo, (0, band_top), mask)
        draw = ImageDraw.Draw(img)

    else:
        # Sem imagem: marca d'água do livro da CSVET, discreta, à direita
        mark = Image.open(ICON).convert("L").point(lambda v: 255 if v > 160 else 0).resize((980, 980), Image.LANCZOS)
        alpha = mark.point(lambda v: round(v * 0.07))
        img.paste(Image.new("RGB", mark.size, WHITE), (W - 820, 900), alpha)
        draw = ImageDraw.Draw(img)

    # Coleção: rótulo espaçado + filete na cor da coleção
    label_font = font("Roboto-500", 34)
    tracked(draw, (margin, 170), label, label_font, accent, 7)
    draw.rectangle([margin, 232, margin + 120, 240], fill=accent)

    # Título e subtítulo
    title_font, lines = fit_title(draw, title, W - 2 * margin)
    y = 330
    line_h = round(title_font.size * 1.12)
    for line in lines:
        draw.text((margin, y), line, font=title_font, fill=WHITE)
        y += line_h
    if subtitle:
        y += 45
        sub_font = font("Roboto-400", 56)
        for line in wrap(draw, subtitle, sub_font, W - 2 * margin)[:3]:
            draw.text((margin, y), line, font=sub_font, fill=SOFT)
            y += 72

    # Autores ou organizadores, acima do rodapé
    names = [n.strip() for n in authors.replace(",", ";").split(";") if n.strip()]
    author_font = font("Roboto-500", 54)
    block = []
    if organizers:
        block.append(("ORGANIZAÇÃO", font("Roboto-500", 28), accent))
    for n in names:
        block.append((n, author_font, WHITE))
    y = 1950 - sum(round(f.size * 1.3) for _, f, _ in block)
    for text, fnt, fill in block:
        if fill == accent:
            tracked(draw, (margin, y), text, fnt, fill, 6)
        else:
            draw.text((margin, y), text, font=fnt, fill=fill)
        y += round(fnt.size * 1.3)

    # Rodapé: filete + logo da editora
    draw.rectangle([margin, 2030, W - margin, 2033], fill=(255, 255, 255, 60))
    logo = Image.open(LOGO).convert("RGBA")
    lw = 420
    logo = logo.resize((lw, round(logo.height * lw / logo.width)), Image.LANCZOS)
    img.paste(logo, (margin, 2075), logo)
    draw.rectangle([0, H - 18, W, H], fill=accent)

    out = Path(out)
    out.parent.mkdir(parents=True, exist_ok=True)
    img.save(out, quality=92, optimize=True)
    print("Capa gerada:", out)


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--title", required=True)
    ap.add_argument("--subtitle", default="")
    ap.add_argument("--authors", required=True, help='nomes separados por ";"')
    ap.add_argument("--series", required=True, choices=sorted(SERIES))
    ap.add_argument("--organizers", action="store_true", help="coletânea: os nomes são de organizadores")
    ap.add_argument("--image", help="foto ou ilustração opcional (com direitos de uso)")
    ap.add_argument("--out", default="capa.jpg")
    a = ap.parse_args()
    build(a.title, a.subtitle, a.authors, a.series, a.organizers, a.image, a.out)
