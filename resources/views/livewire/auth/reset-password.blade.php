<div class="mx-auto max-w-xl px-4 py-10 xs:px-4 md:px-8">
<div class="rounded-lg bg-white p-6 shadow-sm">
    <h1 class="mb-6 font-display text-2xl text-ifa-green">Redefinir senha</h1>

    @if ($completed)
        <div class="rounded border border-success bg-success/10 p-4 text-success">
            Senha redefinida com sucesso. Você já pode entrar com a nova senha.
        </div>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-ifa-green underline">{{ __('app.login.title') }}</a>
    @else
        <form wire:submit="resetPassword" class="space-y-4">
            <div>
                <label class="block text-sm font-medium">{{ __('app.login.email') }}</label>
                <input type="email" wire:model="email" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Nova senha</label>
                <input type="password" wire:model="password" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Confirmar nova senha</label>
                <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
            </div>

            <button type="submit" class="w-full rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
                Redefinir senha
            </button>
        </form>
    @endif
</div>
</div>
