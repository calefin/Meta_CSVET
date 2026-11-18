/**
 * Botão ☰ do painel do OJS no celular (ver styles/backend.css).
 * Fica fora do #app (que o Vue redesenha) e só aparece abaixo de 768 px.
 */
(function () {
	function init() {
		if (!document.getElementById('app-nav') || document.getElementById('csvet-nav-toggle')) {
			return;
		}
		var button = document.createElement('button');
		button.id = 'csvet-nav-toggle';
		button.type = 'button';
		button.setAttribute('aria-label', 'Menu');
		button.setAttribute('aria-controls', 'app-nav');
		button.setAttribute('aria-expanded', 'false');
		button.textContent = '☰';
		button.addEventListener('click', function () {
			var open = document.body.classList.toggle('csvet-nav-open');
			button.textContent = open ? '✕' : '☰';
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		document.body.appendChild(button);

		// Fecha a gaveta ao tocar no fundo escurecido ou ao escolher um item que troca de página
		document.addEventListener('click', function (event) {
			if (!document.body.classList.contains('csvet-nav-open') || event.target === button) {
				return;
			}
			var nav = document.getElementById('app-nav');
			var link = event.target.closest && event.target.closest('a[href]');
			if (!nav.contains(event.target) || link) {
				document.body.classList.remove('csvet-nav-open');
				button.textContent = '☰';
				button.setAttribute('aria-expanded', 'false');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

/**
 * Foto do perfil (Perfil › Público): o plupload 2.3.6 do OJS pede ao moxie
 * "crop" sem "fit" e grava só o miolo de 150×150 px da foto original (uma foto
 * de 1792×2400 vira a boca). Aqui a foto é reduzida no navegador antes do envio:
 * recorte quadrado (em retratos, um pouco acima do centro, onde fica o rosto),
 * respeitando a rotação EXIF do celular. Vale só para uploaders com resize.crop.
 */
(function () {
	var made = typeof WeakSet === 'function' ? new WeakSet() : null;

	function cover(file, width, height) {
		return createImageBitmap(file, {imageOrientation: 'from-image'}).then(function (bmp) {
			var scale = Math.min(bmp.width / width, bmp.height / height);
			var sw = width * scale, sh = height * scale;
			var sx = (bmp.width - sw) / 2;
			var sy = (bmp.height - sh) * (bmp.height > bmp.width ? 0.25 : 0.5);
			var canvas = document.createElement('canvas');
			canvas.width = width;
			canvas.height = height;
			var ctx = canvas.getContext('2d');
			ctx.imageSmoothingQuality = 'high';
			ctx.drawImage(bmp, sx, sy, sw, sh, 0, 0, width, height);
			return new Promise(function (resolve) {
				canvas.toBlob(resolve, 'image/jpeg', 0.9);
			});
		});
	}

	function fixUploader(up) {
		up.bind('FilesAdded', function (up, files) {
			var resize = up.getOption('resize');
			if (!resize || !resize.crop || !resize.width || !resize.height) {
				return;
			}
			files.slice().forEach(function (file) {
				var nat = file.getNative && file.getNative();
				if (!nat || made.has(nat) || !/^image\//.test(nat.type)) {
					return;
				}
				up.removeFile(file);
				cover(nat, resize.width, resize.height).then(function (blob) {
					var name = (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg';
					var resized = new File([blob], name, {type: 'image/jpeg'});
					made.add(resized);
					up.addFile(resized);
				}, function () {
					// Navegador sem createImageBitmap: segue o caminho original do plupload
					made.add(nat);
					up.addFile(nat);
				});
			});
		}, null, 100);
	}

	function patch() {
		if (!made || !window.plupload || !window.plupload.Uploader || window.plupload.Uploader.csvetPatched) {
			return;
		}
		var Original = window.plupload.Uploader;
		var Patched = function (options) {
			var up = new Original(options);
			fixUploader(up);
			return up;
		};
		Patched.prototype = Original.prototype;
		Patched.csvetPatched = true;
		window.plupload.Uploader = Patched;
	}

	patch();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', patch);
	}
})();

/**
 * Gravações da API com link de outro site vão como JSON.
 * O ModSecurity da hospedagem recusa (406, "Ocorreu um erro não esperado") formulário
 * x-www-form-urlencoded que contenha URL de outro site, e os formulários do painel mandam
 * assim: a filiação escolhida na lista leva https://ror.org/..., o ORCID e o site do autor
 * também. O mesmo conteúdo em JSON passa, e a API do OJS aceita os dois formatos. Só muda o
 * que seria recusado; o resto continua como o OJS envia.
 */
(function () {
	function register() {
		var $ = window.jQuery;
		if (!$ || !$.ajaxPrefilter || $.csvetJsonApi) {
			return;
		}
		$.csvetJsonApi = true;
		$.ajaxPrefilter(function (options, original) {
			var data = original.data;
			if (!/\/api\/v1\//.test(options.url) || !/^(POST|PUT)$/i.test(options.type)
				|| !data || typeof data !== 'object' || (window.FormData && data instanceof FormData)
				|| typeof options.data !== 'string' || !/https?%3A/i.test(options.data)) {
				return;
			}
			options.data = JSON.stringify(data);
			options.contentType = 'application/json';
			options.processData = false;
		});
	}
	register();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', register);
	}
})();
