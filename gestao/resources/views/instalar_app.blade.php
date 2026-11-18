<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar o App — profatilio</title>
    <meta name="description" content="Como instalar o app da plataforma Prof. Atilio no celular ou computador — passo a passo para Android, iPhone e Desktop.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://profatilio.com.br/instalar-app">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://profatilio.com.br/instalar-app">
    <meta property="og:title" content="Instale o App — profatilio">
    <meta property="og:description" content="Instale a plataforma de orientação acadêmica do Prof. Atilio como app no seu celular ou computador. Funciona sem baixar da loja.">
    <meta property="og:image" content="https://profatilio.com.br/images/pwa-icon-512.png">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="profatilio.com.br">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Instale o App — profatilio">
    <meta name="twitter:description" content="Guia passo a passo para instalar o app da plataforma Prof. Atilio no Android, iPhone ou computador.">
    <meta name="twitter:image" content="https://profatilio.com.br/images/pwa-icon-512.png">

    <!-- Schema.org HowTo -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "HowTo",
      "name": "Como instalar o app profatilio no celular",
      "description": "Passo a passo para instalar a plataforma profatilio.com.br como aplicativo PWA no Android, iPhone ou Desktop.",
      "url": "https://profatilio.com.br/instalar-app",
      "step": [
        {
          "@@type": "HowToStep",
          "name": "Abra o navegador",
          "text": "No Android, abra o Google Chrome. No iPhone/iPad, abra o Safari."
        },
        {
          "@@type": "HowToStep",
          "name": "Acesse a plataforma",
          "text": "Digite profatilio.com.br/portal na barra de endereço."
        },
        {
          "@@type": "HowToStep",
          "name": "Instale o app",
          "text": "No Android: menu abre opcoes, Adicionar a tela inicial. No iPhone: Compartilhar, Adicionar a Tela de Inicio."
        }
      ]
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter','sans-serif'], outfit: ['Outfit','sans-serif'] },
                    colors: { brand: { teal: '#0d9488', indigo: '#4f46e5' } }
                }
            }
        }
    </script>
    <style>
        body { background: #0f172a; }
        .step-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
        }
        .step-number {
            width: 2rem; height: 2rem;
            background: #0d9488;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.875rem; color: #fff;
            flex-shrink: 0;
        }
        .os-tab { cursor: pointer; transition: all 0.2s; }
        .os-tab.active {
            background: #0d9488;
            color: #fff;
            border-color: #0d9488;
        }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen">

    <!-- Header -->
    <header class="border-b border-white/5 bg-black/30 backdrop-blur-sm">
        <div class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 opacity-80 hover:opacity-100 transition">
                <img src="{{ asset('images/logo.png') }}" alt="profatilio" class="h-8 w-auto">
            </a>
            <a href="/portal" class="text-xs font-semibold text-brand-teal hover:text-teal-300 transition">
                Acessar Portal →
            </a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-12">

        <!-- Título -->
        <div class="text-center mb-10">
            <div class="text-5xl mb-4">📱</div>
            <h1 class="font-outfit font-extrabold text-3xl text-white mb-2">Instale o App no seu Celular</h1>
            <p class="text-slate-400 text-sm leading-relaxed">
                A plataforma profatilio funciona como um aplicativo nativo — sem precisar baixar nada da loja.
                Siga os passos abaixo para o seu dispositivo.
            </p>
        </div>

        <!-- Seletor de OS -->
        <div class="flex gap-2 mb-8 p-1 bg-white/5 rounded-xl">
            <button onclick="showOs('android')" id="tab-android"
                    class="os-tab active flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg border border-transparent text-sm font-semibold">
                🤖 Android
            </button>
            <button onclick="showOs('ios')" id="tab-ios"
                    class="os-tab flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg border border-transparent text-sm font-semibold text-slate-400">
                🍎 iPhone / iPad
            </button>
            <button onclick="showOs('desktop')" id="tab-desktop"
                    class="os-tab flex-1 flex items-center justify-center gap-2 py-2.5 rounded-lg border border-transparent text-sm font-semibold text-slate-400">
                💻 Computador
            </button>
        </div>

        <!-- Android -->
        <div id="os-android" class="space-y-4">
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">1</div>
                <div>
                    <p class="font-semibold text-white">Abra o Chrome</p>
                    <p class="text-sm text-slate-400 mt-0.5">Acesse <strong class="text-brand-teal">profatilio.com.br/portal</strong> no navegador Google Chrome do seu celular Android.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">2</div>
                <div>
                    <p class="font-semibold text-white">Toque no menu ⋮</p>
                    <p class="text-sm text-slate-400 mt-0.5">Toque nos três pontinhos no canto superior direito do Chrome para abrir o menu de opções.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">3</div>
                <div>
                    <p class="font-semibold text-white">Toque em "Adicionar à tela inicial"</p>
                    <p class="text-sm text-slate-400 mt-0.5">Selecione a opção <strong>"Adicionar à tela inicial"</strong> ou <strong>"Instalar aplicativo"</strong> que aparece no menu.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">4</div>
                <div>
                    <p class="font-semibold text-white">Confirme a instalação</p>
                    <p class="text-sm text-slate-400 mt-0.5">Uma janela de confirmação aparecerá com o ícone do app. Toque em <strong>"Instalar"</strong> ou <strong>"Adicionar"</strong>.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">5</div>
                <div>
                    <p class="font-semibold text-white">Pronto! ✅</p>
                    <p class="text-sm text-slate-400 mt-0.5">O ícone do <strong>Portal do Aluno</strong> aparecerá na sua tela inicial. Abra o app e faça login normalmente.</p>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-brand-teal/10 border border-brand-teal/20 text-sm text-slate-300">
                💡 <strong class="text-white">Dica:</strong> Depois de instalado, o app abre em tela cheia sem a barra do navegador, igual a um app da loja.
            </div>
        </div>

        <!-- iOS -->
        <div id="os-ios" class="space-y-4 hidden">
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">1</div>
                <div>
                    <p class="font-semibold text-white">Abra o Safari</p>
                    <p class="text-sm text-slate-400 mt-0.5">No iPhone ou iPad, abra o <strong>Safari</strong> (obrigatório — Chrome e outros navegadores não permitem instalar PWA no iOS).</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">2</div>
                <div>
                    <p class="font-semibold text-white">Acesse a plataforma</p>
                    <p class="text-sm text-slate-400 mt-0.5">Digite <strong class="text-brand-teal">profatilio.com.br/portal</strong> na barra de endereço do Safari e carregue a página.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">3</div>
                <div>
                    <p class="font-semibold text-white">Toque em Compartilhar <span class="inline-block">⎙</span></p>
                    <p class="text-sm text-slate-400 mt-0.5">Toque no ícone de <strong>compartilhar</strong> (quadrado com seta para cima) na barra inferior do Safari.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">4</div>
                <div>
                    <p class="font-semibold text-white">Toque em "Adicionar à Tela de Início"</p>
                    <p class="text-sm text-slate-400 mt-0.5">Role o menu de compartilhamento para baixo e toque em <strong>"Adicionar à Tela de Início"</strong>.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">5</div>
                <div>
                    <p class="font-semibold text-white">Confirme o nome e toque em "Adicionar"</p>
                    <p class="text-sm text-slate-400 mt-0.5">Confirme o nome <strong>"Portal Aluno"</strong> e toque em <strong>Adicionar</strong> no canto superior direito.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">6</div>
                <div>
                    <p class="font-semibold text-white">Pronto! ✅</p>
                    <p class="text-sm text-slate-400 mt-0.5">O ícone aparece na tela inicial do seu iPhone/iPad. Abra o app e faça login.</p>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-amber-900/20 border border-amber-700/30 text-sm text-slate-300">
                ⚠️ <strong class="text-white">Atenção:</strong> No iOS, o PWA só pode ser instalado via <strong>Safari</strong>. Outros navegadores como Chrome ou Firefox no iPhone não têm essa função.
            </div>
        </div>

        <!-- Desktop -->
        <div id="os-desktop" class="space-y-4 hidden">

            <p class="text-sm text-slate-400 mb-2">Funciona no <strong class="text-white">Google Chrome</strong> e <strong class="text-white">Microsoft Edge</strong>.</p>

            <div class="step-card flex gap-4 items-start">
                <div class="step-number">1</div>
                <div>
                    <p class="font-semibold text-white">Acesse a plataforma</p>
                    <p class="text-sm text-slate-400 mt-0.5">Abra o Chrome ou Edge e acesse <strong class="text-brand-teal">profatilio.com.br/admin</strong> (professor) ou <strong class="text-brand-teal">/portal</strong> (aluno).</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">2</div>
                <div>
                    <p class="font-semibold text-white">Clique no ícone de instalação</p>
                    <p class="text-sm text-slate-400 mt-0.5">Na barra de endereços, clique no ícone de <strong>instalar</strong> (computador com seta para baixo ⬇) que aparece à direita. No Edge, pode aparecer como um "+" ou "Aplicativos".</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">3</div>
                <div>
                    <p class="font-semibold text-white">Clique em "Instalar"</p>
                    <p class="text-sm text-slate-400 mt-0.5">Uma janela de confirmação aparece. Clique em <strong>"Instalar"</strong> para adicionar o atalho no Desktop e menu Iniciar.</p>
                </div>
            </div>
            <div class="step-card flex gap-4 items-start">
                <div class="step-number">4</div>
                <div>
                    <p class="font-semibold text-white">Pronto! ✅</p>
                    <p class="text-sm text-slate-400 mt-0.5">O app abre em janela própria (sem abas do navegador), igual a um programa instalado.</p>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-xl bg-brand-teal/10 border border-brand-teal/20 text-sm text-slate-300">
                💡 <strong class="text-white">Dica:</strong> O Firefox não suporta instalação de PWA. Use Chrome ou Edge para ter a melhor experiência.
            </div>
        </div>

        <!-- CTA voltar -->
        <div class="mt-10 text-center">
            <a href="/portal" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-teal text-white font-semibold text-sm hover:bg-teal-600 transition">
                Acessar o Portal
            </a>
        </div>

    </main>

    <footer class="border-t border-white/5 mt-10 py-6 text-center text-xs text-slate-600">
        © {{ date('Y') }} profatilio.com.br
    </footer>

    <script>
        function showOs(os) {
            ['android','ios','desktop'].forEach(function(o) {
                document.getElementById('os-' + o).classList.add('hidden');
                var tab = document.getElementById('tab-' + o);
                tab.classList.remove('active');
                tab.classList.add('text-slate-400');
            });
            document.getElementById('os-' + os).classList.remove('hidden');
            var activeTab = document.getElementById('tab-' + os);
            activeTab.classList.add('active');
            activeTab.classList.remove('text-slate-400');
        }
    </script>
</body>
</html>
