/**
 * Painel "Triagem editorial" no topo da etapa Submissão do fluxo editorial (OJS 3.5).
 * Busca o relatório em /<revista>/screening/data/<id> e mostra o que foi enviado, o que
 * falta e a recomendação (seguir para avaliação ou devolver). Só é carregado para
 * gestores e editores de seção (CsvetScreeningPlugin::addWorkflowPanel).
 * Qualquer erro aqui deixa o fluxo como está: o painel simplesmente não aparece.
 */
(function () {
	var cfg = window.csvetScreening;
	if (!cfg || !window.pkp || !pkp.registry || !pkp.modules || !pkp.modules.vue) {
		return;
	}
	var Vue = pkp.modules.vue;
	var h = Vue.h;
	var s = cfg.strings || {};
	var colors = {
		'return': ['#fdecea', '#b42318'],
		check: ['#fff4e0', '#8a5a00'],
		proceed: ['#e8f5ec', '#1e6b3a']
	};
	var icons = {ok: '✔', missing: '✘', warn: '!', info: '·'};
	var iconColors = {ok: '#1e6b3a', missing: '#b42318', warn: '#8a5a00', info: '#5a6080'};

	function row(status, children) {
		return h('li', {style: 'display:flex;gap:8px;margin:2px 0;list-style:none;'}, [
			h('span', {style: 'font-weight:bold;min-width:14px;color:' + iconColors[status]}, icons[status]),
			h('span', null, children)
		]);
	}

	var Panel = {
		name: 'CsvetScreening',
		props: {submission: {type: Object, required: true}},
		setup: function (props) {
			var report = Vue.ref(null);
			function load() {
				var id = props.submission && props.submission.id;
				if (!id) {
					return;
				}
				fetch(cfg.dataUrl.replace('__ID__', id), {credentials: 'same-origin', cache: 'no-store'})
					.then(function (r) { return r.ok ? r.json() : null; })
					.then(function (data) { report.value = data; })
					.catch(function () { report.value = null; });
			}
			// Recarrega quando a lista de arquivos muda (autor reenviou, tipo trocado)
			Vue.watch(function () {
				var sub = props.submission || {};
				return [sub.id, sub.dateLastActivity, sub.stageId].join('|');
			}, load, {immediate: true});

			return function () {
				var r = report.value;
				if (!r) {
					return null;
				}
				var c = colors[r.verdict] || colors.check;
				var warnings = (r.checks || []).filter(function (x) { return x.status === 'warn'; });
				return h('section', {
					'class': 'csvet-screening',
					style: 'border:1px solid #dde1ee;border-radius:6px;background:#fff;padding:16px;margin:0 0 16px;font-size:14px;line-height:1.5;'
				}, [
					h('h3', {style: 'margin:0 0 4px;font-size:16px;'}, s.title || 'Triagem'),
					h('p', {style: 'margin:0 0 10px;color:#5a6080;font-size:13px;'}, s.lead || ''),
					h('div', {style: 'background:' + c[0] + ';color:' + c[1] + ';border-left:4px solid ' + c[1] + ';padding:8px 12px;margin:0 0 12px;font-weight:bold;'}, r.verdictText),
					h('p', {style: 'margin:0 0 4px;font-weight:bold;'}, (s.required || '').replace('{$section}', r.section)),
					h('ul', {style: 'margin:0 0 10px;padding:0;'}, (r.required || []).map(function (item) {
						return row(item.status, [
							h('strong', null, item.label + ': '),
							item.status === 'ok' ? (s.present || 'ok') + ' — ' : h('strong', {style: 'color:#b42318;'}, (s.missing || 'FALTA') + ' — '),
							item.detail
						]);
					})),
					warnings.length ? h('ul', {style: 'margin:0 0 10px;padding:0;'}, warnings.map(function (w) {
						return row('warn', w.text);
					})) : null,
					r.returnHelp ? h('p', {style: 'margin:0 0 10px;font-size:13px;'}, r.returnHelp) : null,
					h('p', {style: 'margin:0;font-size:13px;color:#5a6080;'}, r.reviewFiles),
					h('p', {style: 'margin:10px 0 0;'}, [
						h('a', {href: cfg.pageUrl.replace('__ID__', r.id), target: '_blank', rel: 'noopener', style: 'font-weight:bold;'}, (s.openScreening || 'Triagem') + ' ↗')
					])
				]);
			};
		}
	};

	try {
		pkp.registry.registerComponent('CsvetScreening', Panel);
		pkp.registry.storeExtend('workflow', function (piniaContext) {
			var extender = piniaContext.store && piniaContext.store.extender;
			if (!extender) {
				return;
			}
			extender.extendFn('getPrimaryItems', function (items, args) {
				try {
					var stageId = args && args.selectedMenuState && args.selectedMenuState.stageId;
					if (args && args.submission && stageId === pkp.const.WORKFLOW_STAGE_ID_SUBMISSION) {
						// O próprio componente (e não o nome): funciona mesmo se o app já tiver sido criado
						return [{component: Panel, props: {submission: args.submission}}].concat(items || []);
					}
				} catch (e) {
					// painel opcional: nunca quebrar o fluxo
				}
				return items;
			});
		});
	} catch (e) {
		if (window.console) {
			console.warn('csvetScreening', e);
		}
	}
})();
