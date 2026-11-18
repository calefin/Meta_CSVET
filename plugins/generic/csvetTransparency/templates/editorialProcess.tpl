{**
 * plugins/generic/csvetTransparency/templates/editorialProcess.tpl
 *
 * Quadro "Processo editorial" na lateral da página do artigo: datas, editor responsável
 * e, quando um autor é do corpo editorial, a nota de que ele não participou do processo.
 *}
<div class="item csvet_editorial_process">
	<section class="sub_item">
		<h2 class="label">{$csvetT.heading|escape}</h2>
		<div class="value">
			{if $csvetDateSubmitted}
				<p>{$csvetT.received|escape}: {$csvetDateSubmitted|date_format:$dateFormatShort}</p>
			{/if}
			{if $csvetDateAccepted}
				<p>{$csvetT.accepted|escape}: {$csvetDateAccepted|date_format:$dateFormatShort}</p>
			{/if}
			{if $csvetEditorName}
				<p>{$csvetT.editor|escape}: {$csvetEditorName|escape}{if $csvetEditorAffiliation} ({$csvetEditorAffiliation|escape}){/if}</p>
			{/if}
			{if !$section || $section->getMetaReviewed()}
				<p>{$csvetT.peerReview|escape}</p>
			{/if}
			{if $csvetBoardNote}
				<p class="csvet_board_note">{$csvetBoardNote}</p>
			{/if}
		</div>
	</section>
</div>
