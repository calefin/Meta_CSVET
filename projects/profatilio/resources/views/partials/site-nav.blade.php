{{-- Navegação única das páginas públicas (home, blog, na-mídia).
     Links de seção usam "/#..." — na própria home o navegador só rola até a âncora, sem recarregar.
     Mobile-first: no celular ficam visíveis só logo + botão "Aluno" + menu; o resto vai para o painel lateral. --}}
@php
    $navServices = [
        ['href' => '/#agendar-consulta',     'icon' => '🩺', 'label' => 'Agendar Consulta',      'desc' => 'Imunologia e animais não convencionais'],
        ['href' => '/necropsia-veterinaria', 'icon' => '🔬', 'label' => 'Necropsia Veterinária', 'desc' => 'Valor na hora e termo online'],
        ['href' => '/solicitar-atendimento', 'icon' => '📝', 'label' => 'Consultoria & Mentoria', 'desc' => 'TCC, IC, revisão ABNT e pareceres'],
        ['href' => '/#cursos',               'icon' => '💻', 'label' => 'Cursos (R e Python)',   'desc' => 'Ciência de dados aplicada à pesquisa'],
    ];
    $isDevolutivas = request()->is('consulta-avaliacao*', 'devolutivas*', 'devolutiva*');
    // Seleção de alunos aberta: aparece no menu só enquanto houver inscrições.
    try {
        $openCall = \App\Models\ResearchCall::where('status', 'open')->latest('id')->first(['slug', 'closes_at', 'status']);
        $openCall = $openCall?->isOpen() ? $openCall : null;
    } catch (\Throwable $e) {
        $openCall = null;
    }
@endphp
<header x-data="{ scrolled: false }"
        x-init="scrolled = window.scrollY > 8"
        @scroll.window.passive="scrolled = window.scrollY > 8"
        @keydown.escape.window="mobileMenu = false"
        class="fixed top-0 inset-x-0 z-50 transition-all duration-300 {{ $isDevolutivas ? 'bg-dark-950/95 backdrop-blur-xl border-b border-white/10 shadow-xl shadow-black/40' : '' }}"
        @unless($isDevolutivas)
        :class="scrolled
            ? 'bg-dark-950/95 backdrop-blur-xl border-b border-white/10 shadow-lg shadow-black/30'
            : 'bg-dark-950/40 backdrop-blur-md border-b border-transparent'"
        @endunless>
    <nav aria-label="Navegação principal" class="max-w-7xl mx-auto px-4 sm:px-6 h-16 lg:h-[4.5rem] flex items-center justify-between gap-3">

        {{-- Marca --}}
        <a href="/" class="flex items-center gap-2.5 min-w-0 shrink group" aria-label="Prof. Dr. Atilio Calefi — página inicial">
            <picture class="shrink-0">
                <source srcset="{{ asset('images/logo-mark-96.webp') }} 1x, {{ asset('images/logo-mark.webp') }} 2x" type="image/webp">
                <img src="{{ asset('images/logo-mark-96.png') }}" alt="" width="48" height="48"
                     class="w-11 h-11 lg:w-12 lg:h-12 object-contain drop-shadow-[0_0_12px_rgba(20,184,166,0.35)] transition-transform duration-300 group-hover:scale-105">
            </picture>
            {{-- Mobile: "Prof. Dr." vira sobrelinha para o nome caber inteiro ao lado do botão do aluno --}}
            <span class="flex flex-col min-w-0 leading-none sm:hidden">
                <span class="text-[0.62rem] font-bold uppercase tracking-[0.18em] text-brand-teal">Prof. Dr.</span>
                <span class="mt-1 font-outfit font-extrabold text-[1.06rem] tracking-tight text-white truncate">Atilio Calefi</span>
            </span>
            <span class="hidden sm:flex flex-col min-w-0 leading-none">
                <span class="font-outfit font-extrabold text-lg lg:text-xl tracking-tight text-white truncate">Prof. Atilio Calefi</span>
                <span class="mt-1 text-[0.68rem] font-semibold uppercase tracking-[0.14em] text-brand-teal/90 truncate">Med. Veterinária · Pesquisa</span>
            </span>
        </a>

        {{-- Desktop --}}
        <div class="hidden lg:flex items-center gap-1 text-sm font-medium text-slate-300">
            <a href="/#bio" class="px-3 py-2 rounded-lg hover:text-white hover:bg-white/5 transition-colors">Sobre</a>

            <div class="relative" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true"
                        class="px-3 py-2 rounded-lg hover:text-white hover:bg-white/5 transition-colors flex items-center gap-1">
                    Serviços
                    <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="open && 'rotate-180 text-brand-teal'" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute left-1/2 -translate-x-1/2 top-full pt-2 w-80">
                    <div class="rounded-2xl border border-white/10 bg-dark-900/95 backdrop-blur-xl shadow-2xl shadow-black/50 p-2">
                        @foreach($navServices as $item)
                            <a href="{{ $item['href'] }}" class="flex items-start gap-3 px-3 py-2.5 rounded-xl hover:bg-white/5 transition-colors">
                                <span class="text-lg leading-none mt-0.5" aria-hidden="true">{{ $item['icon'] }}</span>
                                <span>
                                    <span class="block text-white font-semibold">{{ $item['label'] }}</span>
                                    <span class="block text-xs text-slate-400 mt-0.5">{{ $item['desc'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($openCall)
                <a href="/selecao/{{ $openCall->slug }}" class="px-3 py-2 rounded-lg text-brand-teal font-semibold hover:text-white hover:bg-white/5 transition-colors">Seleção de Pesquisa</a>
            @endif
            <a href="/consulta-avaliacao" class="px-3 py-2 rounded-lg {{ $isDevolutivas ? 'text-brand-teal font-semibold bg-white/5' : 'hover:text-white hover:bg-white/5' }} transition-colors">Devolutivas</a>
            <a href="/normas-abnt" class="px-3 py-2 rounded-lg hover:text-white hover:bg-white/5 transition-colors">Normas ABNT</a>
            <a href="/blog" class="px-3 py-2 rounded-lg hover:text-white hover:bg-white/5 transition-colors">Blog</a>
            <a href="/na-midia" class="px-3 py-2 rounded-lg hover:text-white hover:bg-white/5 transition-colors">Na Mídia</a>
            <a href="/#contato" class="px-3 py-2 rounded-lg hover:text-white hover:bg-white/5 transition-colors">Contato</a>

            <a href="/portal" class="ml-3 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold shadow-lg shadow-brand-teal/25 hover:brightness-110 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m-6.5-9.5v5.5c0 1 3 3 6.5 3s6.5-2 6.5-3v-5.5" /></svg>
                Portal do Aluno
            </a>
        </div>

        {{-- Mobile: acesso do aluno sempre visível + menu --}}
        <div class="flex items-center gap-2 lg:hidden shrink-0">
            <a href="/portal" class="inline-flex items-center gap-1.5 h-10 px-3.5 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white text-sm font-bold shadow-md shadow-brand-teal/25 active:scale-95 transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m-6.5-9.5v5.5c0 1 3 3 6.5 3s6.5-2 6.5-3v-5.5" /></svg>
                Aluno
            </a>
            <button type="button" @click="mobileMenu = !mobileMenu" :aria-expanded="mobileMenu" aria-controls="mobile-menu" aria-label="Abrir menu"
                    class="w-10 h-10 inline-flex items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200 active:scale-95 transition-all">
                <svg x-show="!mobileMenu" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
                <svg x-show="mobileMenu" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </nav>
</header>

{{-- Painel mobile (fora do header para cobrir a tela inteira sem herdar o blur) --}}
<div class="lg:hidden fixed inset-0 z-[60] pointer-events-none" :class="mobileMenu && 'pointer-events-auto'" role="dialog" aria-modal="true" aria-label="Menu" x-effect="document.documentElement.style.overflow = mobileMenu ? 'hidden' : ''">
    <div x-show="mobileMenu" x-cloak x-transition.opacity.duration.200ms @click="mobileMenu = false" class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

    <aside id="mobile-menu" x-show="mobileMenu" x-cloak
           x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
           class="absolute right-0 top-0 h-full w-[88%] max-w-sm bg-dark-900 border-l border-white/10 shadow-2xl flex flex-col overflow-y-auto overscroll-contain">

        <div class="flex items-center justify-between px-5 h-16 border-b border-white/5 shrink-0">
            <span class="flex items-center gap-2.5">
                <img src="{{ asset('images/logo-mark-96.png') }}" alt="" width="36" height="36" class="w-9 h-9 object-contain">
                <span class="font-outfit font-bold text-white">Menu</span>
            </span>
            <button type="button" @click="mobileMenu = false" aria-label="Fechar menu" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-white/5 text-slate-300">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="p-5 flex flex-col gap-6">
            {{-- Área do aluno em destaque --}}
            <div class="rounded-2xl p-4 bg-gradient-to-br from-brand-teal/20 via-brand-indigo/15 to-transparent border border-brand-teal/30">
                <p class="text-[0.7rem] font-bold uppercase tracking-widest text-brand-teal">Sou aluno(a)</p>
                <p class="text-sm text-slate-300 mt-1">Envie seu TCC/IC, veja correções e prazos.</p>
                <a href="/portal" class="mt-3 flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-gradient-to-r from-brand-teal to-brand-indigo text-white font-bold shadow-lg shadow-brand-teal/20 active:scale-[0.98] transition-all">
                    Entrar no Portal do Aluno →
                </a>
                <div class="mt-2.5 flex justify-between text-xs">
                    <a href="/portal/password-reset/request" class="text-slate-400 underline underline-offset-2">Esqueci a senha</a>
                    <a href="/instalar-app" class="text-slate-400 underline underline-offset-2">Instalar app</a>
                </div>
            </div>

            @if($openCall)
                <a href="/selecao/{{ $openCall->slug }}" @click="mobileMenu = false" class="flex items-center gap-3 p-3 rounded-xl border border-brand-teal/30 bg-brand-teal/10 active:bg-brand-teal/20">
                    <span class="text-xl leading-none" aria-hidden="true">🎓</span>
                    <span>
                        <span class="block text-sm font-semibold text-white">Seleção de pesquisa aberta</span>
                        <span class="block text-xs text-slate-400">Projetos e produção de artigos</span>
                    </span>
                </a>
            @endif

            <div>
                <p class="px-1 mb-2 text-[0.7rem] font-bold uppercase tracking-widest text-slate-500">Serviços</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($navServices as $item)
                        <a href="{{ $item['href'] }}" @click="mobileMenu = false" class="flex flex-col gap-1.5 p-3 rounded-xl bg-white/[0.04] border border-white/5 active:bg-white/10 transition-colors">
                            <span class="text-xl leading-none" aria-hidden="true">{{ $item['icon'] }}</span>
                            <span class="text-sm font-semibold text-white leading-tight">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="px-1 mb-1 text-[0.7rem] font-bold uppercase tracking-widest text-slate-500">Navegar</p>
                <ul class="flex flex-col text-[0.95rem] font-medium text-slate-200">
                    @foreach([
                        ['/consulta-avaliacao', 'Devolutivas (provas & trabalhos)'],
                        ['/#bio', 'Sobre o professor'],
                        ['/normas-abnt', 'Normas ABNT 2026'],
                        ['/blog', 'Blog'],
                        ['/na-midia', 'Na Mídia'],
                        ['/#faq', 'Perguntas frequentes'],
                        ['/#contato', 'Contato & parcerias'],
                        ['/verificador', 'Validador de assinaturas'],
                    ] as [$href, $label])
                        <li>
                            <a href="{{ $href }}" @click="mobileMenu = false" class="flex items-center justify-between py-3 px-1 border-b border-white/5 active:text-brand-teal">
                                {{ $label }}
                                <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <a href="/admin" class="text-center text-xs text-slate-500 py-2">Área do Professor</a>
        </div>
    </aside>
</div>
