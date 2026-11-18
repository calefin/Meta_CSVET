<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} - Prof. Atilio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        dark: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617',
                        },
                        brand: {
                            teal: '#0d9488',
                            indigo: '#4f46e5',
                            violet: '#7c3aed',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #020617;
            background-image: 
                radial-gradient(at 0% 0%, rgba(13, 148, 136, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(79, 70, 229, 0.15) 0px, transparent 50%);
        }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-dark-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-8 sm:p-12 shadow-2xl shadow-brand-teal/5 text-center space-y-6">
        
        <!-- Status Icon -->
        <div class="flex justify-center">
            @if($status === 'success')
                <div class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            @elseif($status === 'pending')
                <div class="w-16 h-16 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            @elseif($status === 'info')
                <div class="w-16 h-16 rounded-full bg-brand-teal/10 border border-brand-teal/30 flex items-center justify-center text-brand-teal">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            @else
                <div class="w-16 h-16 rounded-full bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-400">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            @endif
        </div>

        <h1 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white">
            {{ $title }}
        </h1>

        <p class="text-slate-400 text-sm sm:text-base leading-relaxed">
            {!! $message !!}
        </p>

        @if($status === 'pending')
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 text-left space-y-3">
                <p class="text-amber-400 font-semibold text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Acesso ainda nao liberado
                </p>
                <p class="text-slate-400 text-sm leading-relaxed">
                    Voce <strong class="text-slate-300">nao conseguira acessar a plataforma</strong> ainda. O Prof. Atilio precisa liberar manualmente o seu acesso. Assim que aprovado, voce receberá um e-mail de confirmacao.
                </p>
                <p class="text-slate-500 text-xs">
                    Nao precisa ficar tentando entrar — voce sera avisado por e-mail quando estiver liberado.
                </p>
            </div>
        @endif

        @if($status === 'success' && isset($link))
            <div class="pt-4">
                <a href="{{ $link }}" class="inline-block px-6 py-3 bg-gradient-to-r from-brand-teal to-brand-indigo hover:brightness-110 active:scale-95 text-white font-bold rounded-xl shadow-md transition-all">
                    Acessar o Painel
                </a>
            </div>
        @else
            <div class="pt-4">
                <a href="/" class="inline-block px-6 py-3 bg-white/5 border border-white/10 hover:bg-white/10 text-slate-200 font-semibold rounded-xl transition-all">
                    Voltar para o Inicio
                </a>
            </div>
        @endif

    </div>
</body>
</html>
