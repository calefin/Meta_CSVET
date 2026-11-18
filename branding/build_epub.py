"""Gera o e-book (EPUB 3) de um livro da Editora CSVET a partir do manuscrito final em Word.

Uso:
  python branding/build_epub.py --source livro-final.docx --title "Título" --subtitle "Subtítulo" \
      --authors "Ana Pereira; Carlos Mendes" --isbn 978-65-00-00000-0 --year 2026 \
      --cover capa.jpg --model aberto --doi 10.54547/csvet.book.12 --out livro.epub
  --organizers           os nomes são de organizadores (coletânea)
  --model aberto         CC BY 4.0 (padrão)  |  --model comercial   todos os direitos reservados
  --origin "texto"       nota de origem (obras derivadas de trabalho acadêmico)
  --doi 10.54547/...     DOI do livro (sufixo csvet.book.<id da submissão>), na página de créditos

Acessibilidade (Lei nº 13.146/2015, art. 68): o EPUB sai com os metadados schema.org de
acessibilidade (EPUB Accessibility 1.1) e o script avisa as figuras sem texto alternativo,
que devem ser corrigidas no Word (Editar Texto Alt) antes de distribuir.

Requer pandoc (https://pandoc.org). O manuscrito deve usar os estilos do modelo da editora
(Título 1 = capítulo, Título 2/3 = seções). A capa sai de branding/build_cover.py.
Fontes Source Serif 4 e Roboto são incorporadas (licença OFL/Apache permite).
Confira o resultado no Calibre ou no Thorium e valide com o EPUBCheck antes de distribuir.
"""
import argparse
import re
import shutil
import subprocess
import tempfile
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FONTS = ROOT / "branding" / "fonts"
CSS = """
@font-face { font-family: "Source Serif 4"; src: url("../fonts/SourceSerif4-Regular.ttf"); }
@font-face { font-family: "Source Serif 4"; font-weight: bold; src: url("../fonts/SourceSerif4-Semibold.ttf"); }
@font-face { font-family: "Roboto"; src: url("../fonts/Roboto-400.ttf"); }
@font-face { font-family: "Roboto"; font-weight: bold; src: url("../fonts/Roboto-700.ttf"); }
body { font-family: "Source Serif 4", Georgia, serif; line-height: 1.5; margin: 0 4%; }
p { text-indent: 1.2em; margin: 0; text-align: justify; hyphens: auto; -epub-hyphens: auto; }
h1, h2, h3, h4 { font-family: Roboto, Arial, sans-serif; color: #000080; text-indent: 0; line-height: 1.2; page-break-after: avoid; }
h1 { font-size: 1.6em; margin: 2.5em 0 1.2em; page-break-before: always; }
h2 { font-size: 1.2em; margin: 1.6em 0 .5em; }
h3 { font-size: 1.05em; margin: 1.2em 0 .4em; }
h1 + p, h2 + p, h3 + p, blockquote p, figcaption, li p, td p, .creditos p { text-indent: 0; }
blockquote { margin: 1em 0 1em 2em; font-size: .92em; }
figure { margin: 1.2em 0; text-align: center; page-break-inside: avoid; }
img { max-width: 100%; height: auto; }
figcaption { font-family: Roboto, Arial, sans-serif; font-size: .85em; text-align: left; margin-top: .4em; }
table { border-collapse: collapse; margin: 1em auto; font-size: .9em; }
th, td { border-bottom: 1px solid #c6c6c6; padding: .25em .5em; }
a { color: #0088cc; }
.creditos { font-size: .82em; }
.creditos h1 { page-break-before: auto; font-size: 1.1em; margin-top: 0; }
.aviso { border-left: 3px solid #00aaff; padding-left: .8em; }
/* estilos do modelo de livro (pandoc docx+styles) */
div[custom-style="Legenda"] p { font-family: Roboto, Arial, sans-serif; font-size: .85em; text-indent: 0; margin-top: .8em; }
div[custom-style="Fonte da figura"] p { font-family: Roboto, Arial, sans-serif; font-size: .78em; color: #555566; text-indent: 0; margin-bottom: 1em; }
div[custom-style="Citação longa"] p { margin: .8em 0 .8em 2em; font-size: .9em; text-indent: 0; }
div[custom-style="Referência"] p { text-indent: -1.5em; margin: 0 0 .4em 1.5em; font-size: .9em; text-align: left; }
div[custom-style="Resumo"] p, div[custom-style="Autores do capítulo"] p { text-indent: 0; font-size: .92em; }
div[custom-style="Autores do capítulo"] p { font-style: italic; color: #555566; margin-bottom: 1em; }
div[custom-style="Nota para o autor"], div[custom-style="Título do livro"], div[custom-style="Subtítulo do livro"] { display: none; }
"""

RIGHTS = {
    "aberto": "Esta obra está licenciada sob a licença Creative Commons Atribuição 4.0 Internacional (CC BY 4.0): "
              "https://creativecommons.org/licenses/by/4.0/deed.pt-br. O reuso é permitido desde que os autores, a obra e a "
              "Editora CSVET sejam citados e as alterações indicadas. A venda desta edição por terceiros como se fosse própria, "
              "a omissão da autoria e o plágio violam a licença e a Lei nº 9.610/1998.",
    "comercial": "Todos os direitos reservados. Nenhuma parte desta obra pode ser reproduzida, distribuída, vendida, "
                 "compartilhada, transmitida ou usada para treinamento de sistemas de inteligência artificial sem autorização "
                 "prévia e por escrito da Editora CSVET, exceto citações curtas com indicação da autoria e da fonte (Lei nº 9.610/1998, "
                 "art. 46). A violação de direitos autorais é crime (Código Penal, art. 184). Este exemplar é de uso pessoal do comprador.",
}


def run(cmd):
    print(" ".join(str(c) for c in cmd))
    subprocess.run([str(c) for c in cmd], check=True)


def main():
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--source", required=True, help="manuscrito final (.docx)")
    ap.add_argument("--title", required=True)
    ap.add_argument("--subtitle", default="")
    ap.add_argument("--authors", required=True, help='nomes separados por ";"')
    ap.add_argument("--organizers", action="store_true")
    ap.add_argument("--isbn", default="")
    ap.add_argument("--year", default=str(date.today().year))
    ap.add_argument("--cover")
    ap.add_argument("--model", choices=sorted(RIGHTS), default="aberto")
    ap.add_argument("--origin", default="")
    ap.add_argument("--lang", default="pt-BR")
    ap.add_argument("--doi", default="", help="DOI do livro, ex.: 10.54547/csvet.book.12")
    ap.add_argument("--out", default="livro.epub")
    a = ap.parse_args()

    pandoc = shutil.which("pandoc") or str(Path.home() / "AppData/Local/Pandoc/pandoc.exe")
    names = [n.strip() for n in a.authors.split(";") if n.strip()]
    with tempfile.TemporaryDirectory() as tmp:
        tmp = Path(tmp)
        # 1. Word -> Markdown (com as imagens extraídas)
        run([pandoc, a.source, "-f", "docx+styles", "-t", "markdown", "--extract-media", tmp, "-o", tmp / "miolo.md"])
        # Figuras sem texto alternativo: leitores de tela não têm o que ler
        miolo = (tmp / "miolo.md").read_text(encoding="utf-8")
        # Imagens soltas saem em Markdown (![alt](src)); figuras com legenda, em HTML (<img alt=...>)
        figures = re.findall(r"!\[(.*?)\]\(([^)\s]+)", miolo, flags=re.S)
        for tag in re.findall(r"<img\b[^>]*>", miolo, flags=re.S):
            alt = re.search(r'\balt="([^"]*)"', tag)
            src = re.search(r'\bsrc="([^"]*)"', tag)
            figures.append((alt.group(1) if alt else "", src.group(1) if src else "?"))
        missing = [Path(src).name for alt, src in figures if not alt.strip()]
        if missing:
            print(f"ATENÇÃO: {len(missing)} de {len(figures)} figura(s) sem texto alternativo: {', '.join(missing[:10])}"
                  + (" ..." if len(missing) > 10 else ""))
            print("  Corrija no Word (botão direito na imagem > Editar Texto Alt) e gere o EPUB de novo.")
        # 2. Página de créditos (direitos, ISBN, nota de origem) antes do miolo
        credit = [f'# Créditos {{.unnumbered .unlisted}}', "", '::: {.creditos}', "",
                  f"**{a.title}**" + (f" – {a.subtitle}" if a.subtitle else ""), "",
                  ("Organização: " if a.organizers else "") + " · ".join(names), "",
                  f"© {a.year} os autores. Publicado pela Editora CSVET (CSVET Editora, Cursos e Treinamentos, CNPJ 32.645.724/0001-89), "
                  "Americana, SP. https://books.csvet.com.br", "",
                  f"::: {{.aviso}}\n{RIGHTS[a.model]}\n:::", ""]
        if a.origin:
            credit += [f"*{a.origin}*", ""]
        credit += ["Avaliação por pares duplo-anônima. Obra aprovada pelo Conselho Editorial após parecer de especialistas externos.", ""]
        if a.isbn:
            credit += [f"**ISBN {a.isbn} (EPUB)**", ""]
        if a.doi:
            credit += [f"DOI: <https://doi.org/{a.doi}>", ""]
        credit += [":::", ""]
        (tmp / "creditos.md").write_text("\n".join(credit), encoding="utf-8")
        meta = [
            "---",
            f'title: "{a.title}"',
            f'subtitle: "{a.subtitle}"' if a.subtitle else "",
            "creator:",
            *[f'  - role: {"edt" if a.organizers else "aut"}\n    text: "{n}"' for n in names],
            'publisher: "Editora CSVET"',
            f'date: "{a.year}"',
            f'lang: "{a.lang}"',
            f'rights: "{RIGHTS[a.model]}"',
            "identifier:" if (a.isbn or a.doi) else "",
            f'  - scheme: ISBN-13\n    text: "{a.isbn}"' if a.isbn else "",
            f'  - scheme: DOI\n    text: "{a.doi}"' if a.doi else "",
            # Acessibilidade (schema.org / EPUB Accessibility 1.1), nos campos próprios do pandoc 3
            "accessModes: [textual" + (", visual]" if figures else "]"),
            "accessModeSufficient: [textual]",
            "accessibilityFeatures: [readingOrder, structuralNavigation, tableOfContents, displayTransformability"
            + (", alternativeText]" if figures and not missing else "]"),
            "accessibilityHazards: [none]",
            'accessibilitySummary: "Texto fluido com estrutura de capítulos e seções e sumário navegável'
            + (", com texto alternativo em todas as figuras" if figures and not missing else "")
            + '. Fonte, espaçamento e cores ajustáveis no aplicativo de leitura."',
            "---",
        ]
        (tmp / "meta.yaml").write_text("\n".join(m for m in meta if m), encoding="utf-8")
        (tmp / "epub.css").write_text(CSS, encoding="utf-8")
        # 3. Markdown -> EPUB 3 (sumário, capa, fontes incorporadas)
        cmd = [pandoc, tmp / "meta.yaml", tmp / "creditos.md", tmp / "miolo.md", "-f", "markdown", "-t", "epub3",
               "--toc", "--toc-depth=2", "--split-level=1", "--css", tmp / "epub.css", "--resource-path", tmp,
               "-o", a.out]
        for font in ("SourceSerif4-Regular", "SourceSerif4-Semibold", "Roboto-400", "Roboto-700"):
            cmd += ["--epub-embed-font", FONTS / f"{font}.ttf"]
        if a.cover:
            cmd += ["--epub-cover-image", a.cover]
        run(cmd)
    print("E-book gerado:", a.out)


if __name__ == "__main__":
    main()
