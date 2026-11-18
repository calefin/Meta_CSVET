<div>
    <h1 class="font-display text-2xl text-ifa-green">Meu perfil</h1>

    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <p class="text-sm" lang="yo"><strong>Nome:</strong> {{ $user->name }}</p>
        <p class="mt-1 text-sm"><strong>E-mail:</strong> {{ $user->email }}
            @if ($user->email_verified_at)
                <span class="ml-1 text-xs text-success">(confirmado)</span>
            @else
                <span class="ml-1 text-xs text-orisa-earth">(não confirmado)</span>
            @endif
        </p>
    </div>

    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <h2 class="font-medium">Alterar senha</h2>

        @if ($changed)
            <div class="mt-2 rounded border border-success bg-success/10 p-3 text-sm text-success">
                Senha alterada com sucesso.
            </div>
        @endif

        <form wire:submit="changePassword" class="mt-3 space-y-3">
            <div>
                <label class="block text-sm font-medium">Senha atual</label>
                <input type="password" wire:model="current_password" class="mt-1 w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                @error('current_password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Nova senha</label>
                <input type="password" wire:model="password" class="mt-1 w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                @error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">Confirmar nova senha</label>
                <input type="password" wire:model="password_confirmation" class="mt-1 w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-sm text-white hover:bg-ifa-green/90">Salvar nova senha</button>
        </form>
    </div>
</div>
