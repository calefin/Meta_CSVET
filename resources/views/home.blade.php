<x-layouts.guest
    :title="__('app.home.hero_title')"
    :description="__('app.home.hero_subtitle')"
    :hreflangs="[
        'pt-BR' => route('home'),
        'en' => route('home.en'),
        'es' => route('home.es'),
        'x-default' => route('home'),
    ]"
>
    <x-slot:head>
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'ReligiousOrganization',
                    '@id' => url('/#organization'),
                    'name' => config('app.name'),
                    'url' => url('/'),
                    'logo' => url('/icons/icon-512.png'),
                    'description' => __('app.home.about_body'),
                    'telephone' => '+5511992114308',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => 'São Paulo',
                        'addressRegion' => 'SP',
                        'addressCountry' => 'BR',
                    ],
                    'areaServed' => ['São Paulo', 'BR'],
                    'knowsLanguage' => ['pt-BR', 'en', 'es'],
                ],
                [
                    '@type' => 'Service',
                    'name' => __('app.home.service_consultation_title'),
                    'description' => __('app.home.service_consultation_body'),
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => ['São Paulo', 'BR'],
                ],
                [
                    '@type' => 'Service',
                    'name' => __('app.home.service_works_title'),
                    'description' => __('app.home.service_works_body'),
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => ['São Paulo', 'BR'],
                ],
                [
                    '@type' => 'Service',
                    'name' => __('app.home.service_courses_title'),
                    'description' => __('app.home.service_courses_body'),
                    'provider' => ['@id' => url('/#organization')],
                    'areaServed' => ['São Paulo', 'BR'],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    </x-slot:head>

    <section class="relative overflow-hidden bg-ifa-green px-4 pb-28 pt-20 text-center text-white xs:px-4 md:px-8">
        <img src="/logo.svg" alt="" aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 opacity-10">
        <img src="/logo.svg" alt="" aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-24 h-96 w-96 opacity-10">

        <div class="relative">
            <img src="/logo.svg" alt="" aria-hidden="true" class="mx-auto h-20 w-20">
            <h1 class="mt-4 font-display text-4xl md:text-5xl">{{ __('app.home.hero_title') }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-white/90">{{ __('app.home.hero_subtitle') }}</p>
            <p class="mt-3 text-sm uppercase tracking-wide text-white/70">{{ __('app.home.hero_location') }}</p>

            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('public-consultation-request') }}" class="rounded bg-white px-6 py-3 font-medium text-ifa-green hover:bg-sacred-sand">
                    {{ __('app.home.hero_cta_register') }}
                </a>
                <a href="{{ route('login') }}" class="rounded border border-white px-6 py-3 font-medium text-white hover:bg-white/10">
                    {{ __('app.home.hero_cta_login') }}
                </a>
            </div>
        </div>

        <div class="absolute bottom-0 left-0 w-full">
            <x-wave-divider class="text-sacred-sand" />
        </div>
    </section>

    <section class="relative overflow-hidden px-4 py-16 xs:px-4 md:px-8">
        <x-adire-pattern name="about" class="text-orisa-earth/[0.06]" />
        <div class="relative mx-auto grid max-w-5xl items-center gap-10 md:grid-cols-2">
            <div class="order-2 md:order-1">
                <h2 class="font-display text-3xl text-ifa-green">{{ __('app.home.about_title') }}</h2>
                <p class="mt-4 text-ebony/80 leading-relaxed">{{ __('app.home.about_body') }}</p>
            </div>
            <div class="order-1 flex justify-center md:order-2">
                <div class="relative w-full max-w-sm overflow-hidden rounded-lg border-2 border-orisa-earth/20 bg-white p-2 shadow-xl transition hover:scale-[1.02] duration-300">
                    <img src="/images/casa-ancestralidade.webp" alt="Ancião da tradição Yorùbá" width="800" height="800" loading="lazy" class="w-full rounded h-64 object-cover">
                    <div class="absolute bottom-4 right-4 bg-ifa-green px-3 py-1 text-xs text-white uppercase tracking-wider rounded font-mono shadow-sm">Ìjọ Ifá</div>
                </div>
            </div>
        </div>
    </section>

    <section class="relative overflow-hidden bg-sacred-sand/30 px-4 py-16 xs:px-4 md:px-8 border-t border-b border-orisa-earth/10">
        <x-adire-pattern name="family" class="text-orisa-earth/[0.04]" />
        <div class="relative mx-auto grid max-w-5xl items-center gap-10 md:grid-cols-2">
            <div class="flex justify-center">
                <div class="relative w-full max-w-sm overflow-hidden rounded-lg border-2 border-orisa-earth/20 bg-white p-2 shadow-xl transition hover:scale-[1.02] duration-300">
                    <img src="/images/casa-comunidade.webp" alt="Comunidade de Ifá celebrando" width="800" height="800" loading="lazy" class="w-full rounded h-64 object-cover">
                    <div class="absolute bottom-4 left-4 bg-orisa-earth px-3 py-1 text-xs text-white uppercase tracking-wider rounded font-mono shadow-sm">Ẹgbẹ́</div>
                </div>
            </div>
            <div>
                <h2 class="font-display text-3xl text-ifa-green">{{ __('app.home.family_title') }}</h2>
                <p class="mt-4 text-ebony/80 leading-relaxed">{{ __('app.home.family_body') }}</p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-ifa-green/10 px-3 py-1 text-xs font-semibold text-ifa-green">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m0 0-.003-.031c0-.225.012-.447.037-.666A11.944 11.944 0 0 1 12 15c2.17 0 4.207.576 5.963 1.584A6.062 6.062 0 0 1 18 18.72Zm-1.8-9.75a3.486 3.486 0 0 0-3.48 3.479c0 .08.004.158.01.236m3.47-3.715a3.486 3.486 0 0 0 3.48 3.479c0 .08-.004.158-.01.236m-6.94-3.715a3.486 3.486 0 0 0-3.48 3.479c0 .08.004.158.01.236m3.47-3.715A3.486 3.486 0 0 0 9 6.236c0 .08.004.158.01.236m6.94-3.715a3.486 3.486 0 0 1 3.48 3.479c0 .08-.004.158-.01.236M9.702 5.75H9.7" />
                        </svg>
                        Omọ Awo & Linhagem
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-orisa-earth/10 px-3 py-1 text-xs font-semibold text-orisa-earth">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Respeito aos Ewò
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-ifa-green/10 px-3 py-1 text-xs font-semibold text-ifa-green">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-.778.099-1.533.284-2.253" />
                        </svg>
                        Ìwà Pẹ̀lẹ́ (Bom Caráter)
                    </span>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white px-4 py-12 xs:px-4 md:px-8">
        <div class="mx-auto grid max-w-5xl gap-3 md:grid-cols-3">
            <img src="/images/casa-sabedoria.webp" alt="Sabedoria das mais velhas" width="700" height="875" loading="lazy" class="h-64 w-full rounded-lg object-cover shadow-sm">
            <img src="/images/casa-devocao.webp" alt="Devoção e reza diária" width="700" height="700" loading="lazy" class="h-64 w-full rounded-lg object-cover shadow-sm">
            <img src="/images/casa-conexao.webp" alt="Conexão espiritual com Òrun" width="700" height="700" loading="lazy" class="h-64 w-full rounded-lg object-cover shadow-sm">
        </div>
    </section>

    <section class="relative bg-white px-4 py-16 xs:px-4 md:px-8">
        <h2 class="text-center font-display text-3xl text-ifa-green">{{ __('app.home.services_title') }}</h2>
        <div class="mx-auto mt-10 grid max-w-5xl gap-6 md:grid-cols-3">
            <div class="rounded-lg border border-orisa-earth/20 p-6 transition hover:-translate-y-1 hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-ifa-green/10">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-6 w-6 text-ifa-green">
                        <circle cx="12" cy="12" r="9" />
                        <circle cx="12" cy="12" r="3.5" />
                    </svg>
                </div>
                <h3 class="mt-3 font-display text-xl text-orisa-earth">{{ __('app.home.service_consultation_title') }}</h3>
                <p class="mt-2 text-sm text-ebony/70">{{ __('app.home.service_consultation_body') }}</p>
            </div>
            <div class="rounded-lg border border-orisa-earth/20 p-6 transition hover:-translate-y-1 hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-ifa-green/10">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-6 w-6 text-ifa-green">
                        <path d="M12 3c2 3 4 5 4 8a4 4 0 1 1-8 0c0-3 2-5 4-8Z" />
                        <path d="M9 19h6" />
                    </svg>
                </div>
                <h3 class="mt-3 font-display text-xl text-orisa-earth">{{ __('app.home.service_works_title') }}</h3>
                <p class="mt-2 text-sm text-ebony/70">{{ __('app.home.service_works_body') }}</p>
            </div>
            <div class="rounded-lg border border-orisa-earth/20 p-6 transition hover:-translate-y-1 hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-ifa-green/10">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-6 w-6 text-ifa-green">
                        <path d="M4 5.5C4 4.7 4.7 4 5.5 4H12v15H5.5A1.5 1.5 0 0 1 4 17.5v-12Z" />
                        <path d="M20 5.5c0-.8-.7-1.5-1.5-1.5H12v15h6.5a1.5 1.5 0 0 0 1.5-1.5v-12Z" />
                    </svg>
                </div>
                <h3 class="mt-3 font-display text-xl text-orisa-earth">{{ __('app.home.service_courses_title') }}</h3>
                <p class="mt-2 text-sm text-ebony/70">{{ __('app.home.service_courses_body') }}</p>
            </div>
        </div>
    </section>

    <section class="px-4 py-16 xs:px-4 md:px-8">
        <h2 class="text-center font-display text-3xl text-ifa-green">{{ __('app.home.how_title') }}</h2>
        <div class="mx-auto mt-10 grid max-w-5xl gap-6 md:grid-cols-4">
            @foreach ([1, 2, 3, 4] as $step)
                <div class="text-center">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-ifa-green font-mono text-white">{{ $step }}</div>
                    <h3 class="mt-3 font-display text-lg text-orisa-earth">{{ __("app.home.how_step{$step}_title") }}</h3>
                    <p class="mt-2 text-sm text-ebony/70">{{ __("app.home.how_step{$step}_body") }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="relative overflow-hidden bg-orisa-earth px-4 pb-16 pt-24 text-center text-white xs:px-4 md:px-8">
        <div class="absolute top-0 left-0 w-full -translate-y-full">
            <x-wave-divider class="text-orisa-earth" :flip="true" />
        </div>

        <h2 class="font-display text-3xl">{{ __('app.home.cta_title') }}</h2>
        <p class="mt-3 text-white/90">{{ __('app.home.cta_body') }}</p>
        <a href="{{ route('public-consultation-request') }}" class="mt-6 inline-block rounded bg-white px-6 py-3 font-medium text-orisa-earth hover:bg-sacred-sand">
            {{ __('app.home.cta_button') }}
        </a>

        <div class="mx-auto mt-10 max-w-md border-t border-white/20 pt-8">
            <p class="text-white/90">{{ __('app.home.whatsapp_cta') }}</p>
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                <x-whatsapp-button />
                <x-telegram-button label="Fale pelo Telegram" />
            </div>
        </div>
    </section>
</x-layouts.guest>
