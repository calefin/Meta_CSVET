<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Assinatura de Documentos de Estágio — {{ $group->group_name }}</title>
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
                        brand: { teal: '#0d9488', indigo: '#4f46e5', violet: '#7c3aed' }
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
        .glass-card {
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(16px);
            border: 1px rgba(255, 255, 255, 0.08) solid;
        }
    </style>
</head>
<body class="font-sans text-slate-200 min-h-screen flex items-center justify-center p-4 py-12">
    <div class="w-full max-w-md glass-card rounded-3xl p-6 sm:p-8 shadow-2xl shadow-brand-teal/5">
        <div class="text-center mb-6">
            <h1 class="font-outfit font-extrabold text-2xl text-white tracking-tight">📄 Assinatura de Documentos de Estágio</h1>
            <p class="text-slate-400 mt-3 text-sm">{{ $group->group_name }}</p>
            <p class="text-slate-500 text-xs mt-1">
                {{ $group->university_name }}
                @if ($group->course) — {{ $group->course }} @endif
                @if ($group->semester) · {{ $group->semester }} semestre @endif
                · {{ $group->year }}
            </p>
        </div>

        <p class="text-slate-400 text-sm text-center mb-6">
            Informe seu RGM e o e-mail cadastrado pela sua instituição para acessar o envio de documentos (Plano de Estágio, Termo de Compromisso de Estágio).
        </p>

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-xl bg-red-950/30 border border-red-800 text-red-300 text-sm text-center">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('estagio.turma.verificar', ['token' => $group->token]) }}" class="space-y-4">
            @csrf

            {{-- Honeypot antibot: campo invisível, um humano nunca preenche --}}
            <div style="display: none !important;" aria-hidden="true">
                <input type="text" name="secondary_address" tabindex="-1" autocomplete="off" placeholder="Não preencha se for humano">
            </div>

            <div>
                <label for="rgm" class="block text-xs text-slate-400 mb-1">RGM / Matrícula</label>
                <input type="text" id="rgm" name="rgm" required autocomplete="off" value="{{ old('rgm') }}"
                    class="w-full px-4 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal text-base"
                    placeholder="Ex: 123456789">
            </div>

            <div>
                <label for="email" class="block text-xs text-slate-400 mb-1">E-mail cadastrado</label>
                <input type="email" id="email" name="email" required autocomplete="off"
                    class="w-full px-4 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal text-base"
                    placeholder="seuemail@exemplo.com">
            </div>

            <button type="submit" class="w-full py-3 rounded-xl bg-brand-teal hover:bg-teal-600 text-white font-semibold transition text-base">
                Acessar
            </button>
        </form>
    </div>
</body>
</html>
