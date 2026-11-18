/**
 * Confirmação antes de registrar uma decisão editorial (página /decision/record).
 *
 * O botão "Registrar decisão" muda a etapa do artigo e avisa os autores. Antes do
 * clique final, uma janela explica em palavras simples o que a decisão faz e qual é o
 * botão certo para o engano mais comum (aceitar quando a intenção era pedir correções).
 * Cancelar deixa tudo como estava; Continuar registra a decisão normalmente.
 */
(function () {
	// Códigos das decisões do OJS (APP\decision\Decision)
	var TEXTS = {
		pt: {
			2: 'ACEITAR o manuscrito.\n\nO artigo sai da avaliação e vai para a Edição de texto, e os autores recebem a notícia de que foi aceito. Não há nova rodada de correções pelos avaliadores.\n\nSe a intenção é pedir correções aos autores, clique em Cancelar e use "Solicitar revisões".',
			17: 'ACEITAR SEM AVALIAÇÃO POR PARES.\n\nO artigo pula a avaliação e vai direto para a Edição de texto. Use só em editoriais e cartas ao editor.\n\nPara mandar aos avaliadores, clique em Cancelar e use "Enviar para avaliação".',
			3: 'ENVIAR PARA AVALIAÇÃO POR PARES.\n\nO artigo passa da triagem para a avaliação, e você poderá convidar os avaliadores. Confira antes se os arquivos marcados para a avaliação estão sem os nomes dos autores.',
			4: 'SOLICITAR REVISÕES (correções obrigatórias).\n\nO artigo continua na avaliação; os autores recebem os pareceres e enviam a versão corrigida.',
			5: 'REENVIAR PARA AVALIAÇÃO.\n\nOs autores fazem mudanças grandes e a nova versão volta para uma nova rodada de avaliadores.',
			6: 'RECUSAR o manuscrito (decisão definitiva).\n\nOs autores recebem a recusa e o artigo é arquivado.',
			8: 'RECUSAR SEM AVALIAÇÃO (decisão definitiva).\n\nO artigo é recusado na triagem, sem ir aos avaliadores, e é arquivado.',
			7: 'ENVIAR PARA PRODUÇÃO.\n\nA edição de texto termina e o artigo vai para a diagramação e o PDF final.',
			14: 'NOVA RODADA DE AVALIAÇÃO.\n\nAbre uma nova rodada; os avaliadores da rodada anterior não são convidados de novo automaticamente.',
			31: 'CANCELAR A RODADA DE AVALIAÇÃO.\n\nA rodada atual é desfeita.',
			29: 'VOLTAR DA PRODUÇÃO para a Edição de texto.',
			30: 'VOLTAR DA EDIÇÃO DE TEXTO para a avaliação.',
			15: 'DESFAZER A RECUSA: o artigo volta ao fluxo.',
			16: 'DESFAZER A RECUSA NA TRIAGEM: o artigo volta à triagem.',
			recommend: 'Registrar a sua RECOMENDAÇÃO ao editor responsável.\n\nA decisão final é do editor; os autores não são avisados agora.',
			other: 'Registrar esta decisão?\n\nEla muda a etapa do artigo no fluxo editorial.',
			footer: '\n\nOK = registrar a decisão. Cancelar = voltar sem mudar nada.'
		},
		en: {
			2: 'ACCEPT the manuscript.\n\nThe article leaves review and goes to Copyediting, and the authors are told it was accepted. There is no further round of corrections by the reviewers.\n\nIf you want the authors to make corrections, click Cancel and use "Request Revisions".',
			17: 'ACCEPT WITHOUT PEER REVIEW.\n\nThe article skips review and goes straight to Copyediting. Use only for editorials and letters to the editor.\n\nTo send it to reviewers, click Cancel and use "Send for Review".',
			3: 'SEND FOR PEER REVIEW.\n\nThe article moves from screening to review and you can invite reviewers. Check first that the files selected for review do not contain author names.',
			4: 'REQUEST REVISIONS.\n\nThe article stays in review; the authors receive the reviews and submit a revised version.',
			5: 'RESUBMIT FOR REVIEW.\n\nThe authors make major changes and the new version goes to a new round of reviewers.',
			6: 'DECLINE the manuscript (final decision).\n\nThe authors are notified and the article is archived.',
			8: 'DECLINE WITHOUT REVIEW (final decision).\n\nThe article is declined at screening, without going to reviewers, and archived.',
			7: 'SEND TO PRODUCTION.\n\nCopyediting ends and the article goes to layout and the final PDF.',
			14: 'NEW REVIEW ROUND.\n\nA new round is opened; reviewers from the previous round are not invited again automatically.',
			31: 'CANCEL THE REVIEW ROUND.\n\nThe current round is undone.',
			29: 'BACK FROM PRODUCTION to Copyediting.',
			30: 'BACK FROM COPYEDITING to review.',
			15: 'REVERT DECLINE: the article goes back into the workflow.',
			16: 'REVERT DECLINE AT SCREENING: the article goes back to screening.',
			recommend: 'Record your RECOMMENDATION to the handling editor.\n\nThe final decision is the editor\'s; the authors are not notified now.',
			other: 'Record this decision?\n\nIt moves the article to another stage of the editorial workflow.',
			footer: '\n\nOK = record the decision. Cancel = go back without changing anything.'
		}
	};
	var RECORD_LABELS = ['gravar decisão', 'registrar decisão', 'record decision'];

	function message() {
		var lang = (document.documentElement.lang || '').toLowerCase().indexOf('pt') === 0 ? 'pt' : 'en';
		var t = TEXTS[lang];
		var code = parseInt(new URLSearchParams(window.location.search).get('decision'), 10);
		var text = t[code] || (code >= 9 && code <= 13 ? t.recommend : t.other);
		return text + t.footer;
	}

	document.addEventListener('click', function (event) {
		if (!document.querySelector('.app__page--decision')) {
			return;
		}
		var button = event.target.closest && event.target.closest('.decision__footer button');
		if (!button) {
			return;
		}
		var label = (button.textContent || '').trim().toLowerCase();
		if (RECORD_LABELS.indexOf(label) === -1) {
			return;
		}
		if (!window.confirm(message())) {
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true);
})();
