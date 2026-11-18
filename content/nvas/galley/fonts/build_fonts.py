"""Gera as fontes estáticas da galé a partir das variáveis do Google Fonts (OFL).

O Chrome embute fonte variável no PDF como Type 3; com instâncias fixas ela vai como TrueType,
que é o esperado num PDF de artigo. Rode uma vez (precisa de rede e de fontTools):
    python content/nvas/galley/fonts/build_fonts.py
"""
import urllib.request
from io import BytesIO
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

HERE = Path(__file__).resolve().parent
BASE = "https://github.com/google/fonts/raw/main/ofl/"
SOURCES = {
    "serif": BASE + "sourceserif4/SourceSerif4%5Bopsz,wght%5D.ttf",
    "serif-italic": BASE + "sourceserif4/SourceSerif4-Italic%5Bopsz,wght%5D.ttf",
    "sans": BASE + "roboto/Roboto%5Bwdth,wght%5D.ttf",
    "sans-italic": BASE + "roboto/Roboto-Italic%5Bwdth,wght%5D.ttf",
}
# (fonte, arquivo de saída, eixos). opsz 10 para o texto corrido, 24 para o título.
INSTANCES = [
    ("serif", "NVASSerif-Regular.ttf", {"opsz": 10, "wght": 400}),
    ("serif", "NVASSerif-Semibold.ttf", {"opsz": 10, "wght": 600}),
    ("serif-italic", "NVASSerif-Italic.ttf", {"opsz": 10, "wght": 400}),
    ("serif-italic", "NVASSerif-SemiboldItalic.ttf", {"opsz": 10, "wght": 600}),
    ("serif", "NVASSerifDisplay-Semibold.ttf", {"opsz": 24, "wght": 600}),
    ("serif-italic", "NVASSerifDisplay-SemiboldItalic.ttf", {"opsz": 24, "wght": 600}),
    ("sans", "NVASSans-Regular.ttf", {"wdth": 100, "wght": 400}),
    ("sans", "NVASSans-Medium.ttf", {"wdth": 100, "wght": 500}),
    ("sans", "NVASSans-Semibold.ttf", {"wdth": 100, "wght": 600}),
    ("sans", "NVASSans-Bold.ttf", {"wdth": 100, "wght": 700}),
    ("sans-italic", "NVASSans-Italic.ttf", {"wdth": 100, "wght": 400}),
    ("sans-italic", "NVASSans-SemiboldItalic.ttf", {"wdth": 100, "wght": 600}),
]


def main():
    data = {k: urllib.request.urlopen(u).read() for k, u in SOURCES.items()}
    for src, name, axes in INSTANCES:
        font = instantiateVariableFont(TTFont(BytesIO(data[src])), axes, updateFontNames=True)
        font.save(HERE / name)
        print(name)
    for name, url in (("OFL-SourceSerif4.txt", BASE + "sourceserif4/OFL.txt"), ("OFL-Roboto.txt", BASE + "roboto/OFL.txt")):
        (HERE / name).write_bytes(urllib.request.urlopen(url).read())


if __name__ == "__main__":
    main()
