/**
 * Botão "Enviar lembrete" na lista de avaliadores (Avaliação › Rodada) também antes do prazo.
 * O OJS 3.5 só o mostra com a resposta ou a avaliação vencida; sem ele, não há como reenviar
 * o convite nem pegar a mensagem com o link para o WhatsApp (CsvetReviewReminderPlugin).
 * Abre a mesma janela "Lembrete ao Avaliador" do OJS (ação reviewerSendReminder do store).
 * Qualquer erro aqui deixa a lista como está.
 */
(function () {
	if (!window.pkp || !pkp.registry || !pkp.registry.storeExtend || !pkp.const) {
		return;
	}
	var c = pkp.const;
	var open = [
		c.REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE,
		c.REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND,
		c.REVIEW_ASSIGNMENT_STATUS_ACCEPTED
	];
	var pt = /^pt/i.test(document.documentElement.lang || '');

	try {
		pkp.registry.storeExtend('reviewerManager', function (piniaContext) {
			var extender = piniaContext.store && piniaContext.store.extender;
			if (!extender) {
				return;
			}
			extender.extendFn('getItemPrimaryActions', function (items, args) {
				try {
					var ra = args && args.reviewAssignment;
					items = items || [];
					if (!ra || args.redactedForAuthors || open.indexOf(ra.statusId) === -1
						|| items.some(function (i) { return i.name === 'reviewerSendReminder'; })) {
						return items;
					}
					return items.concat([{
						label: pt ? 'Enviar lembrete / link' : 'Send reminder / link',
						name: 'reviewerSendReminder'
					}]);
				} catch (e) {
					return items;
				}
			});
		});
	} catch (e) {
		// Sem o botão, a lista do OJS continua como antes
	}
})();
