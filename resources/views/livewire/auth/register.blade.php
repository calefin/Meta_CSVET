<div class="mx-auto max-w-xl px-4 py-10 xs:px-4 md:px-8">
<div class="rounded-lg bg-white p-6 shadow-sm">
    <h1 class="mb-6 font-display text-2xl text-ifa-green">{{ __('app.register.title') }}</h1>

    @if ($submitted)
        <div class="rounded border border-success bg-success/10 p-4 text-success">
            {{ __('app.register.success') }}
        </div>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-ifa-green underline">{{ __('app.login.title') }}</a>
    @else
        <form
            x-data
            x-on:submit.prevent="
                if (window.grecaptcha) {
                    grecaptcha.ready(() => {
                        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'register' }).then((token) => {
                            $wire.set('recaptchaToken', token).then(() => $wire.register());
                        });
                    });
                } else {
                    $wire.register();
                }
            "
            class="space-y-4" autocomplete="off"
        >
            @error('recaptcha') <p class="rounded bg-danger/10 p-2 text-sm text-danger">{{ $message }}</p> @enderror
            <div>
                <label for="name" class="block text-sm font-medium">{{ __('app.register.name') }}</label>
                <input id="name" type="text" wire:model="name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                    class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                <x-yoruba-keyboard target="name" />
            </div>

            <div>
                <label for="mother_maiden_name" class="block text-sm font-medium">{{ __('app.register.mother_maiden_name') }}</label>
                <input id="mother_maiden_name" type="text" wire:model="mother_maiden_name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                    class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('mother_maiden_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="birthdate" class="block text-sm font-medium">{{ __('app.register.birthdate') }}</label>
                <input
                    id="birthdate" type="text" inputmode="numeric" placeholder="DD/MM/AAAA" maxlength="10"
                    wire:model="birthdate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"
                    x-data
                    x-on:input="
                        let v = $el.value.replace(/\D/g, '').slice(0, 8);
                        if (v.length > 4) v = v.replace(/(\d{2})(\d{2})(\d{0,4})/, '$1/$2/$3');
                        else if (v.length > 2) v = v.replace(/(\d{2})(\d{0,2})/, '$1/$2');
                        $el.value = v;
                        $wire.set('birthdate', v, false);
                    "
                >
                @error('birthdate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="country" class="block text-sm font-medium">{{ __('app.register.country') }}</label>
                <select id="country" wire:model.live="country" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('country') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            @if ($country === 'BR')
                <div>
                    <label for="cpf" class="block text-sm font-medium">{{ __('app.register.cpf') }}</label>
                    <input
                        id="cpf" type="text" inputmode="numeric" placeholder="000.000.000-00" maxlength="14"
                        wire:model="cpf" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"
                        x-data
                        x-on:input="
                            let v = $el.value.replace(/\D/g, '').slice(0, 11);
                            v = v.replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d)/, '$1.$2')
                                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                            $el.value = v;
                            $wire.set('cpf', v, false);
                        "
                    >
                    @error('cpf') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-medium">{{ __('app.register.email') }}</label>
                <input id="email" type="email" wire:model="email" wire:blur="validateOnly('email')" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium">{{ __('app.register.phone') }}</label>
                <input id="phone" type="text" wire:model="phone" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">{{ __('app.register.password') }}</label>
                <input id="password" type="password" wire:model="password" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('password') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">{{ __('app.register.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" wire:model="password_confirmation" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
            </div>

            <div class="flex items-start gap-2">
                <input id="consent" type="checkbox" wire:model="consent" class="mt-1">
                <label for="consent" class="text-sm">{{ __('app.register.consent') }}</label>
            </div>
            @error('consent') <p class="text-sm text-danger">{{ $message }}</p> @enderror

            <button type="submit" class="w-full rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
                {{ __('app.register.submit') }}
            </button>
        </form>

        {{--
        <div class="my-4 flex items-center gap-2 text-xs uppercase text-ebony/40">
            <span class="h-px flex-1 bg-orisa-earth/20"></span>
            {{ __('app.register.or_continue_with') }}
            <span class="h-px flex-1 bg-orisa-earth/20"></span>
        </div>

        <x-google-login-button :label="__('app.login.continue_with_google')" />
        --}}

        <p class="mt-4 text-sm">
            {{ __('app.register.already_have_account') }}
            <a href="{{ route('login') }}" class="text-ifa-green underline">{{ __('app.nav.login') }}</a>
        </p>
    @endif
</div>
</div>
