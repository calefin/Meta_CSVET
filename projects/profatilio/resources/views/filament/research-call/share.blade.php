{{-- Textos de divulgação com botão de copiar. Estilo próprio: páginas/modais
     do Filament não carregam o Tailwind do app. --}}
<style>
    .rc-share { display: grid; gap: 1.25rem; font-size: .875rem; }
    .rc-share h3 { font-weight: 700; margin: 0 0 .4rem; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .rc-share textarea, .rc-share input { width: 100%; font: inherit; font-size: .8rem; line-height: 1.5; padding: .6rem .75rem; border-radius: .6rem; border: 1px solid rgba(148,163,184,.4); background: rgba(148,163,184,.08); color: inherit; }
    .rc-share textarea { min-height: 14rem; resize: vertical; }
    .rc-copy { font-size: .75rem; font-weight: 600; padding: .3rem .7rem; border-radius: .5rem; border: 1px solid rgba(13,148,136,.5); color: #0d9488; background: rgba(13,148,136,.08); cursor: pointer; white-space: nowrap; }
    .rc-copy.ok { background: #0d9488; color: #fff; }
    .rc-tip { font-size: .75rem; opacity: .75; margin-top: .3rem; }
    .rc-warn { font-size: .8rem; padding: .6rem .8rem; border-radius: .6rem; background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.4); }
</style>
<div class="rc-share">
    @if(!$call->isOpen())
        <div class="rc-warn">⚠️ As inscrições desta chamada não estão abertas. Mude a situação para "Inscrições abertas" antes de divulgar, senão quem clicar no link vê o formulário fechado.</div>
    @endif

    <div>
        <h3>Link da página <button type="button" class="rc-copy" x-data="{ ok: false }" :class="ok && 'ok'" x-text="ok ? 'Copiado ✓' : 'Copiar'" x-on:click="const el = document.getElementById('rc-link'); (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(el.value) : Promise.resolve((el.select(), document.execCommand('copy')))).then(() => { ok = true; setTimeout(() => ok = false, 2000) })">Copiar</button></h3>
        <input id="rc-link" type="text" readonly value="{{ $call->publicUrl() }}">
    </div>

    <div>
        <h3>E-mail: assunto <button type="button" class="rc-copy" x-data="{ ok: false }" :class="ok && 'ok'" x-text="ok ? 'Copiado ✓' : 'Copiar'" x-on:click="const el = document.getElementById('rc-subject'); (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(el.value) : Promise.resolve((el.select(), document.execCommand('copy')))).then(() => { ok = true; setTimeout(() => ok = false, 2000) })">Copiar</button></h3>
        <input id="rc-subject" type="text" readonly value="{{ $emailSubject }}">
    </div>

    <div>
        <h3>E-mail: texto <button type="button" class="rc-copy" x-data="{ ok: false }" :class="ok && 'ok'" x-text="ok ? 'Copiado ✓' : 'Copiar'" x-on:click="const el = document.getElementById('rc-email'); (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(el.value) : Promise.resolve((el.select(), document.execCommand('copy')))).then(() => { ok = true; setTimeout(() => ok = false, 2000) })">Copiar</button></h3>
        <textarea id="rc-email" readonly>{{ $emailBody }}</textarea>
        <p class="rc-tip">Para mandar aos seus orientandos de uma vez, use "Enviar E-mail" no menu. Para outras turmas, cole no seu e-mail ou no AVA.</p>
    </div>

    <div>
        <h3>WhatsApp / redes sociais <button type="button" class="rc-copy" x-data="{ ok: false }" :class="ok && 'ok'" x-text="ok ? 'Copiado ✓' : 'Copiar'" x-on:click="const el = document.getElementById('rc-whatsapp'); (navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(el.value) : Promise.resolve((el.select(), document.execCommand('copy')))).then(() => { ok = true; setTimeout(() => ok = false, 2000) })">Copiar</button></h3>
        <textarea id="rc-whatsapp" readonly style="min-height:11rem">{{ $whatsapp }}</textarea>
        <p class="rc-tip">Os asteriscos (*texto*) viram negrito no WhatsApp.</p>
    </div>
</div>
