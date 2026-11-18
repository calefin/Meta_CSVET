<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar E-mail de Confirmação - Prof. Atilio</title>
    
    <!-- SEO Base -->
    <meta name="description" content="Reenvie o e-mail de ativação e confirmação de conta para acessar a plataforma de orientação do Prof. Atilio.">
    <meta name="robots" content="noindex, nofollow">
    <link class="favicon" rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

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
    <div class="w-full max-w-lg bg-dark-900/60 backdrop-blur-xl border border-white/10 rounded-3xl p-8 sm:p-12 shadow-2xl shadow-brand-teal/5 space-y-6">
        
        <div class="text-center space-y-2">
            <div class="flex justify-center mb-4">
                <a href="/">
                    <img src="{{ asset('images/logo.png') }}" alt="Prof. Atilio" class="h-12 w-auto">
                </a>
            </div>
            <h1 class="font-outfit font-extrabold text-2xl sm:text-3xl text-white">
                Reenviar Confirmação
            </h1>
            <p class="text-slate-400 text-sm sm:text-base">
                Insira o seu e-mail de cadastro para reenviarmos o link de ativação da sua conta.
            </p>
        </div>

        @if(session('success'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-5 space-y-2">
                <p class="text-emerald-400 font-semibold text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Sucesso!
                </p>
                <p class="text-slate-300 text-sm leading-relaxed">
                    {{ session('success') }}
                </p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-red-500/30 bg-red-500/5 p-5 space-y-2">
                <p class="text-red-400 font-semibold text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    Não foi possível reenviar
                </p>
                <ul class="list-disc list-inside text-slate-300 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('resend.confirmation') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">E-mail Cadastrado</label>
                <input type="email" name="email" id="email" required
                       class="w-full px-4 py-3 bg-white/5 border border-white/10 focus:border-brand-teal focus:ring-1 focus:ring-brand-teal text-white placeholder-slate-500 rounded-xl transition-all outline-none"
                       placeholder="exemplo@email.com" value="{{ old('email') }}">
            </div>

            <button type="submit" 
                    class="w-full py-3.5 px-6 bg-gradient-to-r from-brand-teal to-brand-indigo hover:brightness-110 active:scale-95 text-white font-bold rounded-xl shadow-lg shadow-brand-teal/10 transition-all">
                Reenviar Link de Ativação
            </button>
        </form>

        <div class="pt-4 border-t border-white/5 text-center flex flex-col sm:flex-row justify-between gap-4 text-sm text-slate-400">
            <a href="/portal/login" class="hover:text-white transition-colors">← Voltar para o Login</a>
            <a href="/" class="hover:text-white transition-colors">Ir para a Página Inicial</a>
        </div>

    </div>
</body>
</html>
