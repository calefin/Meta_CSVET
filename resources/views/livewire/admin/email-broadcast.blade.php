<div>
    <h1 class="font-display text-2xl text-ifa-green">Enviar e-mail aos cadastrados</h1>

    @if ($sent)
        <div class="mt-4 rounded border border-success bg-success/10 p-4 text-success">
            E-mail enfileirado para {{ $sentCount }} destinatário(s). O envio depende do worker de fila estar ativo no servidor.
        </div>
    @endif

    <form wire:submit="send" class="mt-4 max-w-2xl space-y-4 rounded-lg border border-orisa-earth/20 bg-white p-6">
        <div>
            <label class="block text-sm font-medium">Destinatários</label>
            <select wire:model.live="targetGroup" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                <option value="all">Toda a casa (ativos)</option>
                <option value="iniciados">Iniciados</option>
                <option value="consulentes">Consulentes</option>
                <option value="admin">Administradores</option>
            </select>
            <p class="mt-1 text-xs text-ebony/50">{{ $recipientCount }} destinatário(s) nesse grupo.</p>
        </div>

        <div>
            <label class="block text-sm font-medium">Assunto</label>
            <input type="text" wire:model="emailSubject" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
            @error('emailSubject') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Mensagem</label>
            <textarea wire:model="emailBody" rows="8" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
            @error('emailBody') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <button type="submit" wire:confirm="Enviar este e-mail para todos os destinatarios do grupo selecionado?" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Enviar
        </button>
    </form>
</div>
