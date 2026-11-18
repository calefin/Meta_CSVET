<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Serviço prestado pessoalmente pelo Prof. Atilio (serviço volante, Grande SP).
         Nunca descrever como laboratório/estabelecimento — ver docs/specs/2026-11-01-necropsia-avisos-link-seo.md --}}
    <title>Necropsia Veterinária na Grande SP | Prof. Dr. Atilio Calefi</title>
    <meta name="description" content="Necropsia veterinária feita pessoalmente pelo Prof. Dr. Atilio Calefi, médico veterinário doutor pela FMVZ-USP. Relatório macroscópico, histopatológico e fotodocumentação. Valor na hora pelo peso do animal. Grande São Paulo.">
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    <meta name="author" content="Prof. Dr. Atilio Sersun Calefi">
    <link rel="canonical" href="https://profatilio.com.br/necropsia-veterinaria">
    <link class="favicon" rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/logo-mark-96.png') }}">
    <meta name="theme-color" content="#030712">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://profatilio.com.br/necropsia-veterinaria">
    <meta property="og:title" content="Necropsia Veterinária com o Prof. Dr. Atilio Calefi">
    <meta property="og:description" content="Entenda a causa da morte do seu animal. Exame necroscópico com relatório, histopatológico e fotodocumentação — fale direto com o Prof. Atilio.">
    <meta property="og:image" content="{{ asset('images/og-cover.jpg') }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="profatilio.com.br">

    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "Service",
      "@@id": "https://profatilio.com.br/necropsia-veterinaria#servico",
      "name": "Necropsia veterinária",
      "serviceType": "Exame necroscópico (necropsia) de animais",
      "description": "Exame necroscópico realizado pessoalmente pelo Prof. Dr. Atilio Sersun Calefi, com relatório macroscópico, exame histopatológico e fotodocumentação.",
      "url": "https://profatilio.com.br/necropsia-veterinaria",
      "provider": { "@@id": "https://profatilio.com.br/#person" },
      "areaServed": { "@@type": "AdministrativeArea", "name": "Grande São Paulo" },
      "offers": {
        "@@type": "AggregateOffer",
        "priceCurrency": "BRL",
        "lowPrice": "{{ number_format($bands[0]['price'], 2, '.', '') }}",
        "highPrice": "{{ number_format(end($bands)['price'], 2, '.', '') }}",
        "url": "https://profatilio.com.br/solicitar-necropsia"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "FAQPage",
      "mainEntity": [
        @foreach($faq as $item)
        {
          "@@type": "Question",
          "name": @json($item['q']),
          "acceptedAnswer": { "@@type": "Answer", "text": @json($item['a']) }
        }{{ $loop->last ? '' : ',' }}
        @endforeach
      ]
    }
    </script>
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "BreadcrumbList",
      "itemListElement": [
        { "@@type": "ListItem", "position": 1, "name": "Início", "item": "https://profatilio.com.br/" },
        { "@@type": "ListItem", "position": 2, "name": "Necropsia veterinária", "item": "https://profatilio.com.br/necropsia-veterinaria" }
      ]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite('resources/css/site.css')

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('partials.adsense')
</head>
<body class="bg-dark-950 text-slate-100 font-sans antialiased overflow-x-hidden" x-data="{ mobileMenu: false }">

    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1f2937_1px,transparent_1px),linear-gradient(to_bottom,#1f2937_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-[0.15] pointer-events-none"></div>

    @include('partials.site-nav')

    <main class="relative z-10 pt-28 sm:pt-36 pb-24">
        <div class="max-w-4xl mx-auto px-6">

            {{-- Topo --}}
            <div class="text-center max-w-2xl mx-auto mb-14">
                <span class="text-xs font-bold text-brand-teal uppercase tracking-widest font-outfit">Patologia Veterinária</span>
                <h1 class="font-outfit font-extrabold text-3xl sm:text-4xl text-white mt-1">Necropsia Veterinária com o Prof. Dr. Atilio Calefi</h1>
                <p class="text-slate-400 mt-4 font-light text-sm sm:text-base leading-relaxed">
                    Perder um animal sem saber o motivo é muito difícil. A necropsia ajuda a entender a causa da morte, dá respostas à família e ao veterinário e pode proteger outros animais da casa ou do plantel.
                    O exame é feito pessoalmente pelo Prof. Dr. Atilio Sersun Calefi, médico veterinário, doutor pela FMVZ-USP, que atende na Grande São Paulo.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a id="necropsia-cta-orcamento" href="/solicitar-necropsia" class="px-6 py-4 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo hover:brightness-110 text-white font-bold text-center transition-all text-sm shadow-md shadow-brand-teal/10">
                        Calcular o valor agora →
                    </a>
                    @if($whatsappUrl)
                        <a id="necropsia-cta-whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="px-6 py-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white font-bold text-center transition-all text-sm">
                            Falar com o Prof. Atilio no WhatsApp
                        </a>
                    @endif
                </div>
                <div class="w-16 h-1 bg-brand-teal mx-auto mt-10 rounded-full"></div>
            </div>

            {{-- O que está incluso --}}
            <section class="mb-14" aria-labelledby="incluso">
                <h2 id="incluso" class="font-outfit font-extrabold text-2xl text-white mb-6">O que está incluso</h2>
                <div class="grid sm:grid-cols-3 gap-4">
                    <div class="glass-card p-6 rounded-2xl border border-white/5">
                        <h3 class="font-outfit font-bold text-white">Relatório macroscópico</h3>
                        <p class="text-sm text-slate-400 mt-2 font-light leading-relaxed">Descrição das alterações encontradas nos órgãos durante o exame.</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl border border-white/5">
                        <h3 class="font-outfit font-bold text-white">Exame histopatológico</h3>
                        <p class="text-sm text-slate-400 mt-2 font-light leading-relaxed">Análise microscópica dos tecidos coletados, para chegar mais perto do diagnóstico.</p>
                    </div>
                    <div class="glass-card p-6 rounded-2xl border border-white/5">
                        <h3 class="font-outfit font-bold text-white">Fotodocumentação</h3>
                        <p class="text-sm text-slate-400 mt-2 font-light leading-relaxed">Registro fotográfico dos achados, junto com o relatório.</p>
                    </div>
                </div>
            </section>

            {{-- Valores --}}
            <section class="mb-14" aria-labelledby="valores">
                <h2 id="valores" class="font-outfit font-extrabold text-2xl text-white mb-2">Quanto custa</h2>
                <p class="text-sm text-slate-400 font-light mb-6">O valor depende do peso do animal. Quando a necropsia é pedida por um médico veterinário, há <strong class="text-white">10% de desconto</strong>.</p>
                <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wider text-slate-400 border-b border-white/5">
                                <th scope="col" class="px-6 py-4 font-semibold">Peso do animal</th>
                                <th scope="col" class="px-6 py-4 font-semibold">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bands as $band)
                                <tr class="border-b border-white/5 last:border-0">
                                    <td class="px-6 py-4 text-slate-200">{{ $band['label'] }}</td>
                                    <td class="px-6 py-4 font-bold text-white">R$ {{ number_format($band['price'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Como funciona --}}
            <section class="mb-14" aria-labelledby="como-funciona">
                <h2 id="como-funciona" class="font-outfit font-extrabold text-2xl text-white mb-6">Como funciona</h2>
                <ol class="flex flex-col gap-4">
                    @foreach([
                        ['Calcule o valor e deixe seu contato', 'Informe a espécie e o peso do animal no formulário. O valor aparece na hora.'],
                        ['O Prof. Atilio entra em contato', 'Ele retorna pelo WhatsApp e combina diretamente com você a entrega do animal e os prazos.'],
                        ['Assine a autorização pelo celular', 'O termo de autorização é preenchido e assinado online, com comprovante em PDF.'],
                        ['Receba o relatório', 'Você recebe o relatório com os achados, o histopatológico e as fotos.'],
                    ] as $i => [$title, $text])
                        <li class="glass-card p-5 rounded-2xl border border-white/5 flex gap-4">
                            <span class="w-8 h-8 shrink-0 rounded-full bg-brand-teal/15 border border-brand-teal/40 text-brand-teal font-bold flex items-center justify-center text-sm">{{ $i + 1 }}</span>
                            <div>
                                <h3 class="font-outfit font-bold text-white">{{ $title }}</h3>
                                <p class="text-sm text-slate-400 mt-1 font-light leading-relaxed">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Para veterinários --}}
            <section class="mb-14 glass-card p-6 sm:p-8 rounded-2xl border border-brand-teal/20" aria-labelledby="veterinarios">
                <h2 id="veterinarios" class="font-outfit font-extrabold text-2xl text-white">É médico veterinário?</h2>
                <p class="text-sm text-slate-400 mt-3 font-light leading-relaxed">
                    Você pode encaminhar casos de seus pacientes informando seu CRMV no pedido. A solicitação feita por veterinário tem 10% de desconto e o relatório ajuda a fechar o caso clínico com o tutor.
                </p>
            </section>

            {{-- FAQ --}}
            <section class="mb-14" aria-labelledby="duvidas" x-data="{ open: null }">
                <h2 id="duvidas" class="font-outfit font-extrabold text-2xl text-white mb-6">Dúvidas frequentes</h2>
                <div class="flex flex-col gap-3">
                    @foreach($faq as $i => $item)
                        <div class="glass-card rounded-2xl border border-white/5 overflow-hidden">
                            <button id="necropsia-faq-{{ $i }}" type="button" @click="open = (open === {{ $i }} ? null : {{ $i }})" :aria-expanded="open === {{ $i }}" class="w-full px-6 py-4 flex items-center justify-between text-left gap-4">
                                <h3 class="font-outfit font-bold text-white text-base">{{ $item['q'] }}</h3>
                                <span class="text-brand-teal text-xl leading-none" x-text="open === {{ $i }} ? '−' : '+'">+</span>
                            </button>
                            <div x-show="open === {{ $i }}" x-collapse x-cloak class="px-6 pb-5 text-sm text-slate-400 font-light leading-relaxed">
                                {{ $item['a'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Chamada final --}}
            <section class="text-center glass-card p-8 sm:p-10 rounded-3xl border border-white/5">
                <h2 class="font-outfit font-extrabold text-2xl text-white">Precisa de uma necropsia?</h2>
                <p class="text-sm text-slate-400 mt-3 font-light">Calcule o valor e deixe seu contato. O Prof. Atilio retorna o quanto antes.</p>
                <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                    <a id="necropsia-cta-final" href="/solicitar-necropsia" class="px-6 py-4 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo hover:brightness-110 text-white font-bold text-center transition-all text-sm">
                        Calcular o valor →
                    </a>
                    @if($whatsappUrl)
                        <a id="necropsia-cta-whatsapp-final" href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="px-6 py-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-white font-bold text-center transition-all text-sm">
                            Chamar no WhatsApp
                        </a>
                    @endif
                </div>
            </section>
        </div>
    </main>

    @include('partials.site-footer')
    @include('partials.whatsapp_widget')
</body>
</html>
