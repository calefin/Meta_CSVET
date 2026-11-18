{{-- Conversa registrada do projeto (professor + dupla). Página custom: estilos próprios (.tc-chat-*). --}}
@if ($chat !== null)
    @php
        $unread = (int) ($unread ?? 0);
        $readBefore = $readBefore ?? null;
    @endphp
    <style>
        .tc-chat-list { display: grid; gap: .5rem; max-height: 50vh; overflow-y: auto; margin: .6rem 0; padding-right: .25rem; overscroll-behavior: contain; }
        .tc-chat-msg { padding: .55rem .75rem; border-radius: .7rem; background: rgba(148,163,184,.12); font-size: .9rem; line-height: 1.5; overflow-wrap: anywhere; }
        .tc-chat-body { white-space: pre-line; }
        .tc-chat-msg.prof { background: rgba(13,148,136,.12); }
        .tc-chat-msg.me { background: rgba(37,99,235,.10); }
        .tc-chat-msg.new { box-shadow: inset 3px 0 0 #d97706; }
        .tc-chat-head { font-size: .75rem; font-weight: 700; opacity: .75; margin-bottom: .15rem; }
        .tc-chat-new { display: inline-block; margin-left: .35rem; padding: 0 .4rem; border-radius: 999px; background: #d97706; color: #fff; font-size: .7rem; font-weight: 700; }
        .tc-chat-form { display: grid; gap: .5rem; }
        .tc-chat-form textarea { width: 100%; min-height: 4.5rem; padding: .55rem .75rem; border-radius: .6rem; border: 1px solid rgba(148,163,184,.6); background: transparent; color: inherit; font-size: .95rem; }
        #conversa { scroll-margin-top: 5rem; }
    </style>
    {{-- wire:ignore.self: o Livewire não fecha a conversa a cada decisão de triagem; o conteúdo continua atualizando.
         Link com #conversa (e-mail, Painel) abre e rola até aqui. --}}
    <details id="conversa" class="tc-card" wire:ignore.self @if ($openChat ?? false) open @endif style="margin-top:1rem"
        x-data
        x-init="if (location.hash === '#conversa') { $el.open = true; $nextTick(() => $el.scrollIntoView({ block: 'start', behavior: 'smooth' })) }"
        x-on:toggle="if ($el.open) { $wire.markChatRead(); const l = $el.querySelector('.tc-chat-list'); if (l) l.scrollTop = 0 }">
        <summary>💬 Conversa do projeto ({{ $chat->count() }})
            @if ($unread > 0)<span class="tc-chat-new">{{ $unread }} {{ $unread === 1 ? 'nova' : 'novas' }}</span>@endif
        </summary>
        <p class="tc-meta" style="margin-top:.5rem">Aqui ficam as mensagens entre você, a sua dupla e o Prof. Atilio. Você também chega aqui pelo botão
            <strong>"💬 Conversa"</strong> do seu projeto no Painel, ou pelo botão do e-mail de aviso. Responder ao e-mail não chega à conversa: escreva no campo abaixo. A mensagem mais nova aparece primeiro.</p>
        <div class="tc-notice" style="margin-top:.5rem">
            🔒 <strong>Revisão cega: não exponha nada da sua triagem ou extração.</strong> Aqui é proibido contar ou perguntar como você ou a dupla decidiu
            (incluir, excluir, talvez), citar estudos, títulos ou autores que você está triando, dizer quantos já incluiu ou excluiu, comparar números
            extraídos ou opinar sobre se um estudo "entra". Isso quebra o cegamento, invalida a concordância (kappa) e pode levar ao desligamento (termo, cláusulas 6 e 14).
        </div>
        <p class="tc-meta" style="margin-top:.5rem">Pode: combinar prazos e horários, avisar ausências, tirar dúvidas gerais sobre o protocolo e o uso do site.
            Tudo fica registrado com data e hora e é lido pelo professor.</p>
        <div class="tc-chat-form">
            <textarea wire:model="chatBody" maxlength="3000" placeholder="Escreva para a dupla e o professor…"></textarea>
            <div><button type="button" class="tc-btn" wire:click="postChat" wire:loading.attr="disabled"
                wire:confirm="Confirma que a mensagem NÃO revela decisões, estudos, contagens ou dados da triagem ou da extração? Ela fica registrada e é lida pelo professor.">Enviar</button></div>
        </div>
        {{-- A mais nova primeiro (pedido do professor em 13/11/2026). --}}
        <div class="tc-chat-list">
            @forelse ($chat as $m)
                @php $isNew = $m->user_id !== auth()->id() && (!$readBefore || $m->created_at->gt($readBefore)) && $unread > 0; @endphp
                <div class="tc-chat-msg {{ $m->author_role === 'professor' ? 'prof' : '' }} {{ $m->user_id === auth()->id() ? 'me' : '' }} {{ $isNew ? 'new' : '' }}">
                    <div class="tc-chat-head">{{ $m->user_id === auth()->id() ? 'Você' : $m->author_name }} · {{ $m->created_at->format('d/m/Y H:i') }}
                        @if ($isNew)<span class="tc-chat-new">nova</span>@endif
                    </div>
                    <div class="tc-chat-body">{{ $m->body }}</div>
                </div>
            @empty
                <p class="tc-meta">Nenhuma mensagem ainda.</p>
            @endforelse
        </div>
    </details>
@endif
