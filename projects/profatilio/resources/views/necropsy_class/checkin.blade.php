<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Presença - Aula Prática de Necropsia</title>
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
    <div class="w-full max-w-md glass-card rounded-3xl p-8 shadow-2xl shadow-brand-teal/5">
        <div class="text-center mb-6">
            <h1 class="font-outfit font-extrabold text-2xl text-white tracking-tight">🔬 Aula Prática de Necropsia</h1>
            <p class="text-slate-400 mt-2 text-sm">
                {{ $session->class_date->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($session->start_time)->format('H:i') }} às {{ \Carbon\Carbon::parse($session->end_time)->format('H:i') }}
            </p>
            <p class="text-slate-500 text-xs mt-1">{{ $session->location }}</p>
        </div>

        @if ($session->isCancelled())
            <div class="p-4 rounded-xl bg-amber-950/30 border border-amber-800 text-amber-300 text-sm text-center">
                Esta atividade foi cancelada/dispensada ({{ $session->cancellation_reason ?? 'sem motivo informado' }}) e não aceita mais presenças.
            </div>
        @elseif ($alreadyCheckedIn)
            <div class="p-4 rounded-xl bg-emerald-950/30 border border-emerald-800 text-emerald-300 text-sm text-center">
                ✅ Presença já confirmada para <strong>{{ $participant->name }}</strong> (RGM {{ $participant->rgm }}) nesta tarde.
            </div>
        @else
            @if ($errors->any())
                <div class="mb-4 p-3 rounded-xl bg-red-950/30 border border-red-800 text-red-300 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if ($participant)
                <form method="POST" action="{{ route('necropsy.class.checkin.store', ['token' => $session->token]) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="rgm" value="{{ $participant->rgm }}">
                    <div class="p-4 rounded-xl bg-slate-900/60 border border-white/5 text-sm">
                        <p class="text-slate-400">Confirme seus dados:</p>
                        <p class="text-white font-semibold mt-1">{{ $participant->name }}</p>
                        <p class="text-slate-400">RGM {{ $participant->rgm }} — {{ $participant->institution ?? 'Instituição não informada' }}</p>
                    </div>
                    <button type="submit" class="w-full py-3 rounded-xl bg-brand-teal hover:bg-teal-600 text-white font-semibold transition">
                        Confirmar Presença
                    </button>
                    <a href="{{ route('necropsy.class.checkin', ['token' => $session->token]) }}" class="block text-center text-xs text-slate-500 hover:text-slate-300">Não sou eu — buscar outro RGM</a>
                </form>
            @else
                <form method="GET" action="{{ route('necropsy.class.checkin', ['token' => $session->token]) }}" class="space-y-4 mb-4">
                    <label class="block text-sm text-slate-400">Digite seu RGM/matrícula</label>
                    <input type="text" name="rgm" value="{{ $rgm }}" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal"
                        placeholder="Ex: 123456789">
                    <button type="submit" class="w-full py-3 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-semibold transition">
                        Buscar
                    </button>
                </form>

                @if ($rgm !== '')
                    <p class="text-center text-xs text-slate-500 mb-3">RGM não encontrado. Preencha o cadastro rápido abaixo:</p>
                    <form method="POST" action="{{ route('necropsy.class.checkin.store', ['token' => $session->token]) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="rgm" value="{{ $rgm }}">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Nome completo</label>
                            <input type="text" name="name" required
                                class="w-full px-4 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal"
                                placeholder="Seu nome completo">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Instituição</label>
                            <input type="text" name="institution"
                                class="w-full px-4 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal"
                                placeholder="Ex: Universidade Cruzeiro do Sul">
                        </div>
                        <button type="submit" class="w-full py-3 rounded-xl bg-brand-teal hover:bg-teal-600 text-white font-semibold transition">
                            Registrar e Confirmar Presença
                        </button>
                    </form>
                @endif
            @endif
        @endif
    </div>
</body>
</html>
