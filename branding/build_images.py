"""Gera as imagens do portal de livros a partir dos logos da Editora CSVET.

Uso:  python branding/build_images.py
Entrada: branding/assets/ (logos oficiais copiados de csvet.com.br/img) e branding/fonts/Roboto-*.ttf
Saída:
  content/editora/images/   logo do cabeçalho, favicon e miniatura (aplicados por scripts/configure-press.php)
  plugins/themes/csvet/images/og/editora.png   imagem de compartilhamento 1200x630 (WhatsApp, LinkedIn...)

Para mudar os textos da imagem de compartilhamento, edite OG_* abaixo.
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent.parent
ASSETS = ROOT / "branding" / "assets"
FONTS = ROOT / "branding" / "fonts"
CONTENT = ROOT / "content" / "editora" / "images"
OG = ROOT / "plugins" / "themes" / "csvet" / "images" / "og"

OG_KICKER = "LIVROS  E  MONOGRAFIAS"
OG_TAGLINE = "Acesso aberto  ·  Avaliação por pares  ·  Medicina veterinária"
OG_URL = "books.csvet.com.br"

NAVY = (0, 0, 128)
NAVY_DARK = (0, 0, 103)
ACCENT = (0, 170, 255)
SOFT = (201, 205, 240)


def font(weight, size):
    return ImageFont.truetype(str(FONTS / f"Roboto-{weight}.ttf"), size)


def centered(draw, y, text, fnt, fill, width):
    w = draw.textlength(text, font=fnt)
    draw.text(((width - w) / 2, y), text, font=fnt, fill=fill)


def og_image():
    W, H = 1200, 630
    img = Image.new("RGB", (W, H), NAVY_DARK)
    # gradiente vertical navy-dark -> navy, igual ao og-image de csvet.com.br
    px = img.load()
    for y in range(H):
        t = y / (H - 1)
        c = tuple(round(a + (b - a) * t) for a, b in zip(NAVY_DARK, NAVY))
        for x in range(W):
            px[x, y] = c
    logo = Image.open(ASSETS / "logo-csvet-branco.png").convert("RGBA")
    lw = 640
    logo = logo.resize((lw, round(logo.height * lw / logo.width)), Image.LANCZOS)
    img.paste(logo, ((W - lw) // 2, 150), logo)
    d = ImageDraw.Draw(img)
    centered(d, 92, OG_KICKER, font(500, 26), ACCENT, W)
    centered(d, 395, OG_TAGLINE, font(400, 34), SOFT, W)
    centered(d, 520, OG_URL, font(500, 28), ACCENT, W)
    d.rectangle([0, H - 8, W, H], fill=ACCENT)
    OG.mkdir(parents=True, exist_ok=True)
    img.save(OG / "editora.png", optimize=True)


def content_images():
    CONTENT.mkdir(parents=True, exist_ok=True)
    # Logo do cabeçalho (fundo navy do tema): versão branca, 2x para telas retina
    Image.open(ASSETS / "logo-csvet-branco.png").save(CONTENT / "logo-white.png", optimize=True)
    # Favicon e miniatura (lista de editoras do portal): ícone do livro sobre navy
    icon = Image.open(ASSETS / "icon-512.png").convert("RGBA")
    icon.resize((180, 180), Image.LANCZOS).save(CONTENT / "favicon.png", optimize=True)
    icon.save(CONTENT / "thumbnail.png", optimize=True)


if __name__ == "__main__":
    og_image()
    content_images()
    print("Imagens geradas em", CONTENT, "e", OG)
