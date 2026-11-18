<div class="mx-auto max-w-xl px-4 py-10 xs:px-4 md:px-8">
<div class="rounded-lg bg-white p-6 shadow-sm">
    <h1 class="mb-2 font-display text-2xl text-ifa-green">Recuperar senha</h1>

    @if ($submitted)
        <div class="rounded border border-success bg-success/10 p-4 text-success">
            Se este e-mail estiver cadastrado, enviamos um link para redefinir sua senha. Verifique sua caixa de entrada.
        </div>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-ifa-green underline">Voltar para o login</a>
    @else
        <p class="mb-6 text-sm text-ebony/60">Informe seu e-mail e enviaremos um link para criar uma nova senha.</p>

        <form wire:submit="send" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium">{{ __('app.login.email') }}</label>
                <input id="email" type="email" wire:model="email" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="w-full rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
                Enviar link de recuperação
            </button>
        </form>

        <p class="mt-4 text-sm">
            <a href="{{ route('login') }}" class="text-ifa-green underline">Voltar para o login</a>
        </p>
    @endif
</div>
</div>
