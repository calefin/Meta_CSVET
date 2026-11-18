"""
Monta os e-mails de captação (chamada de artigos e convites) no mesmo layout dos
e-mails do OJS, para enviar pelo Gmail.

Uso (na raiz do repositório):
    python content/nvas/emails/build.py           # gera content/nvas/emails/prontos/
    python content/nvas/emails/build.py --local   # idem, com o logo local (prévia antes do deploy)

Cada texto é um <arquivo>.<idioma>.html nesta pasta: a 1ª linha traz o assunto
(<!-- assunto: ... -->), o resto é o corpo. [[botao:URL|Texto]] vira um botão e a
assinatura (_assinatura.<idioma>.html) é acrescentada no fim.
Layout e textos fixos vêm de plugins/generic/csvetEmail (layout.html, trust.html e strings.json).
Abra prontos/index.html no navegador para ver todos.
"""

import html
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
PLUGIN = ROOT / "plugins/generic/csvetEmail"
OUT = HERE / "prontos"

JOURNAL_NAME = "Neotropical Veterinary and Animal Science"
JOURNAL_URL = "https://journals.csvet.com.br/nvas"
LOGO_PATH = "plugins/themes/csvet/images/email/nvas-logo-white.png"
LOGO_URL = "https://journals.csvet.com.br/" + LOGO_PATH

# Substitui o aviso de "mensagem automática" do OJS: estes e-mails são pessoais
OPT_OUT = {
    "pt_BR": "Você recebeu este convite pessoal do editor-chefe da revista. Se preferir não receber novos convites, basta responder a este e-mail.",
    "en": "This is a personal invitation from the Editor-in-Chief of the journal. If you prefer not to receive further invitations, simply reply to this e-mail.",
}

BUTTON = (
    '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">'
    '<tr><td style="background:#000080;border-radius:6px;">'
    '<a href="{url}" style="display:inline-block;padding:12px 28px;font-weight:bold;color:#ffffff;text-decoration:none;">{text}</a>'
    "</td></tr></table>"
)


def logo_tag(src: str) -> str:
    alt_style = "font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.3;color:#ffffff;"
    return (
        f'<img class="csvet-logo" src="{src}" width="300" height="65" alt="{JOURNAL_NAME}" '
        f'style="display:block;border:0;outline:none;width:300px;max-width:100%;height:auto;{alt_style}">'
    )


def build(source: Path, lang: str, layout: str, strings: dict, logo_src: str) -> tuple[str, str]:
    text = source.read_text(encoding="utf-8")
    match = re.match(r"\s*<!--\s*assunto:\s*(.+?)\s*-->\s*", text)
    if not match:
        sys.exit(f"{source.name}: falta a 1ª linha <!-- assunto: ... -->")
    subject, body = match.group(1), text[match.end():]
    body = re.sub(r"\[\[botao:([^|\]]+)\|([^\]]+)\]\]", lambda m: BUTTON.format(url=m.group(1), text=m.group(2)), body)
    body += (HERE / f"_assinatura.{lang}.html").read_text(encoding="utf-8")

    values = {k: html.escape(v) for k, v in strings[lang].items()}
    values.update(
        automatedNotice=html.escape(OPT_OUT[lang]),
        lang="pt-BR" if lang == "pt_BR" else "en",
        journalName=JOURNAL_NAME,
        journalUrl=JOURNAL_URL,
        journalHost=JOURNAL_URL.split("://", 1)[1],
        logo=logo_tag(logo_src),
        content=body,
    )
    # Faixa de credenciais (avaliação, acesso aberto, DOI): só nos e-mails de captação
    trust = (PLUGIN / "trust.html").read_text(encoding="utf-8")
    values["trustBand"] = re.sub(r"\{\{(\w+)\}\}", lambda m: values[m.group(1)], trust)
    page = re.sub(r"\{\{(\w+)\}\}", lambda m: values[m.group(1)], layout)
    page = page.replace(f"<title>{JOURNAL_NAME}</title>", f"<title>{html.escape(subject)}</title>", 1)
    return subject, page


def main() -> None:
    local = "--local" in sys.argv[1:]
    layout = (PLUGIN / "layout.html").read_text(encoding="utf-8")
    strings = json.loads((PLUGIN / "strings.json").read_text(encoding="utf-8"))
    logo_src = (ROOT / LOGO_PATH).as_uri() if local else LOGO_URL

    OUT.mkdir(exist_ok=True)
    rows = []
    for source in sorted(HERE.glob("[!_]*.html")):
        name, lang = re.match(r"(.+)\.(pt_BR|en)$", source.stem).groups()
        subject, page = build(source, lang, layout, strings, logo_src)
        (OUT / source.name).write_text(page, encoding="utf-8")
        rows.append(
            f'<tr><td>{"PT" if lang == "pt_BR" else "EN"}</td><td><a href="{source.name}">{html.escape(name)}</a></td>'
            f"<td><code>{html.escape(subject)}</code></td></tr>"
        )
        print(f"  {source.name}: {subject}")

    (OUT / "index.html").write_text(
        """<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>E-mails de captação NVAS</title>
<style>body{font:15px/1.5 Roboto,Segoe UI,Arial,sans-serif;color:#1d2233;max-width:960px;margin:32px auto;padding:0 16px}
h1{font-family:Georgia,serif;font-weight:normal;color:#000067}td{padding:8px 10px;border-bottom:1px solid #e3e6ef;vertical-align:top}
code{background:#f4f6fb;padding:2px 6px;border-radius:4px;user-select:all}a{color:#000080}ol li{margin-bottom:6px}</style></head><body>
<h1>E-mails de captação NVAS</h1>
<ol><li>Abra o e-mail abaixo, troque os campos entre colchetes ([NOME], [ÁREA]) direto no Gmail depois de colar.</li>
<li>Na página do e-mail: <b>Ctrl+A</b> e <b>Ctrl+C</b>. No Gmail, em uma nova mensagem: <b>Ctrl+V</b>.</li>
<li>Copie o assunto (clique nele para selecionar). Envie um por pessoa, nunca em massa com todos em cópia.</li></ol>
<table>""" + "\n".join(rows) + "</table></body></html>",
        encoding="utf-8",
    )
    print(f"Pronto: {OUT / 'index.html'}" + ("  (logo local: só para prévia, não envie)" if local else ""))


if __name__ == "__main__":
    main()
