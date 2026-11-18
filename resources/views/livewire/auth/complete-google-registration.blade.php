<div class="mx-auto max-w-xl px-4 py-10 xs:px-4 md:px-8">
<div class="rounded-lg bg-white p-6 shadow-sm">
    <h1 class="mb-2 font-display text-2xl text-ifa-green">Completar cadastro</h1>
    <p class="mb-6 text-sm text-ebony/60">Conectado como <strong>{{ $email }}</strong> via Google. Falta só mais um pouco.</p>

    @if ($submitted)
        <div class="rounded border border-success bg-success/10 p-4 text-success">
            {{ __('app.register.success') }}
        </div>
        <a href="{{ route('login') }}" class="mt-4 inline-block text-ifa-green underline">{{ __('app.login.title') }}</a>
    @else
        <form wire:submit="complete" class="space-y-4" autocomplete="off">
            <div>
                <label class="block text-sm font-medium">{{ __('app.register.mother_maiden_name') }}</label>
                <input type="text" wire:model="mother_maiden_name" lang="yo" autocorrect="off" autocapitalize="none" spellcheck="false"
                    id="gmother" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('mother_maiden_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                <x-yoruba-keyboard target="gmother" />
            </div>

            <div>
                <label class="block text-sm font-medium">{{ __('app.register.birthdate') }}</label>
                <input type="text" placeholder="DD/MM/AAAA" maxlength="10" wire:model="birthdate" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                @error('birthdate') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">{{ __('app.register.country') }}</label>
                <select wire:model.live="country" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if ($country === 'BR')
                <div>
                    <label class="block text-sm font-medium">{{ __('app.register.cpf') }}</label>
                    <input type="text" wire:model="cpf" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    @error('cpf') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium">{{ __('app.register.phone') }}</label>
                <input type="text" wire:model="phone" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
            </div>

            <div class="flex items-start gap-2">
                <input type="checkbox" wire:model="consent" id="gconsent" class="mt-1">
                <label for="gconsent" class="text-sm">{{ __('app.register.consent') }}</label>
            </div>
            @error('consent') <p class="text-sm text-danger">{{ $message }}</p> @enderror

            <button type="submit" class="w-full rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
                {{ __('app.register.submit') }}
            </button>
        </form>
    @endif
</div>
</div>
