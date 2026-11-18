{**
 * plugins/generic/csvetReviewReminder/templates/reviewReminderForm.tpl
 *
 * Janela "Lembrete ao Avaliador" do OJS (lib/pkp/templates/controllers/grid/users/reviewer/
 * reviewReminderForm.tpl) com o seletor de modelo acima da mensagem. Ao trocar o modelo,
 * o texto da mensagem é substituído pelo do modelo escolhido. Abaixo da mensagem, a mensagem
 * curta com o link de acesso direto do avaliador, para copiar (sem name: não vai no envio).
 *}
<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#sendReminderForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		var bodies = {$csvetReminderBodies};
		$('#csvetTemplate').on('change', function() {ldelim}
			var body = bodies[$(this).val()] || '';
			var $textarea = $('#sendReminderForm textarea[name="message"]');
			var editor = window.tinymce && $textarea.length ? tinymce.get($textarea.attr('id')) : null;
			if (editor) {ldelim}
				editor.setContent(body);
			{rdelim} else {ldelim}
				$textarea.val(body);
			{rdelim}
		{rdelim});

		$('#csvetCopyLink').on('click', function() {ldelim}
			var $text = $('#csvetLinkMessage');
			var done = function() {ldelim} var $note = $('#csvetLinkCopiedNote'); $note.text($note.data('done')); {rdelim};
			if (navigator.clipboard && window.isSecureContext) {ldelim}
				navigator.clipboard.writeText($text.val()).then(done, function() {ldelim}
					$text.trigger('select'); document.execCommand('copy'); done();
				{rdelim});
			{rdelim} else {ldelim}
				$text.trigger('select'); document.execCommand('copy'); done();
			{rdelim}
		{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="sendReminderForm" method="post" action="{url op="sendReminder"}" >
	{csrf}
	{fbvFormArea id="sendReminder"}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="stageId" value="{$stageId|escape}" />
		<input type="hidden" name="reviewAssignmentId" value="{$reviewAssignmentId|escape}" />

		{fbvFormSection title="user.role.reviewer"}
			{fbvElement type="text" id="reviewerName" value=$reviewerName disabled="true"}
		{/fbvFormSection}

		{if $csvetReminderOptions}
		{fbvFormSection}
			<label class="label" for="csvetTemplate">{$csvetReminderLabel|escape}</label>
			<select name="csvetTemplate" id="csvetTemplate" class="field select">
				{foreach from=$csvetReminderOptions key=templateKey item=templateName}
					<option value="{$templateKey|escape}"{if $templateKey == $csvetReminderSelected} selected="selected"{/if}>{$templateName|escape}</option>
				{/foreach}
			</select>
		{/fbvFormSection}
		{/if}

		{fbvFormSection title="editor.review.personalMessageToReviewer" for="message"}
			{fbvElement type="textarea" id="message" value=$message variables=$emailVariables rich=true}
		{/fbvFormSection}

		{if $csvetLinkMessage}
		{fbvFormSection}
			<label class="label" for="csvetLinkMessage">{$csvetLinkLabel|escape}</label>
			<textarea id="csvetLinkMessage" class="field textarea" rows="9" readonly="readonly" style="width:100%;font-family:inherit">{$csvetLinkMessage|escape}</textarea>
			<p>
				<button type="button" class="pkp_button" id="csvetCopyLink">{$csvetLinkCopy|escape}</button>
				<span id="csvetLinkCopiedNote" data-done="{$csvetLinkCopied|escape}" role="status" style="margin-left:.5em"></span>
			</p>
			<p class="description">{$csvetLinkHelp|escape}</p>
		{/fbvFormSection}
		{/if}
		{fbvFormSection title="reviewer.submission.reviewSchedule"}
			{fbvElement type="text" id="dateNotified" label="reviewer.submission.reviewRequestDate" value=$reviewAssignment->getDateNotified()|date_format:$dateFormatShort readonly=true inline=true size=$fbvStyles.size.SMALL}
			{if $reviewAssignment->getDateConfirmed()}
				{fbvElement type="text" id="dateConfirmed" label="editor.review.dateAccepted" value=$reviewAssignment->getDateConfirmed()|date_format:$dateFormatShort readonly=true inline=true size=$fbvStyles.size.SMALL}
			{else}
				{fbvElement type="text" id="responseDue" label="reviewer.submission.responseDueDate" value=$reviewAssignment->getDateResponseDue()|date_format:$dateFormatShort readonly=true inline=true size=$fbvStyles.size.SMALL}
			{/if}
			{fbvElement type="text" id="reviewDueDate" label="reviewer.submission.reviewDueDate" value=$reviewDueDate readonly=true inline=true size=$fbvStyles.size.SMALL}
		{/fbvFormSection}
		{fbvFormButtons submitText="editor.review.sendReminder"}
	{/fbvFormArea}
</form>
