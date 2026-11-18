"""Gera os modelos de e-mail do OJS para avaliadores, bilíngues (português e inglês na mesma mensagem).

O avaliador pode falar português ou inglês, e o OJS manda o convite no idioma da tela de quem
envia. Cada mensagem traz os dois idiomas: o da tela de quem envia vem primeiro, o outro logo
abaixo, cada um com o seu botão e o prazo no começo (leitura no celular). Título e resumo do
manuscrito, a decisão e a assinatura aparecem uma vez só, no fim.

Uso:  python content/nvas/ojs-emails/build_reviewer_emails.py
Saída: content/nvas/ojs-emails/<KEY>.pt_BR.html e <KEY>.en.html. Os assuntos ficam em
"emailTemplates" do journal.json; scripts/configure-journal.php grava tudo no OJS.

Marcas que o plugin csvetEmail transforma no layout (se o editor de texto do OJS tirar a
classe, sobra um link ou parágrafo comum, que continua funcionando):
  <p class="csvet-cta"><a href="...">...</a></p>   botão azul
  <p class="csvet-langnote">...</p>                 aviso discreto "versão em ... abaixo"
  <hr>                                              divisória entre os idiomas
Use só as variáveis que o OJS oferece em cada modelo (as mesmas do texto padrão do OJS).
"""
from pathlib import Path

OUT_DIR = Path(__file__).resolve().parent


def cta(url, text):
    return f'<p class="csvet-cta"><a href="{url}">{text}</a></p>'


INVITE_PT = cta("{$reviewAssignmentUrl}", "Aceitar ou recusar o convite")
INVITE_EN = cta("{$reviewAssignmentUrl}", "Accept or decline the invitation")
OPEN_PT = cta("{$reviewAssignmentUrl}", "Abrir a avaliação")
OPEN_EN = cta("{$reviewAssignmentUrl}", "Open the review")

RULES_PT = (
    "<ul>"
    "<li>A avaliação é duplo-anônima: você não verá os nomes dos autores, e eles não verão o seu.</li>"
    "<li>O manuscrito é confidencial: não o compartilhe nem o envie, no todo ou em parte, a ferramentas de inteligência artificial.</li>"
    "<li>Se houver conflito de interesses com o trabalho ou com os autores, recuse o convite ou informe-o no formulário.</li>"
    "</ul>"
)
RULES_EN = (
    "<ul>"
    "<li>Review is double-anonymous: you will not see the authors' names, and they will not see yours.</li>"
    "<li>The manuscript is confidential: do not share it or upload it, in whole or in part, to artificial intelligence tools.</li>"
    "<li>If you have a competing interest with the work or the authors, decline the invitation or declare it in the form.</li>"
    "</ul>"
)

MANUSCRIPT = (
    '<p><strong>Manuscrito / Manuscript:</strong> {$submissionTitle}</p>'
    "<p><strong>Resumo / Abstract</strong></p>"
    "{$submissionAbstract}"
)

SIGNED = "<p>Atenciosamente / Kind regards,</p>{$signature}"
SIGNED_EN = "<p>Kind regards / Atenciosamente,</p>{$signature}"
# Mensagens automáticas (lembretes do sistema) não têm remetente: assina a equipe editorial
AUTO_SIGNED = "<p>Atenciosamente / Kind regards,<br>Equipe editorial / Editorial office<br><em>{$contextName}</em></p>"
AUTO_SIGNED_EN = "<p>Kind regards / Atenciosamente,<br>Editorial office / Equipe editorial<br><em>{$contextName}</em></p>"

TEMPLATES = {
    # Convite (Adicionar avaliador)
    "REVIEW_REQUEST": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Convidamos você a avaliar um manuscrito submetido à <em>{$contextName}</em>, na sua área de atuação. "
            "O título e o resumo estão no fim desta mensagem.</p>"
            + INVITE_PT +
            "<p>Responda até <strong>{$responseDueDate}</strong>. Se aceitar, o prazo para enviar a avaliação é "
            "<strong>{$reviewDueDate}</strong>.</p>"
            + RULES_PT +
            "<p>Se não puder avaliar, recuse pelo mesmo botão. A indicação de outro avaliador, respondendo a este "
            "e-mail, é muito bem-vinda.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>We would like to invite you to review a manuscript submitted to <em>{$contextName}</em> in your field. "
            "The title and abstract are at the end of this message.</p>"
            + INVITE_EN +
            "<p>Please respond by <strong>{$responseDueDate}</strong>. If you accept, the review is due by "
            "<strong>{$reviewDueDate}</strong>.</p>"
            + RULES_EN +
            "<p>If you are unable to review, please decline using the same button. Suggestions of other reviewers, "
            "by replying to this e-mail, are very welcome.</p>"
        ),
        "shared": MANUSCRIPT,
        "signed": "manual",
    },
    # Convite para a nova rodada (versão revisada)
    "REVIEW_REQUEST_SUBSEQUENT": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Obrigado pela sua avaliação do manuscrito \"{$submissionTitle}\". Os autores responderam aos pareceres "
            "e enviaram uma versão revisada. Você poderia avaliar esta nova versão?</p>"
            + INVITE_PT +
            "<p>Responda até <strong>{$responseDueDate}</strong>. Se aceitar, o prazo para enviar a avaliação é "
            "<strong>{$reviewDueDate}</strong>.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>Thank you for your review of the manuscript \"{$submissionTitle}\". The authors have addressed the "
            "reviewers' comments and submitted a revised version. Would you be able to review this new version?</p>"
            + INVITE_EN +
            "<p>Please respond by <strong>{$responseDueDate}</strong>. If you accept, the review is due by "
            "<strong>{$reviewDueDate}</strong>.</p>"
        ),
        "shared": MANUSCRIPT,
        "signed": "manual",
    },
    # Automático: o convite passou do prazo de resposta
    "REVIEW_RESPONSE_OVERDUE_AUTO": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Ainda não recebemos sua resposta ao convite para avaliar o manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em>. Poderia nos dizer se aceita? Leva menos de um minuto.</p>"
            + INVITE_PT +
            "<p>Se aceitar, o prazo para enviar a avaliação é <strong>{$reviewDueDate}</strong>. Se não puder, recusar "
            "também nos ajuda: assim convidamos outra pessoa sem demora.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>We have not yet received your reply to our invitation to review the manuscript \"{$submissionTitle}\" "
            "for <em>{$contextName}</em>. Could you let us know whether you accept? It takes less than a minute.</p>"
            + INVITE_EN +
            "<p>If you accept, the review is due by <strong>{$reviewDueDate}</strong>. If you are unable to review, "
            "declining also helps us: we can then invite someone else without delay.</p>"
        ),
        "shared": MANUSCRIPT,
        "signed": "auto",
    },
    # Lembrete enviado pelo editor (menu do avaliador › Enviar lembrete)
    "REVIEW_REMIND": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Este é um lembrete sobre a sua avaliação do manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em>. O prazo para envio é <strong>{$reviewDueDate}</strong>, e ficaremos muito "
            "gratos em recebê-la assim que possível.</p>"
            + OPEN_PT +
            "<p>Se precisar de mais prazo, responda a este e-mail com a data que lhe convém.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>This is a reminder about your review of the manuscript \"{$submissionTitle}\" for "
            "<em>{$contextName}</em>. The review is due by <strong>{$reviewDueDate}</strong>, and we would be very "
            "grateful to receive it as soon as you are able.</p>"
            + OPEN_EN +
            "<p>If you need more time, please reply to this e-mail with a date that suits you.</p>"
        ),
        "shared": "",
        "signed": "manual",
    },
    # Opção do Lembrete ao Avaliador (plugin csvetReviewReminder): convite sem resposta, ou que pode
    # não ter chegado. Repete o convite inteiro, com o botão de aceitar ou recusar
    "NVAS_REVIEW_INVITE_RESEND": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Há alguns dias convidamos você a avaliar um manuscrito submetido à <em>{$contextName}</em> e ainda "
            "não recebemos sua resposta. Como o convite pode não ter chegado, nós o reenviamos aqui. O título e o "
            "resumo estão no fim desta mensagem.</p>"
            + INVITE_PT +
            "<p>Responda até <strong>{$responseDueDate}</strong>. Se aceitar, o prazo para enviar a avaliação é "
            "<strong>{$reviewDueDate}</strong>.</p>"
            + RULES_PT +
            "<p>Se não puder avaliar, recuse pelo mesmo botão: assim convidamos outra pessoa sem demora. A "
            "indicação de outro avaliador, respondendo a este e-mail, é muito bem-vinda.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>A few days ago we invited you to review a manuscript submitted to <em>{$contextName}</em>, and we "
            "have not yet received your reply. As the invitation may not have reached you, we are sending it again "
            "here. The title and abstract are at the end of this message.</p>"
            + INVITE_EN +
            "<p>Please respond by <strong>{$responseDueDate}</strong>. If you accept, the review is due by "
            "<strong>{$reviewDueDate}</strong>.</p>"
            + RULES_EN +
            "<p>If you are unable to review, please decline using the same button, so that we can invite someone "
            "else without delay. Suggestions of other reviewers, by replying to this e-mail, are very welcome.</p>"
        ),
        "shared": MANUSCRIPT,
        "signed": "manual",
    },
    # Opção do Lembrete ao Avaliador (plugin csvetReviewReminder): avaliação aceita e com o prazo vencido
    "NVAS_REVIEW_OVERDUE": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>O prazo da sua avaliação do manuscrito \"{$submissionTitle}\" para a <em>{$contextName}</em> venceu "
            "em <strong>{$reviewDueDate}</strong>. Sabemos que a rotina é corrida e agradecemos muito por ter "
            "aceitado o convite.</p>"
            + OPEN_PT +
            "<p>Poderia nos dizer, respondendo a este e-mail, até quando consegue enviá-la? Se não for mais "
            "possível, avise-nos também: assim convidamos outra pessoa e os autores não ficam esperando.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>The deadline for your review of the manuscript \"{$submissionTitle}\" for <em>{$contextName}</em> "
            "was <strong>{$reviewDueDate}</strong>. We know how busy things can be, and we are very grateful that "
            "you accepted the invitation.</p>"
            + OPEN_EN +
            "<p>Could you let us know, by replying to this e-mail, when you expect to submit it? If it is no longer "
            "possible, please tell us as well, so that we can invite someone else and the authors are not kept "
            "waiting.</p>"
        ),
        "shared": "",
        "signed": "manual",
    },
    # Automático: lembrete antes e depois do prazo da avaliação
    "REVIEW_REMIND_AUTO": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Este é um lembrete automático sobre a sua avaliação do manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em>. O prazo para envio é <strong>{$reviewDueDate}</strong>.</p>"
            + OPEN_PT +
            "<p>Se precisar de mais prazo, responda a este e-mail com a data que lhe convém.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>This is an automatic reminder about your review of the manuscript \"{$submissionTitle}\" for "
            "<em>{$contextName}</em>. The review is due by <strong>{$reviewDueDate}</strong>.</p>"
            + OPEN_EN +
            "<p>If you need more time, please reply to this e-mail with a date that suits you.</p>"
        ),
        "shared": "",
        "signed": "auto",
    },
    # Dispensar avaliador (inclusive quando as avaliações necessárias já chegaram)
    "REVIEW_CANCEL": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Informamos que o convite para avaliar o manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em> foi cancelado, porque a avaliação deste manuscrito já está encaminhada. "
            "Não é preciso fazer mais nada.</p>"
            "<p>Se você já tinha começado, pedimos desculpas e agradecemos o tempo dedicado. Esperamos contar com "
            "você em outra oportunidade.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>We would like to let you know that our request for you to review the manuscript \"{$submissionTitle}\" "
            "for <em>{$contextName}</em> has been cancelled, because the review of this manuscript is already under "
            "way. No further action is needed.</p>"
            "<p>If you had already started, we apologise and thank you for your time. We hope to work with you on "
            "another occasion.</p>"
        ),
        "shared": "",
        "signed": "manual",
    },
    # Desfazer o cancelamento
    "REVIEW_REINSTATE": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Recentemente cancelamos o convite para você avaliar o manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em>. Revimos essa decisão e gostaríamos muito de contar com a sua avaliação.</p>"
            + OPEN_PT
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>We recently cancelled our request for you to review the manuscript \"{$submissionTitle}\" for "
            "<em>{$contextName}</em>. We have reversed that decision and would be very glad to have your review.</p>"
            + OPEN_EN
        ),
        "shared": "",
        "signed": "manual",
    },
    # Novo pedido a quem recusou
    "REVIEW_RESEND_REQUEST": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Há pouco você recusou o convite para avaliar o manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em>. Escrevemos para saber se, afinal, seria possível fazer a avaliação. "
            "Entendemos se não for possível agora.</p>"
            + INVITE_PT +
            "<p>Responda até <strong>{$responseDueDate}</strong>, para que possamos convidar outra pessoa se for "
            "o caso.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>You recently declined our invitation to review the manuscript \"{$submissionTitle}\" for "
            "<em>{$contextName}</em>. We are writing to ask whether you might be able to do the review after all. "
            "We understand if this is not possible at the moment.</p>"
            + INVITE_EN +
            "<p>Please respond by <strong>{$responseDueDate}</strong>, so that we can invite someone else if "
            "needed.</p>"
        ),
        "shared": "",
        "signed": "manual",
    },
    # Agradecimento depois da avaliação
    "REVIEW_ACK": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Obrigado por concluir a avaliação do manuscrito \"{$submissionTitle}\" para a "
            "<em>{$contextName}</em>. Seu tempo e sua experiência são essenciais para a qualidade do que "
            "publicamos.</p>"
            "<p>Se precisar de uma declaração de participação como avaliador (para o currículo ou para a sua "
            "instituição), basta responder a este e-mail.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>Thank you for completing your review of the manuscript \"{$submissionTitle}\" for "
            "<em>{$contextName}</em>. Your time and expertise are essential to the quality of what we publish.</p>"
            "<p>If you need a certificate of your work as a reviewer (for your CV or your institution), simply "
            "reply to this e-mail.</p>"
        ),
        "shared": "",
        "signed": "manual",
    },
    # O editor mudou prazos ou dados da avaliação
    "REVIEW_EDIT": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Os dados da sua avaliação do manuscrito \"{$submissionTitle}\" para a <em>{$contextName}</em> foram "
            "atualizados pelo editor. Os prazos agora são:</p>"
            "<ul><li>Responder ao convite: <strong>{$responseDueDate}</strong></li>"
            "<li>Enviar a avaliação: <strong>{$reviewDueDate}</strong></li></ul>"
            + OPEN_PT +
            "<p>Em caso de dúvida, responda a este e-mail.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>The editor has updated your review assignment for the manuscript \"{$submissionTitle}\" for "
            "<em>{$contextName}</em>. The deadlines are now:</p>"
            "<ul><li>Reply to the invitation: <strong>{$responseDueDate}</strong></li>"
            "<li>Submit the review: <strong>{$reviewDueDate}</strong></li></ul>"
            + OPEN_EN +
            "<p>If you have any questions, please reply to this e-mail.</p>"
        ),
        "shared": "",
        "signed": "manual",
    },
    # Aviso aos avaliadores junto com a decisão do editor
    "EDITOR_DECISION_NOTIFY_REVIEWERS": {
        "pt": (
            "<p>Prezado(a) {$recipientName},</p>"
            "<p>Obrigado por avaliar o manuscrito \"{$submissionTitle}\" para a <em>{$contextName}</em>. Enviamos "
            "aos autores os pareceres e a decisão do editor, que está no fim desta mensagem.</p>"
            "<p>Sua recomendação foi considerada junto com as dos demais avaliadores. Às vezes a decisão difere da "
            "recomendação de um avaliador: o editor pesa vários fatores, e somos gratos pelas suas sugestões.</p>"
        ),
        "en": (
            "<p>Dear {$recipientName},</p>"
            "<p>Thank you for reviewing the manuscript \"{$submissionTitle}\" for <em>{$contextName}</em>. We have "
            "sent the reviews and the editor's decision, shown at the end of this message, to the authors.</p>"
            "<p>Your recommendation was considered together with those of the other reviewers. The decision sometimes "
            "differs from a reviewer's recommendation: the editor weighs several factors, and we are grateful for "
            "your suggestions.</p>"
        ),
        "shared": "<p><strong>Decisão / Decision:</strong></p><p>{$decisionDescription}</p>",
        "signed": "manual",
    },
}


def build(first, second, note, shared, signed):
    return "\n".join(part for part in [
        f'<p class="csvet-langnote">{note}</p>',
        first,
        "<hr>",
        second,
        "<hr>" if shared else "",
        shared,
        signed,
    ] if part) + "\n"


def main():
    for key, t in TEMPLATES.items():
        auto = t["signed"] == "auto"
        files = {
            "pt_BR": build(t["pt"], t["en"], "English version below.", t["shared"], AUTO_SIGNED if auto else SIGNED),
            "en": build(t["en"], t["pt"], "Versão em português abaixo.", t["shared"], AUTO_SIGNED_EN if auto else SIGNED_EN),
        }
        for locale, html in files.items():
            (OUT_DIR / f"{key}.{locale}.html").write_text(html, encoding="utf-8")
    print(f"{len(TEMPLATES)} modelos x 2 idiomas em {OUT_DIR}")


if __name__ == "__main__":
    main()
