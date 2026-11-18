{**
 * plugins/generic/csvetGuide/templates/guide.tpl
 *
 * Guia editorial no layout do painel: abas Editor-chefe / Coeditora (sem JavaScript: :target).
 * Estilos em ../guide.css (o GuideHandler põe no <head>; o Vue descarta <style> dentro do #app).
 * Texto em guides/editor.html e guides/coeditora.html.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<div class="csvet-guide">
	<h1 class="app__pageHeading">Guia editorial</h1>
	<p class="lead">Como cada artigo da NVAS anda do envio à publicação, e o que cabe a cada editor.</p>
	<nav aria-label="Guias">
		<a href="#editor">Editor-chefe</a>
		<a href="#coeditora">Coeditora</a>
	</nav>
	<section id="coeditora">{$guides.coeditora}</section>
	<section id="editor" class="default">{$guides.editor}</section>
</div>
{/block}
