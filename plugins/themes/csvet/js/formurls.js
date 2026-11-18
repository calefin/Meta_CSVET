/**
 * Formulários com link (http:// ou https://) sem o bloqueio "Not Acceptable" da hospedagem.
 *
 * O ModSecurity da HostGator recusa (HTTP 406, página "Not Acceptable") qualquer formulário
 * enviado como x-www-form-urlencoded que contenha um endereço http(s)://. Isso pega a filiação
 * escolhida na lista (https://ror.org/...), o ORCID, a página pessoal (Lattes), biografia ou
 * assinatura com link etc. Depois de algumas recusas seguidas, o firewall da hospedagem pode
 * bloquear por um tempo o IP da pessoa, e aí o site parece "fora do ar" (o app mostra "Sem
 * conexão") para ela e para quem usa a mesma rede.
 *
 * Aqui, só quando o envio contém http(s)://, o corpo inteiro vai codificado em base64 num único
 * campo "csvetEnc"; o plugin generic/csvetFormUrls desfaz isso no servidor antes de o OJS ler
 * os dados. Os demais envios continuam exatamente como o OJS manda. As gravações da API
 * (/api/v1/) já vão como JSON pelo backend.js e não passam por aqui.
 *
 * Cobre: chamadas jQuery ($.post/$.ajax, formulários antigos do painel) e formulários de página
 * inteira (method="post", sem upload de arquivo).
 */
(function () {
	var FIELD = 'csvetEnc';
	var HAS_URL_ENCODED = /https?%3A%2F%2F/i;

	function sameOrigin(url) {
		try {
			return new URL(url, window.location.href).origin === window.location.origin;
		} catch (e) {
			return false;
		}
	}

	function encode(body) {
		// body é x-www-form-urlencoded (só ASCII), então btoa funciona direto
		return FIELD + '=' + encodeURIComponent(window.btoa(body));
	}

	function registerAjax() {
		var $ = window.jQuery;
		if (!$ || !$.ajaxPrefilter || $.csvetFormUrls) {
			return;
		}
		$.csvetFormUrls = true;
		$.ajaxPrefilter(function (options) {
			if (!/^(POST|PUT|PATCH)$/i.test(options.type || '') || /\/api\/v1\//.test(options.url || '')
				|| !sameOrigin(options.url || '') || typeof options.data !== 'string'
				|| options.data.indexOf(FIELD + '=') === 0 || !HAS_URL_ENCODED.test(options.data)
				|| (options.contentType && String(options.contentType).indexOf('application/x-www-form-urlencoded') !== 0)) {
				return;
			}
			options.data = encode(options.data);
		});
	}

	function onSubmit(event) {
		var form = event.target;
		if (event.defaultPrevented || !form || form.tagName !== 'FORM' || form.getAttribute('data-csvet-enc')
			|| (form.getAttribute('method') || 'get').toLowerCase() !== 'post'
			|| (form.enctype || '').toLowerCase() === 'multipart/form-data'
			|| !sameOrigin(form.action) || /\/api\/v1\//.test(form.action)
			|| !window.FormData || !window.URLSearchParams) {
			return;
		}
		var data;
		try {
			data = new FormData(form);
			if (event.submitter && event.submitter.name) {
				data.append(event.submitter.name, event.submitter.value || '');
			}
		} catch (e) {
			return;
		}
		var hasFile = false;
		data.forEach(function (value) {
			if (typeof value !== 'string') {
				hasFile = true;
			}
		});
		if (hasFile) {
			return;
		}
		var body = new URLSearchParams(data).toString();
		if (!HAS_URL_ENCODED.test(body)) {
			return;
		}
		event.preventDefault();
		var proxy = document.createElement('form');
		proxy.method = 'post';
		proxy.action = form.action;
		proxy.acceptCharset = 'UTF-8';
		proxy.style.display = 'none';
		proxy.setAttribute('data-csvet-enc', '1');
		if (form.target) {
			proxy.target = form.target;
		}
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = FIELD;
		input.value = window.btoa(body);
		proxy.appendChild(input);
		document.body.appendChild(proxy);
		HTMLFormElement.prototype.submit.call(proxy);
	}

	registerAjax();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', registerAjax);
	}
	// Na fase de "bolha" do documento: os validadores do OJS (jQuery) já tiveram a chance de
	// cancelar o envio; só os envios que de fato seguiriam para o servidor são reescritos.
	document.addEventListener('submit', onSubmit, false);
})();
