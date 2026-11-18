<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envio de Documentos — {{ $group->group_name }}</title>
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
<body class="font-sans text-slate-200 min-h-screen flex justify-center p-4 py-10">
    <div class="w-full max-w-lg space-y-6">
        <div class="glass-card rounded-3xl p-6 sm:p-8 shadow-2xl shadow-brand-teal/5">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <h1 class="font-outfit font-extrabold text-xl text-white tracking-tight">Olá, {{ $participant->name }}</h1>
                    <p class="text-slate-500 text-xs mt-1">{{ $group->group_name }} — RGM {{ $participant->rgm }}</p>
                </div>
                <a href="{{ route('estagio.turma.sair', ['token' => $group->token]) }}" class="text-xs text-slate-500 hover:text-slate-300 whitespace-nowrap">Sair</a>
            </div>

            @if (session('success'))
                <div class="mt-4 p-3 rounded-xl bg-emerald-950/30 border border-emerald-800 text-emerald-300 text-sm text-center">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 p-3 rounded-xl bg-red-950/30 border border-red-800 text-red-300 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('estagio.documentos.store', ['token' => $group->token]) }}" enctype="multipart/form-data" class="space-y-4 mt-5">
                @csrf

                <div style="display: none !important;" aria-hidden="true">
                    <input type="text" name="secondary_address" tabindex="-1" autocomplete="off" placeholder="Não preencha se for humano">
                </div>

                <div>
                    <label for="document_type" class="block text-xs text-slate-400 mb-1">Tipo de documento</label>
                    <select id="document_type" name="document_type" required
                        class="w-full px-4 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-base">
                        <option value="" disabled selected>Selecione...</option>
                        @foreach ($documentTypes as $key => $label)
                            <option value="{{ $key }}" @selected(old('document_type') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <div>
                        <label for="reference_month_start" class="block text-xs text-slate-400 mb-1">Mês inicial</label>
                        <select id="reference_month_start" name="reference_month_start" required
                            class="w-full px-3 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-base">
                            @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $m)
                                <option value="{{ $i + 1 }}" @selected(old('reference_month_start') == $i + 1)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="reference_month_end" class="block text-xs text-slate-400 mb-1">Mês final</label>
                        <select id="reference_month_end" name="reference_month_end"
                            class="w-full px-3 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-base">
                            <option value="">= inicial</option>
                            @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $m)
                                <option value="{{ $i + 1 }}" @selected(old('reference_month_end') == $i + 1)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label for="reference_year" class="block text-xs text-slate-400 mb-1">Ano</label>
                        <input type="number" id="reference_year" name="reference_year" required min="2020" max="2100"
                            value="{{ old('reference_year', now()->year) }}"
                            class="w-full px-3 py-3 rounded-xl bg-slate-900/60 border border-white/10 text-white focus:outline-none focus:border-brand-teal text-base">
                    </div>
                </div>

                <div>
                    <label for="document" class="block text-xs text-slate-400 mb-1">Arquivo PDF (máx. 20MB)</label>
                    <input type="file" id="document" name="document" accept="application/pdf" required
                        class="w-full text-sm text-slate-300 file:mr-3 file:py-3 file:px-4 file:rounded-xl file:border-0 file:bg-brand-teal file:text-white file:font-semibold file:cursor-pointer">
                </div>

                <button type="submit" class="w-full py-3 rounded-xl bg-brand-teal hover:bg-teal-600 text-white font-semibold transition text-base">
                    Enviar Documento
                </button>
            </form>
        </div>

        <div class="glass-card rounded-3xl p-6 sm:p-8 shadow-2xl shadow-brand-teal/5">
            <h2 class="font-outfit font-bold text-white text-base mb-4">Meus envios</h2>

            @if ($submissions->isEmpty())
                <p class="text-slate-500 text-sm">Nenhum documento enviado ainda.</p>
            @else
                <div class="space-y-3">
                    @foreach ($submissions as $submission)
                        <div class="p-4 rounded-xl bg-slate-900/60 border border-white/5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-white text-sm font-semibold">{{ $submission->document_type }}</p>
                                    <p class="text-slate-500 text-xs mt-0.5">Referência: {{ $submission->referencePeriodLabel() }}</p>
                                    <p class="text-slate-600 text-xs mt-0.5">Enviado em {{ $submission->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                @php
                                    $statusColor = match ($submission->status) {
                                        'Assinado' => 'bg-emerald-950/40 text-emerald-300 border-emerald-800',
                                        'Devolvido' => 'bg-red-950/40 text-red-300 border-red-800',
                                        default => 'bg-amber-950/40 text-amber-300 border-amber-800',
                                    };
                                @endphp
                                <span class="text-xs px-2 py-1 rounded-full border whitespace-nowrap {{ $statusColor }}">{{ $submission->status }}</span>
                            </div>

                            @if ($submission->status === 'Devolvido' && $submission->return_reason)
                                <p class="text-red-300/80 text-xs mt-2">Motivo: {{ $submission->return_reason }}</p>
                            @endif

                            @if ($submission->status === 'Assinado')
                                <a href="{{ route('estagio.documentos.assinado.portal', ['token' => $group->token, 'submission' => $submission->id]) }}"
                                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-300 hover:text-emerald-200">
                                    ⬇ Baixar documento assinado
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</body>
</html>
