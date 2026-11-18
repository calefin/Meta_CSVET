<x-layouts.guest
    :title="__('app.privacy.title')"
    :description="__('app.privacy.description')"
    :hreflangs="[
        'pt-BR' => route('privacy'),
        'en' => route('privacy.en'),
        'es' => route('privacy.es'),
        'x-default' => route('privacy'),
    ]"
>
    <div class="mx-auto max-w-3xl px-4 py-12 xs:px-4 md:px-8">
        <h1 class="font-display text-3xl text-ifa-green">{{ __('app.privacy.title') }}</h1>
        <p class="mt-2 text-sm text-ebony/60">{{ __('app.privacy.last_updated') }}: {{ now()->format('d/m/Y') }}</p>

        <div class="mt-6 space-y-4 text-ebony/80">
            <p>{{ __('app.privacy.intro') }}</p>

            <h2 class="font-display text-xl text-orisa-earth">{{ __('app.privacy.data_collected_title') }}</h2>
            <p>{{ __('app.privacy.data_collected_body') }}</p>

            <h2 class="font-display text-xl text-orisa-earth">{{ __('app.privacy.data_use_title') }}</h2>
            <p>{{ __('app.privacy.data_use_body') }}</p>

            <h2 class="font-display text-xl text-orisa-earth">{{ __('app.privacy.rights_title') }}</h2>
            <p>{{ __('app.privacy.rights_body') }}</p>

            <h2 class="font-display text-xl text-orisa-earth">{{ __('app.privacy.contact_title') }}</h2>
            <p>
                {!! __('app.privacy.contact_body', [
                    'email' => '<a href="mailto:contato@ijoifa.com" class="text-ifa-green hover:underline">contato@ijoifa.com</a>',
                    'whatsapp' => '<a href="https://wa.me/5511992114308" target="_blank" rel="noopener" class="text-ifa-green hover:underline">(11) 99211-4308</a>',
                ]) !!}
            </p>
        </div>
    </div>
</x-layouts.guest>
