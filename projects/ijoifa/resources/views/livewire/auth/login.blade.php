<div class="mx-auto max-w-xl px-4 py-10 xs:px-4 md:px-8">
<div class="rounded-lg bg-white p-6 shadow-sm">
    <h1 class="mb-6 font-display text-2xl text-ifa-green">{{ __('app.login.title') }}</h1>

    @if (session('status_message'))
        <div class="mb-4 rounded border border-orun-blue bg-orun-blue/10 p-3 text-sm text-orun-blue">
            {{ session('status_message') }}
        </div>
    @endif

    <form
        x-data
        x-on:submit.prevent="
            if (window.grecaptcha) {
                grecaptcha.ready(() => {
                    grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'login' }).then((token) => {
                        $wire.set('recaptchaToken', token).then(() => $wire.login());
                    });
                });
            } else {
                $wire.login();
            }
        "
        class="space-y-4"
    >
        @error('recaptcha') <p class="rounded bg-danger/10 p-2 text-sm text-danger">{{ $message }}</p> @enderror

        <div>
            <label for="email" class="block text-sm font-medium">{{ __('app.login.email') }}</label>
            <input id="email" type="email" wire:model="email" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">{{ __('app.login.password') }}</label>
            <input id="password" type="password" wire:model="password" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between gap-2">
            <span class="flex items-center gap-2">
                <input id="remember" type="checkbox" wire:model="remember">
                <label for="remember" class="text-sm">{{ __('app.login.remember') }}</label>
            </span>
            <a href="{{ route('password.request') }}" class="text-sm text-ifa-green hover:underline">Esqueci minha senha</a>
        </div>

        <button type="submit" class="w-full rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            {{ __('app.login.submit') }}
        </button>
    </form>

    {{--
    <div class="my-4 flex items-center gap-2 text-xs uppercase text-ebony/40">
        <span class="h-px flex-1 bg-orisa-earth/20"></span>
        {{ __('app.login.or_continue_with') }}
        <span class="h-px flex-1 bg-orisa-earth/20"></span>
    </div>

    <x-google-login-button :label="__('app.login.continue_with_google')" />
    --}}

    <p class="mt-4 text-sm">
        {{ __('app.login.no_account') }}
        <a href="{{ route('register') }}" class="text-ifa-green underline">{{ __('app.nav.register') }}</a>
    </p>
</div>
</div>
