<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Mensagens</h1>
        <button wire:click="openCompose" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Nova mensagem
        </button>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-orisa-earth/20 bg-white">
            @forelse ($conversations as $other)
                <button
                    wire:click="openConversation({{ $other->id }})"
                    class="flex w-full items-center justify-between border-b border-orisa-earth/10 px-4 py-3 text-left text-sm hover:bg-sacred-sand {{ $selectedUser?->id === $other->id ? 'bg-sacred-sand' : '' }}"
                >
                    <span lang="yo">{{ $other->name }}</span>
                    @if ($other->unread_count > 0)
                        <span class="rounded-full bg-danger px-2 py-0.5 text-xs text-white">{{ $other->unread_count }}</span>
                    @endif
                </button>
            @empty
                <p class="p-4 text-sm text-ebony/60">Nenhuma conversa ainda.</p>
            @endforelse
        </div>

        <div class="md:col-span-2 rounded-lg border border-orisa-earth/20 bg-white p-4">
            @if ($showComposeForm)
                <h2 class="font-display text-lg text-ifa-green">Nova mensagem</h2>
                <form wire:submit="send" class="mt-3 space-y-3">
                    <div>
                        <label class="block text-sm font-medium">Para</label>
                        <select wire:model="newRecipientId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                            <option value="">— Selecione —</option>
                            @foreach ($allowedRecipients as $recipient)
                                <option value="{{ $recipient->id }}" lang="yo">{{ $recipient->name }} ({{ $recipient->role }})</option>
                            @endforeach
                        </select>
                        @error('newRecipientId') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Assunto (opcional)</label>
                        <input type="text" wire:model="subject" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Mensagem</label>
                        <textarea wire:model="body" rows="4" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm"></textarea>
                        @error('body') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Enviar</button>
                </form>
            @elseif ($selectedUser)
                <h2 class="font-display text-lg text-ifa-green" lang="yo">{{ $selectedUser->name }}</h2>

                <div class="mt-3 max-h-96 space-y-3 overflow-y-auto">
                    @foreach ($thread as $message)
                        <div class="rounded p-3 text-sm {{ $message->sender_id === auth()->id() ? 'ml-8 bg-ifa-green/10' : 'mr-8 bg-sacred-sand' }}">
                            @if ($message->subject)
                                <p class="font-medium">{{ $message->subject }}</p>
                            @endif
                            <p>{{ $message->body }}</p>
                            <p class="mt-1 text-xs text-ebony/50">{{ $message->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    @endforeach
                </div>

                <form wire:submit="reply" class="mt-3 flex gap-2">
                    <input type="text" wire:model="body" placeholder="Responder..." class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                    <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Enviar</button>
                </form>
            @else
                <p class="text-sm text-ebony/60">Selecione uma conversa ou inicie uma nova mensagem.</p>
            @endif
        </div>
    </div>
</div>
